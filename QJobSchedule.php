<?php
class QJobSchedule {
	
	const SCHEDULER_DATA_FILE = 'scheduler_data.srlzd';
	const MAX_SECONDS_TO_CATCH_UP = 600;

	/**
	 * @var QJob
	 */
	public $qjob = null;
	
	private $tickTime = 0;

	/**
	 * Enqueue scheduled jobs.
	 */
	public function enqueueJobs()
	{
		$locker = new QJobLocker($this->qjob, 'schedule');
		if (! $locker->lock()) {
			return false;
		}		
		
		$this->load();
		
		foreach ($this->qjob->jobs as $jobClass => $opt) {
			if (isset($opt['enabled'])) {
				$isEnabled = $opt['enabled'] == 1 || $opt['enabled'] == 'true';
				if (! $isEnabled) continue;
			}
			 
			$queueName = $opt['queue'];
			$jobTimes = isset($opt['time']) ? $opt['time'] : null;
			$interval = isset($opt['interval']) ? (int) $opt['interval'] : 0;
	
			$queue = $this->qjob->getQueueManager()->getQueue($queueName);

			$currentTime = time();
			$lastRunTime = $queue->jobsInfo[$jobClass]['lastRun'];
			$elapsedSeconds = $currentTime - $lastRunTime;
	
			$readyToEnqueue = false;
	
			if ($interval > 0 && $elapsedSeconds >= $interval) {
				$readyToEnqueue = true;
			}
	
			if ($jobTimes != null) {
				// Run time scheduled jobs
				if ($currentTime - $this->tickTime > self::MAX_SECONDS_TO_CATCH_UP) {
					$this->tickTime = $currentTime - self::MAX_SECONDS_TO_CATCH_UP;
				}
				
				$jobTimesArray = array();
				foreach (explode(',', $jobTimes) as $jobTime) {
					$jobTimeParts = explode(':', $jobTime);
					if (count($jobTimeParts) != 2) {
						continue;
					}
				
					list($jobHour, $jobMinute) = $jobTimeParts;
					$jobHour = (int) $jobHour;
					$jobMinute = (int) $jobMinute;
					$jobTimesArray[] = "$jobHour:$jobMinute";
				}
								
				while ($this->tickTime <= $currentTime) {
					//if ($jobHour == $tickHour && $jobMinute == $tickMinute &&
					$tickHour = (int) date('H', $this->tickTime);
					$tickMinute = (int) date('i', $this->tickTime);

					if (in_array("$tickHour:$tickMinute", $jobTimesArray) &&
							($currentTime - $lastRunTime) > 60) {
						$readyToEnqueue = true;
						break;
					}
		
					// Advance to the next minute
					while((int) date('i', $this->tickTime) == $tickMinute) {
						$this->tickTime++;
					}
				}
			}
	
			if ($readyToEnqueue) {
				try {
					$this->qjob->enqueue($jobClass, array(), $queueName);
				} catch (Exception $e) {
					$this->log("Error when enqueueing $jobClass: " . $e->getMessage() . ' ' . $e->getTraceAsString());
				}
			}
		}
		
		$this->save();
		$locker->unlock();
	}
	
	private function getDataFilePath()
	{
		return $this->qjob->runtimePath . '/' .  self::SCHEDULER_DATA_FILE;
	}
	
	private function load()
	{
		$dataFile = $this->getDataFilePath();
	
		if (file_exists($dataFile)) {
			$data = unserialize(file_get_contents($dataFile));
	
			foreach ($data as $k => $v) {
				$this->$k = $v;
			}
		}
	}
	
	public function save()
	{
		$vars = array('tickTime');
		
		$data = array();
		foreach ($vars as $var) {
			$data[$var] = $this->$var;	
		}
		
		return file_put_contents($this->getDataFilePath(), serialize($data));
	}	
	
	public function log($message)
	{
		$this->qjob->log('[schedule]: ' . $message);
	}
}
