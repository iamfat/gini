<?php

namespace Model\Auth;

class RPC implements \Model\Auth\Driver {

	private $_rpc;
	private $_opt;

    function __construct(array $opt){
		$this->_rpc = new \Model\RPC($opt['rpc.url']);
		$this->_opt = $opt;
    }
    //验证令牌/密码
    function verify($username, $password) {

		$nusername = $username . '|' . $this->_opt['backend'];

		$nusername = preg_replace('/%[^%]+$/', '', $nusername);

		try {
			$key = $this->_rpc->auth->verify($nusername, $password);
			if ($key) {
				$_SESSION['#RPC_TOKEN_KEY'][$this->_backend][$username] = $key;
				return TRUE;
			}
		}
		catch (\Model\RPC\Exception $e) {
		}

		return FALSE;
    }
    //设置令牌
    function change_username($username, $new_username) {
        //安全问题 禁用
        return FALSE;
    }
    //设置密码
    function change_password($username, $password) {
        //安全问题 禁用
        return FALSE;
    }
    //添加令牌/密码对
    function add($username, $password) {
        //安全问题 禁用
        return FALSE;
    }
    //删除令牌/密码对
    function remove($username) {
        //安全问题 禁用
        return FALSE;

	}

}
