QJob
====

PHP background jobs easy and simple.

Atention: this still in beta version.

Requirements
------------

* PHP 5+

Usage
-----

	<?php
	require_once dirname(__FILE__) . '/../../../vendor/stnsolutions/qjob/QJob.php';
	
	class AppJobs {
		/**
		 * @return QJob
		 */
		public static function getInstance() {
			return new QJob(array(
				'runtimePath' => dirname(__FILE__) . '/../runtime/qjob',
				'jobsPath' => dirname(__FILE__) . '/../components',
				'jobs' => array(
					'TestJob' => array(
						'interval' => 1,
						//'time' => '23:51',	
						'queue' => 'test',	
					)	
				)
			));
		}
	}
	
	Run this from cron:
	*/5 * * * * /usr/bin/lynx -dump http://mysite.com/cron.php >/dev/null 2>&1

	<?php 
	// cron.php	
	ini_set('display_errors', '1');
	date_default_timezone_set('America/Sao_Paulo');
	require_once dirname(__FILE__) . '/protected/components/AppJobs.php';
	AppJobs::getInstance()->run();
	
Job options
-----------

* interval: Seconds for recurrent jobs;
* time: Scheduled times to run. Ex: 08:00, 18:00;
* enabled: true | false indicating if the job is enabled. Default is enabled.
* queue: Name of the queue to run in;

Note: specify either one of 'interval' or 'time' options.

Logs
----

By default QJob will write to syslog error log of your virtual host.

Manual Enqueuing
----------------

AppJobs::getInstance()->enqueue('TestJob', array('param1' => 'value1')); 