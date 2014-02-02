<?php
class QJobQueue {
	public $name = 'default';
	public $jobsInfo = array();

	public function __construct($name = 'default')
	{
		$this->name = $name;
		$this->load();
	}

	public function run()
	{
		/* @var $job QJobItem */
		foreach ($this->getJobs() as $job) {
			if (! $job instanceof QJobItem) {
				$this->log("Removing from queue: $job->file.");
				@unlink($job->file);
				continue;
			}
		
			$locker = new QJobLocker(QJob::$i, $job->id);
			if (! $locker->lock()) {
				$this->log("Job $job->class is already locked.");
				continue;
			}
			
			$class = $job->class;
			
			if (! class_exists($class)) {
				$file = QJob::$i->jobsPath . '/' . $class . '.php';
				if (file_exists($file)) {
					require_once $file;
				} else {
					$this->log("Job class '$class' could not be found. Removing from queue.");
					$this->removeJob($job->id);
				}
			}
			
			$instance = new $class();

			$time = time();
			$sucess = false;
			$errorMessage = '';

			$this->log("$class: running... ");
			try {
				$instance->run();
				$sucess = true;
			} catch (Exception $e) {
				$errorMessage = $e->getMessage() . ' - ' . $e->getTraceAsString();
			}

			if ($sucess) {
				$this->log("$class: succeed.");
				$this->removeJob($job->id);
				$this->jobsInfo[$class]['lastRun'] = time();
				$this->save();
			} else {
				if ($element->deleteOnFailure) {
					$this->log("$class: failed. $errorMessage");
					$this->removeJob($job);
				} else {
					$this->log("$class: waiting.");
				}
			}

			$locker->unlock();
		}
	}
	
	public function enqueue($job)
	{
		$job->dateTimeEnqueued = date('Y-m-d H:i:s');
		file_put_contents($this->getPath() . '/' . $job->id, serialize($job));
		
		$paramsStr = '';
		foreach ($job->params as $k => $v) {
			$paramsStr .= "$k = '$v', ";				
		}
		$paramsStr = rtrim($paramsStr, ', ');

		$this->log('Enqueued job ' . $job->class . "($paramsStr).");
		
		return true;
	}
	
	public function log($message)
	{
		QJob::$i->log("[queue]: $message");
	}
	
	private function getDataFile()
	{
		return $this->getPath() . '.srzd';
	}
	
	public function load()
	{
		$data = array();
		$f = $this->getDataFile();
		if (file_exists($f)) {
			$data = unserialize(file_get_contents($f));
			$data = is_array($data) ? $data : array();
		}
		
		foreach (QJob::$i->jobs as $jobClass => $opt) {
			if ($opt['queue'] != $this->name) continue;
			$this->jobsInfo[$jobClass]['lastRun'] = isset($data[$jobClass]['lastRun']) ? $data[$jobClass]['lastRun'] : 0;
		}
	}
	
	public function save()
	{
		file_put_contents($this->getDataFile(), serialize($this->jobsInfo));
	}

	public function getJobs()
	{
		$jobs = array();
		foreach (glob(QJob::$i->runtimePath . '/' . $this->name . '/*') as $file) {
			if (! is_file($file)) continue;
			$j = unserialize(file_get_contents($file));
			$j->file = $file;
			$jobs[] = $j;
		}
	
		return $jobs;
	}

	public function removeJob($id)
	{
		unlink($this->getPath() . '/' . $id);
	}
	
	public function getPath()
	{
		$p = QJob::$i->runtimePath . '/' . $this->name;
		if (! file_exists($p)) {
			mkdir($p, QJob::$i->dirMode, true);
		}
		
		return $p;
	}
}

class QJobItem {
	public $id = null;
	public $file = null;
	public $class = null;
	public $params = array();
	public $dateTimeEnqueued = null;

	public function __construct($class)
	{
		$this->id = uniqid($class . '-', true);
		$this->class = $class;
	}
}