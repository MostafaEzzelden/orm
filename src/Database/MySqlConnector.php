<?php

namespace Core\Database;

use PDO;

class MySqlConnector
{
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    public function getOptions(array $config)
    {
        $options = isset($config['options']) ? $config['options'] : [];

        return array_diff_key($this->options, $options) + $options;
    }

    public function createConnection($dsn, array $config, array $options)
    {
        $username = $config['username'] ?: '';

        $password = $config['password'] ?: '';

        return new PDO($dsn, $username, $password, $options);
    }

    public function getDefaultOptions()
    {
        return $this->options;
    }

    public function setDefaultOptions(array $options)
    {
        $this->options = $options;
    }

    public function connect(array $config)
    {
        $dsn = $this->getDsn($config);

        $options = $this->getOptions($config);

        $connection = $this->createConnection($dsn, $config, $options);

        $collation = $config['collation'] ?? $this->defaultCollection();

        $charset = $config['charset'] ?? $this->defaultCharset();

        $names = "set names '$charset' collate '$collation'";

        $connection->prepare($names)->execute();

        return $connection;
    }

    protected function getDsn(array $config)
    {
        extract($config);

        $dsn = "mysql:host={$host};dbname={$database}";

        if (isset($config['port'])) {
            $dsn .= ";port={$port}";
        }

        if (isset($config['unix_socket'])) {
            $dsn .= ";unix_socket={$config['unix_socket']}";
        }

        return $dsn;
    }
    
    protected function defaultCharset()
    {
        return 'utf8';
    }
    
    protected function defaultCollection()
    {
        return 'utf8_unicode_ci';
    }
}
