<?php

namespace Gini\PHPUnit\Core;

class File extends \Gini\PHPUnit\TestCase\Unit
{
    public function testHumanReadableBytes(): void
    {
        self::assertSame('0B', \Gini\File::humanReadableBytes(0));
        self::assertSame('1,023B', \Gini\File::humanReadableBytes(1023));
        self::assertSame('1KB', \Gini\File::humanReadableBytes(1024));
        self::assertSame('1KB', \Gini\File::humanReadableBytes(1025));
        self::assertSame('1MB', \Gini\File::humanReadableBytes(1024 * 1024));
        self::assertSame('1GB', \Gini\File::humanReadableBytes(1024 ** 3));
        self::assertSame('1TB', \Gini\File::humanReadableBytes(1024 ** 4));
        self::assertSame('1PB', \Gini\File::humanReadableBytes(1024 ** 5));
        // 移位是整除取 floor: 1024*1023 字节 => 1,023KB
        self::assertSame('1,023KB', \Gini\File::humanReadableBytes(1024 * 1023));
    }

    public function testRelativePath(): void
    {
        self::assertSame('c', \Gini\File::relativePath('/a/b/c', '/a/b'));
        self::assertSame('d/e', \Gini\File::relativePath('/a/b/d/e', '/a/b'));
        self::assertSame('../x', \Gini\File::relativePath('/a/x', '/a/b'));
        // 路径完全相同返回 .
        self::assertSame('.', \Gini\File::relativePath('/a/b', '/a/b'));
        // 完全不相干
        self::assertSame('../../x/y', \Gini\File::relativePath('/x/y', '/a/b'));
        // 反斜杠/双斜杠/./. 规范化
        self::assertSame('c', \Gini\File::relativePath('\\a\\b\\c', '/a/b'));
        self::assertSame('c', \Gini\File::relativePath('/a//./b/c', '/a/b/.'));
        // 缺省 $from 时以 APP_PATH 为基准
        self::assertSame('some/file', \Gini\File::relativePath(APP_PATH . '/some/file'));
    }

    public function testInPaths(): void
    {
        self::assertTrue(\Gini\File::inPaths('/foo/bar/baz', ['/foo']));
        self::assertTrue(\Gini\File::inPaths('/FOO/bar', ['/foo']));
        self::assertFalse(\Gini\File::inPaths('/other/path', ['/foo']));
        self::assertFalse(\Gini\File::inPaths('/foo', []));
    }

    public function testExtension(): void
    {
        self::assertSame('php', \Gini\File::extension('/a/b/c.php'));
        self::assertSame('gz', \Gini\File::extension('archive.tar.gz'));
        // pathinfo 把点文件视为带扩展名（PHP 既有行为）
        self::assertSame('bashrc', \Gini\File::extension('.bashrc'));
        self::assertSame('hidden', \Gini\File::extension('/path/.hidden'));
    }
}
