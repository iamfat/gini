<?php

namespace Controller\CLI {
	
	class Cron extends \Controller\CLI {

		function __index(&$args) {
			$helps = array(
				'list' => 'List crons',
				'export' => 'Export to STDIN in crontab syntax'
			);

			foreach ($helps as $command => $help) {
				printf("gini cron %-20s %s\n", $command, $help);
			}
		}

		function action_list(&$args) {
			foreach ((array)_CONF('cron') as $cron) {
				printf("gini @%s %s\n", APP_SHORTNAME, $cron['command']);
			}
		}

		function action_export(&$args) {
			foreach ((array)_CONF('cron') as $cron) {
				printf("\n# %s\n", $cron['comment']);
				printf("%s gini @%s %s\n\n", $cron['interval'], APP_SHORTNAME, $cron['command']);
			}
		}

	}

}