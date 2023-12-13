<?php

namespace Gini\Those;

use Gini\Those;

class WhoAre implements Condition
{
    protected $field;
    protected $ofWhat;

    public function __construct($field)
    {
        $this->field = $field;
    }

    public function of(Those $what)
    {
        $this->ofWhat = $what;
        return $this;
    }

    public function createWhere(Those $those)
    {
        // those(e) whoAre a.b.c.d of g
        // SELECT  FROM e, g JOIN a JOIN b JOIN c WHERE e.id = c.d_id

        $db = $those->db();

        $ofWhat = $this->ofWhat;

        $ofWhat->finalizeCondition();
        // let ofWhat parse fieldName to add corresponding pivot table
        $ofWhatFieldName = Whose::fieldName($ofWhat, $this->field);

        $from = $those->context('from');
        $ofWhatFrom = $db->quoteIdent($ofWhat->tableName()) . ' AS ' . $ofWhat->context('current-table');
        $ofWhatJoin = $ofWhat->context('join');
        if ($ofWhatJoin) {
            $ofWhatFrom .= ' '.implode(' ', $ofWhatJoin);
        }

        $from[] = $ofWhatFrom;
        $those->context('from', $from);

        $whereArr = (array)$ofWhat->context('where');
        if ($whereArr) {
            $whereArr[] = 'AND';
        }
        $whereArr[] = Whose::fieldName($those, 'id') . ' = ' . $ofWhatFieldName;
        return '(' . implode(' ', $whereArr) . ')';
    }
}
