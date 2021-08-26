<?php

namespace Gini\Controller\CLI;

class Config extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini config export\n";
    }

    public function actionUpdate($args)
    {
        echo "\e[31mDEPRECATED! Please run 'gini cache' instead!\e[0m\n\n";
    }

    public function actionExport($args)
    {
        $opt = \Gini\Util::getOpt($args, 'h', ['help', 'json', 'yaml']);
        if (isset($opt['h']) || isset($opt['help'])) {
            echo "Usage: gini config export [-h|--help] [--json|--yaml]\n";

            return;
        }

        \Gini\Config::setup();
        $items = \Gini\Config::export();
        if (isset($opt['json'])) {
            echo J($items, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo yaml_emit($items, YAML_UTF8_ENCODING);
        }
    }

    public function actionEnv($args)
    {
        $env = \Gini\Config::env();
        foreach ($env as $k => $v) {
            echo "$k=$v\n";
        }
    }

    public function actionMergeEnv($args)
    {
        $opt = \Gini\Util::getOpt($args, 'e:f', ['env:']);
        $envFile = ($opt['e'] ?: $opt['env']) ?: APP_PATH . '/.env';
        if ($envFile && !is_file($envFile)) {
            echo "Invalid env file.";
            return;
        }

        $force = $opt['f'];
        $dstPath = $opt['_'][0] ?: 'raw/config';

        $vars = [];
        $rows = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($rows as &$row) {
            if (!$row || $row[0] == '#') {
                continue;
            }
            list($key,) = explode('=', trim($row), 2);
            $vars[$key] = true;
        }

        if (count($vars) == 0) {
            echo "Nothing to be merged!\n";
            return;
        }

        // 也许变量太多, 不要一次性拼接到一个正则里, 最多拼接10个
        $quotes = array_chunk(array_map('preg_quote', array_keys($vars)), 10);
        $quote_patterns = array_map(function ($v) {
            return '/\$\{' . implode('|', $v) . '(|:=.*)\}/';
        }, $quotes);

        $filterEnvRelated = function ($items) use (&$filterEnvRelated, $quote_patterns) {
            if (is_array($items)) {
                $f_items = [];
                foreach ($items as $k => $v) {
                    $fv = $filterEnvRelated($v);
                    if (isset($fv)) {
                        $f_items[$k] = $fv;
                    }
                }
                return (count($f_items) > 0) ? $f_items : null;
            }
            foreach ($quote_patterns as $pattern) {
                if (preg_match($pattern, $items)) {
                    return $items;
                }
            }
            return null;
        };

        $conf = \Gini\Config::fetch($envFile, true);
        // 遍历$conf
        $conf = $filterEnvRelated($conf);

        foreach ($conf as $category => $items) {
            $confFile = $dstPath . '/' . $category;
            if (file_exists($confFile . '.yaml')) {
                $confFilePath = $confFile . '.yaml';
            } else {
                $confFilePath = $confFile . '.yml';
            }

            $origItems = [];
            if (file_exists($confFilePath)) {
                $content = file_get_contents($confFilePath);
                $origItems = (array) yaml_parse($content);
            }

            $newItems = \Gini\Util::arrayMergeDeep(
                $origItems,
                $items
            );

            if (count($origItems) > 0) {
                echo "Updating $confFilePath...";
            } else {
                echo "Adding $confFilePath...";
            }
            if (!$force && file_exists($confFilePath)) {
                $confirm = strtolower(readline('File exists. Overwrite? [Y/n] '));
                if ($confirm && $confirm != 'y') {
                    echo "   \e[33mcanceled.\e[0m\n";
                    continue;
                }
            }
            file_put_contents($confFilePath, yaml_emit($newItems, YAML_UTF8_ENCODING));
            echo "   \e[32mdone.\e[0m\n";
        }
    }
}
