<?php

namespace Gini\Controller\CLI;

class ORM extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini orm update\n";
        echo "gini orm export\n";
    }

    public function actionUpdate($args)
    {
        // ORM required class map.
        if (!isset($GLOBALS['gini.class_map'])) {
            echo "\e[31mYou need to run \e[1m\"gini cache class\"\e[0;31m before update ORM.\e[0m\n";

            return;
        }
        // enumerate orms
        echo "Updating database structures according ORM definition...\n";

        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        $orms = [];
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) {
                continue;
            }

            \Gini\File::eachFilesIn($orm_dir, function ($file) use ($orm_dir, &$orms) {
                if (!preg_match('/^(.+)\.php$/', $file, $parts)) {
                    return;
                }

                $oname = $parts[1];
                if ($oname === 'Object') return;
                $className = '\Gini\ORM\\' . str_replace('/', '\\', $oname);

                // Check if it is abstract class
                $rc = new \ReflectionClass($className);
                if ($rc->isAbstract() || $rc->isTrait() || $rc->isInterface() || !$rc->isSubclassOf('\Gini\ORM\Base')) {
                    return;
                }

                $oname = strtolower($oname);
                $orms[$oname] = \Gini\IoC::construct($className);
            });
        }

        // sort ORM objects according relation dependencies.
        $adjusted = [];
        $push = function ($oname) use (&$orms, &$adjusted, &$push) {
            if (isset($adjusted[$oname])) {
                return;
            }
            $o = $orms[$oname];
            if (!isset($o)) {
                printf("   \e[31m%s MISSING!\e[0m\n", $oname);
                return;
            }
            $adjusted[$oname] = true;
            $structure = $o->structure();

            $relations = $o->ormRelations();
            if ($relations) {
                foreach ($relations as $k => $r) {
                    if (array_key_exists('object', (array) $structure[$k])) {
                        $dep_oname = $structure[$k]['object'];
                    } elseif ($r['ref']) {
                        $ref = explode('.', $r['ref'], 2);
                        $dep_oname = $ref[0];
                    } else {
                        continue;
                    }
                    $push($dep_oname);
                }
            }

            $manyStructure = $o->manyStructure();
            if ($manyStructure) {
                foreach ($manyStructure as $k => $v) {
                    if (isset($v['object'])) {
                        $push($v['object']);
                    }
                }
            }

            printf("   %s\n", $oname);
            $o->adjustTable();
        };

        array_map($push, array_keys($orms));

        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionExport($args)
    {
        printf("Exporting ORM structures...\n\n");

        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) {
                continue;
            }

            \Gini\File::eachFilesIn($orm_dir, function ($file) use ($orm_dir) {
                if (!preg_match('/^(.+)\.php$/', $file, $parts)) {
                    return;
                }

                $oname = $parts[1];
                if ($oname === 'Object') return;
                $class_name = '\Gini\ORM\\' . str_replace('/', '\\', $oname);

                // Check if it is abstract class
                $rc = new \ReflectionClass($class_name);
                if ($rc->isAbstract() || $rc->isTrait() || $rc->isInterface()) {
                    return;
                }

                printf("   %s\n", $oname);

                $o = \Gini\IoC::construct($class_name);
                $structure = $o->structure();

                // unset system fields
                unset($structure['id']);
                unset($structure['_extra']);

                $i = 1;
                $max = count($structure);
                foreach ($structure as $k => $v) {
                    if ($i == $max) {
                        break;
                    }
                    printf("   ├─ %s (%s)\n", $k, implode(',', array_map(function ($k, $v) {
                        return $v ? "$k:$v" : $k;
                    }, array_keys($v), $v)));
                    ++$i;
                }

                printf("   └─ %s (%s)\n\n", $k, implode(',', array_map(function ($k, $v) {
                    return $v ? "$k:$v" : $k;
                }, array_keys($v), $v)));
            });
        }
    }

    public function actionUpgradeId()
    {
        // ORM required class map.
        if (!isset($GLOBALS['gini.class_map'])) {
            echo "\e[31mYou need to run \e[1m\"gini cache class\"\e[0;31m before upgrade ORM id.\e[0m\n";

            return;
        }
        // enumerate orms
        echo "Updating database structures according ORM definition...\n";

        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) {
                continue;
            }

            \Gini\File::eachFilesIn($orm_dir, function ($file) use ($orm_dir) {
                if (!preg_match('/^(.+)\.php$/', $file, $parts)) {
                    return;
                }

                $oname = $parts[1];
                $class_name = '\Gini\ORM\\' . str_replace('/', '\\', $oname);

                // Check if it is abstract class
                $rc = new \ReflectionClass($class_name);
                if ($rc->isAbstract() || $rc->isTrait() || $rc->isInterface()) {
                    return;
                }

                $o = \Gini\IoC::construct($class_name);
                // some object might not have database backend
                $db = $o->db();
                if (!$db) {
                    return;
                }

                printf("   %s\n", $oname);
                $structure = $o->structure();

                foreach ($structure as $field => $option) {
                    if (isset($option['object'])) {
                        $db->query('UPDATE :table SET :field=NULL WHERE :field=0', [
                            ':table' => $o->tableName(),
                            ':field' => $field . '_id',
                        ]);
                    }
                }
            });
        }

        echo "   \e[32mdone.\e[0m\n";
    }
}
