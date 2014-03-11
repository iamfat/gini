<?php

namespace Gini\RPC;

class Cookie
{
    public $file;

    public function __construct()
    {
        $this->file = tempnam(sys_get_temp_dir(), 'rpc.cookie.');
    }

    public function __destruct()
    {
         if ($this->file && file_exists($this->file)) unlink($this->file);
    }
}
