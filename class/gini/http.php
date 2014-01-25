<?php

namespace Gini {

    class HTTP {
    
        private $_header=[];
        private $_post=[];
    
        function header($name , $value) {
            $this->_header[$name]=$value;
            return $this;
        }
    
        function get($url, $query=null, $timeout=5) {
            $qpos = strpos($url, '?');
            $url .= ($qpos === false) ? '?' : '&';
            $url .= is_string($query) ? $query : http_build_query($query);
            return $this->request($url, $timeout);
        }
    
        function post($url, $query, $timeout=5) {
            $this->_post = $query;
            return $this->request($url, $timeout);
        }
    
        function clean(){
            $this->_header = [];
            $this->_post = [];
        }

        function cookie() {
            $cookie = array();
            $file = $this->_cookie_file;
            if (file_exists($file)) {
                $rows = file($file);
                foreach($rows as $row){
                    if('#'==$row[0])
                        continue;
                    $row = trim($row, "\r\n\t ");
                    $arr = explode("\t", $row);
                    if(isset($arr[5]) && isset($arr[6])) {
                        $cookie[$arr[5]] = rawurldecode($arr[6]);
                    }
                }

            }
            return $cookie;
        }

        private $_cookie_file;
        function cookieFile($file = null) {
            $this->_cookie_file = $file;
            return $this;
        }

        private $_proxy;
        private $_proxy_type;
        function proxy($proxy, $socks5 = false) {
            $this->_proxy = $proxy;
            $this->_proxy_type = $socks5 ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP;
            return $this;
        }

        function request($url, $timeout=5){
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_URL => $url,
                CURLOPT_HEADER => true,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
                CURLOPT_REFERER => 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
            ));

            if ($this->_cookie_file) {
                curl_setopt_array($ch, array(
                    CURLOPT_COOKIEFILE => $this->_cookie_file,
                    CURLOPT_COOKIEJAR => $this->_cookie_file,
                ));
            }

            if ($this->_proxy) {
                curl_setopt_array($ch, array(
                    CURLOPT_HTTPPROXYTUNNEL => true,
                    CURLOPT_PROXY => $this->_proxy,
                    CURLOPT_PROXYTYPE => $this->_proxy_type,
                ));
            }

            if($this->_header){
                $curl_header=array();
                foreach($this->_header as $k=>$v){
                    $curl_header[]=$k.': '.$v;
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
            }

            if ($this->_post) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($this->_post) ? http_build_query($this->_post) : $this->_post);
            }
        
            $data = curl_exec($ch);

            $this->clean();

            $errno = curl_errno($ch);
            if ($errno || !$data) {
                $err = curl_error($ch);
                Logger::of('core')->error("CURL ERROR($errno $err): $url ");
                curl_close($ch);
                return null;
            }

            $info = curl_getinfo($ch);

            curl_close($ch);
        
            return new HTTP\Response($data, $info['http_code']);
        }

    
    }

}

namespace Gini\HTTP {
    
    class Response {

        public $header=[];
        public $status=null;
        public $body=null;
    
        function __construct($data, $status) {
            list($header, $body)=explode("\n\n", str_replace("\r", "", $data), 2);
        
            $this->body=trim($body);
 
            $header = explode("\n", $header);
            $status = array_shift($header);
            $this->status = $status;

            foreach ($header as $h) {
                list($k, $v) = explode(': ', $h, 2);
                if ($k) {
                    $this->header[$k] = $v;
                }
            }
        }
    
        function __toString() {
            return $this->body;
        }
    }

}

