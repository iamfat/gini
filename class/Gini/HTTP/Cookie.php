<?php

namespace Gini\HTTP;

class Cookie
{
    public $file;

    public function __construct()
    {
        $this->file = tempnam(sys_get_temp_dir(), 'http.cookie.');
        register_shutdown_function([$this, 'destruct']);
    }

    public function destruct()
    {
        if ($this->file && file_exists($this->file)) {
            unlink($this->file);
        }
    }
}
