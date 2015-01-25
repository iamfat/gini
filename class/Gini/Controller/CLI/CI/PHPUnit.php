<?php

namespace Gini\Controller\CLI\CI;

class PHPUnit extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini ci phpunit init\n";
        echo "gini ci phpunit create <Class\\For\\Test>\n";
    }

    public function actionInit()
    {
        echo "Preparing PHPUnit environment...\n";

        $phpunit_content = <<<'TEMPL'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit colors="true">
    <testsuites>
        <testsuite name="gini">
            <directory suffix=".php">./tests/unit</directory>
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

        file_exists(APP_PATH.'/phpunit.xml.dist')
            or file_put_contents(APP_PATH.'/phpunit.xml.dist', $phpunit_content);

        $phpunit_gini = <<<'TEMPL'
<?php

$gini_dirs = [
    isset($_SERVER['GINI_SYS_PATH']) ? $_SERVER['GINI_SYS_PATH'] . '/lib' : __DIR__ . '/../../../gini/lib',
    (getenv("COMPOSER_HOME") ?: getenv("HOME") . '/.composer') . '/vendor/iamfat/gini/lib',
    '/usr/share/local/gini/lib',
];

foreach ($gini_dirs as $dir) {
    $file = $dir.'/phpunit.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
}

die("missing Gini PHPUnit Components!\n");
TEMPL;

        \Gini\File::ensureDir(APP_PATH.'/tests/unit');
        file_exists(APP_PATH.'/tests/unit/gini.php')
            or file_put_contents(APP_PATH.'/tests/unit/gini.php', $phpunit_gini);

        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionCreate($args)
    {
        count($args) > 0 or die("Usage: gini ci phpunit create <Class\\For\\Test>\n");

        $class = $args[0];
        preg_match('|^[\w\\\]+$|', $class) or die("Usage: gini ci phpunit create <Class\\For\\Test>\n");

        echo "Creating $class\n";

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

require_once __DIR__ . '/../gini.php';

class {$name} extends \Gini\PHPUnit\CLI {

    public function testHello() {
        \$this->assertTrue(false, "PLEASE IMPLEMENT THIS!");
    }

}

TEMPL;

        $dir = APP_PATH.'/tests/unit';
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
