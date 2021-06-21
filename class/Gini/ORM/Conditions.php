<?php

namespace Gini\ORM {

    class Conditions extends Where
    {
        const TYPE_ALL = 'all';
        const TYPE_ANY = 'any';
        public static $allow_types = [
            self::TYPE_ALL,
            self::TYPE_ANY
        ];

        public $object_list = [];
        public $type = '';
        public function __construct($type, $object_list)
        {
            if (!in_array($type, self::$allow_types)) {
                throw new \Exception('unknown type');
            }
            $this->type = $type;
            $this->object_list = $object_list;
            return $this;
        }
    }
}
