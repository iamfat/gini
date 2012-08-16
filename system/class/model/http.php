<?php

abstract class _HTTP {
	
	private $_header=array();
	private $_post=array();
	
	static function instance() {
		return new HTTP;
	}
	
	function header($name , $value){
		$this->_header[$name]=$value;
		return $this;
	}
	
	function post($query){
		$this->_post=array_merge($this->_post, $query);
		return $this;
	}
	
	function clean(){
		$this->_header=array();
		$this->_post=array();
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
	function cookie_file($file = NULL) {
		$this->_cookie_file = $file;
		return $this;
	}

	private $_proxy;
	private $_proxy_type;
	function proxy($proxy, $socks5 = FALSE) {
		$this->_proxy = $proxy;
		$this->_proxy_type = $socks5 ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP;
		return $this;
	}

	function & request($url, $timeout=5){
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_URL => $url,
			CURLOPT_HEADER => TRUE,
			CURLOPT_AUTOREFERER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FRESH_CONNECT => TRUE,
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
				CURLOPT_HTTPPROXYTUNNEL => TRUE,
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
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->_post));
		}
		
		$data = curl_exec($ch);

		$this->clean();

		$errno = curl_errno($ch);
		if ($errno || !$data) {
			$err = curl_error($ch);
			Log::add("CURL ERROR($errno $err): $url ", 'error');
			curl_close($ch);
			return NULL;
		}

		$info = curl_getinfo($ch);

		curl_close($ch);
		
		$response->header = array();
		$response->body = NULL;
		$response->error = FALSE;
		
		list($header, $body)=explode("\n\n", str_replace("\r", "", $data), 2);
		$response=new HTTP_Response;
		
		$response->body=trim($body);
 
		$header = explode("\n", $header);
		$status = array_shift($header);
		$response->status = $info['http_code'];

		foreach($header as $h){
			list($k, $v)=explode(': ', $h, 2);
			if($k)$response->header[$k]=$v;
		}
		
		return $response;
	}

	
}

class HTTP_Response {

	public $header=array();
	public $status=NULL;
	public $body=NULL;
	
	/**
	 * Pull the href attribute out of an html link element.
	 */
	function link_href($rel) {
	  $rel = preg_quote($rel);
	  preg_match('|<link\s+rel=["\'](.*)'. $rel .'(.*)["\'](.*)/?>|iU', $this->body, $matches);
	  if (isset($matches[3])) {
		preg_match('|href=["\']([^"]+)["\']|iU', $matches[3], $href);
		return trim($href[1]);
	  }
	  return FALSE;
	}
	
	/**
	 * Pull the http-equiv attribute out of an html meta element
	 */
	function meta_httpequiv($equiv) {
	  preg_match('|<meta\s+http-equiv=["\']'. $equiv .'["\'](.*)/?>|iU', $this->body, $matches);
	  if (isset($matches[1])) {
		preg_match('|content=["\']([^"]+)["\']|iU', $matches[1], $content);
		if (isset($content[1])) {
		  return $content[1];
		}
	  }
	  return FALSE;
	}
	
}
