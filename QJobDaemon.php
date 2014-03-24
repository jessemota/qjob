<?php

class QJobDaemon {
    
    public function run()
    {

    }
    
    public function log($m)
    {
        QJob::getInstance()->log("daemon: $m");
        //file_put_contents('/tmp/log.txt', "$m\n", FILE_APPEND);
    }
    
    public function shouldStop()
    {
        $locker = QJob::getInstance()->getDaemonManager()->getLocker(get_class($this));
        if (is_numeric(strpos(file_get_contents($locker->lockFileName), 'STOP'))) {
            $this->log('Stop request detected.');
            return true;
        }
        
        return false;
    }
}
