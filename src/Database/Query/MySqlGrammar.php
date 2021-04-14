<?php

namespace Core\Database\Query;


class MySqlGrammar
{
    protected $tablePrefix = '';
    
    protected $wrapper = '`%s`';

    protected $selectComponents = array(
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
    );

    public function wrapArray(array $values)
    {
        return array_map(array($this, 'wrap'), $values);
    }

    public function wrapTable($table)
    {
        return $this->wrap($this->tablePrefix . $table);
    }

    public function wrap($value)
    {
        if (strpos(strtolower($value), ' as ') !== false) {
            $segments = explode(' ', $value);

            return $this->wrap($segments[0]) . ' as ' . $this->wrap($segments[2]);
        }

        $wrapped = array();

        $segments = explode('.', $value);

        foreach ($segments as $key => $segment) {
            if ($key == 0 and count($segments) > 1) {
                $wrapped[] = $this->wrapTable($segment);
            } else {
                $wrapped[] = $this->wrapValue($segment);
            }
        }

        return implode('.', $wrapped);
    }

    protected function wrapValue($value)
    {
        return $value !== '*' ? sprintf($this->wrapper, $value) : $value;
    }

    public function columnize(array $columns)
    {
        return implode(', ', array_map(array($this, 'wrap'), $columns));
    }

    public function parameterize(array $values)
    {
        return implode(', ', array_map(array($this, 'parameter'), $values));
    }

    public function parameter($value)
    {
        return '?';
    }

    public function getValue($expression)
    {
        return $expression->getValue();
    }

    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    public function compileSelect($query)
    {
        if (is_null($query->columns)) $query->columns = array('*');

        return trim($this->concatenate($this->compileComponents($query)));
    }

    protected function compileComponents($query)
    {
        $sql = array();

        foreach ($this->selectComponents as $component) {
            if (!is_null($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    protected function compileAggregate($query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        if ($query->distinct and $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    protected function compileColumns($query, $columns)
    {
        if (!is_null($query->aggregate)) return;

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select . $this->columnize($columns);
    }

    protected function compileFrom($query, $table)
    {
        return 'from ' . $this->wrapTable($table);
    }

    protected function compileJoins($query, $joins)
    {
        $sql = array();

        foreach ($joins as $join) {
            $table = $this->wrapTable($join->table);

            $clauses = array();

            foreach ($join->clauses as $clause) {
                $clauses[] = $this->compileJoinConstraint($clause);
            }

            $clauses[0] = $this->removeLeadingBoolean($clauses[0]);

            $clauses = implode(' ', $clauses);

            $type = $join->type;

            $sql[] = "$type join $table on $clauses";
        }

        return implode(' ', $sql);
    }

    protected function compileJoinConstraint(array $clause)
    {
        $first = $this->wrap($clause['first']);

        $second = $this->wrap($clause['second']);

        return "{$clause['boolean']} $first {$clause['operator']} $second";
    }

    protected function compileWheres($query)
    {
        $sql = array();

        if (is_null($query->wheres)) return '';

        foreach ($query->wheres as $where) {
            $method = "where{$where['type']}";

            $sql[] = $where['boolean'] . ' ' . $this->$method($query, $where);
        }

        if (count($sql) > 0) {
            $sql = implode(' ', $sql);

            return 'where ' . preg_replace('/and |or /', '', $sql, 1);
        }

        return '';
    }

    protected function whereNested($query, $where)
    {
        $nested = $where['query'];

        return '(' . substr($this->compileWheres($nested), 6) . ')';
    }

    protected function whereSub($query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ($select)";
    }

    protected function whereBasic($query, $where)
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $value;
    }

    protected function whereBetween($query, $where)
    {
        return $this->wrap($where['column']) . ' between ? and ?';
    }

    protected function whereExists($query, $where)
    {
        return 'exists (' . $this->compileSelect($where['query']) . ')';
    }

    protected function whereNotExists($query, $where)
    {
        return 'not exists (' . $this->compileSelect($where['query']) . ')';
    }

    protected function whereIn($query, $where)
    {
        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']) . ' in (' . $values . ')';
    }

    protected function whereNotIn($query, $where)
    {
        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']) . ' not in (' . $values . ')';
    }

    protected function whereInSub($query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' in (' . $select . ')';
    }

    protected function whereNotInSub($query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' not in (' . $select . ')';
    }

    protected function whereNull($query, $where)
    {
        return $this->wrap($where['column']) . ' is null';
    }

    protected function whereNotNull($query, $where)
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    protected function whereRaw($query, $where)
    {
        return $where['sql'];
    }

    protected function compileGroups($query, $groups)
    {
        return 'group by ' . $this->columnize($groups);
    }

    protected function compileHavings($query, $havings)
    {
        $me = $this;

        $sql = implode(' ', array_map(array($this, 'compileHaving'), $havings));

        return 'having ' . preg_replace('/and /', '', $sql, 1);
    }

    protected function compileHaving(array $having)
    {
        if ($having['type'] === 'raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return 'and ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    protected function compileOrders($query, $orders)
    {
        $me = $this;

        return 'order by ' . implode(', ', array_map(
            function ($order) use ($me) {
                if (isset($order['sql'])) return $order['sql'];

                return $me->wrap($order['column']) . ' ' . $order['direction'];
            },
            $orders
        ));
    }

    protected function compileLimit($query, $limit)
    {
        return 'limit ' . (int) $limit;
    }

    protected function compileOffset($query, $offset)
    {
        return 'offset ' . (int) $offset;
    }

    protected function compileUnions($query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $joiner = $union['all'] ? ' union all ' : ' union ';

            $sql .= $joiner . $union['query']->toSql();
        }

        return ltrim($sql);
    }

    public function compileInsert($query, array $values)
    {
        $table = $this->wrapTable($query->from);

        if (!is_array(reset($values))) {
            $values = array($values);
        }

        $columns = $this->columnize(array_keys(reset($values)));

        $parameters = $this->parameterize(reset($values));

        $value = array_fill(0, count($values), "($parameters)");

        $parameters = implode(', ', $value);

        return "insert into $table ($columns) values $parameters";
    }

    public function compileInsertGetId($query, $values, $sequence)
    {
        return $this->compileInsert($query, $values);
    }

    public function compileUpdate($query, $values)
    {
        $table = $this->wrapTable($query->from);

        $columns = array();

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) . ' = ' . $this->parameter($value);
        }

        $columns = implode(', ', $columns);

        if (isset($query->joins)) {
            $joins = ' ' . $this->compileJoins($query, $query->joins);
        } else {
            $joins = '';
        }

        $where = $this->compileWheres($query);

        $sql = trim("update {$table}{$joins} set $columns $where");

        if (isset($query->orders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->orders);
        }

        if (isset($query->limit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->limit);
        }

        return rtrim($sql);
    }

    public function compileDelete($query)
    {
        $table = $this->wrapTable($query->from);

        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from $table " . $where);
    }

    public function compileTruncate($query)
    {
        return array('truncate ' . $this->wrapTable($query->from) => array());
    }

    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/and |or /', '', $value, 1);
    }
}
