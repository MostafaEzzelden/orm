<?php

namespace Core\Database;

use stdClass;
use Core\Database\DB;
use InvalidArgumentException;

class CommandBuilder
{
    private const SELECT_COMMAND_CLAUSES = [
        'join',
        'where',
        'groupBy',
        'having',
        'orderBy',
        'limit',
        'offset'
    ];

    private $table = "";
    private $select = [];
    private $where = "";
    private $join = "";
    private $orderBy = "";
    private $groupBy = "";
    private $having = "";
    private $offset = "";
    private $limit = "";
    private $commandString = "";

    private $appendAndKeyword = false;
    private $appendOrKeyword = false;

    /**
     * Set table name
     *
     * @param string $table
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set join query
     *
     * @param string $table
     * @param string $column_1
     * @param string $operator
     * @param string $column_2
     * @return self
     */
    public function join(string $table, string $column_1, string $operator, string $column_2): self
    {
        $this->join .= " inner join $table on $column_1 $operator $column_2";

        $this->join = trim($this->join);
        
        return $this;
    }

    /**
     * Set wheres query
     *
     * @param string|closure $column
     * @param string|null $operator
     * @param string|null $value
     * @return self
     */
    public function where($column, string $operator = null, string $value = null): self
    {
        if (empty($this->where)) $this->where = "where ";

        if ($this->appendAndKeyword) $this->where .= " and ";

        if (is_callable($column)) {
            $this->where .= "(";
            $this->appendAndKeyword = false;
            $this->appendOrKeyword = false;
            $column($this);
            $this->where .= ")";
        } else {
            $this->where .= "$column $operator '$value'";
        }

        $this->appendAndKeyword = true;

        return $this;
    }

    /**
     * Set or where query
     *
     * @param string|closure $column
     * @param string $operator
     * @param string $value
     * @return self
     */
    public function orWhere($column, string $operator = null, string $value = null): self
    {
        if (empty($this->where)) $this->where = "where ";

        if ($this->appendAndKeyword || $this->appendOrKeyword) $this->where .= " or ";

        if (is_callable($column)) {
            $this->where .= "(";
            $this->appendAndKeyword = false;
            $this->appendOrKeyword = false;
            $column($this);
            $this->where .= ")";
        } else {
            $this->where .= "$column $operator '$value'";
        }

        $this->appendOrKeyword = true;

        return $this;
    }

    /**
     * Set where in query
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($this->where)) $this->where = "where ";

        if ($this->appendAndKeyword || $this->appendOrKeyword) $this->where .= " and ";

        $this->where .= "$column in ('" . implode("','", $values) . "')";

        $this->appendAndKeyword = true;

        return $this;
    }

    /**
     * Set or where in query
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        if (empty($this->where)) $this->where = "where ";

        if ($this->appendAndKeyword || $this->appendOrKeyword) $this->where .= " or ";

        $this->where .= "$column in ('" . implode("','", $values) . "')";

        $this->appendAndKeyword = true;

        return $this;
    }

    /**
     * Set select query
     *
     * @param string|array $select
     * @return self
     */
    public function select($columns = ['*']): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->select = $columns;
        return $this;
    }

    public function having(int $having): self
    {
        $this->having = "having $having";

        return $this;
    }

    /**
     * Set Order by
     *
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->orderBy = "order by $column $direction";

        return $this;
    }

    public function groupBy(string $column)
    {
        $this->groupBy = "group by $column";

        return $this;
    }

    /**
     * Set query limit
     *
     * @param integer $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = "limit $limit";

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = "offset $offset";

        return $this;
    }
    /**
     * Build select query
     *
     * @return self
     */
    private function buildSelectCommand(): self
    {
        if (empty($this->select)) $this->select = ['*'];

        $command = "select " .  implode(', ', $this->select) . ' from ' . $this->table;
        foreach (self::SELECT_COMMAND_CLAUSES as $clause) if (!empty($this->$clause)) {
            $command .= " {$this->$clause}";
        }
        $this->commandString = $command;

        return $this;
    }

    /**
     * Get the command string
     *
     * @return string
     */
    public function getCommandString(): string
    {
        $this->buildSelectCommand();
        return $this->commandString;
    }

    /**
     * Get query results
     *
     * @param model $class_name
     * @return array
     */
    public function get($columns = ['*']): array
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        
        $this->select = $columns;
        
        return DB::getInstance()->fetch($this->getCommandString());
    }

    public function avg(string $column)
    {
        return current($this->select("AVG($column) AS result")->get())->result;
    }

    public function sum(string $column)
    {
        return current($this->select("SUM($column) AS result")->get())->result;
    }

    public function count(string $column = "*")
    {
        return current($this->select("COUNT($column) AS result")->get())->result;
    }

    public function min(string $column)
    {
        return current($this->select("MIN($column) AS result")->get())->result;
    }

    public function max(string $column)
    {
        return current($this->select("MAX($column) AS result")->get())->result;
    }

    public function insert(array $data)
    {
        return DB::getInstance()->insert($this->buildInsertCommand($data)->commandString);
    }

    public function insertGetId(array $data)
    {
        return DB::getInstance()->insertGetId($this->buildInsertCommand($data)->commandString);
    }

    private function buildInsertCommand(array $data): self
    {
        if ($this->isAssoc($data)) $data = [$data];

        $columns = array_keys(current($data));

        $values = implode(',', array_reduce(array_map('array_values', $data), function ($carry, $value) {
            $carry[] = "('" . implode("','", $value) . "')";
            return $carry;
        }, []));

        $this->commandString = "insert into {$this->table} (" . implode(',', $columns) . ") values $values";

        return $this;
    }

    private function isAssoc(array $arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function delete()
    {
        $this->buildDeleteCommand();
        return DB::getInstance()->delete($this->commandString);
    }

    private function buildDeleteCommand(): self
    {
        $this->commandString = "delete from {$this->table}";

        if (!empty($this->where)) $this->commandString .= " {$this->where}";

        return $this;
    }

    public function update(array $data)
    {
        return DB::getInstance()->update($this->buildUpdateCommand($data)->commandString);
    }

    private function buildUpdateCommand(array $data): self
    {
        $command = "update {$this->table} set ";

        $values = implode(',', array_map(function ($key, $value) {
            return "$key='$value'";
        }, array_keys($data), array_values($data)));

        $command .= $values;

        if (!empty($this->where)) $command .= " {$this->where}";

        $this->commandString = $command;

        return $this;
    }

    public function toSql()
    {
        return $this->getCommandString();
    }
}
