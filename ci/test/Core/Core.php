<?php

namespace Gini\PHPUnit\Core;

class Core extends \Gini\PHPUnit\TestCase\CLI
{
    public function testLocateFile()
    {
        $path = \Gini\Core::locateFile('class/Gini/Core.php', 'gini');
        self::assertEquals($path, SYS_PATH.'/class/Gini/Core.php');

        $path = \Gini\Core::locateFile('class/Gini/Core.php');
        self::assertEquals($path, SYS_PATH.'/class/Gini/Core.php');

        $path = \Gini\Core::locateFile('class/Gini/Core.php', 'foo');
        self::assertEquals($path, null);
    }

    public function testPharFilePaths()
    {
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/Core.php');
        self::assertTrue(in_array(SYS_PATH.'/class/Gini/Core.php', $paths));
    }

    public function testShortcuts()
    {
        _G('@foo', 'bar');
        self::assertEquals(_G('@foo'), 'bar');
        self::assertEquals(_G('@foobar'), null);

        $s = s('Hello, %s!', 'world');
        self::assertEquals($s, 'Hello, world!');

        $s = s('Hello, %s!');
        self::assertEquals($s, 'Hello, %s!');

        $h = H('<html>');
        self::assertEquals($h, '&lt;html&gt;');

        $h = H('<html> %s', '<html>');
        self::assertEquals($h, '&lt;html&gt; &lt;html&gt;');

        $v = V('layout');
        self::assertTrue($v instanceof \Gini\View);
    }
}
