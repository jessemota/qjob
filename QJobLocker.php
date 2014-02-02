<?php
class QJobLocker {
	
	const FORCE_UNLOCK_MINUTES = 10;
	
	/**
	 * @var QJob
	 */
	public $qjob;
	public $name;
	public $lockFileName;
	
	public function __construct($qjob, $name)
	{
		$this->qjob = $qjob;
		$this->name = $name;
		$this->lockFileName = $this->qjob->runtimePath . '/' . $this->name . '.lck';
	}
	
	public function lock()
	{
		if ($this->isLocked()) {
			if (! $this->forceUnlock()) {
				return false;
			}
		}
				
		touch($this->lockFileName);
		return true;
	}
	
	public function isLocked()
	{
		return file_exists($this->lockFileName);
	}
	
	public function forceUnlock()
	{
		if (! $this->isLocked()) {
			return true;
		}
	
		$currentTime = time();
		$changeTime = filectime($this->lockFileName);
	
		// if the lock is older than 1 hour, try to remove it
		$minutesOld = $m = ($currentTime - $changeTime) * 60;
		if ($minutesOld >= self::FORCE_UNLOCK_MINUTES) {
			$this->unlock();
	
			if (file_exists($this->lockFileName)) {
				$this->log("error: lock file is $minutesOld old but could not unlocked.");
				return false;
			}
	
			return true;
		}
	
		return false;
	}
	
	public function unlock()
	{
		@unlink($this->lockFileName);
	
		if ($this->isLocked()) {
			$this->log('error: could not remove lock file.');
		}
	}

	public function log($message)
	{
		$this->qjob->log('[lock]: ' . $message);
	}
}