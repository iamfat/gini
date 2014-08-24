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
class Logger
{
    protected static $_LOGGERS = [];

    /**
     * Get logger by name
     *
     * @param  string $name Name of the logger
     * @return Logger
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

        $config = \Gini\Config::get("logger.{$this->_name}") ?: \Gini\Config::get("logger.default");
        foreach ($config as $handlerName => $options) {
            $options = (array) $options;
            $level = isset($options['level']) ? $options['level'] : Logger\Level::DEBUG;
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
        if ($level == Logger\Level::DEBUG && static::isDebugging()) {

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

    /**
     * System is unusable.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        $this->log(Logger\Level::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function alert($message, array $context = array())
    {
        $this->log(Logger\Level::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function critical($message, array $context = array())
    {
        $this->log(Logger\Level::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function error($message, array $context = array())
    {
        $this->log(Logger\Level::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function warning($message, array $context = array())
    {
        $this->log(Logger\Level::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function notice($message, array $context = array())
    {
        $this->log(Logger\Level::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function info($message, array $context = array())
    {
        $this->log(Logger\Level::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function debug($message, array $context = array())
    {
        $this->log(Logger\Level::DEBUG, $message, $context);
    }

}
