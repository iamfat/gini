<?php

namespace Gini\Session;

class None implements \SessionHandlerInterface
{
    public function close()
    {
        return true;
    }
    public function destroy(string $session_id)
    {
        return true;
    }
    public function gc(int $maxlifetime)
    {
        return true;
    }
    public function open(string $save_path, string $session_name)
    {
        return true;
    }
    public function read(string $session_id)
    {
        return false;
    }
    public function write(string $session_id, string $session_data)
    {
        return true;
    }
}
