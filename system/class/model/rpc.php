<?php

namespace Model\RPC {
    class Exception extends \Exception {}
}

namespace Model {

    register_shutdown_function('\\Model\\RPC::remove_cookie_file');

    class RPC {

        private $_url;
        private $_path;

        public $sess_id;

        static function cookie_file($id=0) {
            static $files;
            if ($files[$id] === null) {
                $files[$id] = $file = tempnam(sys_get_temp_dir(), 'rpc.cookie.');
            }
            return $files[$id];
        }

        static function remove_cookie_file() {
            @unlink(self::cookie_file());
        }
        
        function __construct($url, $path=null) {
            $this->_url = $url;
            $this->_path = $path;
        }

        function __get($name) {
            return new RPC($this->_url, $this->_path ? $this->_path . '/' . $name : $name);
        }

        function __call($method, $params) {
            if ($method === __FUNCTION__) return null;

            if ($this->_path) $method = $this->_path . '/' . $method;

            $id = uniqid();

            $raw_data = $this->post(array(
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => $id,
            ));

            $data = @json_decode($raw_data, true);
            if (!isset($data['result'])) {
                if (isset($data['error'])) {
                    $message = sprintf('remote error: %s', $data['error']['message']);
                    $code = $data['error']['code'];
                    throw new \Model\RPC\Exception($message, $code);
                }
                elseif ($id != $data['id']) {
                    $message = 'wrong response id!';
                    throw new \Model\RPC\Exception($message);
                }
                elseif (is_null($data)) {
                    $message = sprintf('unknown error with raw data: %s', $raw_data ?: '(null)');
                    throw new \Model\RPC\Exception($message);
                }
            }

            return $data['result'];
        }

        function post($post_data, $timeout = 5) {

            $cookie_file = self::cookie_file($this->sess_id);

            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_COOKIEJAR => $cookie_file,
                CURLOPT_COOKIEFILE => $cookie_file,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_URL => $this->_url,
                CURLOPT_AUTOREFERER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_RETURNTRANSFER => true,
                // CURLOPT_FRESH_CONNECT => false,
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type' => 'application/json',
                ),
            ));

            $post_data = @json_encode($post_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

            TRACE(">>> %s: %s", $this->_url, $post_data);
            $data = curl_exec($ch);
            TRACE("<<< %s: %s", $this->_url, trim($data));
            
            $errno = curl_errno($ch);
            if ($errno) {
                TRACE("curl error %s", curl_error($ch));
                $data = null;
            }

            curl_close($ch);
            return $data;
        }

    }

}



