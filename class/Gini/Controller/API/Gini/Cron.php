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
        $job = \Gini\Config::get('cron')[$name];
        if (!$job) {
            return false;
        }

        if ($nohup) {
            $basePath = $_SERVER['GINI_MODULE_BASE_PATH'] ? $_SERVER['GINI_MODULE_BASE_PATH'] : SYS_PATH . '/..';
            $appPath = APP_PATH;
            $sysPath = SYS_PATH . '/bin/gini';
            $env = $_SERVER['GINI_ENV'] ?: getenv('GINI_ENV');
            exec("GINI_MODULE_BASE_PATH={$basePath} GINI_APP_PATH={$appPath} GINI_ENV={$env} {$sysPath} {$job['command']} >/dev/null 2>&1 &");
            return true;
        }

        $command_args = \Gini\Util::parseArgs($job['command']);
        ob_start();
        \Gini\CLI::dispatch($command_args);
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }
}
