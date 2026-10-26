<?php

namespace Gini\PHPUnit\Core;

class View extends \Gini\PHPUnit\TestCase\Unit
{
    private $tmpDir;
    private $mapBackup;
    private $localeBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/gini-ut-view-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        file_put_contents($this->tmpDir . '/hello.phtml', 'Hello <?= H($name) ?>!');
        file_put_contents($this->tmpDir . '/hello_zh.phtml', '你好 <?= H($name) ?>!');
        file_put_contents($this->tmpDir . '/data.js', 'alert(msg);');

        $this->mapBackup = $GLOBALS['gini.view_map'] ?? null;
        $GLOBALS['gini.view_map'] = ($this->mapBackup ?: []) + [
            'ut-view/hello' => $this->tmpDir . '/hello.phtml',
            '@zh_CN/ut-view/hello' => $this->tmpDir . '/hello_zh.phtml',
            'ut-view/data' => $this->tmpDir . '/data.js',
        ];

        $this->localeBackup = \Gini\Config::get('system.locale');
    }

    protected function tearDown(): void
    {
        $this->removeTmpDir();
        if ($this->mapBackup === null) {
            unset($GLOBALS['gini.view_map']);
        } else {
            $GLOBALS['gini.view_map'] = $this->mapBackup;
        }
        \Gini\Config::set('system.locale', $this->localeBackup);
        parent::tearDown();
    }

    private function removeTmpDir(): void
    {
        if (!$this->tmpDir || !is_dir($this->tmpDir)) {
            return;
        }
        foreach (scandir($this->tmpDir) as $f) {
            if ($f !== '.' && $f !== '..') {
                unlink($this->tmpDir . '/' . $f);
            }
        }
        rmdir($this->tmpDir);
    }

    public function testPhtmlEngineRendersVars(): void
    {
        self::assertFileExists($this->tmpDir . '/hello.phtml');

        $view = V('ut-view/hello', ['name' => 'World']);
        self::assertSame('Hello World!', (string) $view);
    }

    public function testViewVarMagicAccessors(): void
    {
        $view = V('ut-view/hello');

        self::assertFalse(isset($view->name));
        $view->name = 'X';
        self::assertSame('X', $view->name);
        self::assertTrue(isset($view->name));

        // 赋 null 触发 __unset
        $view->name = null;
        self::assertFalse(isset($view->name));

        // set() 数组形态与单键形态均返回自身
        $ret = $view->set(['name' => 'Y', 'extra' => 1])->set('solo', 2);
        self::assertSame($view, $ret);
        $view->name = 'World';
        self::assertSame('Hello World!', (string) $view);
    }

    public function testJavascriptEngineWrapsInIifeAndExportsVars(): void
    {
        $view = V('ut-view/data', ['msg' => 'hi']);

        self::assertSame(
            "(function () {\nvar msg=\"hi\";\nalert(msg);\n})();",
            (string) $view
        );
    }

    public function testToStringResultIsCachedPerViewInstance(): void
    {
        $view = V('ut-view/hello', ['name' => 'First']);
        self::assertSame('Hello First!', (string) $view);

        // 渲染后改写模板, 同一实例仍返回缓存输出
        file_put_contents($this->tmpDir . '/hello.phtml', 'Hello <?= H($name) ?> CHANGED!');
        self::assertSame('Hello First!', (string) $view);

        // 新实例才读取新模板
        self::assertSame('Hello First CHANGED!', (string) V('ut-view/hello', ['name' => 'First']));
    }

    public function testMissingViewRendersEmptyString(): void
    {
        self::assertSame('', (string) V('ut-view/nonexistent', ['x' => 1]));
    }

    public function testLocaleSpecificViewMapKeyTakesPrecedence(): void
    {
        \Gini\Config::set('system.locale', 'zh_CN');

        self::assertSame('你好 世界!', (string) V('ut-view/hello', ['name' => '世界']));
    }
}
