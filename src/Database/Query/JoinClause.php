<?php

namespace Core\Database\Query;

class JoinClause
{

    public $type;

    public $table;

    public $clauses = array();

    public function __construct($type, $table)
    {
        $this->type = $type;
        $this->table = $table;
    }

    public function on($first, $operator, $second, $boolean = 'and')
    {
        $this->clauses[] = compact('first', 'operator', 'second', 'boolean');

        return $this;
    }

    public function orOn($first, $operator, $second)
    {
        return $this->on($first, $operator, $second, 'or');
    }
}
