<?php

namespace Gini\View {
    
    interface Engine {
        public function __construct($path, array $vars);
        public function __toString();
    }
    
}

namespace Gini {

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
            
        //返回View内容
        private $_ob_cache;        
        function __toString(){

            if ($this->_ob_cache !== null) return $this->_ob_cache;

            $path = $this->_path;
            $locale = _CONF('system.locale');
            $localeSpecificPath = "@$locale/$path";

            $engines = _CONF('view.engines');
            if (isset($GLOBALS['gini.view_map'][$localeSpecificPath])) {
                $realPath = $GLOBALS['gini.view_map'][$localeSpecificPath];
                $engine = $engines[pathinfo($realPath, PATHINFO_EXTENSION)];
            }
            elseif (isset($GLOBALS['gini.view_map'][$path])) {
                $realPath = $GLOBALS['gini.view_map'][$path];
                $engine = $engines[pathinfo($realPath, PATHINFO_EXTENSION)];
            }
            else {
                foreach ($engines as $ext => $engine) {
                    $realPath = \Gini\Core::locatePharFile(VIEW_DIR, "$localeSpecificPath.$ext");
                    if (!$realPath) {
                        $realPath = \Gini\Core::locatePharFile(VIEW_DIR, "$path.$ext");
                    }                
                    if ($realPath) break;
                }
            }

            if ($engine && $realPath) {
                $class = "\\Gini\\View\\$engine";
                $output = new $class($realPath, $this->_vars);
            }
            
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
