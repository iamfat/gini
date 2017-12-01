<?php

namespace Gini\Session;

class None implements \SessionHandlerInterface
{
    public function close()
    {
        return true;
    }
    public function destroy($session_id)
    {
        return true;
    }
    public function gc($maxlifetime)
    {
        return true;
    }
    public function open($save_path, $session_name)
    {
        return true;
    }
    public function read($session_id)
    {
        return false;
    }
    public function write($session_id, $session_data)
    {
        return true;
    }
}
