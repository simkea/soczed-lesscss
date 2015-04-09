<?php

require_once dirname(__FILE__) . '/../../../../../../shell/abstract.php';

/**
 * Magento Compiler Shell Script
 */
class Soczed_Less_shell_Cache extends Mage_Shell_Abstract {


    /**
     * Run Action
     * @return mixed
     */
    public function run() {
        // PHP config changes
        $canRun = $this->getPhpIni();

        // Kundenimport
        if ($this->getArg('clean')) {
            if($canRun) $this->cleanAction();
        }
        else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp() {
        return <<<USAGE
Usage:  php54 -f php app/code/community/Soczed/Less/shell/Cache.php [options]

OPTIONS
clean            Delete all entrys from registrated less-Files

USAGE;
    }

    /**
     * Product Import
     * @return void
     */
    protected function cleanAction() {
        $files = Mage::getModel('less/file')->getCollection();
        if(is_object($files)){
            foreach($files as $file){
                $file->delete();
            }
        }
    }

    /**
     * @return bool
     */
    protected function getPhpIni() {
        $max_execution_time = ini_get('max_execution_time');
        $memory_limit = ini_get('memory_limit');
        $memory = substr($memory_limit, 0, -1);

        if ((int)$max_execution_time < 180 && (int)$memory < 64) {
            $msg = 'Please check the php.ini and contact an administrator, the max_execution_time and memory is too low for this script.\n';
            $msg .= 'max_execution_time = ' . $max_execution_time . "\n";
            $msg .= 'memory_limit = ' . $memory_limit;
            echo($msg);
            return false;
        }
        return true;
    }

}

$shell = new Soczed_Less_shell_Cache();
$shell->run();