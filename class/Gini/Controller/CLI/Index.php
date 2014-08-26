<?php

namespace Gini\Controller\CLI;

class Index extends \Gini\Controller\CLI
{

    private static function _loadGiniComposer()
    {
        if (file_exists(SYS_PATH.'/vendor/autoload.php')) {
            // gini was installed independently.
            require_once SYS_PATH.'/vendor/autoload.php';
        } elseif (file_exists(SYS_PATH.'/../../autoload.php')) {
            // gini was installed via composer way.
            require_once SYS_PATH.'/../../autoload.php';
        }
    }

    private static function _configFile()
    {
        return posix_getpwuid(posix_getuid())['dir'] .'/.gini.conf';
    }

    private static function _config()
    {
        $configFile = self::_configFile();
        if (file_exists($configFile)) {
            $config = \Symfony\Component\Yaml\Yaml::parse(@file_get_contents($configFile));
        }

        return (array) $config;
    }

    private static function _serverUri()
    {
        return $_SERVER['GINI_INDEX_URI'] ?: 'http://gini-index.genee.cn/';
    }

    private static function _davOptionsAndHeaders()
    {
        $uri = self::_serverUri();

        $config = self::_config();
        if (isset($config['token'])) {
            // Use Token
            $options = [ 'baseUri' => $uri ];
            $headers = [ 'Authorization' => 'Gini '.$config['token'] ];

        } else {
            // Use Username / Password
            $userName = readline('User: ');
            echo 'Password: ';
            `stty -echo`;
            $password = rtrim(fgets(STDIN), "\n");
            `stty echo`;
            echo "\n";

            $options = [
                'baseUri' => $uri,
                'userName' => $userName,
                'password' => $password,
            ];

            $headers = [];
        }

        return [$options, $headers];
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
        if (count($args) > 0) {
            $username = trim($args[0]);
        } else {
            $username = readline('User: ');
        }

        echo 'Password: ';
        `stty -echo`;
        $password = rtrim(fgets(STDIN), "\n");
        `stty echo`;
        echo "\n";

        $config = [ 'username' => $username ];

        try {
            $uri = self::_serverUri();
            $rpc = new \Gini\RPC(rtrim($uri, '/').'/api');
            $config['token'] = $rpc->createToken($username, $password);

            file_put_contents(self::_configFile(), \Symfony\Component\Yaml\Yaml::dump($config));

            echo "You've successfully logged in as $username.\n";
        } catch (\Gini\RPC\Exception $e) {
            echo "Server Error: ".$e->getMessage()."\n";
        }

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
            echo "Hey! You are \e[33m".$config['username']."\e[0m!\n";
        } else {
            echo "Oops. You are \e[33mNOBODY\e[0m!\n";
        }
    }

    public function actionPublish($argv)
    {
        count($argv) > 0 or die("Usage: gini index publish <version>\n\n");

        $appId = APP_ID;
        $version = $argv[0];
        $GIT_DIR = escapeshellarg(APP_PATH.'/.git');
        $command = "git --git-dir=$GIT_DIR archive $version --format tgz 2> /dev/null";

        $path = "$appId/$version.tgz";
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

            list($options, $headers) = self::_davOptionsAndHeaders();

            $client = new \Sabre\DAV\Client($options);
            $response = $client->request('MKCOL', $appId, null, $headers);
            if ($response['statusCode'] == 401 && isset($response['headers']['www-authenticate'])) {
                // Authentication required
                // 'Authorization: Basic '. base64_encode("user:password")
                die ("Failed to publish $appId/$version.\n");
            }

            $response = $client->request('PUT', $path, $content, $headers);
            if ($response['statusCode'] >= 200 && $response['statusCode'] <= 206) {
                echo "$appId/$version was published successfully.\n";
            } else {
                die ("Failed to publish $appId/$version.\n");
            }

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

        list($options, $headers) = self::_davOptionsAndHeaders();

        $client = new \Sabre\DAV\Client($options);
        $response = $client->request('HEAD', $path, null, $headers);
        if ($response['statusCode'] == 200) {
            echo "Unpublishing $appId/$version...\n";
            $response = $client->request('DELETE', $path, null, $headers);
            if ($response['statusCode'] >= 200 && $response <= 206) {
                echo "done.\n";
            } else {
                echo "failed.\n";
            }
        } else {
            echo "Failed to find $path\n";
        }

    }

    public function actionInstall($argv)
    {
        (count($argv) > 0 || APP_ID != 'gini') or die("Usage: gini index install <module> <version>\n\n");

        if (!class_exists('\Sabre\DAV\Client')) {
            self::_loadGiniComposer();
        }

        list($options, $headers) = self::_davOptionsAndHeaders();

        $client = new \Sabre\DAV\Client($options);

        $installedModules = [];
        $installModule = function ($module, $versionRange, $targetDir, $isApp=false) use (&$installModule, &$installedModules, $client, $headers) {

            if (isset($installedModules[$module])) {
                $info = $installedModules[$module];
                $v = new \Gini\Version($info->version);
                // if installed version is incorrect, abort the operation.
                if (!$v->satisfies($versionRange)) {
                    die("Conflict detected on $module! Installed: {$v->fullVersion} Expecting: $versionRange\n");
                }
            } else {

                // fetch index.json
                echo "Fetching INDEX file for {$module}...\n";
                $response = $client->request('GET', $module.'/index.json', null, $headers);
                if ($response['statusCode'] !== 200) {
                    die("Failed to fetch INDEX file.\n");
                }

                $indexInfo = json_decode($response['body'], true);
                // find latest match version
                foreach ($indexInfo as $version => $info) {
                    $v = new \Gini\Version($version);
                    if ($v->satisfies($versionRange)) {
                        if ($matched) {
                            if ($matched->compare($v) > 0) continue;
                        }
                        $matched = $v;
                    }
                }

                if (!$matched) {
                    die("Failed to locate required version!\n");
                }

                $version = $matched->fullVersion;
                $info = (object) $indexInfo[$version];

                $tarPath = "{$module}/{$version}.tgz";
                echo "Downloading {$module} from {$tarPath}...\n";
                $response = $client->request('GET', $tarPath, null, $headers);
                if ($response['statusCode'] !== 200) {
                    die("Failed to fetch INDEX file.\n");
                }

                if ($isApp) {
                    $modulePath = $targetDir;
                } else {
                    $modulePath = "$targetDir/modules/$module";
                }

                \Gini\File::ensureDir($modulePath);
                echo "Extracting {$module} to {$modulePath}...\n";
                $ph = popen('tar -zx -C '.escapeshellcmd($modulePath), 'w');
                if (is_resource($ph)) {
                    fwrite($ph, $response['body']);
                    pclose($ph);
                }

                $installedModules[$module] = $info;
            }

            if ($info) {
                foreach ((array) $info->dependencies as $m => $r) {
                    if ($m == 'gini') continue;
                    $installModule($m, $r, $targetDir, false);
                }
            }

        };

        if (count($argv) > 0) {
            // e.g. gini install xxx
            $module = $argv[0];

            if (count($argv) > 1) {
                $versionRange = $argv[1];
            } else {
                $versionRange = readline('Please provide a version constraint for the '.$module.' requirement:');
            }

            $installModule($module, $versionRange, getcwd()."/$module", true);

        } else {
            // run: gini install, then you should be in module directory
            if (APP_ID != 'gini') {
                // try to install its dependencies
                $app = \Gini\Core::moduleInfo(APP_ID);
                $installedModules[APP_ID] = $app;
                $installModule(APP_ID, $app->version, APP_PATH, true);
           }
        }
    }

    protected function _strPad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        $diff = mb_strwidth( $input ) - mb_strlen( $input );

        return str_pad( $input, $pad_length + $diff, $pad_string, $pad_type );
    }

    public function actionSearch($argv)
    {
        count($argv) > 0 or die("Usage: gini index search <keywords>\n\n");

        try {
            $uri = self::_serverUri();
            $rpc = new \Gini\RPC(rtrim($uri, '/').'/api');
            $modules = $rpc->search($argv[0]);

            foreach ((array) $modules as $m) {
                printf("%s %s %s\e[0m\n",
                    $this->_strPad($m['id'], 20, ' '),
                    $this->_strPad($m['version'], 15, ' '),
                    $this->_strPad($m['name'], 30, ' ')
                );
            }

        } catch (\Gini\RPC\Exception $e) {
            echo "Server Error: ".$e->getMessage()."\n";
        }
    }

}
