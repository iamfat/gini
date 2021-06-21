<?php

namespace Gini\ORM {
    class Condition extends Where
    {
        public $column;
        public $operator;
        public $params;


        public function __construct($name)
        {
            $this->column = $name;
            return $this;
        }

        public function isIn($value) {
            $this->operator = 'in';
            $this->params = $value;
            return $this;
        }

        public function isNotIn($value) {
            $this->operator = 'not in';
            $this->params = $value;
            return $this;
        }

        public function isRelatedTo($value) {
            $this->operator = 'match';
            $this->params = $value;
            return $this;
        }

        public function match($op, $value) {
            switch ($op) {
                case '^=':
                    $this->operator = 'like';
                    $this->params = $value.'%';
                    break;

                case '$=':
                    $this->operator = 'like';
                    $this->params = '%'.$value;
                    break;

                case '*=':
                    $this->operator = 'like';
                    $this->params = '%'.$value.'%';
                    break;

                case '=':
                    $this->operator = '=';
                    $this->params = $value;
                    break;

                case '<>':
                    $this->operator = '<>';
                    $this->params = $value;
                    break;
                break;

                default:
                    $this->operator = $op;
                    $this->params = $value;

                    break;
            }
            return $this;
        }

        // is(1), is('hello'), is('@name')
        public function is($v)
        {
            return $this->match('=', $v);
        }

        public function isNot($v)
        {
            return $this->match('<>', $v);
        }

        public function beginsWith($v)
        {
            return $this->match('^=', $v);
        }

        public function contains($v)
        {
            return $this->match('*=', $v);
        }

        public function endsWith($v)
        {
            return $this->match('$=', $v);
        }

        public function isLessThan($v)
        {
            return $this->match('<', $v);
        }

        public function isGreaterThan($v)
        {
            return $this->match('>', $v);
        }

        public function isGreaterThanOrEqual($v)
        {
            return $this->match('>=', $v);
        }

        public function isLessThanOrEqual($v)
        {
            return $this->match('<=', $v);
        }

        public function isBetween($a, $b)
        {
            $this->operator = 'between';
            $this->params = [$a, $b];
            return $this;
        }
    }
}
