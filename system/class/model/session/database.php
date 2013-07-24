<?php

abstract class _Session_Database implements Session_Handler {

    private $db_name;
    private $table;

    function __construct(){
        
        $this->db_name = _CONF('session.database.name');
        $this->table = _CONF('session.database.table') ?: '_session';
        
        $db = Database::factory($this->db_name);
        $db->prepare_table($this->table, array(
            'fields' => array(
                'id'=>array('type'=>'char(40)', 'null'=>false, 'default'=>''),
                'data'=>array('type'=>'text', 'null'=>true, 'default'=>null),
                'mtime'=>array('type'=>'int', 'null'=>false, 'default'=>0),                
            ),
            'indexes' => array(
                'primary'=>array('type'=>'primary', 'fields'=>array('id')),
                'mtime'=>array('fields'=>array('mtime')),
            )
        ));

    }
    
    function read($id) {
        $db = Database::factory($this->db_name);
        $val = $db->value('SELECT "data" FROM "_session" WHERE "id"=?', null, [$id]);
        if ($val) {
            $db->query('UPDATE "_session" SET "mtime"=? WHERE "id"=?', null, [time(), $id]);
        }
        return $val;
    }
    
    function write($id, $data){
        $now = Date::time();
        $db = Database::factory($this->db_name);
        $ret= $db->query('REPLACE INTO "_session" ("id", "data", "mtime") VALUES (:id, :data, :mtime)', 
                    null, 
                    [':id'=>$id, ':data'=>$data, ':mtime'=>$now]);

        return !is_null($ret);
    }

    function destroy($id){
        $db = Database::factory($this->db_name);
        return $db->query('DELETE FROM "_session" WHERE "id"=?', null, [$id]);
    }
    
    function gc($max_life_time){

        if ($max_life_time == 0) return true;
        
        $exp_time = Date::time() - $max_life_time;
        $db = Database::factory($this->db_name);
        $ret = $db->query('DELETE FROM "_session" WHERE "mtime"<?', null, [$exp_time]);

        return !is_null($ret);
    }
    
}

