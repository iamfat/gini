<?php

$gini_dirs = [
    isset($_SERVER['GINI_SYS_PATH']) ? $_SERVER['GINI_SYS_PATH'] . '/../bin' : '../bin',
    '/usr/share/local/gini/bin',
];

foreach ($gini_dirs as $dir) {
    $file = $dir.'/gini-phpunit.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
}

die("missing Gini PHPUnit Components!");
