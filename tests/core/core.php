<?php

namespace Gini\PHPUnit\Core;

require_once __DIR__ . '/../gini.php';

class Core extends \Gini\PHPUnit\CLI {

    public function testLocateFile()
    {
        $path = \Gini\Core::locateFile('class/gini/core.php', 'gini');
        $this->assertEquals($path, SYS_PATH.'/class/gini/core.php');

        $path = \Gini\Core::locateFile('class/gini/core.php');
        $this->assertEquals($path, SYS_PATH.'/class/gini/core.php');

        $path = \Gini\Core::locateFile('class/gini/core.php', 'foo');
        $this->assertEquals($path, null);
    }
    
    public function testPharFilePaths()
    {
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, 'gini/core.php');
        $this->assertTrue(in_array(SYS_PATH.'/class/gini/core.php', $paths));
    }
    
    public function testShortcuts()
    {
        _G('@foo', 'bar');
        $this->assertEquals(_G('@foo'), 'bar');
        $this->assertEquals(_G('@foobar'), null);
        
        $s = s('Hello, %s!', 'world');
        $this->assertEquals($s, 'Hello, world!');

        $s = s('Hello, %s!');
        $this->assertEquals($s, 'Hello, %s!');
        
        $h = H('<html>');
        $this->assertEquals($h, '&lt;html&gt;');

        $h = H('<html> %s', '<html>');
        $this->assertEquals($h, '&lt;html&gt; &lt;html&gt;');
        
        $v = V('layout');
        $this->assertTrue($v instanceof \Gini\View);
    }
}
