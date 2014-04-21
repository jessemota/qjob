<?php
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

/**
 * 
 * QJob::enqueue('default', 'Mail', array('email' => 'bla@bla.com'));
 * 
 * 
 * ex.:
 * $jobs = 'jobs' => array(
 *     'MyJob' => array(
 *         'time' => '2:15',
 * 		),
 * );
 * 
 * $qjob = new QJob(array(
 * 		'runtimePath' => dirname(__FILE__) . '/protected/runtime/jobs',
 * 		'jobs' => array(
 *     		'MyJob' => array(
 *         		'time' => '2:15',
 * 			),
 *     		'OtherJob' => array(
 *         		'interval' => '600',
 * 			),
 * 		)
 * );
 * 
 * $qjob->run();
 */

require_once dirname(__FILE__) . '/QJobQueueManager.php';
require_once dirname(__FILE__) . '/QJobQueue.php';
require_once dirname(__FILE__) . '/QJobSchedule.php';
require_once dirname(__FILE__) . '/QJobLocker.php';
require_once dirname(__FILE__) . '/QJobTask.php';
require_once dirname(__FILE__) . '/QJobDaemonManager.php';
require_once dirname(__FILE__) . '/QJobDaemon.php';

class QJob {
	
	const LAST_RUN_FILE = 'last_run.txt'; 
	const JOB_INFO_FILE = '/status.srlzd';

	/**
	 * @var QJob
	 */
	public static $i;
    public $daemons = array();	
	public $jobs = array();
	public $runtimePath = null;
	public $jobsPath = null;
	public $dirMode = 0755;
	public $logger = null;

	public function __construct($options = array())
	{
		self::$i = $this;
		
		foreach ($options as $k => $v) {
			$this->$k = $v;
		}
		
		if (strlen($this->runtimePath) > 1) {
			$this->runtimePath = rtrim($this->runtimePath, '/');
		}
		
		if ($this->runtimePath == null) {
			$this->log($m = 'runtimePath is a required option.');
			throw new Exception($m);
		}
		
		if ($this->jobsPath == null) {
			$this->log($m = 'jobsPath is a required option.');
			throw new Exception($m);
		}
		
		foreach ($this->jobs as $class => $opt) {
			if (! isset($opt['queue'])) {
				$this->jobs[$class]['queue'] = 'default';
			}
		}
		
		// init logger
		if (is_array($this->logger) && isset($this->logger['class'])) {
		    $class = $this->logger['class'];
		    $this->logger = new $class();
		}

		// init runtime path
		if (! file_exists($this->runtimePath)) {
		    mkdir($this->runtimePath, $this->dirMode);
		}
	}
	
	/**
	 * @throws Exception
	 * @return QJob
	 */
	public static function getInstance()
	{
	    if (self::$i == null) {
	        throw new Exception('No instance of ' . __CLASS__);
	    }
	    
	    return self::$i;
	}
	
	/**
	 * @return QJobQueueManager
	 */
	public function getQueueManager()
	{
		$i = new QJobQueueManager();
		$i->qjob = $this;
		return $i;
	}
	
	/**
	 * @return QJobSchedule
	 */
	public function getSchedule()
	{
		$i = new QJobSchedule();
		$i->qjob = $this;
		return $i;
	}
	
	/**
	 * @return QJobDaemonManager
	 */
	public function getDaemonManager()
	{
	    $i = new QJobDaemonManager();
	    $i->qjob = $this;
	    return $i;
	}
	
	public function run()
	{
		if (! file_exists($this->runtimePath)) {
			mkdir($this->runtimePath, $this->dirMode, true);
		}

		//ignore_user_abort(true);
		set_time_limit(600);
		
		// enqueue scheduled and periodic jobs
		$this->getSchedule()->enqueueJobs();

		/* @var $queue QJobQueue */
		// execute enqueued jobs
		foreach ($this->getQueueManager()->getQueues() as $queue) {
			$queue->run();
		}
	}	

	/**
	 * @param mixed $job Job class name or instance.
	 * @return boolean
	 */
	public function enqueue($class, $params = null, $queueName = 'default')
	{
		$q = $this->getQueueManager()->getQueue($queueName);
		
		$job = new QJobTask($class);
		$job->params = $params == null ? array() : $params;
		$job->queueName = $queueName;
		return $q->enqueue($job);
	}

	public function startDaemon($class, $forceStart = false)
	{
	    $this->getDaemonManager()->start($class, $forceStart);
	}
	
	public function stopDaemon($class)
	{
	    $this->getDaemonManager()->stop($class);
	}

	public function isDaemonRunning($name)
	{
	    $testCmd = "ps ax|grep -i $name|grep -v grep|cut -d ' ' -f1";
	    $pids = array();
	    exec($testCmd, $pids);
	    return ! empty($pids);
	}
	
	public function log($message)
	{
		if (is_object($this->logger) && method_exists($this->logger, 'log')) {
			$this->logger->log($message);
		} else {
			error_log(get_class($this) . ': ' . $message, 0);
		}		
	}
}
