<?php

namespace Gini\Controller\API\Gini;

class Cron extends \Gini\Controller\API
{
    public function actionList()
    {
        $cron = (array) \Gini\Config::get('cron');
        foreach ($cron as &$job) {
            if (!isset($job['schedule']) && $job['interval']) {
                $job['schedule'] = $job['interval'];
                unset($job['interval']);
            }
        }

        return $cron;
    }

    public function actionRun($name, $nohup=false)
    {
        if ($nohup) {
            fastcgi_finish_request();
        }
        $job = \Gini\Config::get('cron')[$name];
        if (!$job) {
            return false;
        }
        $command_args = \Gini\Util::parseArgs($job['command']);
        ob_start();
        \Gini\CLI::dispatch($command_args);
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }
}
