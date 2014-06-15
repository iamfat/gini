<?php

/**
 * CLI support
 *
 * @author Jia Huang
 * @version $Id$
 * @copyright Genee, 2014-02-07
 **/

/**
 * Define DocBlock
 **/

namespace Gini;

class CLI
{
    const PARSE_BLANK = 0;
    const PARSE_IN_ARG = 1;
    const PARSE_IN_QUOTE = 2;

    public static function parseArguments($line)
    {
        $max = strlen($line);
        $st;     // parsing status: PARSE_BLANK, PARSE_IN_ARG, PARSE_IN_QUOTE
        $qt;    // quote char
        $esc;    // escape or not
        $args = array();    // arguments

        for ($i = 0; $i < $max; $i++) {
            $c = $line[$i];
            if ($esc) {
                if ($c == '0' || $c == 'x') {
                    $arg .= stripcslashes('\\'.substr($line, $i, 3));
                    $i += 2;
                } else {
                    $arg .= stripcslashes('\\'.$c);
                }

                $esc = false;

                if ($st == self::PARSE_BLANK) {
                    $st = self::PARSE_IN_ARG;
                    $qt = null;
                }
                continue;
            } elseif ($c == '\\') {
                $esc = true;
                continue;
            }

            switch ($st) {
            case self::PARSE_BLANK:
                if ($c == ' ' || $c == "\t") {
                    continue;
                } elseif ($c == '"' || $c == '\'') {
                    $st = self::PARSE_IN_QUOTE;
                    $qt = $c;
                } else {
                    $arg .= $c;
                    $st = self::PARSE_IN_ARG;
                    $qt = null;
                }
                break;
            case self::PARSE_IN_ARG:
                if ($c == ' ' || $c == "\t") {
                    $args[] = $arg;
                    $arg = '';
                    $st = self::PARSE_BLANK;
                } else {
                    $arg .= $c;
                }
                break;
            case self::PARSE_IN_QUOTE:
                if ($c == $qt) {
                    $st = self::PARSE_BLANK;
                    $args[] = $arg;
                    $arg = '';
                } else {
                    $arg .= $c;
                }
                break;
            }

        }

        if ($arg) {
            $args[] = $arg;
        }

        return $args;
    }

    public static function parsePrompt($prompt)
    {
        return preg_replace_callback('|%(\w+)|', function ($matches) {
            return $_SERVER[$matches[1]] ?: getenv($matches[1]) ?: $matches[0];
        }, $prompt);
    }

    public static function relaunch()
    {
        //$ph = proc_open($_SERVER['_'] . ' &', array(STDIN, STDOUT, STDERR), $pipes, null, $env);
        // fork process to avoid memory leak
        $env_path = '/tmp/gini-cli';
        $env_file = $env_path.'/'.posix_getpid().'.json';
        if (!file_exists($env_path)) @mkdir($env_path, 0777, true);
        file_put_contents($env_file, J($_SERVER));
        if (isset($_SERVER['__RELAUNCH_PROCESS'])) {
            unset($_SERVER['__RELAUNCH_PROCESS']);
            exit(200);
        } else {
            do {
                // load $_SERVER from shared memo-cliry
                $_SERVER['__RELAUNCH_PROCESS'] = 1;
                $ph = proc_open($_SERVER['SCRIPT_FILENAME'], array(STDIN, STDOUT, STDERR), $pipes, null, $_SERVER);
                if (is_resource($ph)) {
                    $code = proc_close($ph);
                    $_SERVER = (array) json_decode(@file_get_contents($env_file), true);
                }
            } while ($code == 200);
            exit;
        }
    }

    private static $prompt;

    public static function main(array $argv)
    {
        $cmd = count($argv) > 0 ? strtolower($argv[0]) : '';

        if ($cmd[0] == '@') {
            // @app: automatically set APP_PATH and run
            $app_base_path = isset($_SERVER['GINI_MODULE_BASE_PATH']) ?
                                $_SERVER['GINI_MODULE_BASE_PATH'] : $_SERVER['GINI_SYS_PATH'].'/..';

            $cmd = substr($cmd, 1);
            $_SERVER['GINI_APP_PATH'] = $app_base_path . '/' .$cmd;
            if (!is_dir($_SERVER['GINI_APP_PATH'] )) {
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
        case '--':
            $method = 'commandAvailable';
            break;
        case '-v':
            $method = 'commandVersion';
            break;
        case '':
        case '-h':
            $method = 'commandHelp';
            break;
        case 'root':
            $method = 'commandRoot';
            break;
        }

        if ($method && method_exists(__CLASS__, $method)) {
            call_user_func(array(__CLASS__, $method), $argv);
        } else {
            $GLOBALS['GINI.CURRENT_CLI'] = $cmd;
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
        echo "    ".implode(",  ", self::possibleCommands([]))."\n\n";
        echo "gini -v    show gini version\n";
        echo "gini -h    show quick help\n";
        echo "\n";
    }

    public static function possibleCommands($argv)
    {
        // list available cli programs
        $candidates = ['/'=>[]] + Util::pathAndArgs($argv, true);

        $commands = [];
        $class = null;
        foreach (array_reverse($candidates) as $path => $params) {
            $paths = \Gini\Core::pharFilePaths(CLASS_DIR, rtrim('Gini/Controller/CLI/'.$path, '/'));
            foreach ($paths as $p) {
                if (!is_dir($p)) continue;

                $dh = opendir($p);
                if ($dh) {
                    while ($name = readdir($dh)) {
                        if ($name[0] == '.') continue;
                       if (!is_file($p . '/' . $name)) continue;
                         $commands[] = strtolower(basename($name, '.php'));
                    }
                    closedir($dh);
                }

            }

            if (count($commands) > 0) break; // break the loop if hits.

            // enumerate actions in class
            $path = ltrim($path, '/');
            $basename = basename($path);
            $dirname = dirname($path);

            $class_namespace = '\Gini\Controller\CLI\\';
            if ($dirname != '.') {
                $class_namespace .= strtr($dirname, ['/'=>'\\']).'\\';
            }

            $class = $class_namespace . $basename;
            if (class_exists($class)) break;

            $class = $class_namespace . 'Controller' . $basename;
            if (class_exists($class)) break;

            $class = null;
        }

        if (count($commands) == 0 || $path == '/') {

            if (!$class) {
                $class = '\Gini\Controller\CLI\App';
                $params = $argv;
            }

            if (class_exists($class)) {
                $rc = new \ReflectionClass($class);
                $methods = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $m) {
                    if (strncmp('action', $m->name, 6) != 0) continue;
                    if (preg_match_all('`([A-Z]+[a-z\d]+|.+)`', substr($m->name, 6), $parts)) {
                        $method = array_reduce($parts[0], function ($v, $i) {
                            return ($v ? $v . '-' :  '') . strtolower($i);
                        });
                        if ($params[0] === $method) {
                            $commands = [];
                            break;
                        }
                        $commands[] = $method;
                    }
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
        printf("[E] \e[1m%s\e[0m (\e[1;34m%s\e[0m:$line)\n", $message, $file, $line);
        // if (is debugging) {
            $trace = array_slice($e->getTrace(), 1, 3);
            foreach ($trace as $n => $t) {
                $file = $t['file'];
                foreach (\Gini\Core::$MODULE_INFO as $info) {
                    if (0 == strncmp($file, $info->path, strlen($info->path))) {
                        $file = "[$info->id] ".\Gini\File::relativePath($file, $info->path);
                        break;
                    }
                }
                fprintf(STDERR, "%3d. %s%s() in (%s:%d)\n", $n + 1,
                                $t['class'] ? $t['class'].'::':'',
                                $t['function'],
                                $file,
                                $t['line']);

            }
            fprintf(STDERR, "\n");
        // }
    }

    public static function dispatch(array $argv)
    {
        $candidates = Util::pathAndArgs($argv);

        $class = null;
        foreach (array_reverse($candidates) as $path => $params) {
            $path = ltrim($path, '/');
            $basename = basename($path);
            $dirname = dirname($path);

            $class_namespace = '\Gini\Controller\CLI\\';
            if ($dirname != '.') {
                $class_namespace .= strtr($dirname, ['/'=>'\\']).'\\';
            }

            $class = $class_namespace . $basename;
            if (class_exists($class)) break;

            $class = $class_namespace . 'Controller' . $basename;
            if (class_exists($class)) break;
        }

        if (!$class || !class_exists($class, false)) {
            $class = '\Gini\Controller\CLI\App';
            $params = $argv;
        }

        \Gini\Config::set('runtime.controller_path', $path);
        \Gini\Config::set('runtime.controller_class', $class);

        $controller = \Gini\IoC::construct($class);

        $action = $params[0];
        if ($action && method_exists($controller, 'action'.$action)) {
            $action = 'action'.$action;
            array_shift($params);
        } elseif (!$action && method_exists($controller, '__index')) {
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
