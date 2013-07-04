<?php

namespace Model {

    class View {

        protected $_vars;
        protected $_path;

        function __construct($path, $vars=null){
            $this->_path = $path;
            $this->_vars = (array)$vars;
        }
            
        //返回子View
        function __get($key){
            assert($key[0] != '_');        
            return $this->_vars[$key];
        }

        function __set($key, $value) {
            assert($key[0] != '_');
            if ($value === null) {
                unset($this->_vars[$key]);
            } else {
                $this->_vars[$key] = $value;
            }
        }

        function __unset($key) {
            unset($this->_vars[$key]);
        }

        function __isset($key) {
            return isset($this->_vars[$key]);
        }
            
        private function __load_view($_path, $_extension) {

            if ($_path) {
                ob_start();

                switch ($_extension) {
                case 'js':
                    echo "(function(){\n";
                    foreach ($this->_vars as $k => $v) {
                        echo "var $k=".json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n";
                    }
                    @include($_path);
                    echo "\n})();";
                    break;
                default:
                    extract($this->_vars);
                    @include($_path);
                }

                $output = ob_get_contents();
                ob_end_clean();
            }
            
            return $output;
        }
        
        //返回View内容
        private $_ob_cache;        
        function __toString(){

            if ($this->_ob_cache !== null) return $this->_ob_cache;

            $path = $this->_path;
            $scope = null;

            $locale = _CONF('system.locale');
            
            list($extension, $path) = explode('/', $path, 2);
            
            if ($GLOBALS['gini.view_map']) {
                $_path 
                    = $GLOBALS['gini.view_map'][$extension.'/@'.$locale.'/'.$path] 
                        ?: $GLOBALS['gini.view_map'][$extension.'/'.$path];
            }
            else {
                $_path = \Gini\Core::phar_file_exists(VIEW_DIR.'/'.$extension, '@'.$locale.'/'.$path.'.'.$extension);
                if (!$_path) {
                    $_path = \Gini\Core::phar_file_exists(VIEW_DIR.'/'.$extension, $path.'.'.$extension);    
                }
            }

            $output = $this->__load_view($_path, $extension);
        
            return $this->_ob_cache = (string) $output;
                        
        }
        
        function set($name, $value=null){
            if (is_array($name)) {
                array_map(array($this, __FUNCTION__), array_keys($name), array_values($name));
                return $this;
            } 
            else {
                $this->$name=$value;
            }
            
            return $this;
        }

        static function setup() { }
                
    }

}
