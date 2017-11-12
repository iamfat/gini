<?php

namespace Gini\ORM;

trait SoftDeletion
{
    public $deleted_at = 'datetime,null';

    protected function _delete()
    {
        $db = $this->db();
        $tbl_name = $this->tableName();

        $SQL = 'UPDATE '.$db->quoteIdent($tbl_name)
            .' SET "deleted_at" = NOW()'
            .' WHERE "id" = '.$db->quote($this->id);

        return (bool) $db->query($SQL);
    }
}
