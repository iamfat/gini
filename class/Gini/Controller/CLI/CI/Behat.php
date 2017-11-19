<?php

namespace Gini\Controller\CLI\CI;

class Behat extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini ci behat init\n";
    }

    public function actionInit()
    {
        echo "Preparing Behat environment...\n";

        $sysPath = SYS_PATH;
        $config_yaml = <<<TEMPL
default:
  autoload:
    '': %paths.base%/ci/features/php
  suites:
    default:
      paths: [ %paths.base%/ci/features ]
      contexts:
        - DefaultContext:
            giniBootstrap: $sysPath/lib/bootstrap.php
TEMPL;

        file_exists(APP_PATH.'/behat.yml')
            or file_put_contents(APP_PATH.'/behat.yml', $config_yaml);

        $hints = <<<CODE
   Don't forget to add following code to \e[1m__construct()\e[0m of the XXXContext to load Gini!\e[0m

        public function __construct(\e[33m\$giniBootstrap=null\e[0m)
        {
            \e[33mis_file(\$giniBootstrap) and require_once(\$giniBootstrap);\e[0m
        }


CODE;

        echo $hints;
        echo "   \e[32mdone.\e[0m\n";
    }

}
