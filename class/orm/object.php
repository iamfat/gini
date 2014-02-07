<?php

namespace ORM;

class Object extends \Gini\ORM
{
    public $id = 'bigint,primary,serial';
    public $_extra = 'array';

    static $_db;    // database object
    static $_db_index ; // database index,

    private $those;    // add to support those API

    public function fetch($force=false)
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
        if ($method == __CLASS__) return;

        if (method_exists('\\Gini\\Those', $method)) {
            if (!$this->those) $this->those = new \Gini\Those($this->name());
            call_user_func_array(array($this->those, $method), $params);

            return $this;
        }

        return call_user_func_array(array($this->object, $method), $params);
    }

}
