<?php

namespace Gini\Controller\CLI\CI;

class PHPUnit extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini ci phpunit init\n";
        echo "gini ci phpunit create <Class/For/Test>\n";
    }

    public function actionInit()
    {
        echo "Preparing PHPUnit environment...\n";

        $sysPath = SYS_PATH;
        $phpunit_content = <<<TEMPL
<?xml version="1.0" encoding="UTF-8"?>
<phpunit colors="true" bootstrap="$sysPath/lib/bootstrap.php">
    <testsuites>
        <testsuite name="gini">
            <directory suffix=".php">./ci/test</directory>
            <exclude>gini.php</exclude>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist addUncoveredFilesFromWhitelist="true">
          <directory suffix=".php">class</directory>
        </whitelist>
    </filter>
    <logging>
        <log type="junit" target="reports/unit.xml"/>
        <log type="coverage-clover" target="reports/coverage.xml"/>
    </logging>
</phpunit>
TEMPL;

        file_exists(APP_PATH.'/phpunit.xml')
            or file_put_contents(APP_PATH.'/phpunit.xml', $phpunit_content);

        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionCreate($args)
    {
        count($args) > 0 or die("Usage: gini ci phpunit create <Class/For/Test>\n");

        $class = $args[0];
        preg_match('|^[\w\\\/]+$|', $class) or die("Usage: gini ci phpunit create <Class/For/Test>\n");

        echo "Creating $class\n";

        // convert all '/' to '\\'
        $class = strtr($class, [ '/' => '\\' ]);
        $pos = strrpos($class, '\\');
        if ($pos === false) {
            $namespace = '';
            $name = $class;
        } else {
            $namespace = '\\'.trim(substr($class, 0, $pos), '\\');
            $name = trim(substr($class, $pos + 1), '\\');
        }

        $content = <<<TEMPL
<?php

namespace Gini\PHPUnit{$namespace};

class {$name} extends \Gini\PHPUnit\TestCase\CLI {

    public function testHello()
    {
        \$this->assertTrue(false, "PLEASE IMPLEMENT THIS!");
    }

}

TEMPL;

        $dir = APP_PATH.'/ci/test';
        if ($namespace) {
            $dir .= strtr($namespace, '\\', '/');
        }

        if (!file_exists($dir.'/'.$name.'.php')) {
            \Gini\File::ensureDir($dir);
            file_put_contents($dir.'/'.$name.'.php', $content);
            echo "   \e[32mdone.\e[0m\n";
        } else {
            echo "   \e[31mfile exists!\e[0m\n";
        }
    }
}
