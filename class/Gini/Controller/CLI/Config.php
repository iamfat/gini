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
            echo J($items, JSON_PRETTY_PRINT)."\n";
        } else {
            echo yaml_emit($items, YAML_UTF8_ENCODING);
        }
    }
}
