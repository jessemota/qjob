QJob
====

PHP background jobs easy and simple. 

* QJob allows scheduled background jobs;
* Allow jobs to run at specified intervals;
* No need for a daemon;
* Does not require any special lib;
* Compatible with PHP 5.1

Usage
-----

    <?php
    require_once 'QJob.php';
    
    class AppJobs {
        /**
         * @return QJob
         */
        public static function getInstance() {
            return new QJob(array(
                'runtimePath' => dirname(__FILE__) . '/../runtime/qjob',
                'jobsPath' => dirname(__FILE__) . '/../jobs',
                'jobs' => array(
                    'TestJob' => array(
                        'interval' => 3600,
                        'queue' => 'test',    
                    )    
                )
            ));
        }
    }
    
Run this from cron at least each 10 minutes. Example below will run it each 5 minutes:

    # crontab
    */5 * * * * /usr/bin/lynx -dump http://mysite.com/cron.php >/dev/null 2>&1

    <?php 
    // cron.php    
    ini_set('display_errors', '1');
    date_default_timezone_set('America/New_York');
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

By default QJob will write to error log of your virtual host.

Here is how you can specify a custom logger:

	'jobs' => array(
	    'TestJob' => array(
	        'interval' => 3600,
	        'queue' => 'test',
	        'logger' => new MyLogger(),    
	    )    
	)

And your logger class could look like this:

	class MyLogger {
		public function log($message)
		{
			// my own log here
		}
	}

Manual Enqueuing
----------------

At any moment you can enqueue a job:

    AppJobs::getInstance()->enqueue('TestJob', array('param1' => 'value1'));
