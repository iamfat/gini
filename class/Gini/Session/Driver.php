<?php

namespace Gini\Session;

interface Driver
{
    public function read($id);
    public function write($id, $data);
    public function destroy($id);
    public function gc($max);
}
