<?php

namespace Gini\Controller\CLI;

class Composer extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini composer init [-n|--no-packagist]\n";
    }

    public function actionInit($args)
    {
        $app = \Gini\Core::moduleInfo(APP_ID);

        $composer_json = [
            'name' => $app->id,
            'description' => $app->description ?: '',
            'license' => 'proprietary',
            'repositories' => [
                ['type' => 'composer', 'url' => 'http://satis.genee.cn'],
            ],
        ];

        $opt = \Gini\Util::getOpt($args, 'n', ['no-packagist']);
        if (isset($opt['n']) || isset($opt['--no-packagist'])) {
            $composer_json['repositories'][] = ['packagist' => false];
            echo "Generating Composer configuration file without Packagist...\n";
        } else {
            echo "Generating Composer configuration file...\n";
        }

        $walked = [];
        $walk = function ($info) use (&$walk, &$walked, &$composer_json) {
            $walked[$info->id] = true;
            foreach ($info->dependencies as $name => $version) {
                if (isset($walked[$name])) {
                    continue;
                }
                $app = \Gini\Core::moduleInfo($name);
                if ($app) {
                    $walk($app);
                }
            }
            $composer_json = \Gini\Util::arrayMergeDeep($info->composer ?: [], $composer_json);
        };

        $walk($app);

        if (isset($composer_json['require']) || isset($composer_json['require-dev'])) {
            if (file_exists(APP_PATH.'/composer.json')) {
                $confirm = strtolower(readline('File exists. Overwrite? [Y/n] '));
                if ($confirm && $confirm != 'y') {
                    echo "   \e[33mcanceled.\e[0m\n";

                    return;
                }
            }

            file_put_contents(APP_PATH.'/composer.json', J($composer_json, JSON_PRETTY_PRINT));
            echo "   \e[32mdone.\e[0m\n";
        }
    }
}
