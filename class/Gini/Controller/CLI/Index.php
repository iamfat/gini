<?php

namespace Gini\Controller\CLI;

use Gini\CGI\Response;
use Gini\RPC;

class AuthorizationException extends \Exception
{
}

class Index extends \Gini\Controller\CLI
{
    private static function _loadGiniComposer()
    {
        if (file_exists(SYS_PATH . '/vendor/autoload.php')) {
            // gini was installed independently.
            require_once SYS_PATH . '/vendor/autoload.php';
        } elseif (file_exists(SYS_PATH . '/../../autoload.php')) {
            // gini was installed via composer way.
            require_once SYS_PATH . '/../../autoload.php';
        }
    }

    private static function _configFile()
    {
        return posix_getpwuid(posix_getuid())['dir'] . '/.gini.conf';
    }

    private static function _config()
    {
        $configFile = self::_configFile();
        if (file_exists($configFile)) {
            $config = yaml_parse(@file_get_contents($configFile));
        }

        return $config ?? [];
    }

    private static function _serverUri()
    {
        return $_SERVER['GINI_INDEX_URI'] ?? 'http://gini-index.genee.cn/';
    }

    private static function _davOptionsAndHeaders($forceLogin = false)
    {
        $uri = self::_serverUri();
        $options = ['baseUri' => rtrim($uri, '/') . '/'];
        $headers = [];

        $config = self::_config();
        if (isset($_SERVER['GINI_INDEX_TOKEN'])) {
            $token = $_SERVER['GINI_INDEX_TOKEN'];
        } elseif (isset($config['token'])) {
            $token = $config['token'];
        } elseif ($forceLogin) {
            echo "\n";
            // Use Username / Password
            $username = readline('User: ');
            echo 'Password: ';
            `stty -echo`;
            $password = rtrim(fgets(STDIN), "\n");
            `stty echo`;
            echo "\n\n";

            try {
                $rpc = new RPC(rtrim($uri, '/') . '/api');
                $token = $rpc
                    ->connectTimeout($_SERVER['GINI_INDEX_CONNECT_TIMEOUT'])
                    ->createToken($username, $password);
                if ($token) {
                    $config['username'] = $username;
                    $config['token'] = $token;
                    yaml_emit_file(self::_configFile(), $config, YAML_UTF8_ENCODING);
                    echo "You've successfully logged in as $username.\n";
                }
            } catch (RPC\Exception $e) {
                echo 'Server Error: ' . $e->getMessage() . "\n";
            }
        }

        if ($token) {
            $headers['Authorization'] = 'Gini ' . $token;
        }

        return [$options, $headers];
    }

    private function _responseMustSucceed($response)
    {
        if ($response['statusCode'] == 401 && isset($response['headers']['www-authenticate'])) {
            throw new AuthorizationException();
        } elseif ($response['statusCode'] < 200 || $response['statusCode'] > 206) {
            throw new Response\Exception(null, $response['statusCode']);
        }
    }

    private function _runWithAuthorization($func)
    {
        $forceLogin = false;
        for (;;) {
            try {
                list($options, $headers) = self::_davOptionsAndHeaders($forceLogin);
                $client = new \Sabre\DAV\Client($options);
                $func($client, $headers);
                break;
            } catch (AuthorizationException $e) {
                if ($forceLogin) {
                    die("\e[31m401: Access Denied.\e[0m\n");
                } else {
                    $forceLogin = true;
                }
            } catch (Response\Exception $e) {
                die("\e[31m" . $e->getCode() . ': ' . $e->getMessage() . "\e[0m\n");
            }
        }
    }

    public function __index($args)
    {
        echo "gini index who\n";
        echo "gini index login <user>\n";
        echo "gini index logout\n";
        echo "gini index publish <version>\n";
        echo "gini index unpublish <version>\n";
        echo "gini index install <module> <version>\n";
        echo "gini index search <keyword>\n";
    }

    public function actionLogin($args)
    {
        $configFile = self::_configFile();
        if (file_exists($configFile)) {
            unlink($configFile);
        }

        for (;;) {
            list(, $headers) = self::_davOptionsAndHeaders(true);
            if (isset($headers['Authorization'])) {
                break;
            } else {
                echo "Access denied!\n";
            }
        };
    }

    public function actionLogout($args)
    {
        $configFile = self::_configFile();
        if (file_exists($configFile)) {
            unlink($configFile);
        }

        echo "You are logged out now.\n";
    }

    public function actionWho($args)
    {
        $config = self::_config();
        if (isset($config['username'])) {
            echo "Hey! You are \e[33m" . $config['username'] . "\e[0m!\n";
        } else {
            echo "Oops. You are \e[33mNOBODY\e[0m!\n";
        }
    }

    public function actionPublish($argv)
    {
        $appId = APP_ID;
        $version = $argv[0] ?? \Gini\Core::moduleInfo($appId)->version;

        $EXCLUDES = '--exclude "./vendor" --exclude "./modules" --exclude "./composer.*" --exclude "./.*"';
        $giniIgnore = APP_PATH . '/.gini-ignore';
        if (file_exists($giniIgnore)) {
            $EXCLUDES .= ' -X ' . escapeshellarg($giniIgnore);
        }

        $REPO_DIR = escapeshellarg(APP_PATH);
        $command = "tar c -C $REPO_DIR $EXCLUDES -z * 2> /dev/null";

        $ph = popen($command, 'r');
        if (is_resource($ph)) {
            $content = '';
            while (!feof($ph)) {
                $content .= fread($ph, 4096);
            }

            if (strlen($content) == 0) {
                die("\e[31mError: $appId/$version missing!\e[0m\n");
            }

            // sometimes people will run publish before run composer
            if (!class_exists('\Sabre\DAV\Client')) {
                self::_loadGiniComposer();
            }

            echo "Publishing $appId/$version...\n";

            $this->_runWithAuthorization(function ($client, $headers) use ($appId, $version, $content) {
                $response = $client->request('MKCOL', $appId, null, $headers);
                if ($response['statusCode'] != 405) {
                    $this->_responseMustSucceed($response);
                }

                $path = "$appId/$version.tgz";
                $response = $client->request('PUT', $path, $content, $headers);
                $this->_responseMustSucceed($response);
            });

            echo "$appId/$version was published successfully.\n";
            pclose($ph);
        }
    }

    public function actionUnpublish($argv)
    {
        count($argv) > 0 or die("Usage: gini index unpublish <version>\n\n");

        $version = $argv[0];
        $appId = APP_ID;
        $path = "$appId/$version.tgz";

        if (!class_exists('\Sabre\DAV\Client')) {
            self::_loadGiniComposer();
        }

        echo "Unpublishing $appId/$version...\n";

        $this->_runWithAuthorization(function ($client, $headers) use ($path) {
            $response = $client->request('HEAD', $path, null, $headers);
            $this->_responseMustSucceed($response);
            $response = $client->request('DELETE', $path, null, $headers);
            $this->_responseMustSucceed($response);
        });

        echo "Done.\n";
    }

    public function actionInstall($argv)
    {
        (count($argv) > 0 || APP_ID != 'gini') or die("Usage: gini index install <module> <version>\n\n");
        if (!class_exists('\Sabre\DAV\Client')) {
            self::_loadGiniComposer();
        }

        $this->_runWithAuthorization(function ($client, $headers) use ($argv) {
            $loadedModules = [];

            $loadModule = function ($name, $versionRange) use (&$loadedModules, &$client, &$headers) {
                if (isset($loadedModules[$name])) {
                    $module = $loadedModules[$name];
                } else {
                    // try to see if we've already got it somewhere
                    if (isset(\Gini\Core::$MODULE_INFO[$name])) {
                        $module = \Gini\Core::$MODULE_INFO[$name];
                        $v = new \Gini\Version($module->version);
                        if ($v->satisfies($versionRange)) {
                            $matched = $module;
                            $matchedVersion = $v;
                        }
                    }

                    // fetch index.json
                    echo "Fetching catalog of {$name}...\n";
                    $response = $client->request('GET', $name . '/index.json', null, $headers);
                    if ($response['statusCode'] != 404) {
                        $this->_responseMustSucceed($response);
                        $moduleIndex = (array) json_decode($response['body'], true);
                        // find latest match version
                        foreach ($moduleIndex as $version => $m) {
                            if (!$m) continue;
                            $v = new \Gini\Version($version);
                            if ($v->satisfies($versionRange)) {
                                if ($matchedVersion && $matchedVersion->compare($v) > 0) {
                                    continue;
                                }
                                $matchedVersion = $v;
                                $matched = (object) $m;
                            }
                        }
                    }

                    if (!$matched) {
                        die("  Failed to locate required version!\n");
                    }

                    if (!$module || $matched->version != $module->version) {
                        $module = $matched;
                    }
                }

                $loadedModules[$name] = $module;
                return $module;
            };

            $installModule = function ($module, $targetDir) use (&$installModule, &$loadModule, &$client, &$headers) {
                $name = $module->id;
                $version = $module->version;

                if ($name !== APP_ID &&  (!isset(\Gini\Core::$MODULE_INFO[$name]) || \Gini\Core::$MODULE_INFO[$name]->version !== $version)) {

                    echo "Installing $name/$version...\n";
                    $cacheDir = $_SERVER['HOME'] . '/.gini-modules';
                    $tarPath = "{$name}/{$version}.tgz";
                    $cacheTarPath = "$cacheDir/$tarPath";
                    if (file_exists($cacheTarPath)) {
                        echo "  Reading {$tarPath} in local cache...\n";
                        $moduleContent = file_get_contents($cacheTarPath);
                    } else {
                        echo "  Downloading {$name} from {$tarPath}...\n";
                        $response = $client->request('GET', $tarPath, null, $headers);
                        $this->_responseMustSucceed($response);
                        $moduleContent = $response['body'];
                        \Gini\File::ensureDir(dirname($cacheTarPath));
                        file_put_contents($cacheTarPath, $moduleContent);
                    }

                    if ($name === APP_ID) {
                        $modulePath = $targetDir;
                    } else {
                        $modulePath = "$targetDir/modules/$name";
                    }

                    if (is_dir($modulePath) && file_exists($modulePath)) {
                        \Gini\File::removeDir($modulePath);
                    }
                    \Gini\File::ensureDir($modulePath);
                    echo "  Extracting {$name}...\n";
                    $ph = popen('tar -zx -C ' . escapeshellcmd($modulePath), 'w');
                    if (is_resource($ph)) {
                        fwrite($ph, $moduleContent);
                        pclose($ph);
                    }
                }

                $deps = [];
                foreach ((array) $module->dependencies as $name => $r) {
                    if ($name == 'gini') {
                        continue;
                    }
                    $deps[] = $loadModule($name, $r);
                }
                foreach ($deps as $dep) {
                    $installModule($dep, $targetDir);
                }
            };

            if (count($argv) > 0) {
                // e.g. gini install xxx
                $module = $argv[0];
                if (count($argv) > 1) {
                    $versionRange = $argv[1];
                } else {
                    $versionRange = readline('Please provide a version constraint for the ' . $module . ' requirement:');
                }
                $targetDir = $_SERVER['PWD'] . "/$module";
                $app = $loadModule($module, $versionRange);
            } else {
                // run: gini install, then you should be in module directory
                if (APP_ID == 'gini') {
                    return;
                }
                $app = \Gini\Core::moduleInfo(APP_ID);
                $targetDir = APP_PATH;
            }
            $installModule($app, $targetDir);
        });
    }

    protected function _strPad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        $diff = mb_strwidth($input) - mb_strlen($input);

        return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
    }

    public function actionSearch($argv)
    {
        count($argv) > 0 or die("Usage: gini index search <keywords>\n\n");

        try {
            $uri = self::_serverUri();
            $rpc = new RPC(rtrim($uri, '/') . '/api');
            $rpc->connectTimeout($_SERVER['GINI_INDEX_CONNECT_TIMEOUT']);
            $modules = $rpc->search($argv[0]);
            foreach ((array) $modules as $m) {
                if (!isset($m['id'])) {
                    continue;
                }
                printf(
                    "%s %s %s\e[0m\n",
                    $this->_strPad($m['id'], 30, ' '),
                    $this->_strPad($m['version'], 15, ' '),
                    $this->_strPad($m['name'], 30, ' ')
                );
            }
        } catch (RPC\Exception $e) {
            echo 'Server Error: ' . $e->getMessage() . "\n";
        }
    }
}
