<?php
class QJobQueueManager {
	
	/**
	 * @var QJob
	 */
	public $qjob = null;
	
	public $queues = array();

	/**
	 * @param unknown_type $name
	 * @return QJobQueue
	 */
	public function getQueue($name = 'default')
	{
		if (in_array($name, $this->queues)) {
			return $this->queues[$name];
		}
		
		$this->queues[$name] = new QJobQueue($name);
		return $this->queues[$name];
	}
	
	public function getQueues()
	{
		$q = array();
		foreach (glob($this->qjob->runtimePath . '/*') as $file) {
			if (is_dir($file)) {
				$q[] = $this->getQueue(basename($file));
			}
		}
		return $q;
	}
}
