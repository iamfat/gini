<?php

require_once "obfuscator.php";


class Packer {

	private $output_file;
	
	private $phar;
	private $phar_base;
	
	function __construct($output_file) {
		ini_set('phar.readonly', FALSE);
		
		touch($output_file);
		$this->output_file = realpath($output_file);
		$this->phar_base = preg_replace('|\.phar$|', '/', $this->output_file);
	}
	
	private static function relative_path($path, $base) {
		return preg_replace('|^'.preg_quote($base, '|').'/?(.*)$|', '$1', $path);
	}
	
	function add($path) {
		try {
			$this->phar = new Phar($this->output_file, 0, basename($this->output_file));
		}
		catch (UnexpectedValueException $e) {
			@unlink($this->output_file);
			$this->phar = new Phar($this->output_file, 0, basename($this->output_file));
		}
		$this->phar->setStub('<?php echo "GENEE. Life Gets Easier.\n"; __HALT_COMPILER();?>');
		//$this->phar->startBuffering();

		$path = realpath($path);
		if (is_dir($path)) {
			$this->phar_base = $path;
			$ite = new RecursiveDirectoryIterator($path);
			foreach (new RecursiveIteratorIterator($ite) as $filename => $fileinfo) {
				$name = basename($filename);
				if ($name[0] == '.') continue;
				$this->encode_file($filename);
			}
		}
		else {
			$this->phar_base = dirname($path);
			$this->encode_file($path);
		}
		
		//$this->phar->stopBuffering();
		//$this->phar->compressFiles(Phar::GZ); 
	}
	
	function encode_file($path) {
		$rpath = self::relative_path($path, $this->phar_base);

		if (preg_match('/\.(php|phtml)$/', $path, $matches)) {
			echo "Encoding PHP: $rpath...";
			//移除脚本中的注释和回车	
			$content = file_get_contents($path);
			$total = strlen($content);

			// 预编译代码
			if (class_exists('PHP_MCompiler')) {
				$mc = PHP_MCompiler($content);
				$content = $mc->compile($content);
			}

			// 混淆变量
			if (class_exists('PHP_Obfuscator')) {
				$ob = new PHP_Obfuscator($content);
				$ob->set_reserved_keywords(array('$config', '$lang'));
				$content = $ob->format();
			}

			$converted = strlen($content);
			echo "$converted / $total";
		}
		elseif (preg_match('/\.(js)$/', $path)) {
			echo "Compiling JS: $rpath...";
			$content = @file_get_contents($path);
			$total = strlen($content);

			// TODO: 使用closure compiler
			ob_start();
			passthru('java -jar '.dirname(__FILE__).'/compiler.jar --js '.escapeshellarg($path), $ret);
			if ($ret==0) {
				$content = JS_FORMATTED."\n".ob_get_contents();
				$converted = strlen($content);
			}
			ob_end_clean();
			/*
			$content = JS_FORMATTED."\n".JSMin::minify($content);
			$converted = strlen($content);
			*/

			echo "$converted / $total";
		}
		elseif (preg_match('/\.(css)$/', $path)) {
			echo "Compiling CSS: $rpath...";
			$content = @file_get_contents($path);
			$total = strlen($content);

			$content = CSS_PATCHED_FLAG."\n"
				. CSSP::fragment($content)->format(CSSP::FORMAT_NOCOMMENTS | CSSP::FORMAT_MINIFY);
			$converted = strlen($content);

			echo "$converted / $total";
		}
		elseif (preg_match('/\.(cssp)$/', $path)) {
			echo "Compressing CSSP: $rpath...";
			$content = @file_get_contents($path);
			$total = strlen($content);

			$content = preg_replace('/\s+/', ' ', $content);
			$converted = strlen($content);
			echo "$converted / $total";
		}
		else {
			echo "Copying $rpath...";
			//复制相关文件
			$content = @file_get_contents($path);
			$total = strlen($content);
			echo "$total bytes";
		}

		if ($content) $this->phar[$rpath] = $content;
		else echo "... EMPTY";
		echo "\n";
	}
	
}
