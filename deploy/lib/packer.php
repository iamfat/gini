<?php

require_once __DIR__ . "/macro.php";
require_once __DIR__ . "/obfuscator.php";

class Packer {

	private $file;
	
	private $phar;
	private $phar_base;
	
	function __construct($file) {
		if (ini_get('phar.readonly')) {
			die ("Please set \x1b[1mphar.readonly=Off\x1b[0m in php.ini\n");
		}
		ini_set('phar.readonly', FALSE);
		
		// touch($file);
		try {
			$this->phar = new Phar($file, 0, basename($file));
		}
		catch (UnexpectedValueException $e) {
			@unlink($file);
			$this->phar = new Phar($file, 0, basename($file));
		}
		$this->phar->setStub('<?php echo "GINI PACK FILE.\n"; __HALT_COMPILER();?>');

		$this->phar_base = preg_replace('|\.phar$|', '/', $file);
	}
	
	private static function relative_path($path, $base) {
		return preg_replace('|^'.preg_quote($base, '|').'/?(.*)$|', '$1', $path);
	}
	
	function import($path) {

		$ite = new RecursiveDirectoryIterator($path);
		foreach (new RecursiveIteratorIterator($ite) as $filename => $fileinfo) {
			$name = basename($filename);
			if ($name[0] == '.') continue;
			$this->encode_file($filename);
		}

	}
	
	function encode_file($path) {
		$rpath = self::relative_path($path, $this->phar_base);

		if (preg_match('/\.(php|phtml)$/', $path)) {
			echo "Encoding PHP: $rpath...";
			//移除脚本中的注释和回车	
			$content = file_get_contents($path);
			$total = strlen($content);

			// 预编译代码
			if (class_exists('Macro')) {
				$content = Macro::compile($content);
			}

			// 混淆变量
			if (class_exists('Obfuscator')) {
				$ob = new Obfuscator($content);
				$ob->set_reserved_keywords(['$config', '$lang']);
				$content = $ob->format();
			}

			$converted = strlen($content);
			echo "$converted / $total";
		}
		elseif (fnmatch("*.js", $path)) {
			echo "Compiling JS: $rpath...";
			
			$content = @file_get_contents($path);
			$total = strlen($content);

			$content = shell_exec('uglifyjs '.escapeshellarg($path) . ' 2>&1');
			$converted = strlen($content);

			echo "$converted / $total";
		}
		elseif (fnmatch('*.css', $path)) {
			echo "Compiling CSS: $rpath...";
			$content = @file_get_contents($path);
			$total = strlen($content);

			$content = CSSMin::minify($content);
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
