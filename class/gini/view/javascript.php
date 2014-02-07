<?php

namespace Gini\View {

    class JavaScript implements Engine
    {
        private $_path;
        private $_vars;

        public function __construct($path, array $vars)
        {
            $this->_path = $path;
            $this->_vars = $vars;
        }

        public function __toString()
        {
            if ($this->_path) {
                ob_start();

                echo "(function () {\n";
                foreach ($this->_vars as $k => $v) {
                    echo "var $k=".json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n";
                }
                @include($_path);
                echo "\n})();";

                $output = ob_get_contents();
                ob_end_clean();
            }

            return $output;
        }

    }

}
