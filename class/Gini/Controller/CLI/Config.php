<?php

namespace Gini\Controller\CLI;

class Config extends \Gini\Controller\CLI
{

    public function __index($args)
    {
        echo "gini config update\n";
        echo "gini config export\n";
    }

    public function actionUpdate()
    {
        printf("%s\n", "Updating configurations...");

        $config_items = \Gini\Config::fetch();

        $config_file = APP_PATH . '/cache/config.json';

        \Gini\File::ensureDir(APP_PATH.'/cache');
        file_put_contents($config_file,
            J($config_items));

        \Gini\Config::setup();

        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionExport()
    {
        $items = \Gini\Config::fetch();
        // echo J($items, JSON_PRETTY_PRINT);
        echo yaml_emit($items, YAML_UTF8_ENCODING);
    }

}
