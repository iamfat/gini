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
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) continue;

            \Gini\File::eachFilesIn($orm_dir, function ($file) use ($orm_dir) {
                $oname = preg_replace('|.php$|', '', $file);
                if ($oname == 'Object') return;

                $class_name = '\Gini\ORM\\'.str_replace('/', '\\', $oname);

                // Check if it is abstract class
                $rc = new \ReflectionClass($class_name);
                if ($rc->isAbstract()) return;

                printf("   %s\n", $oname);
                $o = \Gini\IoC::construct($class_name);
                // some object might not have database backend
                $db = $o->db();
                if ($db) {
                    $db->adjustTable($o->tableName(), $o->schema());
                }
            });

        }

        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionExport($args)
    {
        printf("Exporting ORM structures...\n\n");

        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) continue;

            \Gini\File::eachFilesIn($orm_dir, function ($file) use ($orm_dir) {
                $oname = strtolower(preg_replace('|.php$|', '', $file));
                if ($oname == 'object') return;
                $class_name = '\Gini\ORM\\'.str_replace('/', '\\', $oname);

                // Check if it is abstract class
                $rc = new \ReflectionClass($class_name);
                if ($rc->isAbstract()) return;

                printf("   %s\n", $oname);

                $o = \Gini\IoC::construct($class_name);
                $structure = $o->structure();

                // unset system fields
                unset($structure['id']);
                unset($structure['_extra']);

                $i = 1; $max = count($structure);
                foreach ($structure as $k => $v) {
                    if ($i == $max) break;
                    printf("   ├─ %s (%s)\n", $k, implode(',', array_map(function ($k, $v) {
                        return $v ? "$k:$v" : $k;
                    }, array_keys($v), $v)));
                    $i++;
                }

                printf("   └─ %s (%s)\n\n", $k, implode(',', array_map(function ($k, $v) {
                    return $v ? "$k:$v" : $k;
                }, array_keys($v), $v)));
            });

        }
    }

}
