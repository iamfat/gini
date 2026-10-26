<?php

namespace Gini\PHPUnit\App;

class Cache extends \Gini\PHPUnit\TestCase\Unit
{
    private $tmpDir;
    private $moduleInfoBackup;
    private $classMapBackup;
    private $viewMapBackup;

    protected function setUp(): void
    {
        parent::setUp();

        // 临时目录假模块: class/view/raw 三类夹具
        $this->tmpDir = sys_get_temp_dir() . '/gini-ut-appcache-' . uniqid();
        mkdir($this->tmpDir . '/class/Admin', 0777, true);
        mkdir($this->tmpDir . '/class/Gini/ORM', 0777, true);
        mkdir($this->tmpDir . '/view/a', 0777, true);
        mkdir($this->tmpDir . '/raw/config', 0777, true);

        // class 夹具: 键小写 + '-'→'_', 值保留原始大小写
        file_put_contents($this->tmpDir . '/class/ut-thing.php', '<?php class UtThing {}');
        file_put_contents($this->tmpDir . '/class/Admin/Panel.php', '<?php class Panel {}');
        // ORM 夹具: 非抽象普通类, 通过 isAbstract 过滤参与 plurals 生成
        file_put_contents(
            $this->tmpDir . '/class/Gini/ORM/UtCacheThing.php',
            '<?php namespace Gini\ORM; class UtCacheThing {}'
        );
        // view 夹具: 同名多引擎
        file_put_contents($this->tmpDir . '/view/a/b.phtml', 'B');
        file_put_contents($this->tmpDir . '/view/a/c.js', 'C');
        // config 夹具: 冻结快照标记 + orm.plurals 预置
        file_put_contents($this->tmpDir . "/raw/config/utcache.yml", "marker: hello\n");
        file_put_contents(
            $this->tmpDir . "/raw/config/orm.yml",
            "plurals:\n    preset-alias: thing\n    ut-cache-alias: utcachething\n"
        );

        $this->moduleInfoBackup = \Gini\Core::$MODULE_INFO;
        \Gini\Core::$MODULE_INFO['ut-cache-fixture'] = (object) ['path' => $this->tmpDir];

        $this->classMapBackup = $GLOBALS['gini.class_map'] ?? null;
        $this->viewMapBackup = $GLOBALS['gini.view_map'] ?? null;
    }

    protected function tearDown(): void
    {
        \Gini\Core::$MODULE_INFO = $this->moduleInfoBackup;

        if ($this->classMapBackup === null) {
            unset($GLOBALS['gini.class_map']);
        } else {
            $GLOBALS['gini.class_map'] = $this->classMapBackup;
        }
        if ($this->viewMapBackup === null) {
            unset($GLOBALS['gini.view_map']);
        } else {
            $GLOBALS['gini.view_map'] = $this->viewMapBackup;
        }
        // 清理产物缓存文件, 还原 Config 为文件态
        foreach (['class_map.json', 'view_map.json', 'config.json'] as $f) {
            $p = APP_PATH . '/cache/' . $f;
            if (file_exists($p)) {
                unlink($p);
            }
        }
        \Gini\Config::import(\Gini\Config::fetch());

        $this->removeTmpDir();
        self::assertFileDoesNotExist($this->tmpDir, '夹具临时目录必须清理');

        parent::tearDown();
    }

    private function removeTmpDir(): void
    {
        $remove = function (string $dir) use (&$remove): void {
            if (!is_dir($dir)) {
                return;
            }
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $p = $dir . '/' . $f;
                is_dir($p) ? $remove($p) : unlink($p);
            }
            rmdir($dir);
        };
        $remove($this->tmpDir);
    }

    private function invokePrivate(string $method): string
    {
        $rm = new \ReflectionMethod(\Gini\App\Cache::class, $method);
        $rm->setAccessible(true);
        ob_start();
        $rm->invoke(null);
        $output = ob_get_clean();
        self::assertStringContainsString('done.', $output);

        return $output;
    }

    public function testCacheClassBuildsKeyMapFromAllModules(): void
    {
        $this->invokePrivate('_cacheClass');

        $map = json_decode(file_get_contents(APP_PATH . '/cache/class_map.json'), true);
        self::assertIsArray($map);
        // 键: 小写 + '-'→'_'; 值: 扫描目录 + 原始大小写文件名
        self::assertSame($this->tmpDir . '/class/ut-thing.php', $map['ut_thing']);
        self::assertSame($this->tmpDir . '/class/Admin/Panel.php', $map['admin/panel']);
        // 框架自身类同样入表
        self::assertArrayHasKey('gini/orm', $map);
        // 全局 class_map 同步为同一份
        self::assertSame($map, $GLOBALS['gini.class_map']);
    }

    public function testCacheViewMapsViewNamesAcrossEngines(): void
    {
        $this->invokePrivate('_cacheView');

        $map = json_decode(file_get_contents(APP_PATH . '/cache/view_map.json'), true);
        self::assertIsArray($map);
        // 视图名(去扩展名)为键; 同名不同引擎并存
        self::assertSame($this->tmpDir . '/view/a/b.phtml', $map['a/b']);
        self::assertSame($this->tmpDir . '/view/a/c.js', $map['a/c']);
        // 框架自身视图入表
        self::assertArrayHasKey('error/404', $map);
        self::assertSame($map, $GLOBALS['gini.view_map']);
    }

    public function testCacheConfigFreezesSnapshotAndMergesOrmPlurals(): void
    {
        $this->invokePrivate('_cacheConfig');

        $items = json_decode(file_get_contents(APP_PATH . '/cache/config.json'), true);
        // 夹具模块 raw/config 进入冻结快照
        self::assertSame('hello', $items['utcache']['marker']);

        // ORM 夹具类生成 plurals 候选; 仅当单数名已在预置列表值中才并入
        self::assertSame('utcachething', $items['orm']['plurals']['utcachethings']);
        self::assertSame('thing', $items['orm']['plurals']['preset-alias']);
        self::assertCount(3, $items['orm']['plurals']);

        // Config::setup() 已从冻结文件重载
        self::assertSame('hello', \Gini\Config::get('utcache.marker'));
    }
}
