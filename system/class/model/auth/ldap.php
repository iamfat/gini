<?php

namespace Model\Auth;

class LDAP implements \Model\Auth_Handler {

	private $ldap;
	private $options;

	function __construct(array $opt) {
		$this->options = $opt;
		$this->ldap = new \Model\LDAP($opt['ldap.options']);
	}
	
	function verify($token, $password){
		$opt = & $this->options;
		$filter = sprintf('(%s=%s)', $opt['ldap.token_attr'], $token);
		$sr = $this->ldap->search($opt['ldap.token_base'], $filter);
		$entries = $this->ldap->entries($sr);
		if (!$entries['count']) return FALSE;
		$token_dn = $entries[0]['dn'];
		return $this->ldap->bind($token_dn, $password);
	}
	
	function change_token($token, $token_new){
		$opt = & $this->options;
		$filter = sprintf('(%s=%s)', $opt['ldap.token_attr'], $token);
		$sr = $this->ldap->search($opt['ldap.token_base'], $filter);
		$entries = $this->ldap->entries($sr);
		if (!$entries['count']) return FALSE;
		$old_dn = $entries[0]['dn'];
		list($first,$rest) = explode(',', $old_dn, 2);
		list($k, $v) = explode('=', $first, 2);
		return $this->ldap->rename($old_dn, $k.'='.$token_new) && $this->ldap->mod_replace(
			$k.'='.$token_new.','.$rest, 
			array(
				$opt['ldap.token_attr'] => $token_new
			)
		);
	}
	
	function change_password($token, $password){
		$opt = & $this->options;
		$filter = sprintf('(%s=%s)', $opt['ldap.token_attr'], $token);
		$sr = $this->ldap->search($opt['ldap.token_base'], $filter);
		$entries = $this->ldap->entries($sr);
		if (!$entries['count']) return FALSE;
		$token_dn = $entries[0]['dn'];
		return $this->ldap->set_password($token_dn, $password);
	}
	
	function add($token, $password){
		$opt = & $this->options;
		return $this->ldap->add_account($opt['ldap.token_base'], $token, $password);
	}
	
	function remove($token){
		$opt = & $this->options;
		$filter = sprintf('(%s=%s)', $opt['ldap.token_attr'], $token);
		$sr = $this->ldap->search($opt['ldap.token_base'], $filter);
		$entries = $this->ldap->entries($sr);
		if (!$entries['count']) return TRUE;
		$token_dn = $entries[0]['dn'];
		return $this->ldap->delete($token_dn);
	}
	
}
