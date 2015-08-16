<?php

/**
 * CLI support.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-02-07
 **/

/**
 * Define DocBlock.
 **/

namespace Gini;

class CLI
{
    public static function main(array $argv)
    {
        $cmd = count($argv) > 0 ? strtolower($argv[0]) : '';
        if ($cmd[0] == '@') {
            // @app: automatically set APP_PATH and run
            $app_base_path = isset($_SERVER['GINI_MODULE_BASE_PATH']) ?
                                $_SERVER['GINI_MODULE_BASE_PATH'] : $_SERVER['GINI_SYS_PATH'].'/..';

            $cmd = substr($cmd, 1);
            $_SERVER['GINI_APP_PATH'] = $app_base_path.'/'.$cmd;
            if (!is_dir($_SERVER['GINI_APP_PATH'])) {
                exit("\e[1;34mgini\e[0m: missing app '$cmd'.\n");
            }

            array_shift($argv);
            $eargv = array(escapeshellcmd($_SERVER['SCRIPT_FILENAME']));
            foreach ($argv as $arg) {
                $eargv[] = escapeshellcmd($arg);
            }
            proc_close(proc_open(implode(' ', $eargv), array(STDIN, STDOUT, STDERR), $pipes, null, $_SERVER));

            return;
        }

        switch ($cmd) {
        case '-v':
            $method = 'commandVersion';
            break;
        case '--':
            $method = 'commandAvailable';
            break;
        case '-h':
            $method = 'commandHelp';
            break;
        case '':
        case 'root':
            $method = 'commandRoot';
            break;
        }

        if ($method && method_exists(__CLASS__, $method)) {
            call_user_func(array(__CLASS__, $method), $argv);
        } else {
            static::dispatch($argv);
        }
    }

    public static function commandRoot()
    {
        echo APP_PATH."\n";
    }

    public static function commandVersion()
    {
        $info = \Gini\Core::moduleInfo('gini');
        echo "$info->name ($info->id/$info->version)\n";
    }

    public static function commandHelp()
    {
        echo "Usage: gini <command>\n";
        echo "where <command> is one of:\n";
        echo '    '.implode(',  ', self::possibleCommands([]))."\n\n";
        echo "gini -v    show gini version\n";
        echo "gini -h    show quick help\n";
        echo "\n";
    }

    public static function possibleCommands($argv)
    {
        // list available cli programs
        $candidates = ['/' => []] + Util::pathAndArgs($argv, true);

        $commands = [];
        $class = null;
        foreach (array_reverse($candidates) as $path => $params) {
            $paths = \Gini\Core::pharFilePaths(CLASS_DIR,
                rtrim('Gini/Controller/CLI/'.ltrim($path, '/'), '/'));
            foreach ($paths as $p) {
                if (!is_dir($p)) {
                    continue;
                }

                \Gini\File::eachFilesIn($p, function ($file) use (&$commands) {
                    $name = basename(strtolower(explode('/', $file, 2)[0]), '.php');
                    $commands[$name] = $name;
                });
            }

            // enumerate actions in class
            $path = strtr(ltrim($path, '/'), ['-' => '', '_' => '']);
            $basename = basename($path);
            $dirname = dirname($path);

            $class_namespace = '\Gini\Controller\CLI\\';
            if ($dirname != '.') {
                $class_namespace .= strtr($dirname, ['/' => '\\']).'\\';
            }

            $class = $class_namespace.$basename;
            if (class_exists($class)) {
                break;
            }

            $class = $class_namespace.'Controller'.$basename;
            if (class_exists($class)) {
                break;
            }

            $class = null;
        }

        if (!$class) {
            $class = '\Gini\Controller\CLI\App';
            $params = $argv;
        }

        if (class_exists($class)) {
            $rc = new \ReflectionClass($class);
            $methods = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $m) {
                if (strncmp('action', $m->name, 6) != 0) {
                    continue;
                }
                if (preg_match_all('`([A-Z]+[a-z\d]+|.+)`', substr($m->name, 6), $parts)) {
                    $method = strtolower(implode('-', $parts[0]));
                    if ($params[0] === $method) {
                        $commands = [];
                        break;
                    }
                    $commands[] = $method;
                }
            }
        }

        return $commands;
    }

    public static function commandAvailable($argv)
    {
        array_shift($argv);

        $commands = self::possibleCommands($argv);

        array_walk($commands, function ($command) {
            echo "$command\n";
        });

        echo "\n";
    }

    public static function exception($e)
    {
        $message = $e->getMessage();
        $file = $e->getFile();
        foreach (\Gini\Core::$MODULE_INFO as $info) {
            if (0 == strncmp($file, $info->path, strlen($info->path))) {
                $file = "[$info->id] ".\Gini\File::relativePath($file, $info->path);
                break;
            }
        }
        $line = $e->getLine();
        printf("\e[31m[E] \e[1m%s\e[0m\n", $message);
        error_log(sprintf('[E] %s (%s:%d)', $message, $file, $line));
        $trace = array_slice($e->getTrace(), 1, 3);
        foreach ($trace as $n => $t) {
            $file = $t['file'];
            foreach (\Gini\Core::$MODULE_INFO as $info) {
                if (0 == strncmp($file, $info->path, strlen($info->path))) {
                    $file = "[$info->id] ".\Gini\File::relativePath($file, $info->path);
                    break;
                }
            }
            error_log(sprintf('%3d. %s%s() in (%s:%d)', $n + 1,
                            $t['class'] ? $t['class'].'::' : '',
                            $t['function'],
                            $file,
                            $t['line']));
        }
    }

    public static function dispatch(array $argv)
    {
        if (!isset($GLOBALS['gini.class_map'])) {
            echo "\e[33mNOTICE: You are currently executing commands without cache!\e[0m\n\n";
        }

        $candidates = Util::pathAndArgs($argv, true);

        $class = null;
        foreach (array_reverse($candidates) as $path => $params) {
            $path = strtr(ltrim($path, '/'), ['-' => '', '_' => '']);
            $basename = basename($path);
            $dirname = dirname($path);

            $class_namespace = '\Gini\Controller\CLI\\';
            if ($dirname != '.') {
                $class_namespace .= strtr($dirname, ['/' => '\\']).'\\';
            }

            $class = $class_namespace.$basename;
            if (class_exists($class)) {
                break;
            }

            $class = $class_namespace.'Controller'.$basename;
            if (class_exists($class)) {
                break;
            }
        }

        if (!$class || !class_exists($class, false)) {
            $class = '\Gini\Controller\CLI\App';
            $params = $argv;
        }

        \Gini\Config::set('runtime.controller_path', $path);
        \Gini\Config::set('runtime.controller_class', $class);

        $controller = \Gini\IoC::construct($class);

        $action = strtr($params[0], ['-' => '', '_' => '']);
        if ($action && method_exists($controller, 'action'.$action)) {
            $action = 'action'.$action;
            array_shift($params);
        } elseif (method_exists($controller, '__index')) {
            $action = '__index';
        } else {
            $action = '__unknown';
        }

        $controller->action = $action;
        $controller->params = $params;
        $controller->execute();
    }

    public static function setup()
    {
        URI::setup();
        Session::setup();
    }

    public static function shutdown()
    {
        Session::shutdown();
    }
}
