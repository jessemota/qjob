<?php
class QJobItem {
	public $queueName = null;
	public $id = null;
	public $file = null;
	public $class = null;
	public $params = array();
	public $dateTimeEnqueued = null;
	public $removeOnError = true;

	public function __construct($class = null)
	{
		$class = $class == null ? get_class($this) : $class;
		$this->id = uniqid($class . '-', true);
		$this->class = $class;
	}
	
	public function log($message)
	{
		QJob::$i->log("[$this->class]: $message");
	}
}