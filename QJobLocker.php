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
	
	public function lock($allowForceUnlock = true)
	{
		if ($this->isLocked()) {
		    if ($allowForceUnlock) {
    			return $this->forceUnlock();
		    }
		    
		    return false;
		}
				
		return touch($this->lockFileName);
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
	
		// if the lock is old, try to remove it
		$minutesOld = $m = ($currentTime - $changeTime) / 60;
		
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
	
	public function appendData($data)
	{
	    file_put_contents($this->lockFileName, $data);
	}

	public function log($message)
	{
		$this->qjob->log('[lock]: ' . $message);
	}
}