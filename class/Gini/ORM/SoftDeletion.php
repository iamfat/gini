<?php

namespace Gini\ORM;

trait SoftDeletion
{
    public $delete_at = 'datetime';

    protected function _delete()
    {
        $db = $this->db();
        $tbl_name = $this->tableName();

        $SQL = 'UPDATE '.$db->quoteIdent($tbl_name)
            .' SET "delete_at" = NOW()'
            .' WHERE "id" = '.$db->quote($this->id);

        return (bool) $db->query($SQL);
    }
}
