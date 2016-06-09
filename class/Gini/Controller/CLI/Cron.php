<?php

namespace Gini\Controller\CLI;

class Cron extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        $helps = array(
                'list' => 'List crons',
                'export' => 'Export to STDIN in crontab syntax',
            );

        foreach ($helps as $command => $help) {
            printf("gini cron %-20s %s\n", $command, $help);
        }
    }

    public function actionList($args)
    {
        $cron = (array) \Gini\Config::get('cron');
        foreach ($cron as &$job) {
            if (!isset($job['schedule']) && $job['interval']) {
                $job['schedule'] = $job['interval'];
                unset($job['interval']);
            }
        }
        echo yaml_emit($cron, YAML_UTF8_ENCODING);
    }

    public function actionRun($args) {
        foreach ($args as $name) {
            $job = \Gini\Config::get('cron')[$name];
            if (!$job) continue;
            $command_args = \Gini\Util::parseArgs($job['command']);
            \Gini\CLI::dispatch($command_args);
        }
    }

    public function actionExport($args)
    {
        $gini_bin = $_SERVER['_'] ?: $_SERVER['SCRIPT_FILENAME'];
        $opt = \Gini\Util::getOpt($args, 'hu:', ['help', 'user:', 'prefix:', 'suffix:']);

        if (isset($opt['h']) || isset($opt['help'])) {
            echo "Usage: gini cron export [-h|--help] [-u|--user=USER] [--prefix=PREFIX] [--suffix=SUFFIX]\n";

            return;
        }

        $prefix = $opt['prefix'] ?: '';
        $suffix = $opt['suffix'] ?: '';
        $user = $opt['u'] ?: $opt['user'] ?: '';

        foreach ((array) \Gini\Config::get('cron') as $cron) {
            if ($cron['comment']) {
                printf("# %s\n", $cron['comment']);
            }
            printf("%s%s\t%s%s @%s %s%s\n\n", $cron['schedule'] ?: $cron['interval'], $user ? "\t$user" : '', $prefix, $gini_bin, APP_ID, $cron['command'], $suffix);
        }
    }
}
