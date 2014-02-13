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

    static $built_in_commands = array(
        'help' => 'Help',
        '-' => 'List available commands',
        );

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
        file_put_contents($env_file, json_encode($_SERVER));
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
        if (count($argv) < 1) {
            static::commandHelp($argv);
            exit;
        }

        $cli = strtolower($argv[0]);
        switch ($cli) {
        case '-':
            $method = 'commandAvailable';
            break;
        default:
            $method = 'command'.$cli;
        }

        if ($cli && method_exists(__CLASS__, $method)) {
            call_user_func(array(__CLASS__, $method), $argv);
        } else {
            $GLOBALS['GINI.CURRENT_CLI'] = $cli;
            static::exec($argv);
        }

    }

    public static function commandHelp(array $argv)
    {
        echo "usage: \e[1;34mgini\e[0m <command> [<args>]\n\n";
        echo "The most commonly used git commands are:\n";
        foreach (self::$built_in_commands as $k => $v) {
            printf("   \e[1;34m%-10s\e[0m %s\n", $k, $v);
        }
    }

    public static function commandRoot()
    {
        echo $_SERVER['GINI_APP_PATH']."\n";
    }

    public static function commandAvailable(array $argv)
    {
        // list available cli programs
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, 'controller/cli');
        foreach ($paths as $path) {
            if (!is_dir($path)) continue;

            $dh = opendir($path);
            if ($dh) {
                while ($name = readdir($dh)) {
                    if ($name[0] == '.') continue;
                    if (!is_file($path . '/' . $name)) continue;
                    printf("%s\t", basename($name, '.php'));
                }
                closedir($dh);
            }

        }
        echo "\n";
    }

    public static function exec(array $argv)
    {
        $cmd = $argv[0];
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
        } else {
            self::dispatch($argv);
        }
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
        $orig_argv = $argv;

        $cmd = reset($argv);

        $path = '';

        $candidates = [];
        while (count($argv) > 0) {
            $arg = array_shift($argv);
            if (!preg_match('|^[a-z]\w+$|', $arg)) break;
            if ($path) $path .= '/' . $arg;
            else $path = $arg;
            $candidates[$path] = $argv;
        }

        $class = null;
        foreach (array_reverse($candidates) as $path => $params) {
            $basename = basename($path);
            $dirname = dirname($path);
            $class_namespace = '\Controller\CLI\\';
            if ($dirname != '.') {
                $class_namespace .= str_replace('/', '_', $dirname).'\\';
            }
            $class = $class_namespace . $basename;
            $class = str_replace('-', '_', $class);
            if (class_exists($class)) break;
            $class = $class_namespace . 'Controller_' . $basename;
            $class = str_replace('-', '_', $class);
            if (class_exists($class)) break;
        }

        if (!$class || !class_exists($class, false)) {
            $class = '\Controller\CLI\App';
            $params = $orig_argv;
        }

        \Gini\Config::set('runtime.controller_path', $path);
        \Gini\Config::set('runtime.controller_class', $class);

        $controller = \Gini\IoC::construct($class);

        $action = preg_replace('/[-_]/', '', $params[0]);
        if ($action && $action[0]!='_' && method_exists($controller, 'action'.$action)) {
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
