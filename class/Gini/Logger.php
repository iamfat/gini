<?php
/**
 * Logger
 *
 * @author Jia Huang
 * @version $Id$
 * @copyright Genee, 2014-01-27
 **/

/**
 * Define DocBlock
 **/

namespace Gini;

/**
 * Logger Class
 */
class Logger extends \Psr\Log\AbstractLogger
{
    protected static $_LOGGERS = [];

    /**
     * Get logger by name
     *
     * @param  string $name Name of the logger
     * @return Logger
     * @author Jia Huang
     */
    public static function of($name)
    {
        if (!isset(self::$_LOGGERS[$name])) {
           self::$_LOGGERS[$name] = new Logger($name);
        }

        return self::$_LOGGERS[$name];
    }

    protected $_name;
    protected $_handlers = [];

    /**
     * Instantiate Logger object by name
     *
     * @param string $name Logger name
     */
    public function __construct($name)
    {
        $this->_name = $name;

        foreach ((array) \Gini\Config::get("logger.{$this->_name}") as $handlerName => $options) {
            $options = (array) $options;
            $level = isset($options['level']) ? $options['level'] : \Psr\Log\LogLevel::DEBUG;
            $handlerClass = "\Gini\Logger\\$handlerName";
            $handler = \Gini\IoC::construct($handlerClass, $this->_name, $level, $options);
            $this->_handlers[] = $handler;
        }

    }

    /**
     * Check if we are debugging something
     *
     * @return bool
     **/
    public static function isDebugging()
    {
        return file_exists(APP_PATH . '/.debug');
    }

    /**
     * Check if the function name matched our debugging patterns in .debug file
     *
     * @param  string $func Function name to trace
     * @return bool
     **/
    public static function isDebuggingFunction($func)
    {
        static $tracablePattern;
        if (!isset($tracablePattern)) {
            $tracablePattern = file(APP_PATH . '/.debug', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        if (count($tracablePattern) == 0) return true;
        foreach ($tracablePattern as $pattern) {
            if (preg_match('`'.$pattern.'`', $func)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log function
     *
     * @param  string $level   Log level
     * @param  string $message Log message
     * @param  array  $context Log context data
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        // log to configured handlers
        foreach ($this->_handlers as $handler) {
            $handler->log($level, $message, $context);
        }

        // interal debugging support
        if ($level == \Psr\Log\LogLevel::DEBUG && static::isDebugging()) {

            $trace = array_slice(debug_backtrace(), 2, 1)[0];
            $func = $trace['function'];
            if (isset($trace['class'])) {
                $func = $trace['class'].$trace['type'].$func;
            }

            $levelLabel = strtoupper($level);
            if (static::isDebuggingFunction($func)) {
                $message = "{time} [{pid}] {$this->_name}.{$levelLabel}: {func}: $message";

                $context['time'] = date('Y-m-d H:i:s');
                $context['pid'] = posix_getpid();
                $context['func'] = $func;

                $replacements = [];
                foreach ($context as $key => $val) {
                    $replacements['{'.$key.'}'] = $val;
                }

                $message = strtr($message, $replacements);
                fputs(STDERR, "\e[1;30m$message\e[0m\n");
            }

        }

    }

}
