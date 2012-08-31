<?php

namespace Model;

if (!class_exists('Date', false)) {
	class Date extends _Date {};
}

abstract class _Date {

	private static $_time;

	static function time() {
		return isset(self::$_time) ? (int) self::$_time : time();
	}

	static function set_time($time=NULL) {
		if ($time === NULL) {
			self::$_time = NULL;
		}
		else {
			self::$_time = (int) $time;
		}
	}

	static function range($dfrom, $dto, $from_format=NULL){
		
		if($dfrom > 0) 
			$sfrom = Date::format($dfrom, $from_format);
		else
			$sfrom = T('最初');
		
		if($dto > 0)
			$sto = Date::relative($dto, $dfrom);
		else
			$sto = T('现在');
			
		return $sfrom.' ~ '.$sto;
	}

	static function fuzzy_range($dfrom, $dto, $detail=FALSE){

		if($dfrom > 0)
			$sfrom=Date::fuzzy($dfrom, $detail);
		else
			$sfrom=T('最初');
		
		if($dto > 0)
			$sto=Date::fuzzy($dto, $detail);
		else
			$sto=T('现在');
			
		return $sfrom.' ~ '.$sto;
	}

	static function default_format($type=NULL) {
		$time_format .= _CONF('system.24hour') ? 'H:i:s' : 'h:i:s A';
		switch ($type) {
		case 'time':
			return $time_format;
		case 'date':
			return 'Y/m/d';
		default:
			return 'Y/m/d '.$time_format;
		}
	}

	static function format($time=NULL, $format=NULL) {
		if (!$time) $time = Date::time();
		
		$date = getdate($time);
		
		if (!$format) {
			$format = Date::default_format();
		}

		return date(T($format), $time);
	}
	
	static function format_duration($dfrom, $dto, $precision='s') {
		static $factors;
		if (!$factors) {
			$factors = Date::$UNIT_FACTORS;
			arsort($factors);
		}
		
		$duration = max(0, $dto - $dfrom);
		$output = '';
		$zero = TRUE;
		foreach ($factors as $k=>$v) {
			$n = floor($duration / $v);
			if (!$zero || $n != 0) {
				$zero = FALSE;
				//$interval[$k] = $n;
				$output[] = $n . T(self::$UNITS[$k]);
			}
			$duration = $duration % $v;
			if (!$duration || $k == $precision) break;
		}
		
		return implode(' ', $output);
	}

	protected static $UNITS = array(
		's'=>'秒',
		'i'=>'分钟',
		'h'=>'小时',
		'd'=>'天',
		'm'=>'月',
		'y'=>'年',
	);

	protected static $UNIT_FACTORS = array(
		's'=>1,
		'i'=>60,
		'h'=>3600,
		'd'=>86400,
		'm'=>2592000,
		'y'=>31104000,
	);

	static function units($format=NULL) {
		$units = Date::$UNITS;
		if ($format) {
			$unit_keys = array_flip(str_split($format));
			$units = array_intersect_key($units, $unit_keys);
		}
		
		return array_map("T",$units);
	}
	
	static function unit($key) {
		return Date::$UNITS[$key];
	}
	
	static function convert_interval($time, $unit='s') {
		return Date::$UNIT_FACTORS[$unit] * $time;
	}
	
	static function format_interval($time, $valid=NULL) {
		static $factors;
		if (!$factors) {
			$factors = Date::$UNIT_FACTORS;
			arsort($factors);
		}

		foreach ($factors as $k=>$v) {
			$k_valid = !$valid || FALSE !== strpos($valid, $k);
			if ($k_valid) {
				$last = array(floor($time/$v), $k);
				if (($time % $v) == 0) {
					break;
				}
			}
		}

		return $last ?: array();
	}

	static function relative($time, $now=NULL) {
		if (!$time) return FALSE;
	
		if (!$now) $now = Date::time();
	
		$diff = abs($time - $now);
		$nd=getdate($now);
		$td=getdate($time);

		$time_format = Date::default_format('time');

		if($diff >= 0 && $diff<86400 && $nd['yday'] == $td['yday']){			
			$rest=$diff%3600;
			$hours=($diff-$rest)/3600;
			$seconds=$rest%60;
			$minutes=($rest-$seconds)/60;

			return Date::format($time, $time_format);
		} elseif ($nd['year'] == $td['year']) {
			return Date::format($time, 'm/d '.$time_format);
		} else {
			return Date::format($time, 'Y/m/d '.$time_format);
		}

	}

	static function fuzzy($time, $detail=FALSE, $now = 0) {
		if(!$time)return T('早些时候');
	
		if(!$now) $now = Date::time();
	
		$diff=$now-$time;
	
		$nd=getdate($now);
		$td=getdate($time);


		if($detail){

			$time_format = Date::default_format('time');
			
			if($diff > 0 && $diff<86400 && $nd['yday']==$td['yday']){
				
				$rest=$diff%3600;
				$hours=($diff-$rest)/3600;
				$seconds=$rest%60;
				$minutes=($rest-$seconds)/60;
				
				if ($hours>1) {
					return Date::format($time, $time_format);
				}
				elseif ($hours==1) {
					return T('一个多小时前');
				}
					
				return T('几分钟前');
		
			} elseif (date('Y', $now) == date('Y', $time)) {
				return Date::format($time, 'm/d '.$time_format);
			} else {
				return Date::format($time, 'Y/m/d '.$time_format);
			}
		}
		
		if($nd['year']==$td['year']){
			if($nd['yday']==$td['yday']) return T('今天');
			elseif($nd['yday']-1==$td['yday']) return T('昨天');
			return Date::format($time, 'm/d');
		}
		
		return Date::format($time, 'Y/m/d');
	}

}
