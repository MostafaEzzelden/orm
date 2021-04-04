<?php

namespace Core\Database;

use Exception;
use mysqli;

class DB
{
    private static $instance;

    private $connection;
    private $host = '127.0.0.1';
    private $username = 'root';
    private $passwd = '';
    private $dbname = 'orm';
    private $port = 3306;

    static public function getInstance()
    {
        if (!static::$instance) new static;
        return static::$instance;
    }

    private function __construct()
    {
        $connection = new mysqli($this->host, $this->username, $this->passwd, $this->dbname, $this->port);

        if ($connection->connect_error) die($connection->error);

        $this->connection = $connection;

        static::$instance = $this;
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    public function query(string $query)
    {
        return $this->getConnection()->query($query);
    }

    public function fetch(string $query)
    {
        $output = [];

        $result = $this->query($query);

        if (!$result) throw new Exception($this->getConnection()->error);

        while ($row = $result->fetch_assoc()) {
            $output[] = $row;
        }

        $result->free();

        return $output;
    }

    public function insertGetId(string $query)
    {
        if ($this->query($query)) return (int)$this->getConnection()->insert_id;

        throw new Exception($this->getConnection()->error);
    }

    public function insert(string $query)
    {
        if ($this->query($query)) return $this->getConnection()->affected_rows;

        throw new Exception($this->getConnection()->error);
    }

    public function update(string $query)
    {
        if ($result = $this->query($query)) return $result;
        throw new Exception($this->getConnection()->error);
    }

    public function delete(string $query)
    {
        if ($this->query($query)) return $this->getConnection()->affected_rows;
        throw new Exception($this->getConnection()->error);
    }
}
