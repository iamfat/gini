<?php

namespace Model {

    class Util {

        static function array_replace_keys( array & $arr, $key_arr) {
            $new_arr = array();
            foreach($arr as $k=>$v) {
                if(isset($key_arr[$k]))
                    $new_arr[$key_arr[$k]]=$v;
                else 
                    $new_arr[$k]=$v;
            }
            return $arr = & $new_arr;
        }

        static function & make_array( array & $old_arr, $key_key, $val_key){
            $new_arr=array();
            foreach($old_arr as $o){
                if(is_object($o)){
                    $new_arr[$o->$key_key]=$o->$val_key;
                }else{
                    $new_arr[$o[$key_key]]=$o[$val_key];
                }
            }
            return $new_arr;
        }

        static function & array_concat(array $arr, $suffix){
            foreach($arr as & $v){
                $v.=$suffix;
            }
            return $arr;
        }
        
        static function array_prepend(array &$arr, $key, &$value){
            $new_arr = $arr;
            array_splice($arr, 0);
            $arr[$key] = $value;
            $arr += $new_arr;
        }
        
        static function array_merge_deep(array &$arr, array & $arr1){
            foreach($arr1 as $k=>&$v){
                if (is_string($k)) {
                    if (isset($arr[$k]) && is_array($v)) {
                        if(!is_array($arr[$k])) $arr[$k]=array();
                        self::array_merge_deep($arr[$k], $v);
                    } 
                    else {
                        $arr[$k] = $v;
                    }
                }
                else {
                    $arr[] = $v;
                }
            }
        }

        private static $_key_prefix;
        static function set_key_prefix($prefix) {
            self::$_key_prefix = ($prefix ? $prefix.':':'');
        }
        
        static function key() {
            $args = func_get_args();
            array_unshift($args, self::$_key_prefix);
            return hash('md4', implode('.', $args));
        }
        
        static function random_password($length=12, $level=3) {
            list($usec, $sec) = explode(' ', microtime());
            srand((float) $sec + ((float) $usec * 100000));
            
            $validchars[1] = "0123456789abcdfghjkmnpqrstvwxyz";
            $validchars[2] = "0123456789abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $validchars[3] = "0123456789_!@#$%&*()-=+/abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_!@#$%&*()-=+/";
            
            $password  = "";
            $counter   = 0;
            $max_length = strlen($validchars[$level])-1;
            
            while ($counter < $length) {
                $actChar = substr($validchars[$level], rand(0, $max_length), 1);
                // All character must be different
                //if (!strstr($password, $actChar)) {
                    $password .= $actChar;
                    $counter++;
                //}
            }
            
            return $password;
        }

    }

}
