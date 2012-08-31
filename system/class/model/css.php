<?php

namespace Model;

if (!class_exists('CSS', false)) {
	class CSS extends _CSS {};
}

define('CSS_PATCHED_FLAG', '/*CSS PATCHED*/');

use \Gini\Core;
use \Model\Config;

abstract class _CSS {

	static function format($css) {
		if (preg_match('|^'.preg_quote(CSS_PATCHED_FLAG, '|').'|', $css)) {
			return $css;
		}

		$mini = _CONF('page.css_minify');
		
		Core::load(THIRD_DIR, 'cssp', 'system');
		
		if (class_exists('CSSP', false)) {
			$mode = CSSP::FORMAT_NOCOMMENTS;
			if ($mini) {
				$mode |= CSSP::FORMAT_MINIFY;
			}
			return CSS_PATCHED_FLAG."\n".CSSP::fragment($css)->format($mode);
		}
		
	}

	static function load_async($css) {
		if (is_scalar($css)) $css = array($css);
		
		$urls = array();
		foreach ((array) $css as $f) {
			if (FALSE === strpos($f, '://')) {
				$urls[] = self::cache_file($f);
			}
			else {
				$urls[] = $f;
			}
		}
		
		$output = '<script type="text/javascript">Q.require_css('.json_encode($urls).');</script>';

		return $output;		
	}

	static function cache_file($f) {
		$css_file = Misc::key('css', $f).'.css'; 
		$cache_file = Cache::cache_filename($css_file);
		$cache_path = ROOT_PATH.WEB_DIR.$cache_file;
		$version = (int)_CONF('page.css_version');
		if (_CONF('debug.css_check_cache')) {
			if (file_exists($cache_path)) {
				$files = array_unique(explode(' ', $f));
				$mtime = 0;
				foreach ($files as $file) {
					$file = trim($file);
					list($category, $file) = explode(':', $file, 2);
					if (!$file) { $file = $category; $category = NULL; }
						if (!$file) continue;
					$path = Core::file_exists(PRIVATE_DIR.'css/'.$file.'.css', $category);
					if ($path) {
						$mtime = max($mtime, filemtime($path));
					}
				}

				if ($mtime <= filemtime($cache_path)) {
					return $cache_file.'?v='.$version;
				}
			}
		}
		elseif (file_exists($cache_path)) {
			return $cache_file.'?v='.$version;
		}

		return URI::url('css', array('f'=>$f, 'v'=>$version));
	}

	static function cache_content($f) {	
		$files = array_unique(explode(' ', $f));
		$content = '';
		foreach ($files as $file) {
			$file = trim($file);
			list($category, $file) = explode(':', $file, 2);
			if (!$file) { $file = $category; $category = NULL; }
			if (!$file) continue;

			$path = Core::file_exists(PRIVATE_DIR.'css/'.$file.'.css', $category);
			if ($path) {
				$content .=CSS::format(@file_get_contents($path));
			}			
			
		}

		$url_base = preg_replace('/[^\/]*$/', '', $_SERVER['SCRIPT_NAME']);	
		$content = preg_replace('/\burl\s*\(\s*(["\'])?\s*([^:]+?)\s*\1?\s*\)/', 'url('.$url_base.'\\2)', $content);

		$css_file = Misc::key('css', $f).'.css'; 
		Cache::cache_content($css_file, $content);
		return $content;
	}

}
