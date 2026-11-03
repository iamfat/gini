<?php

namespace Gini\Controller\CLI\CI;

/**
 * Scan legacy business code for PHP 8.5 patterns that the framework cannot
 * absorb (removed functions, removed curly-brace offset syntax). Deprecated
 * but still working semantics are absorbed by the global error handler and
 * are not reported (see openspec/specs/legacy-runtime-compat).
 **/
class Compat extends \Gini\Controller\CLI
{
    /**
     * Functions usable on PHP 7.4 but removed on PHP 8.0+.
     *
     * @ignore
     **/
    public static $removedFunctions = [
        'each',
        'create_function',
        'get_magic_quotes_gpc',
        'get_magic_quotes_runtime',
        'set_magic_quotes_runtime',
        'money_format',
        'jpeg2wbmp',
        'png2wbmp',
        'img2wbmp',
    ];

    /**
     * Directory names skipped while scanning (dependency trees, caches, tooling).
     *
     * @ignore
     **/
    public static $skipDirs = [
        'vendor', 'vendor7', 'vendor8', 'vendor81', 'vendor85', 'node_modules',
        'cache', '.git', 'reports', 'build', '.idea', '.vscode',
    ];

    /**
     * gini ci compat [path]
     *
     * Report legacy-code usages that break on PHP 8.5 with no framework-level
     * fallback. Path argument is consumed as whole params (never treated as
     * action), so `__index` is the CLI entry. Exit code is 1 when anything is
     * found, so it can gate CI.
     *
     * @param array $params [0] = path to scan, defaults to CWD
     **/
    public function __index($params)
    {
        $path = $params[0] ?? getcwd();

        if (!is_dir($path) && !is_file($path)) {
            echo "! not a directory or file: $path\n";

            exit(1);
        }

        echo "Scanning $path for PHP 8.5-fatal legacy patterns ...\n";

        $hits = self::scan($path);

        $hitCount = is_array($hits) ? count($hits) : 0;

        if ($hitCount === 0) {
            echo "OK - no PHP 8.5 incompatible patterns found.\n";

            return;
        }

        foreach ($hits as $hit) {
            echo vsprintf('[%s] %s:%d: %s', $hit), "\n";
        }

        echo $hitCount, " hit(s).\n";
        echo "Note: deprecated-but-working semantics (utf8_encode, strlen(null), "
            . "\${var}, dynamic properties, ...) are absorbed by the framework's "
            . "error handler and are NOT reported here.\n";
        echo "Note: 'count(null)' and other type-juggling tightenings cannot be "
            . "detected statically; review count() calls manually.\n";

        exit(1);
    }

    /**
     * Scan PHP files under $path, excluding dependency/tool directories and
     * the framework's own test fixtures (ci/test/** embed legacy snippets on
     * purpose, testing the scanner itself).
     *
     * @ignore
     **/
    public static function scan($path, $suffixes = ['.php', '.phtml'])
    {
        $self = __DIR__;

        // Framework test files legitimately contain legacy snippets; scanning
        // them would always report hits. Users scanning the framework repo
        // itself only care about runtime code.
        $skipMarker = '/ci/test/';

        $fnPattern = '/(?<![\w$:>-])('.implode('|', self::$removedFunctions).')\s*\(/i';
        // $var{...} - removed offset syntax; skip "${...}" interpolation which
        // starts with "${" not "$ident".
        $bracePattern = '/\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\s*\{[^}]+\}/';

        foreach (self::_phpFiles($path, $suffixes) as $file) {
            // Framework test files legitimately embed legacy snippets (they
            // fixture the scanner itself); scanning them self-reports forever.
            if (strpos(str_replace('\\', '/', $file), $skipMarker) !== false) {
                continue;
            }

            // Skip dependency trees, caches and tooling dirs: hits there are
            // not owned by the scanned project.
            $rel = str_replace('\\', '/', substr($file, strlen($path)));
            foreach (self::$skipDirs as $dir) {
                if (stripos("/{$rel}/", "/{$dir}/") !== false) {
                    continue 2;
                }
            }
            $lines = file($file);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $i => $line) {
                // Strip comments so documentation about the patterns does not
                // self-report; keep string contents (code in strings is real):
                // crude tokenizer: drop // and # single-line comments only when
                // they start the trimmed line or follow code, without a quote open.
                if (preg_match('~^\s*(//|#)~', $line)) {
                    continue;
                }
                $code = preg_replace('~(?<![\'\"])//.*$~', '', $line);
                $code = preg_replace('~(?<![\'\"])#.*$~', '', $code);
                $line = $code;
                foreach ([
                    ['removed-fn', $fnPattern],
                    ['brace-offset', $bracePattern],
                ] as $category => $spec) {
                    [$cat, $pattern] = $spec;
                    if (preg_match($pattern, $line)) {
                        $snippet = trim($line);
                        if (strlen($snippet) > 70) {
                            $snippet = substr($snippet, 0, 70).'...';
                        }
                        $hits[] = [$cat, $file, $i + 1, $snippet];
                    }
                }
            }
        }

        return $hits;
    }

    private static function _phpFiles($path, $suffixes)
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $f) {
            $name = $f->getFilename();
            $matched = false;
            foreach ($suffixes as $suffix) {
                $len = strlen($suffix);
                if (substr($name, -$len) === $suffix) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
            yield $f->getPathname();
        }
    }
}