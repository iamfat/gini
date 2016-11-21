<?php

namespace Gini;

class HTTP
{
    private $_header = [];
    private $_post = [];

    public function header($name, $value)
    {
        $this->_header[strtolower($name)] = $value;

        return $this;
    }

    public function get($url, $query = null, $timeout = 5)
    {
        return $this->request('GET', $url, $query, $timeout);
    }

    public function post($url, $query, $timeout = 5)
    {
        return $this->request('POST', $url, $query, $timeout);
    }

    public function delete($url, $query, $timeout = 5)
    {
        return $this->request('DELETE', $url, $query, $timeout);
    }

    public function put($url, $query, $timeout = 5)
    {
        return $this->request('PUT', $url, $query, $timeout);
    }

    public function clean()
    {
        $this->_header = [];
    }

    public function cookie()
    {
        if (!$this->_cookie) {
            return [];
        }
        $cookie = [];
        $file = $this->_cookie->file;
        if (file_exists($file)) {
            $rows = file($file);
            foreach ($rows as $row) {
                if ('#' == $row[0]) {
                    continue;
                }
                $row = trim($row, "\r\n\t ");
                $arr = explode("\t", $row);
                if (isset($arr[5]) && isset($arr[6])) {
                    $cookie[$arr[5]] = rawurldecode($arr[6]);
                }
            }
        }

        return $cookie;
    }

    private $_cookie;
    public function enableCookie()
    {
        $this->_cookie = IoC::construct('\Gini\HTTP\Cookie');

        return $this;
    }

    public function disableCookie()
    {
        $this->_cookie = null;

        return $this;
    }

    private $_proxy;
    private $_proxy_type;
    public function proxy($proxy, $socks5 = false)
    {
        $this->_proxy = $proxy;
        $this->_proxy_type = $socks5 ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP;

        return $this;
    }

    public function request($method, $url, $query, $timeout = 5)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_DNS_USE_GLOBAL_CACHE => false,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYPEER => false,
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

        if (!isset($this->_header['Expect'])) {
            $this->_header['Expect'] = '';
        }
        $curl_header = [];
        foreach ($this->_header as $k => $v) {
            $curl_header[] = $k.': '.$v;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);

        if (!is_scalar($query)) {
            if (!array_filter($query, function ($v) { return $v instanceof \CURLFile; })) {
                if ($this->_header['content-type'] == 'application/json') {
                    $query = json_encode((object) $query, JSON_UNESCAPED_UNICODE);
                } else {
                    $query = http_build_query($query);
                }
            }
        }

        if ($this->_cookie) {
            curl_setopt_array($ch, [
                CURLOPT_COOKIEFILE => $this->_cookie->file,
                CURLOPT_COOKIEJAR => $this->_cookie->file,
            ]);
        }

        if ($this->_proxy) {
            curl_setopt_array($ch, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY => $this->_proxy,
                CURLOPT_PROXYTYPE => $this->_proxy_type,
            ]);
        }

        if ($method == 'GET') {
            if ($query) {
                $qpos = strpos($url, '?');
                $url .= ($qpos === false) ? '?' : '&';
                $url .= strval($query);
            }
        } elseif ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }

        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);

        $errno = curl_errno($ch);
        if ($errno || !$data) {
            $err = curl_error($ch);
            Logger::of('core')->error("CURL ERROR($errno $err): $url ");
            curl_close($ch);

            return;
        }

        $info = curl_getinfo($ch);

        curl_close($ch);

        return new HTTP\Response($data, $info['http_code']);
    }
}
