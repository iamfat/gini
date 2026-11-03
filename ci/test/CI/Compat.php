<?php

namespace Gini\PHPUnit\CI;

/**
 * gini ci compat 扫描器的行为契约：
 *  - 命中 PHP 8.0+ 已移除函数与已移除的下标语法；
 *  - 不误报 ${var} 插值、deprecated-but-working 的语义；
 *  - 跳过 vendor/cache/.git 等依赖与工具目录；
 *  - 命中清单的 [category, file, line, snippet] 结构。
 */
class Compat extends \Gini\PHPUnit\TestCase\Unit
{
    private $_fixture;

    protected function setUp(): void
    {
        $this->_fixture = sys_get_temp_dir().'/gini-compat-'.uniqid();
        mkdir($this->_fixture.'/legacy', 0777, true);
        mkdir($this->_fixture.'/vendor/pkg', 0777, true);
        mkdir($this->_fixture.'/.git', 0777, true);

        file_put_contents($this->_fixture.'/legacy/old.php', <<<'PHP'
<?php
$a = each([1, 2]);
$b = create_function('', 'return 1;');
$c = money_format('%i', 1);
$d = get_magic_quotes_gpc();
$str = 'abc';
$e = $str{0};
$f = "${name}";
PHP);
        file_put_contents($this->_fixture.'/legacy/clean.php', '<?php $s = strlen("x");');
        file_put_contents($this->_fixture.'/vendor/pkg/dep.php', '<?php $x = each([1]);');
        file_put_contents($this->_fixture.'/.git/hidden.php', '<?php $y = create_function("", "");');
    }

    protected function tearDown(): void
    {
        \Gini\File::removeDir($this->_fixture);
    }

    public function testScansRemovedFunctionsAndBraceOffsets(): void
    {
        $hits = \Gini\Controller\CLI\CI\Compat::scan($this->_fixture);

        $this->_assertHasHits($hits, 5);
        self::assertSame(
            ['removed-fn', 'brace-offset'],
            array_values(array_unique(array_column($hits, 0))),
            '命中类别应只含已移除函数与花括号下标'
        );
    }

    public function testSkipsVendorAndHiddenDirs(): void
    {
        $hits = \Gini\Controller\CLI\CI\Compat::scan($this->_fixture);

        foreach ($hits as $hit) {
            self::assertStringNotContainsString(
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
                $hit[1],
                '依赖树内的命中不应上报'
            );
            self::assertStringNotContainsString(
                DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR,
                $hit[1],
                '.git 内的命中不应上报'
            );
        }
    }

    public function testNoFalsePositiveForInterpolationAndDeprecatedButWorking(): void
    {
        $hits = \Gini\Controller\CLI\CI\Compat::scan($this->_fixture);
        $snippets = implode("\n", array_column($hits, 3));

        self::assertStringNotContainsString('${name}', $snippets,
            '${var} 插值只是弃用不是移除，不应命中');
        self::assertStringNotContainsString('strlen(', $snippets,
            '传 null 的参数收缩只是弃用，不应命中');
        self::assertStringNotContainsString(
            'restore_include_path', implode('', array_column($hits, 3)),
            'restore_include_path 在 8.5 仍存在，不应列入移除清单');
    }

    public function testSingleFileScan(): void
    {
        $hits = \Gini\Controller\CLI\CI\Compat::scan($this->_fixture.'/legacy/old.php');

        self::assertCount(5, $hits);
        self::assertSame($this->_fixture.'/legacy/old.php', $hits[0][1]);
        self::assertSame(2, $hits[0][2], '逐行行号应从 1 起');
    }

    private function _assertHasHits(array $hits, $count): void
    {
        self::assertCount($count, $hits);
        foreach ($hits as $hit) {
            self::assertCount(4, $hit);
            self::assertMatchesRegularExpression('/^\[?(removed-fn|brace-offset)\]?$/', $hit[0]);
        }
    }

    /**
     * gini ci compat 的 exit(1) 在进程内无法断言；仅在宿主有 docker 时
     * 用子进程跑真实 CLI 验证退出码与文案。容器内（无 docker）跳过。
     */
    public function testIndexCommandExitCodes(): void
    {
        if (!is_executable('/usr/bin/docker') && !is_executable('/usr/local/bin/docker')) {
            $docker = trim((string) shell_exec('which docker 2>/dev/null'));
            if ($docker === '' || !is_executable($docker)) {
                self::markTestSkipped('host docker unavailable (inside container)');
            }
        }

        $repoRoot = dirname(__DIR__, 2);

        $run = function ($target) use ($repoRoot) {
            $cmd = sprintf(
                "docker run --rm -v %s:/app -v %s:/fx:ro -w /app docker.genee.cn/php:8.5-alpine "
                . "sh -c 'ln -sfn vendor85 vendor; bin/gini cache >/dev/null 2>&1; "
                . "bin/gini ci compat /fx; echo EXITCODE=\$?', ",
                escapeshellarg($repoRoot), escapeshellarg($target)
            );
            exec($cmd, $out, $exit);
            return [implode("\n", $out), $exit];
        };

        [$text, $exit] = $run($this->_fixture);
        self::assertStringContainsString('5 hit(s)', $text);
        self::assertStringContainsString('Note: deprecated-but-working semantics', $text);
        self::assertSame(1, $exit, '有命中必须退出码 1（可作 CI 门禁）');

        $clean = $this->_fixture.'/legacy/clean.php';
        [$text2, $exit2] = $run($clean);
        self::assertStringContainsString('OK - no PHP 8.5 incompatible patterns found.', $text2,
            '无命中场景正常返回');
        self::assertSame(0, $exit2);

        [$text3, $exit3] = $run('/definitely/missing/'.uniqid());
        self::assertStringContainsString('not a directory or file', $text3);
        self::assertSame(1, $exit3, '非法路径报错并退出 1');
    }
}