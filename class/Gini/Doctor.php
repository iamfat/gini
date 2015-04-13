<?php

namespace Gini;

class Doctor
{
    private static function _outputErrors(array $errors)
    {
        foreach ($errors as $err) {
            echo "   \e[31m*\e[0m $err\n";
        }
    }

    // exit if there is error
    public static function diagnose($items = null)
    {
        $errors = [];

        if (!$items || in_array('dependencies', $items)) {
            echo "Checking module dependencies...\n";
            // check gini dependencies
            foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                if (!$info->error) {
                    continue;
                }
                $errors['dependencies'][] = "$name: $info->error";
            }
            if ($errors['dependencies']) {
                static::_outputErrors($errors['dependencies']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        // check composer requires
        if (!$items || in_array('composer', $items)) {
            echo "Checking composer dependencies...\n";
            foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                if ($info->composer) {
                    if (!file_exists(APP_PATH.'/vendor')) {
                        $errors['composer'][] = $name.': composer packages missing!';
                    }
                    break;
                }
            }
            if ($errors['composer']) {
                static::_outputErrors($errors['composer']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        if (!$items || in_array('file', $items)) {
            echo "Checking file/directory modes...\n";
            // check if /tmp/gini-session is writable
            $path_gini_session = sys_get_temp_dir().'/gini-session';
            if (is_dir($path_gini_session) && !is_writable($path_gini_session)) {
                $errors['file'][] = "$path_gini_session is not writable!";
            }
            if ($errors['file']) {
                static::_outputErrors($errors['file']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        if (!$items || in_array('web', $items)) {
            echo "Checking web dependencies...\n";
            if (!file_exists(APP_PATH.'/web')) {
                $errors['web'][] = "Please run \e[1m\"gini web update\"\e[0m to generate web directory!";
            }
            if ($errors['web']) {
                static::_outputErrors($errors['web']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        //
        if (!$items || in_array('database', $items)) {
            $conf = \Gini\Config::get('database');
            if (!empty($conf)) {
                echo "Checking Database...\n";
                foreach ((array)$conf as $key => $opts) {
                    $db = \Gini\Database::db($key)
                    $database_errors = $db->diagnose();
                    if ($database_errors) {
                        static::_outputErrors($module_errors);
                    } else {
                        echo "   \e[32mdone.\e[0m\n";
                    }
                    echo "\n";
                }
            }
        }

        if (!$items || in_array('i18n', $items)) {
            echo "Checking Locale...\n";
            $locale = \Gini\Config::get('system.locale') ?: 'en_US';
            $lodir = I18N_PATH.'/'.$locale.'/LC_MESSAGES';
            $mofile = $lodir . '/' . APP_ID . '.mo';
            $pofile = $lodir . '/' . APP_ID . '.po';

            if (!file_exists($mofile) || !file_exists($pofile)) {
                static::_outputErrors(['Please run: gini i18n format ' . $locale]);
            }
            else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        // enumerate all doctor extensions
        if ((
                !$items || (
                    in_array('dependencies', $items) && in_array('module_spec', $items)
                )
            ) && !isset($errors['dependencies'])
        ) {
            // check gini dependencies
            foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                $class = '\Gini\Module\\'.strtr($name, ['-' => '', '_' => '']);
                $diag_func = "$class::diagnose";
                if (is_callable($diag_func)) {
                    echo "Checking Module[$name]...\n";
                    $module_errors = call_user_func($diag_func);
                    if ($module_errors) {
                        static::_outputErrors($module_errors);
                        $errors['dependencies'][] = "Module[$name] found some error";
                    } else {
                        echo "   \e[32mdone.\e[0m\n";
                    }
                    echo "\n";
                }
            }
        }

        return $errors;
    }
}
