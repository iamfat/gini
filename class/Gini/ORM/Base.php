<?php

namespace Gini\ORM;

/**
 * @method self whose($field);
 * @method self limit($start, $per_page = null);
 * @method self andWhose($field);
 * @method self orWhose($field);
 * @method self whoIs($field);
 * @method self andWhoIs($field);
 * @method self whichIs($field);
 * @method self andWhichIs($field);
 * @method self orWhoIs($field);
 * @method self orWhichIs($field);
 * @method self isIn($args);
 * @method self isNotIn($args);
 * @method self match($op, $v);
 * @method self is($v);
 * @method self isNot($v);
 * @method self beginsWith($v)($v);
 * @method self contains($v);
 * @method self endsWith($v);
 * @method self isLessThan($v);
 * @method self isGreaterThan($v);
 * @method self isGreaterThanOrEqual($v);
 * @method self isLessThanOrEqual($v);
 * @method self isBetween($a, $b);
 * @method self orderBy($field, $mode = 'asc');
*/
abstract class Base extends \Gini\ORM
{
    public $id = 'bigint,primary,serial';
    public $_extra = 'array';

    private $those;    // add to support those API

    public function fetch($force = false)
    {
        if ($this->criteria() === null && $this->those) {
            //try those API
            $ids = (array) $this->those->limit(1)->get('id');
            $this->criteria(reset($ids));
        }

        return parent::fetch($force);
    }

    public function __call($method, $params)
    {
        if ($method === __FUNCTION__) {
            return;
        }

        if (method_exists('\Gini\Those', $method)) {
            if (!$this->those) {
                $this->those = \Gini\IoC::construct('\Gini\Those', $this->name());
            }
            call_user_func_array([$this->those, $method], $params);

            return $this;
        }

        return parent::__call($method, $params);
    }
}
