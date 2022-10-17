<?php

namespace Gini\Helper;

class TouchableArray extends \ArrayObject
{
    protected $dirty = false;

    public function offsetSet($key,  $value): void
    {
        parent::offsetSet($key, $value);
        $this->dirty = true;
    }

    public function offsetUnset($key): void
    {
        parent::offsetUnset($key);
        $this->dirty = true;
    }

    public function resetTouch()
    {
        $this->dirty = false;
    }

    public function isTouched()
    {
        return $this->dirty;
    }
}
