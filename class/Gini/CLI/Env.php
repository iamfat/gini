<?php

namespace Gini\CLI;

const QUOTE_PATTERN = '/^(["\'])(.*)\1$/';

class Env
{
    public static function setup()
    {
        $envPath = static::dotEnv();
        if (file_exists($envPath)) {
            $rows = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($rows as &$row) {
                if (!$row || $row[0] == '#') {
                    continue;
                }
                $row = static::normalize($row);
                putenv($row);
            }
        }
    }

    public static function dotEnv()
    {
        return $_SERVER['PWD'] . '/.env';
    }

    public static function stripQuote($value)
    {
        if (preg_match(QUOTE_PATTERN, $value, $quote_matches)) {
            $value = $quote_matches[2] ?? '';
        } else {
            $value = stripslashes($value ?? '');
        }
        return $value;
    }

    public static function normalize($row)
    {
        list($key, $value) = explode('=', trim($row), 2);
        $value = static::stripQuote($value);
        $value = trim(preg_replace_callback('/\$\{([A-Z0-9_]+?)\s*(?:\:\=\s*(.*?))?\s*\}/i', function ($matches) {
            return static::get($matches[1], $matches[2] ?? '');
        }, $value));

        return $key . '=' . addslashes($value);
    }

    public static function get($name, $defaultValue = '')
    {
        $envValue = getenv($name);
        if ($envValue === false) {
            $envValue = static::stripQuote($defaultValue);
        }
        return $envValue;
    }

    public static function getAll()
    {
        return getenv();
    }
}
