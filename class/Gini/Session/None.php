<?php

namespace Gini\Session;

class None implements \SessionHandlerInterface
{
    public function close(): bool
    {
        return true;
    }
    public function destroy($session_id): bool
    {
        return true;
    }
    public function gc($maxlifetime)
    {
        return false;
    }
    public function open($save_path, $session_name): bool
    {
        return true;
    }
    public function read($session_id): string
    {
        return '';
    }
    public function write($session_id, $session_data): bool
    {
        return true;
    }
}
