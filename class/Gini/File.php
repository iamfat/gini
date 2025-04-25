<?php

/**
 * File Manipulation Class.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright , 2014-02-08
 **/

/**
 * Define DocBlock.
 **/

namespace Gini;

class File
{
    /**
     * Ensure the existence of a directory.
     *
     * @param string $path
     * @param string $mode default is 0755
     *
     * @return bool
     */
    public static function ensureDir($path, $mode = 0755)
    {
        if (!is_dir($path)) {
            return mkdir($path, $mode, true);
        }

        return true;
    }

    /**
     * Convert bytes to human readable string.
     *
     * @param string $byte
     */
    public static function humanReadableBytes($a)
    {
        $unim = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $c = 0;
        while ($a >= 1024) {
            $a >>= 10;
            ++$c;
        }

        return number_format($a) . $unim[$c];
    }

    /**
     * Remove a directory recursively.
     *
     * @param string $path
     */
    public static function removeDir($path)
    {
        if (is_dir($path) && !is_link($path)) {
            $dh = opendir($path);
            if ($dh) {
                while (false !== ($n = readdir($dh))) {
                    if ($n === '.' || $n === '..') {
                        continue;
                    }
                    self::removeDir($path . '/' . $n);
                }
                closedir($dh);
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }

    /**
     * Delete a file. If the directory where the file located was empty then, remove the directory as well.
     *
     * @param string $path
     * @param string $clean_empty
     */
    public static function delete($path, $clean_empty = false)
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }

        if ($clean_empty) {
            $path = dirname($path);
            while (is_dir($path) && rmdir($path)) {
                $path = dirname($path);
            }
        }
    }

    public static function copy($source, $dest, $mode = 0755)
    {
        $dh = @opendir($source);
        if ($dh) {
            while (false !== ($name = readdir($dh))) {
                if ($name == '.' || $name == '..') {
                    continue;
                }

                $path = $source . '/' . $name;
                if (is_dir($path)) {
                    self::copy($path, $dest . '/' . $name);
                } else {
                    self::ensureDir($dest, $mode);
                    $dest_path = $dest . '/' . $name;
                    copy($path, $dest_path);
                }
            }
            @closedir($dh);
        }
    }

    public static function traverse($path, $callback)
    {
        if (false === call_user_func($callback, $path)) {
            return;
        }
        if (is_dir($path)) {
            $path = preg_replace('/[^\/]$/', '$0/', $path);
            $dh = opendir($path);
            if ($dh) {
                while (false !== ($file = readdir($dh))) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    self::traverse($path . $file, $callback);
                }
                closedir($dh);
            }
        }
    }

    public static function relativePath($to, $from = null)
    {
        if (!$from) {
            $from = APP_PATH;
        }

        // 规范化路径
        $from = rtrim(str_replace(['\\', '//', './', '/.'], ['/', '/', '/', '/'], $from), '/');
        $to = rtrim(str_replace(['\\', '//', './', '/.'], ['/', '/', '/', '/'], $to), '/');

        // 分割路径
        $from = array_values(array_filter(explode('/', $from), 'strlen'));
        $to = array_values(array_filter(explode('/', $to), 'strlen'));

        // 比较路径
        $common = 0;
        $max = min(count($from), count($to));
        for ($i = 0; $i < $max; $i++) {
            if ($from[$i] !== $to[$i]) {
                break;
            }
            $common++;
        }

        // 构建相对路径
        $relative = array_fill(0, count($from) - $common, '..');
        $relative = array_merge($relative, array_slice($to, $common));

        return implode('/', $relative) ?: '.';
    }

    public static function inPaths($path, $paths = array())
    {
        foreach ($paths as $p) {
            if (preg_match('|^' . preg_quote($p) . '|iu', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file extension.
     *
     * @param string $path
     *
     * @return string File extension
     */
    public static function extension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Calculate the size of a directory or a file.
     *
     * @param string $path
     */
    public static function size($path)
    {
        if (is_dir($path)) {
            $size = 0;
            if (!is_link($path)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                    if (!is_link($file)) {
                        $size += $file->getSize();
                    }
                }
            }

            return $size;
        } else {
            return filesize($path);
        }
    }

    /**
     * Get file mime type.
     *
     * @param string $file
     *
     * @return string File mime type
     */
    public static function mimeType($file)
    {
        if (file_exists($file)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file);
            finfo_close($finfo);

            return $mime_type;
        }
    }

    public static function eachFilesIn($root, $callback, array $options = [])
    {
        $walk = function ($root, $prefix, $callback) use (&$walk, $options) {
            $dir = $root . '/' . $prefix;
            if (!is_dir($dir)) {
                return;
            }
            $dh = opendir($dir);
            if ($dh) {
                while (false !== ($name = readdir($dh))) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }

                    if ($name[0] === '.' && (!isset($options['hidden']) || $options['hidden'] !== true)) {
                        continue;
                    }

                    $file = $prefix ? $prefix . '/' . $name : $name;
                    $full_path = $root . '/' . $file;

                    if (isset($options['filter']) && !$options['filter']($file)) {
                        continue;
                    }

                    if (is_dir($full_path)) {
                        $walk($root, $file, $callback);
                        continue;
                    }

                    if ($callback) {
                        $callback($file);
                    }
                }
                closedir($dh);
            }
        };

        $walk($root, '', $callback);
    }

    public static function eachDirsIn($root, $callback, array $options = [])
    {
        $walk = function ($root, $prefix, $callback) use (&$walk, $options) {
            $dir = $root . '/' . $prefix;
            if (!is_dir($dir)) {
                return;
            }
            $dh = opendir($dir);
            if ($dh) {
                while (false !== ($name = readdir($dh))) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }

                    if ($name[0] === '.' && $options['hidden'] !== true) {
                        continue;
                    }

                    $file = $prefix ? $prefix . '/' . $name : $name;
                    $full_path = $root . '/' . $file;

                    if ($options['filter'] && !$options['filter']($file)) {
                        continue;
                    }

                    if (is_dir($full_path)) {
                        if ($callback) {
                            $callback($file);
                        }
                        $walk($root, $file, $callback);
                    }
                }
                closedir($dh);
            }
        };

        $walk($root, '', $callback);
    }
}
