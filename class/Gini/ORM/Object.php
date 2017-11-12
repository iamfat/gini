<?php

namespace Gini\ORM;

class Object extends \Gini\ORM
{
    public $id = 'bigint,primary,serial';
    public $_extra = 'array';

    protected static $db_name;      // database name
    protected static $db_index;     // database index
    protected static $db_relation;  // database relation

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
        if ($method == __CLASS__) {
            return;
        }

        if (method_exists('\Gini\Those', $method)) {
            if (!$this->those) {
                $this->those = \Gini\IoC::construct('\Gini\Those', $this->name());
            }
            call_user_func_array([$this->those, $method], $params);

            return $this;
        }
    }
}
