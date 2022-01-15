<?php

namespace Gini\Controller\CLI;

class Suite extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        $suiteDir = $_SERVER['PWD'];
        if (count($args) === 0) {
            die("Usage: gini suite [other-gini-commands]\n");
        }

        $envPath = $suiteDir . '/.env';
        $env = $_SERVER + ['GINI_IN_SUITE' => 1];
        unset($env['GINI_APP_PATH']);
        if (file_exists($envPath)) {
            $rows = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($rows as &$row) {
                if (!$row || $row[0] == '#') {
                    continue;
                }
                $row = \Gini\Config::normalizeEnv($row);
                putenv($row);
                $env[] = $row;
            }
        }

        $gini_bin = realpath($_SERVER['_'] ?: $_SERVER['SCRIPT_FILENAME']);
        $opt = \Gini\Util::getOpt($args, [], ['exclude:']);

        $command = escapeshellcmd($gini_bin) . ' ' . implode(' ', array_map('escapeshellarg', $opt['_']));
        $modules = [];
        foreach (glob($suiteDir . '/*/gini.json') as $moduleInfoPath) {
            $module = (object) @json_decode(@file_get_contents($moduleInfoPath), true);
            $module->path = dirname($moduleInfoPath);
            $module->shortId = \Gini\File::relativePath($module->path, $suiteDir);
            $modules[$module->shortId] = $module;
        }

        $sortedModules = [];
        $sortModule = function ($module) use (&$sortedModules, $modules, &$sortModule) {
            foreach ((array) $module->suiteDependencies as $depId) {
                if (isset($modules[$depId])) {
                    $sortModule($modules[$depId]);
                }
            }
            if (!isset($sortedModules[$module->shortId])) {
                $sortedModules[$module->shortId] = $module;
            }
        };

        foreach ($modules as $module) {
            $sortModule($module);
        }

        $excludes = (array) explode(',', $opt['exclude']);
        foreach ($sortedModules as $module) {
            if (in_array($module->shortId, $excludes)) {
                continue;
            }

            echo "\e[30;42m + $module->shortId\e[K\e[0m\n";
            $proc = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $module->path, $env);
            if (is_resource($proc)) {
                $exitCode = proc_close($proc);
                if ($exitCode !== 0) {
                    echo "\e[31m + $module->shortId was aborted with code=$exitCode\e[0m\n";
                    break;
                }
            }
        }

        exit($exitCode);
    }
}
