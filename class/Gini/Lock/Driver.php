<?php

namespace Gini\Lock;

interface Driver
{
    public function lock();
    public function unlock();
}
