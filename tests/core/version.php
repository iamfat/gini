<?php

namespace Gini\PHPUnit\Core;

require_once __DIR__ . '/../gini.php';

class Version extends \Gini\PHPUnit\CLI {

    public function testMainCompare()
    {
        $versions = [
            '1.0.0',
            '1.0.3',
            '1.0.14',
            '1.1.0',
            '1.1.3',
            '1.1.14',
            '1.5.0',
            '1.10.0',
            '2.1.0',
            '2.3.4'
        ];
        
        foreach ($versions as $ia => $a) {
            $aVersion = new \Gini\Version($a);
            foreach ($versions as $ib => $b) {
                if ($ia == $ib) {
                    $this->assertEquals($aVersion->compare($b), 0, "expecting $a = $b");
                } elseif ($ia > $ib) {
                    $this->assertEquals($aVersion->compare($b), 1, "expecting $a > $b");
                } else {
                    $this->assertEquals($aVersion->compare($b), -1, "expecting $a < $b");
                }
            }           
        }
    }
    
    public function testPreleaseCompare()
    {
        // 1.0.0-alpha < 1.0.0-alpha.1 < 1.0.0-alpha.beta < 1.0.0-beta < 1.0.0-beta.2 < 1.0.0-beta.11 < 1.0.0-rc.1 < 1.0.0
        $versions = [
            '1.0.0-0',
            '1.0.0-1',
            '1.0.0-2',
            '1.0.0-11',
            '1.0.0-alpha',
            '1.0.0-alpha.1',
            '1.0.0-alpha.beta',
            '1.0.0-beta',
            '1.0.0-beta.2',
            '1.0.0-beta.11',
            '1.0.0-rc.1',
            '1.0.0',
            '1.1.0',
            '2.1.0'
        ];
        
        foreach ($versions as $ia => $a) {
            $aVersion = new \Gini\Version($a);
            foreach ($versions as $ib => $b) {
                if ($ia == $ib) {
                    $this->assertEquals($aVersion->compare($b), 0, "expecting $a = $b");
                } elseif ($ia > $ib) {
                    $this->assertEquals($aVersion->compare($b), 1, "expecting $a > $b");
                } else {
                    $this->assertEquals($aVersion->compare($b), -1, "expecting $a < $b");
                }
            }           
        }
        
    }
    
    public function testRange() {
        
        $tests = [
            ['1.2.3', '1.2.3', true],
            ['1.2.3', '1.2.3-0', false],
            ['>1.2.3', '1.2.5', true],
            ['<1.2.3', '1.2.5', false],
            ['<1.2.3', '1.2.0', true],
            ['~0.2.0', '0.2.0-alpha', true],
            ['~0.2.0', '0.2.0-0', true],
            ['~0.2.0', '0.2.0-1', true],
        ];
        
        foreach ($tests as $t) {
            $v = new \Gini\Version($t[1]);
            if ($t[2]) {
                $this->assertTrue($v->satisfies($t[0]), $t[1] . ' should satisfy ' . $t[0]);
            } else {
                $this->assertFalse($v->satisfies($t[0]), $t[1] . ' should not satisfy ' . $t[0]);
            }
            
        }
        
    }

}
