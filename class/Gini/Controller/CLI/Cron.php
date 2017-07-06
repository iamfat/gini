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

    public function actionRun($args)
    {
        foreach ($args as $name) {
            $job = \Gini\Config::get('cron')[$name];
            if (!$job) {
                continue;
            }
            $command_args = \Gini\Util::parseArgs($job['command']);
            \Gini\CLI::dispatch($command_args);
        }
    }

    public function actionSchedule()
    {
        // read cron cache
        $cron_cache_file = sys_get_temp_dir().'/cron_cache_'.sha1(APP_PATH);
        $fh = fopen($cron_cache_file, 'c+');
        if ($fh) {
            if (flock($fh, LOCK_EX | LOCK_NB)) {
                $fsize = filesize($cron_cache_file);
                $cron_cache = @json_decode(fread($fh, $fsize), true) ?: [];
                $cron_config = (array) \Gini\Config::get('cron');
                foreach ($cron_config as $name => $job) {
                    $schedule = $job['schedule'] ?: $job['interval'];
                    $cron = \Cron\CronExpression::factory($schedule);
                    $cache = &$cron_cache[$name];
                    if (isset($cache)) {
                        $next = date_create($cache['next']);
                        $now = date_create('now');
                        if ($next <= $now) {
                            // we have to run it
                            $cache['last_run_at'] = $now->format('c');
                            \Gini\Logger::of('cron')->info('cron run {command}', [
                                'command' => $job['command'], ]);
                            $pid = pcntl_fork();
                            if ($pid == -1) {
                                continue;
                            } elseif ($pid == 0) {
                                $command_args = \Gini\Util::parseArgs($job['command']);
                                \Gini\CLI::dispatch($command_args);
                                exit;
                            }
                        }
                    }
                    $cache['next'] = $cron->getNextRunDate()->format('c');
                }

                while (pcntl_wait($status) > 0);
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, J($cron_cache));

                flock($fh, LOCK_UN);
            }
            fclose($fh);
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
