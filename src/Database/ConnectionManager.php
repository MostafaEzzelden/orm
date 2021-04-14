<?php

namespace Core\Database;

use PDO;
use Core\Database\ORM\Model;
use InvalidArgumentException;
use Core\Database\MySqlConnector;

class ConnectionManager
{
    private $connections = [];

    public function __construct()
    {
        Model::setConnectionManager($this);
    }

    public function addConnection(array $config, string $name = 'default')
    {
        $this->connections[$name] = ['config' => $config];

        return $this;
    }

    public function connection(string $name = null)
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name]['connection'])) {
            $connection = $this->makeConnection($name);
            $this->connections[$name]['connection'] = $this->prepare($name, $connection);
        }

        return $this->connections[$name]['connection'];
    }

    public function getConnections()
    {
        return $this->connections;
    }

    protected function getDefaultConnection()
    {
        return 'default';
    }

    protected function makeConnection($name)
    {
        if (!isset($this->connections[$name])) {
            throw new InvalidArgumentException("Connection $name not configured.");
        }
        
        $config = $this->connections[$name]['config'];

        $pdo = $this->createConnector($config)->connect($config);

        return $this->createConnection($pdo, $config);
    }

    protected function createConnector(array $config)
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException("A driver must be specified.");
        }

        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnector;
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]");
    }

    protected function createConnection(PDO $connection, $config)
    {
        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnection($connection, $config);
        }

        throw new \InvalidArgumentException("Unsupported driver [{$config['driver']}]");
    }

    protected function prepare($name, $connection)
    {
        $name = $name ?: $this->getDefaultConnection();

        $config = $this->connections[$name]['config'];

        if (isset($config['fetch_mode'])) {
            $connection->setFetchMode($config['fetch_mode']);
        }

        return $connection;
    }
}
