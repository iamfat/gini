<?php

$gini_dirs = [
    isset($_SERVER['GINI_SYS_PATH']) ? $_SERVER['GINI_SYS_PATH'] . '/../lib' : __DIR__ . '/../lib',
    (getenv("COMPOSER_HOME") ?: getenv("HOME") . '/.composer') . '/vendor/iamfat/gini/lib',
    '/usr/share/local/gini/lib',
];

foreach ($gini_dirs as $dir) {
    $file = $dir.'/phpunit.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
}

die("missing Gini PHPUnit Components!");
