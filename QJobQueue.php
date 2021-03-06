<?php

namespace qjob;

/**
 Copyright (C) 2014 Renato Santana [http://www.renatosantana.com]

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is furnished
 to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

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
		ini_set('max_execution_time', '0');
		
		/* @var $job QJob */
		foreach ($this->getEnqueuedJobs() as $jobDef) {
			if (! $jobDef instanceof QJobTask) {
				$this->log("Removing from queue: $jobDef->file.");
				@unlink($jobDef->file);
				continue;
			}
		
			$locker = new QJobLocker(QJob::$i, $jobDef->id);
			if (! $locker->lock()) {
				$this->log("Cannot lock job '$jobDef->class' or already locked.");
				continue;
			}
			
			$class = $jobDef->class;
			
			if (! class_exists($class, false)) {
				$file = QJob::$i->jobsPath . '/' . $class . '.php';
				if (file_exists($file)) {
					require_once $file;
				} else {
					$this->log("Job class '$class' could not be found. Removing from queue.");
					$this->removeJob($jobDef->id);
				}
			}
			
			$jobInstance = new $class();

			if (! $jobInstance instanceof QJobTask) {
			    $this->log("$class: aborting. Must inherit from QJobTask.");
			    $this->removeJob($jobDef->id);
			    $locker->unlock();
			    continue;
			}
			
			foreach (get_object_vars($jobDef) as $k => $v) {
				$jobInstance->$k = $v;
			}

			$time = time();
			$sucess = false;
			$errorMessage = '';

			$this->log("$class: running... ");
			try {
				$jobInstance->run();
				$sucess = true;
			} catch (Exception $e) {
				$errorMessage = $e->getMessage() . ' - ' . $e->getTraceAsString();
			}

			if ($sucess) {
				$this->log("$class: succeed.");
				$this->removeJob($jobDef->id);
				$this->jobsInfo[$class]['lastRun'] = time();
				$this->save();
			} else {
				if ($jobDef->removeOnError) {
					$this->log("$class: failed. $errorMessage");
					$this->removeJob($jobDef->id);
					$this->jobsInfo[$class]['lastRun'] = time();
					$this->save();
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
		QJob::$i->log("[Queue $this->name]: $message");
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

	public function getEnqueuedJobs()
	{
		$jobs = array();
		foreach (glob(QJob::$i->runtimePath . '/' . $this->name . '/*') as $file) {
			if (! is_file($file)) continue;
			$j = unserialize(file_get_contents($file));			
			if (! is_object($j)) $j = new stdClass();
			$j->file = $file;
			$jobs[] = $j;
		}
	
		return $jobs;
	}

	public function hasJobOfClass($class)
	{
		foreach ($this->getEnqueuedJobs() as $jobDef) {
			if ($jobDef->class == $class) {
				return true;
			}
		}
		
		return false;
	}
	
	public function removeJob($id)
	{
		@unlink($this->getPath() . '/' . $id);
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
