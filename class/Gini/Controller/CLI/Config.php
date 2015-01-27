<?php

namespace Gini\Controller\CLI;

class Config extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini config update\n";
        echo "gini config export\n";
    }

    public function actionUpdate($args)
    {
        $opt = \Gini\Util::getOpt($args, 'he:', ['help', 'env:']);
        if (isset($opt['h']) || isset($opt['help'])) {
            echo "Usage: gini config update [-h|--help] [-e|--env=ENV]\n";
            return;
        }

        printf("%s\n", "Updating configurations...");

        $env = $opt['e'] ?: $opt['env'] ?: null;
        $config_items = \Gini\Config::fetch($env);

        $config_file = APP_PATH.'/cache/config.json';

        \Gini\File::ensureDir(APP_PATH.'/cache');
        file_put_contents($config_file,
            J($config_items));

        \Gini\Config::setup();

        echo "   \e[32mdone.\e[0m\n";
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
            echo J($items, JSON_PRETTY_PRINT)."\n";
        } else {
            echo yaml_emit($items, YAML_UTF8_ENCODING);
        }
    }
}
