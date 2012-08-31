<?php

namespace GR\System\Model {

	interface Auth_Handler {
		function __construct(array $opt);
		//验证令牌/密码
		function verify($token, $password);
		//设置令牌
		function change_token($token, $new_token);
		//设置密码
		function change_password($token, $password);
		//添加令牌/密码对
		function add($token, $password);
		//删除令牌/密码对
		function remove($token);
	}

	use \Model\Event;

	TRY_DECLARE('\Model\Auth', __FILE__);

	abstract class Auth {

		//返回当前令牌
		static function token() {
			static $curr_token;

			//system.auth_token可强制重载进程令牌
			if(_CONF('auth.token')){
				return _CONF('auth.token');
			}
			
			if ($curr_token === NULL) { 
				Event::trigger('auth.token', $_SESSION['auth.token']);
				$curr_token = $_SESSION['auth.token'];
			}

			return $curr_token;
		}

		//设置当前用户的令牌
		static function login($token) {
			// session_unset();
			Session::cleanup();
			session_regenerate_id();
			$_SESSION['auth.token'] = $token;
			Event::trigger('auth.login', $token);
			return $token;
		}

		//取消当前用户/指定用户的令牌
		static function logout() {
			$curr_token = self::token();
			Event::trigger('auth.logout', $token);
			session_unset();
			Event::trigger('auth.post_logout', $token);
		}
		
		//显示当前用户是否已登录
		static function logged_in(){
			return self::token() != NULL;
		}
		
		private $token;
		private $handler;
		private $options;

		function __construct($token) {
			if ($token === NULL) return;

			list($token, $backend) = self::parse_token($token);

			$backend = $backend ?: _CONF('auth.default_backend');

			$opts = (array) _CONF('auth.backends');
			$opt = $opts[$backend];
			
			assert($opt['handler']);	//handler必须存在

			$opt['backend'] = $backend;		//将backend传入

			$this->options = $opt;
			$this->token = $token;
			$class = 'Auth_'.ucwords($opt['handler']);
			$this->handler = new $class($opt);
		}

		function create($password) {
			if (!$this->token) return FALSE;
			if ($this->options['readonly'] && !$this->options['allow_create']) return TRUE;
			return $this->handler->add($this->token, $password);
		}

		//验证令牌/密码对
		function verify($password) {
			if (!$this->token) return FALSE;
			return $this->handler->verify($this->token, $password);
		}

		//更改用户令牌
		function change_token($token_new){
			if (!$this->token) return FALSE;
			if ($this->options['readonly']) return TRUE;
			$ret = $this->handler->change_token($this->token, $token_new);
			if ($ret) {
				$this->token = $token_new;
			}
			return $ret;
		}

		//更改用户密码
		function change_password($password){
			if (!$this->token) return FALSE;
			if ($this->options['readonly']) return TRUE;
			return $this->handler->change_password($this->token, $password);
		}
		
		//删除令牌/密码对
		function remove(){
			if (!$this->token) return FALSE;
			if ($this->options['readonly']) return TRUE;
			return $this->handler->remove($this->token);
		}

		static function normalize($token = NULL, $default_backend = NULL) {
			if (!$token) return NULL;
			$token = trim($token);
			if (!$token) return '';
			if (!preg_match('/\|[\w.-]+/', $token)) {
				$default_backend = $default_backend ?: _CONF('auth.default_backend');
				$token .= '|'.$default_backend;
			}
			return $token;
		}

		static function make_token($name, $backend=NULL) {
			list($name, $b) = self::parse_token($name);
			$backend = $backend ?: ($b ?: _CONF('auth.default_backend'));
			return $name . '|' . $backend;
		}

		static function parse_token($token) {
			return explode('|', $token, 2);
		}

	}

}

namespace Model {
	
	if (DECLARED('\Model\Auth', __FILE___)) {
		class Auth extends \GR\System\Model\Auth {}
	}	
}
