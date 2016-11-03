<?php

namespace qjob;

class QJobDaemonManager {
    
    /**
     * @var QJob
     */
    public $qjob = null;
    
    public function startAll()
    {
        foreach ($this->qjob->daemons as $jobClass => $opt) {
            if (isset($opt['enabled'])) {
                $isEnabled = $opt['enabled'] == 1 || $opt['enabled'] == 'true';
                if (! $isEnabled) continue;
            }
        
            $this->start($jobClass);
        }
    }
    
    /**
     * Start a daemon.
     * @param string $class
     */
    public function start($class, $forceUnlock = false)
    {
        $locker = new QJobLocker(QJob::$i, 'daemon-' . $class);
        if (! $locker->lock($forceUnlock)) {
            $this->log($m = "Cannot lock daemon '$class' or already locked.");
            echo "$m\n";
            return false;
        }

        // load class
        if (! class_exists($class, false)) {
            $file = QJob::$i->jobsPath . '/' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                $this->log($m = "Daemon class '$class' could not be found.");
                echo "$m\n";
                $locker->unlock();
                return false;
            }
        }        

        $daemon = new $class();
        
        if (! $daemon instanceof QJobDaemon) {
            $this->log($m = "Daemon class '$class' does not extend QJobDaemon.");
            echo "$m\n";
            $locker->unlock();
            return false;
        }
        
        // allow processing signals
        declare(ticks = 1);
        
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            // failed
            return false;
        } else if ($pid) {
            // success - we are the parent
            $locker->appendData($pid);
            $status = null;
            return pcntl_waitpid($pid, $status, WNOHANG) == 0;
        } else {
            $this->log("Started '$class'.");
            // we are the child
        }
        
        // deattach from the controlling terminal
        ignore_user_abort();
        
        // Close all of the std file descriptors as we are a daemon
        fclose(STDIN);  
        fclose(STDOUT);
        //fclose(STDERR);        
        
        // setup signal handlers
        pcntl_signal(SIGTERM, 'qjobSigHandler');
        pcntl_signal(SIGHUP, 'qjobSigHandler');
        
        // run the actual task
        $daemon->run();
        $locker->unlock();
        exit;
    }
    
    public function stop($name)
    {
        $locker = $this->getLocker($name);
        
        if (file_exists($locker->lockFileName)) {
            $this->out("Requesting stop: $name.");
            $this->getLocker($name)->appendData(',STOP');
            $this->out("Waiting for the process to end...");
            while ($this->getLocker($name)->isLocked());
        } else {
            $this->out('It seems that the daemon is not running.');
        }
    }
    
    public function out($message)
    {
        echo $message . "\n";
    }
    
    public function log($message)
    {
        $this->qjob->log('[daemonManager]: ' . $message);
    }

    public function getLocker($class)
    {
        return new QJobLocker(QJob::$i, 'daemon-' . $class);
    }
}

function qjobSigHandler($signo)
{
    switch ($signo) {
        case SIGTERM:
            // handle shutdown tasks
            QJob::getInstance()->log('Terminated');
            exit;
            break;
        case SIGHUP:
            //The SIGHUP signal is sent to a process when its controlling terminal is closed.
            // handle restart tasks
            QJob::getInstance()->log('Terminal closed.');
            break;
        default:
            // handle all other signals
    }
}