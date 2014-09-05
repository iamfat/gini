<?php

namespace Gini\Controller\CLI;

    class Cron extends \Gini\Controller\CLI
    {
        public function __index($args)
        {
            $helps = array(
                'list' => 'List crons',
                'export' => 'Export to STDIN in crontab syntax'
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
            if ($args[0]) {
                $user = $args[0];
            }

            foreach ((array) \Gini\Config::get('cron') as $cron) {
                if ($cron['comment']) printf("# %s\n", $cron['comment']);
                printf("%s%s\tgini @%s %s\n\n", $cron['interval'], $user ? "\t$user" : '', APP_ID, $cron['command']);
            }
        }

    }
