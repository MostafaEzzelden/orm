<?php

namespace Core\Database\Query;

use Closure;
use BadMethodCallException;
use Core\Database\Query\JoinClause;

class QueryBuilder
{
    protected $connection;

    protected $grammar;

    protected $bindings = array();

    public $aggregate;

    public $columns;

    public $distinct = false;

    public $from;

    public $joins;

    public $wheres;

    public $groups;

    public $havings;

    public $orders;

    public $limit;

    public $offset;

    public $unions;

    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
    );

    public function __construct($connection, $grammar)
    {
        $this->grammar = $grammar;
        $this->connection = $connection;
    }

    public function select($columns = array('*'))
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }

    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    public function join($table, $first, $operator = null, $second = null, $type = 'inner')
    {
        if ($first instanceof Closure) {
            $this->joins[] = new JoinClause($type, $table);

            call_user_func($first, end($this->joins));
        } else {
            $join = new JoinClause($type, $table);

            $join->on($first, $operator, $second);

            $this->joins[] = $join;
        }

        return $this;
    }

    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException("Value must be provided.");
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (!in_array(strtolower($operator), $this->operators, true)) {
            list($value, $operator) = array($operator, '=');
        }

        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator != '=');
        }

        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return ($isOperator and $operator != '=' and is_null($value));
    }

    public function whereRaw($sql, array $bindings = array(), $boolean = 'and')
    {
        $type = 'raw';

        $this->wheres[] = compact('type', 'sql', 'boolean');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    public function orWhereRaw($sql, array $bindings = array())
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    public function whereBetween($column, array $values, $boolean = 'and')
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean');

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    public function whereNested(Closure $callback, $boolean = 'and')
    {
        $type = 'Nested';

        $query = $this->newQuery();

        $query->from($this->from);

        call_user_func($callback, $query);

        if (count($query->wheres)) {
            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->mergeBindings($query);
        }

        return $this;
    }

    protected function whereSub($column, $operator, Closure $callback, $boolean)
    {
        $type = 'Sub';

        $query = $this->newQuery();

        call_user_func($callback, $query);

        $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    public function whereExists(Closure $callback, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotExists' : 'Exists';

        $query = $this->newQuery();

        call_user_func($callback, $query);

        $this->wheres[] = compact('type', 'operator', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    public function orWhereExists(Closure $callback, $not = false)
    {
        return $this->whereExists($callback, 'or', $not);
    }

    public function whereNotExists(Closure $callback, $boolean = 'and')
    {
        return $this->whereExists($callback, $boolean, true);
    }

    public function orWhereNotExists(Closure $callback)
    {
        return $this->orWhereExists($callback, true);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    protected function whereInSub($column, Closure $callback, $boolean, $not)
    {
        $type = $not ? 'NotInSub' : 'InSub';

        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull($column, $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    public function groupBy()
    {
        $this->groups = array_merge((array) $this->groups, func_get_args());

        return $this;
    }

    public function having($column, $operator = null, $value = null)
    {
        $type = 'basic';

        $this->havings[] = compact('type', 'column', 'operator', 'value');

        $this->bindings[] = $value;

        return $this;
    }

    public function havingRaw($sql, array $bindings = array(), $boolean = 'and')
    {
        $type = 'raw';

        $this->havings[] = compact('type', 'sql', 'boolean');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    public function orHavingRaw($sql, array $bindings = array())
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    public function orderBy($column, $direction = 'asc')
    {
        $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';

        $this->orders[] = compact('column', 'direction');

        return $this;
    }

    public function orderByRaw($sql, $bindings = array())
    {
        $type = 'raw';

        $this->orders[] = compact('type', 'sql');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    public function offset($value)
    {
        $this->offset = $value;

        return $this;
    }

    public function skip($value)
    {
        return $this->offset($value);
    }

    public function limit($value)
    {
        if ($value > 0) $this->limit = $value;

        return $this;
    }

    public function take($value)
    {
        return $this->limit($value);
    }

    public function union($query, $all = false)
    {
        if ($query instanceof Closure) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->unions[] = compact('query', 'all');

        return $this->mergeBindings($query);
    }

    public function unionAll($query)
    {
        return $this->union($query, true);
    }

    public function toSql()
    {
        return $this->grammar->compileSelect($this);
    }

    public function find($id, $columns = array('*'))
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    public function pluck($column)
    {
        $result = (array) $this->first(array($column));

        return count($result) > 0 ? reset($result) : null;
    }

    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? reset($results) : null;
    }

    public function get($columns = array('*'))
    {
        return $this->getFresh($columns);
    }

    public function getFresh($columns = array('*'))
    {
        if (is_null($this->columns)) $this->columns = $columns;

        return $this->runSelect();
    }

    protected function runSelect()
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    public function chunk($count, $callback)
    {
        $results = $this->forPage($page = 1, $count)->get();

        while (count($results) > 0) {
            call_user_func($callback, $results);

            $page++;

            $results = $this->forPage($page, $count)->get();
        }
    }

    public function exists()
    {
        return $this->count() > 0;
    }

    public function count($column = '*')
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array('*'))
    {
        $this->aggregate = compact('function', 'columns');

        $results = $this->get($columns);

        $this->columns = null;
        $this->aggregate = null;

        if (isset($results[0])) {
            $result = (array) $results[0];

            return $result['aggregate'];
        }
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        if (!is_array(reset($values))) {
            $values = array($values);
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $bindings = array();

        foreach ($values as $record) {
            $bindings = array_merge($bindings, array_values($record));
        }

        $sql = $this->grammar->compileInsert($this, $values);

        $bindings = $this->cleanBindings($bindings);

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->connection->insertGetId($sql, $values, $sequence);
            }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $bindings = array_values(array_merge($values, $this->bindings));

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings($bindings));
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = array())
    {
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge(array($column => $this->raw("$wrapped + $amount")), $extra);

        return $this->update($columns);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = array())
    {
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge(array($column => $this->raw("$wrapped - $amount")), $extra);

        return $this->update($columns);
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        if (!is_null($id)) $this->where('id', '=', $id);

        $sql = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $this->bindings);
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }


    public function newQuery()
    {
        return new QueryBuilder($this->connection, $this->grammar);
    }

    public function mergeWheres($wheres, $bindings)
    {
        $this->wheres = array_merge($this->wheres, (array) $wheres);

        $this->bindings = array_values(array_merge($this->bindings, (array) $bindings));
    }


    protected function cleanBindings(array $bindings)
    {
        return array_values($bindings);
    }

    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    public function setBindings(array $bindings)
    {
        $this->bindings = $bindings;

        return $this;
    }

    public function mergeBindings($query)
    {
        $this->bindings = array_values(array_merge($this->bindings, $query->bindings));

        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getGrammar()
    {
        return $this->grammar;
    }

    public function __call($method, $parameters)
    {
        $className = get_class($this);

        throw new BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }
}
