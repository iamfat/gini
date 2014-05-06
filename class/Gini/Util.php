<?php

namespace Gini;

class Util
{
    public static function arrayReplaceKeys(array & $arr, $key_arr)
    {
        $new_arr = array();
        foreach ($arr as $k=>$v) {
            if(isset($key_arr[$k]))
                $new_arr[$key_arr[$k]]=$v;
            else
                $new_arr[$k]=$v;
        }

        return $arr = $new_arr;
    }

    public static function arrayMergeDeep(array $arr, array $arr1)
    {
        foreach ($arr1 as $k=>&$v) {
            if (is_string($k)) {
                if (isset($arr[$k]) && is_array($v)) {
                    if(!is_array($arr[$k])) $arr[$k]=array();
                    $arr[$k] = self::arrayMergeDeep($arr[$k], $v);
                } else {
                    $arr[$k] = $v;
                }
            } else {
                $arr[] = $v;
            }
        }

        return $arr;
    }

    public static function randPassword($length=12, $level=3)
    {
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
