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

        $envPath = $suiteDir.'/' . $_SERVER['GINI_ENV'] . '.env';
        $env = [ 'GINI_IN_SUITE' => 1 ];
        if (file_exists($envPath)) {
            $rows = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($rows as &$row) {
                if (!$row || $row[0] == '#') {
                    continue;
                }
                $env[] = $row;
            }
        }

        $gini_bin = $_SERVER['_'] ?: $_SERVER['SCRIPT_FILENAME'];
        $command = escapeshellcmd($gini_bin) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        foreach (glob($suiteDir . '/*/gini.json') as $moduleInfoPath) {
            $moduleDir = dirname($moduleInfoPath);
            $cleanDir = \Gini\File::relativePath($moduleDir, $suiteDir);
            echo "\e[30;42m + $cleanDir\e[K\e[0m\n";
            $proc = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $moduleDir, $env);
            if (is_resource($proc)) {
                proc_close($proc);
            }
        }
    }
}
