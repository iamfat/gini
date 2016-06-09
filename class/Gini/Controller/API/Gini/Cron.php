<?php

namespace Gini\Controller\API\Gini;

class Cron extends \Gini\Controller\API
{
    public function actionList() {
        return \Gini\Config::get('cron');
    }

    public function actionRun($name=null) {
        $job = \Gini\Config::get('cron')[$name];
        if (!$job) return false;
        $command_args = \Gini\Util::parseArgs($job['command']);
        ob_start();
        \Gini\CLI::dispatch($command_args);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }
}