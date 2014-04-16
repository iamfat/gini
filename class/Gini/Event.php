<?php

/**
 * Gini Event
 *
 * @author Jia Huang
 * @version $Id$
 * @copyright Genee, 2014-02-08
 **/

/**
 * Define DocBlock
 **/

namespace Gini;

class Event
{
    private static $_EVENTS=[];

    private $queue=[];
    private $sorted=false;
    private $name;

    private $_return;
    private $_pass = false;
    private $_abort = false;

    private function _sort()
    {
        uasort($this->queue, function ($a, $b) {
            if ($a->weight != $b->weight) {
                return $a->weight > $b->weight;
            }

            return $a->order > $b->order;
        });

        $this->sorted=true;
    }

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function pass()
    {
        $this->_pass = true;
    }

    public function abort()
    {
        $this->_abort = true;
    }

    private $triggering = false;
    private function _trigger(array $params)
    {
        if (!$this->sorted) $this->_sort();

        array_unshift($params, $this);

        foreach ($this->queue as $hook) {

            $return = $hook->return;
            if (is_array($return) && count($return) == 2 && is_object($return[0]) ) {
                // array(object, method)
                $callback = $return;
            } elseif (is_object($return) && ($return instanceof \Closure)) {
                $callback = $return;
            } elseif (is_string($return) && 0 == strncmp($return, 'callback:', 9)) {
                // callback:\Namespace\Class\Method
                $callback = substr($return, 9);
            } else {
                $this->_abort = true;
                $this->_return = $return;
                break;
            }

            $this->_pass = false;
            if (is_callable($callback)) {
                $return = call_user_func_array($callback, $params);
            }

            if (!$this->_pass) {
                $this->_return = $return;
            }

            if ($this->_abort) break;
        }

    }

    public function addHandler($return, $weight=0, $key=null)
    {
        $event = (object) ['weight'=>$weight, 'return'=>$return];

        if (!$key) {
            if (is_array($return)
                && count($return) == 2 && is_object($return[0])) {
                $key = 'dynamic:'.spl_object_hash($return[0]).'.'.$return[1];
            } elseif (is_object($return) && ($return instanceof \Closure)) {
                $key = 'dynamic:'.spl_object_hash($return);
            } elseif (is_string($return)
                && 0 == strncmp($return, 'callback:', 9)) {
                $key = $return;
            } else {
                $key = 'return:'.J($return);
            }
        }

        $key = strtolower($key);
        if (!isset($this->queue[$key])) {
            $event->order = count($this->queue);
        }

        $this->queue[$key] = $event;

    }

    public static function get($name, $ensure=false)
    {
        $e = self::$_EVENTS[$name];
        if (!$e && $ensure) {
            $e = self::$_EVENTS[$name] = new Event($name);
        }

        return $e;
    }

    public static function _normalizeNames($selector)
    {
        return is_array($selector) ? $selector : [$selector];
    }

    /**
     * Bind some events with specified callback with/without weight
     *
     * @param  string $names
     * @param  string $return
     * @param  string $weight
     * @return void
     */
    public static function bind($names, $return, $weight=0)
    {
        $names = static::_normalizeNames($names);

        \Gini\Logger::of('core')
            ->debug("{name} <= {return} [{weight}]", [
                'name' => J($names),
                'return' => J($return),
                'weight' => $weight
            ]);

        foreach ($names as $name) {
            static::get($name, true)->addHandler($return, $weight);
        }
    }

    /**
     * Trigger specified events
     *
     * @param  string $names
     * @return void
     */
    public static function trigger()
    {
        $return = null;
        $params = func_get_args();
        $_EVENTS = array_shift($params);
        foreach (static::_normalizeNames($_EVENTS) as $name) {
            $e = static::get($name);
            if ($e) {
                $e->_abort = false;
                $e->_return = $return;
                $e->_trigger($params);
                $return = $e->_return;
                $e->_return = null;
                if ($e->_return) {
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Check if an event was binded
     *
     * @param  string $name event name
     * @return bool
     */
    public static function isBinded($name)
    {
        $e = static::get($name);

        return $e ? count($e->queue) > 0 : false;
    }

    /**
     * Load event hooks according config
     *
     * @return void
     */
    public static function setup()
    {
        foreach ((array) \Gini\Config::get('hooks') as $event => $event_hooks) {
            foreach ((array) $event_hooks as $key => $hook) {
                if (!is_string($key)) {
                    $key = null;
                }

                // $config['xxx'] = array('return'=>'callback:xxx_func', );
                if (is_array($hook) && isset($hook['return'])) {
                    $return = $hook['return'];
                    $weight = $hook['weight'];
                } else {
                    $return = $hook;
                    $weight = 0;
                }

                static::bind($event, $return, $weight, $key);
            }
        }
    }

}
