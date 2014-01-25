<?php

namespace Gini {

    class File {
    
        static function ensureDir($path, $mode=0755){
            if (!is_dir($path)) {
                return mkdir($path, $mode, true);
            }
            return true;
        }
    
        static function humanReadableBytes($a) {
            $unim = array('B','KB','MB','GB','TB','PB');
            $c=0;
            while ($a>=1024) {
                $a >>= 10;
                $c++;
            }
            return number_format($a).$unim[$c];
        }
    
        static function removeDir($path) {
            if (is_dir($path) && !is_link($path)) {
                $dh = opendir($path);
                if($dh){
                    while($n = readdir($dh)){
                        if($n[0]=='.')continue;
                        self::removeDir($path.'/'.$n);
                    }
                    closedir($dh);
                }
                rmdir($path);
            }
            else {
                unlink($path);
            }
        }
    
        static function delete($path, $clean_empty = false) {
    
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
    
            if($clean_empty) {
                $path = dirname($path);
                while (is_dir($path) && rmdir($path)) {
                    $path=dirname($path);
                }
            }
    
        }
    
        static function copy($source, $dest, $mode=0755){ 
            $dh = @opendir($source); 
            if ($dh) {
                while($name = readdir($dh)) { 
                    if($name == '.' || $name == '..') 
                        continue; 
    
                    $path = $source . '/' . $name;
                    if (is_dir($path)) { 
                        self::copy($path, $dest . '/' . $name); 
                    } 
                    else {
                        self::ensureDir($dest, $mode);
                        $dest_path = $dest . '/' . $name;
                        copy($path, $dest_path); 
    
                    } 
                } 
                @closedir($dh);
            }
        } 
    
        static function traverse($path, $callback) {
            if (false === call_user_func($callback, $path)) return;
            if (is_dir($path)) {
                $path = preg_replace('/[^\/]$/', '$0/', $path);
                $dh = opendir($path);
                if ($dh) {
                    while ($file = readdir($dh)) {
                        if ($file[0] == '.') continue;
                        self::traverse($path.$file, $callback); 
                    }
                    closedir($dh);
                }
            }
        }
    
        static function relativePath($path, $base=null) {
            if (!$base) $base = getcwd();
            return preg_replace('|^'.preg_quote($base, '|').'/(.*)$|', '$1', $path);
        }
    
        static function inPaths($path, $paths=array()) {
            foreach($paths as $p) {
                if(preg_match('|^'.preg_quote($p).'|iu', $path))return true;
            }
            return false;
        }
    
        static function extension($path) {
            return pathinfo($path, PATHINFO_EXTENSION);
        }
    
        static function size($path) {
            if (is_dir($path)) {
                $size = 0; 
                if(!is_link($path)){
                    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file){ 
                        if(!is_link($file)){
                            $size += $file->getSize(); 
                        }
                    } 
                }
                return $size;
            }
            else {
                return filesize($path);
            }
        }
    
    }

}

