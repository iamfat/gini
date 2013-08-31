<?php

namespace Model\Auth {
    
    interface Driver {
        function __construct(array $opt);
        //验证令牌/密码
        function verify($username, $password);
        //设置令牌
        function change_username($username, $new_username);
        //设置密码
        function change_password($username, $password);
        //添加令牌/密码对
        function add($username, $password);
        //删除令牌/密码对
        function remove($username);
    }

}

namespace Model {

    class Auth {
    
        //返回当前令牌
        static function username() {
            static $curr_username;
    
            //auth.username可强制重载进程令牌
            if(_CONF('auth.username')){
                return _CONF('auth.username');
            }

            if ($curr_username === null) { 
                Event::trigger('auth.username', $_SESSION['auth.username']);
                $curr_username = $_SESSION['auth.username'];
            }
    
            return $curr_username;
        }
    
        //设置当前用户的令牌
        static function login($username) {
            // session_unset();
            Event::trigger('auth.before_login', $username);
            Session::cleanup();
            Session::regenerate_id();
            $_SESSION['auth.username'] = $username;
            Event::trigger('auth.after_login', $username);
            return $username;
        }
    
        //取消当前用户/指定用户的令牌
        static function logout() {
            $curr_username = self::username();
            Event::trigger('auth.before_logout', $username);
            Session::cleanup(true);
            Event::trigger('auth.after_logout', $username);
        }
        
        //显示当前用户是否已登录
        static function logged_in(){
            return self::username() != null;
        }

        static function backends() {
            return (array) _CONF('auth.backends');
        }
        
        static function normalize($username = null, $default_backend = null) {
            if (!$username) return null;
            $username = trim($username);
            if (!$username) return '';
            if (!preg_match('/\|[\w.-]+/', $username)) {
                $default_backend 
                    = $default_backend ?: _CONF('auth.default_backend');
                $username .= '|'.$default_backend;
            }
            return $username;
        }
    
        static function make_username($name, $backend=null) {
            list($name, $b) = self::parse_username($name);
            $backend = $backend ?: ($b ?: _CONF('auth.default_backend'));
            return $name . '|' . $backend;
        }
    
        static function parse_username($username) {
            return explode('|', $username, 2);
        }
    
        private $username;
        private $driver;
        private $options;
    
        function __construct($username) {
            if ($username === null) return;
    
            list($username, $backend) = self::parse_username($username);
    
            $backend = $backend ?: _CONF('auth.default_backend');
    
            $opts = (array) _CONF('auth.backends');
            $opt = $opts[$backend];
            
            if (!$opt['driver']) return;    //driver不存在, 表示没有验证驱动
    
            $opt['backend'] = $backend;        //将backend传入
    
            $this->options = $opt;
            $this->username = $username;
            $class = '\\Model\\Auth\\'.$opt['driver'];
            $this->driver = new $class($opt);
        }
    
        function create($password) {
            if (!$this->driver) return false;
            if (!$this->username) return false;
            if ($this->options['readonly'] 
                && !$this->options['allow_create']) return true;
            return $this->driver->add($this->username, $password);
        }
    
        //验证令牌/密码对
        function verify($password) {
            if (!$this->driver) return false;
            if (!$this->username) return false;
            return $this->driver->verify($this->username, $password);
        }
    
        //更改用户令牌
        function change_username($username_new){
            if (!$this->driver) return false;
            if (!$this->username) return false;
            if ($this->options['readonly']) return true;
            $ret = $this->driver->change_username(
                        $this->username, $username_new);
            if ($ret) {
                $this->username = $username_new;
            }
            return $ret;
        }
    
        //更改用户密码
        function change_password($password){
            if (!$this->driver) return false;
            if (!$this->username) return false;
            if ($this->options['readonly']) return true;
            return $this->driver->change_password($this->username, $password);
        }
        
        //删除令牌/密码对
        function remove(){
            if (!$this->driver) return false;
            if (!$this->username) return false;
            if ($this->options['readonly']) return true;
            return $this->driver->remove($this->username);
        }
    
    }

}
