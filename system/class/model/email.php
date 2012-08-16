<?php

namespace Model;

if (!class_exists('Email', false)) {
	class Email extends _Email {};
}

use \Gini\Config;

/*
	e.g.
	
	$email = new Email;
	$email->from('jia.huang@geneegroup.com', 'Jia Huang');
	$email->to('somebody@geneegroup.com', 'Somebody');
	$email->subject('Hello, world!');
	$email->body('lalalalalalala...');
	$email->send();

*/

abstract class _Email {

	private $_recipients;
	private $_sender;
	private $_reply_to;
	private $_subject;
	private $_body;
	private $_header;
	private $_multi;
	
	function __construct($sender=NULL){
		if(is_null($sender)){
			$sender->email = Config::get('system.email_address');
			$sender->name = Config::get('system.email_name', $sender->email);
		}

		$this->from($sender->email, $sender->name);
	}
	
	private function make_header() {
		$header = (array) $this->_header;

		$header['User-Agent']='Gini-Q';				
		$header['Date']=$this->get_date();
		$header['X-Mailer']='Gini-Q';		
		$header['X-Priority']='3 (Normal)';
		$header['Message-ID']=$this->get_message_id();		
		$header['Mime-Version']='1.0';
		if ($this->_multi) {
			$header['Content-Type']='multipart/alternative; boundary='.$this->_multi.'';   
		}
		else {
			$header['Content-Transfer-Encoding']='8bit';
			$header['Content-Type']='text/plain; charset="UTF-8"';   
		}

		$header_content = '';
		foreach($header as $k=>$v){
			$header_content .= "$k: $v\n";
		}

		return $header_content;
	}
	
	function clear(){
		unset($this->_recipients);
		unset($this->_subject);
		unset($this->_body);
		unset($this->_header);
		unset($this->_sender);
		unset($this->_reply_to);
	}

	private function encode_text($text) {
		$arr = str_split($text, 75);
		foreach($arr as &$t) {
			if (mb_detect_encoding($t, 'UTF-8', true)) {
				$t = '=?utf-8?B?'.base64_encode($t).'?=';
			}
		}
		return implode(' ', $arr);
	}

	function send()
	{		
		$success = FALSE;
		if (Config::get('debug.email')) {
			$success = TRUE;
		}
		else {		
			$subject = $this->encode_text($this->_subject);
			$body = $this->_body;
			
			$recipients = $this->_header['To'];
			unset($this->_header['To']);

			$header = $this->make_header();
			$success = mail($recipients, $subject, $body, $header);
		}
		
		$subject = $this->_subject;
		$recipients =  $this->_recipients;
		$sender = $this->_sender;
		$reply_to = $this->_reply_to;

		Log::add("邮件:{$subject} 由{$sender}(RT:{$reply_to}) 发送到{$recipients} S:{$success}", 'mail');
		$this->clear();
		return $success;		
	}

	function from($email, $name=NULL)
	{
		$this->_header['From'] = $name ? $this->encode_text($name) . "<$email>" : $email;
		$this->_header['Return-Path']="<$email>";
		$this->_header['X-Sender']=$email;

		$this->_sender = $email;
	}
  	
	function reply_to($email, $name=NULL)
	{
		$this->_header['Reply-To'] = $name ? $this->encode_text($name) . "<$email>" : $email;

		$this->_reply_to = $email;
	}
 
	function to($email, $name=NULL)
	{
		if (is_array($email)) {
			$mails = array();
			$header_to = array();
			foreach($email as $k=>$v) {
				if (is_numeric($k)) {
					$mails[] = $v;
					$header_to[] = $v;
				}
				else {
					// $k是email, $v是name
					$mails[] = $k;
					$header_to[] = $v ? $this->encode_text($v) . "<$k>" : $k;
				}
			}
			$this->_header['To'] = implode(', ', $header_to);
			$this->_recipients = implode(', ', $mails);
		}
		else {
			$this->_header['To'] = $name ? $this->encode_text($name) . "<$email>" : $email;
			$this->_recipients = $email;
		}
	}
  	  	
	function subject($subject)
	{
		$subject = preg_replace("/(\r\n)|(\r)|(\n)/", "", $subject);
		$subject = preg_replace("/(\t)/", " ", $subject);
		
		$this->_subject = trim($subject);		
	}
  	
	function body($text, $html=NULL){
		if (!$html) {
			$this->_multi=NULL;
			$this->_body=$text;
		}
		else {
			$this->_multi='GINI-'.md5(Date::time());
			$this->_body.="--{$this->_multi}\n";
			$this->_body.="Content-Type: text/plain; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
			$this->_body.= stripslashes(rtrim(str_replace("\r", "", $text)));	
			$this->_body.="\n\n--{$this->_multi}\n";
			$this->_body.="Content-Type: text/html; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
			$this->_body.= stripslashes(rtrim(str_replace("\r", "", $html)));	
			$this->_body.="\n--{$this->_multi}--\n\n";
		}
	}
	
	private function get_message_id() {
		$from = $this->_headers['Return-Path'];
		$from = str_replace(">", "", $from);
		$from = str_replace("<", "", $from);
		return  "<".uniqid('').strstr($from, '@').">";	
	}

	private function get_date() {
		$timezone = date("Z");
		$operator = (substr($timezone, 0, 1) == '-') ? '-' : '+';
		$timezone = abs($timezone);
		$timezone = floor($timezone/3600) * 100 + ($timezone % 3600 ) / 60;
		
		return sprintf("%s %s%04d", date("D, j M Y H:i:s"), $operator, $timezone);		
	}

}
