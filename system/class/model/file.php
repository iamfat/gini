<?php

namespace Model;

class File {

	static function exists(){
		$paths=func_get_args();
		foreach($paths as $path){
			if(is_array($path)){
				$path=call_user_func_array('File::exists', array_reverse($path));
				if($path) return $path;
			} elseif(file_exists($path)) {
				return $path;
			}
		}
		return NULL;
	}
	
	static function check_path($path, $mode=0755){
		$path = dirname($path);
		if (!is_dir($path)) {
			return mkdir($path, $mode, true);
		}
		return TRUE;
	}

	static function bytes($a) {
		$unim = array('B','KB','MB','GB','TB','PB');
		$c=0;
		while ($a>=1024) {
			$a >>= 10;
			$c++;
		}
		return number_format($a).$unim[$c];
	}
	
	static function rmdir($path) {
		if (is_dir($path) && !is_link($path)) {
			$dh=@opendir($path);
			if($dh){
				while($n=readdir($dh)){
					if($n[0]=='.')continue;
					self::rmdir($path.'/'.$n);
				}
				@closedir($dh);
			}
			@rmdir($path);
		}
		else {
			@unlink($path);
		}
	}
	
	static function delete($path, $clean_empty = FALSE) {

		if (is_file($path) || is_link($path)) {
			@unlink($path);
		}
		
		if($clean_empty) {
			$path=dirname($path);
			while(is_dir($path) && @rmdir($path)){
				$path=dirname($path);
			}
		}

	}

	static function copy_r($source, $dest, $mode=0755){ 
		$dh = @opendir($source); 
		if ($dh) {
			while($name = readdir($dh)) { 
				if($name == '.' || $name == '..') 
					continue; 

				$path = $source . '/' . $name;
				if (is_dir($path)) { 
					File::copy_r($path, $dest . '/' . $name); 
				} 
				else {
					$dest_path = $dest . '/' . $name;
				   	File::check_path($dest_path, $mode);
					copy($path, $dest_path); 
					
				} 
			} 
			@closedir($dh);
		}
	} 

	static function traverse($path, $callback, $params=NULL, $parent=NULL) {
		if (FALSE === call_user_func($callback, $path, $params)) return;
		if (is_dir($path)) {
			$path = preg_replace('/[^\/]$/', '$0/', $path);
			$dh = opendir($path);
			if ($dh) {
				while ($file = readdir($dh)) {
					if ($file[0] == '.') continue;
					self::traverse($path.$file, $callback, $params, $path); 
				}
				closedir($dh);
			}
		}
	}
	
	static function relative_path($path, $base=NULL) {
		if (!$base) $base = getcwd();
		/*
			Cheng.Liu@2010.11.13
			兼容去除路径中'/'的问题
		*/
		return preg_replace('|^'.preg_quote($base, '|').'/(.*)$|', '$1', $path);
	}

	static function in_paths($path, $paths=array()) {
		foreach($paths as $p) {
			if(preg_match('|^'.preg_quote($p).'|iu', $path))return TRUE;
		}
		return FALSE;
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
			return @filesize($path);
		}
	}
	
	static function basename($path) {

		//返回$url最后出现"/"的位置
		$pos = strrpos($path,"/");
		$pos = $pos === FALSE ? strrpos($path,"\\") : $pos;
		$pos = $pos === FALSE ? -1 : $pos;
		
		$len = strlen($path);
		if ($len == 0 || $len < $pos) {
			return FALSE;
		}
		else {
			$filename = substr($path, $pos + 1, $len - $pos - 1);
			return $filename;
		}
   
	}
	
}
