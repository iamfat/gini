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
        foreach ((array) \Gini\Config::get('cron') as $cron) {
            printf("gini @%s %s\n", APP_ID, $cron['command']);
        }
    }

    public function actionExport($args)
    {
        $gini_bin = $_SERVER['_'];
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
            printf("%s%s\t%s%s @%s %s%s\n\n", $cron['interval'], $user ? "\t$user" : '', $prefix, $gini_bin, APP_ID, $cron['command'], $suffix);
        }
    }
}
