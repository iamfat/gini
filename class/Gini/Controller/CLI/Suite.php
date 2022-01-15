<?php

namespace Gini\Controller\CLI;

class Suite extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        if (count($args) === 0) {
            die("Usage: gini suite [other-gini-commands]\n");
        }

        $env = \Gini\CLI\Env::getAll();
        $env['GINI_IN_SUITE'] = '1';
        unset($env['GINI_APP_PATH']);

        $gini_bin = realpath($_SERVER['_'] ?? $_SERVER['SCRIPT_FILENAME']);
        $opt = \Gini\Util::getOpt($args, [], ['exclude:']);

        $command = escapeshellcmd($gini_bin) . ' ' . implode(' ', array_map('escapeshellarg', $opt['_']));
        $modules = [];
        $suiteDir = $_SERVER['PWD'];
        foreach (glob($suiteDir . '/*/gini.json') as $moduleInfoPath) {
            $module = (object) @json_decode(@file_get_contents($moduleInfoPath), true);
            $module->path = dirname($moduleInfoPath);
            $module->shortId = \Gini\File::relativePath($module->path, $suiteDir);
            $modules[$module->shortId] = $module;
        }

        $sortedModules = [];
        $sortModule = function ($module) use (&$sortedModules, $modules, &$sortModule) {
            if (isset($module->suiteDependencies) && is_array($module->suiteDependencies)) {
                foreach ($module->suiteDependencies as $depId) {
                    if (isset($modules[$depId])) {
                        $sortModule($modules[$depId]);
                    }
                }
            }

            if (!isset($sortedModules[$module->shortId])) {
                $sortedModules[$module->shortId] = $module;
            }
        };

        foreach ($modules as $module) {
            $sortModule($module);
        }

        $excludes = explode(',', $opt['exclude'] ?? '');
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
