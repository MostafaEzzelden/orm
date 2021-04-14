<?php

namespace Core\Database;

use PDO;
use Closure;
use DateTime;
use Exception;
use Core\Database\Query\MySqlGrammar;
use Core\Database\Query\QueryBuilder;

class MySqlConnection
{
    protected $pdo;

    protected $queryGrammar;

    protected $fetchMode = PDO::FETCH_ASSOC;

    protected $transactions = 0;

    protected $queryLog = [];

    protected $loggingQueries = true;

    protected $pretending = false;

    protected $database;

    protected $tablePrefix = '';

    protected $config = [];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;

        $this->database = $config['database'];

        $this->tablePrefix = $config['prefix'];

        $this->config = $config;

        $this->useDefaultQueryGrammar();
    }

    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new MySqlGrammar);
    }

    public function table($table)
    {
        $query = new QueryBuilder($this, $this->getQueryGrammar());

        return $query->from($table);
    }

    public function raw($value)
    {
        return $value;
    }

    public function selectOne($query, $bindings = [])
    {
        $records = $this->select($query, $bindings);

        return count($records) > 0 ? reset($records) : null;
    }

    public function select($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) return [];

            $statement = $me->getPdo()->prepare($query);

            $statement->execute($me->prepareBindings($bindings));

            return $statement->fetchAll($me->getFetchMode());
        });
    }

    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    public function insertGetId($query, $bindings = [], $sequence = null)
    {
        $this->insert($query, $bindings);

        $id = $this->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) return true;

            $bindings = $me->prepareBindings($bindings);

            return $me->getPdo()->prepare($query)->execute($bindings);
        });
    }

    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) return 0;

            $statement = $me->getPdo()->prepare($query);

            $statement->execute($me->prepareBindings($bindings));

            return $statement->rowCount();
        });
    }

    public function unprepared($query)
    {
        return $this->run($query, [], function ($me, $query, $bindings) {
            if ($me->pretending()) return true;

            return (bool) $me->getPdo()->exec($query);
        });
    }

    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTime) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif ($value === false) {
                $bindings[$key] = 0;
            }
        }

        return $bindings;
    }

    public function transaction(Closure $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }

        return $result;
    }

    public function beginTransaction()
    {
        ++$this->transactions;

        if ($this->transactions == 1) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit()
    {
        if ($this->transactions == 1) $this->pdo->commit();

        --$this->transactions;
    }

    public function rollBack()
    {
        if ($this->transactions == 1) {
            $this->transactions = 0;

            $this->pdo->rollBack();
        } else {
            --$this->transactions;
        }
    }

    public function pretend(Closure $callback)
    {
        $this->pretending = true;

        $this->queryLog = [];

        $callback($this);

        $this->pretending = false;

        return $this->queryLog;
    }

    protected function run($query, $bindings, Closure $callback)
    {
        $start = microtime(true);

        try {
            $result = $callback($this, $query, $bindings);
        } catch (Exception $e) {
            $this->handleQueryException($e, $query, $bindings);
        }

        $time = $this->getElapsedTime($start);

        $this->logQuery($query, $bindings, $time);

        return $result;
    }

    protected function handleQueryException(Exception $e, $query, $bindings)
    {
        $bindings = var_export($bindings, true);

        $message = $e->getMessage() . " (SQL: {$query}) (Bindings: {$bindings})";

        throw new Exception($message, 0, $e);
    }

    public function logQuery($query, $bindings, $time = null)
    {
        if (!$this->loggingQueries) return;
        $this->queryLog[] = compact('query', 'bindings', 'time');
    }

    public function listen(Closure $callback)
    {
    }

    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function setPdo(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName()
    {
        return $this->getConfig('name');
    }

    public function getConfig($option)
    {
        return $this->config[$option] ?: null;
    }

    public function getDriverName()
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    public function setQueryGrammar($grammar)
    {
        $this->queryGrammar = $grammar;
    }

    public function pretending()
    {
        return $this->pretending === true;
    }

    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    public function setFetchMode($fetchMode)
    {
        $this->fetchMode = $fetchMode;
    }

    public function getQueryLog()
    {
        return $this->queryLog;
    }

    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    public function getDatabaseName()
    {
        return $this->database;
    }

    public function setDatabaseName($database)
    {
        $this->database = $database;
    }

    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        $this->getQueryGrammar()->setTablePrefix($prefix);
    }

    public function withTablePrefix($grammar)
    {
        $grammar->setTablePrefix($this->tablePrefix);

        return $grammar;
    }
}
