<?php

namespace Gini;

class Document
{
    private $_baseClass;
    private $_fileFilters = [];
    private $_classFilters = [];
    private $_methodFilters = [];

    public static function of($baseClass)
    {
        return IoC::construct('\Gini\Document', $baseClass);
    }

    public function __construct($baseClass = 'Gini')
    {
        $this->_baseClass = $baseClass;
    }

    public function filterFiles($func)
    {
        $this->_fileFilters[] = $func;
        return $this;
    }

    public function filterClasses($func)
    {
        $this->_classFilters[] = $func;
        return $this;
    }

    public function filterMethods($func)
    {
        $this->_methodFilters[] = $func;
        return $this;
    }

    public function format($formatFunc = null)
    {
        $baseClass = trim($this->_baseClass, '\\');
        $baseDir = str_replace('\\', '/', $baseClass);
        $dirs = Core::pharFilePaths(CLASS_DIR, $baseDir);
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            File::eachFilesIn($dir, function ($file) use ($baseClass, &$files) {
                if (File::extension($file) != 'php') {
                    return;
                }

                foreach ($this->_fileFilters as $func) {
                    if (!$func($file)) {
                        return;
                    }
                }

                $files[] = $file;
            });
        }

        $files = array_unique($files);
        array_walk($files, function ($file) use ($baseClass, &$formatFunc) {
            if (!preg_match('/^(.+)\.php$/', $file, $parts)) {
                return;
            }

            $name = $parts[1];
            $className = '\\' . $baseClass . '\\' . str_replace('/', '\\', $name);

            // Check if it is abstract class
            $rc = new \ReflectionClass($className);
            foreach ($this->_classFilters as $func) {
                if (false === $func($rc)) {
                    return;
                }
            }

            $rms = $rc->getMethods();
            foreach ($rms as $rm) {
                $skip = false;
                foreach ($this->_methodFilters as $func) {
                    if (false === $func($rm)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                // $method = strtolower($method[0]).substr($method, 1);
                $rps = $rm->getParameters();
                // echo $rm->getDocComment()."\n";
                if ($formatFunc) {
                    $params = array_map(function ($rp) {
                        $param = ['name' => $rp->name];
                        $rp->hasType() and $param['type'] = $rp->getType();
                        $rp->isPassedByReference() and $param['ref'] = true;
                        $rp->isDefaultValueAvailable() and $param['default'] = $rp->getDefaultValue();
                        return $param;
                    }, $rps);
                    $formatFunc((object) [
                        'class' => $className,
                        'method' => $rm->name,
                        'params' => $params,
                        'reflection' => (object) [
                            'class' => $rc,
                            'method' => $rm,
                            'params' => $rps
                        ]
                    ]);
                } else {
                    $docParams = array_map(function ($rp) {
                        $decl = '';
                        $rp->hasType() and $decl .= $rp->getType() . ' ';
                        $rp->isPassedByReference() and $decl .= '&';
                        $decl .= '$' . $rp->name;
                        $rp->isDefaultValueAvailable() and $decl .= '=' . J($rp->getDefaultValue());
                        return $decl;
                    }, $rps);
                    $method = preg_replace('/^(action|get|post|delete|put|patch|options)/', '', $rm->name);
                    echo strtr($name, ['/' => '.']) . "." . $method . "(" . implode(', ', $docParams) . ")\n";
                }
            }
        });
    }
}
