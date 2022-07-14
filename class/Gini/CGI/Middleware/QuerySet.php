<?php

namespace Gini\CGI\Middleware;

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        $needle_len = strlen($needle);
        return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, -$needle_len));
    }
}

const REGEX_RANGE = '#^\s*([\(\[])\s*([^,\s]*)\s*,\s*([^,\s]*)\s*([\)\]])\s*$#im';
const REGEX_BRACKET_COLLECTION = '#^\s*\{\s*(.+)\s*\}\s*$#im';

function smartCast($v)
{
    if ($v === null) return null;

    if ($v === 'true') {
        return true;
    }

    if ($v === 'false') {
        return false;
    }

    if ($v === 'null' || $v === '') {
        return null;
    }

    if (preg_match('/^[\'"](.*)[\'"]$/', $v, $matches)) {
        return $matches[1];
    }

    if (is_numeric($v)) {
        if (strstr($v, '.') === false) {
            return intval($v);
        } else {
            return floatval($v);
        }
    }

    return strval($v);
}

final class QuerySet implements Prototype
{
    static function parseSet($v)
    {
        if (preg_match(REGEX_RANGE, $v,  $matches)) {
            $range = [];

            if ($matches[2]) {
                $lop = $matches[1] === '(' ? 'gt' : 'gte';
                $range[] = [$lop, smartCast($matches[2])];
            }

            if ($matches[3]) {
                $rop = $matches[4] === ')' ? 'lt' : 'lte';
                $range[] = [$rop, smartCast($matches[3])];
            }

            return $range;
        }

        if (preg_match(REGEX_BRACKET_COLLECTION, $v,  $matches)) {
            $items = array_map('trim', explode(',', $matches[1]));
            $items = array_filter($items, function ($it) {
                return $it !== '';
            });
            $values = array_map(function ($it) {
                return smartCast($it);
            }, $items);
            return [
                [
                    'or',
                    $values
                ],
            ];
        }

        if ($v === '') return null;

        $not = false;
        $or = false;
        $like = false;

        if ($v[0] === '!') {
            $not = true;
            $v = substr($v, 1);
        } else if ($v[0] === '|') {
            $or = true;
            $v = substr($v, 1);
        }

        $items = array_map('trim', explode(',', $v));
        $value = count($items) > 1 ? array_map(function ($it) {
            return smartCast($it);
        }, $items) : smartCast($items[0]);

        if (is_string($value)) {
            if (strstr($value, '*') !== false) {
                $like = true;
                $value = strtr($value, '*', '%');
            }
        }

        if ($like) {
            return [[$not ? 'not like' : 'like', $value]];
        }

        if ($not) {
            return [['not', $value]];
        }

        return $or ? [['or', $value]] : $value;
    }

    function process($controller, $action, $params)
    {
        $get = $controller->form('get');
        $dirty = false;
        foreach ($get as $k => $v) {
            $kl = strlen($k);
            if ($kl > 1 && $k[$kl - 1] === '$') {
                // query-set
                $get[substr($k, 0, -1)] = self::parseSet($v);
                $dirty = true;
            }
        }
        if ($dirty) $controller->form('get', $get, true);
    }
}
