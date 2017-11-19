<?php

namespace Gini\Controller\CLI\CI;

class PHPSpec extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini ci phpspec init\n";
    }

    public function actionInit()
    {
        echo "Preparing PHPSpec environment...\n";

        $sysPath = SYS_PATH;
        $phpspec_content = <<<TEMPL
suites:
  default:
    spec_path: %paths.config%/ci
    src_path: %paths.config%/class
code_generation: false
bootstrap: $sysPath/lib/bootstrap.php
TEMPL;

        file_exists(APP_PATH.'/phpspec.yml.dist')
            or file_put_contents(APP_PATH.'/phpspec.yml.dist', $phpspec_content);

        echo "   \e[32mdone.\e[0m\n";
    }

}
