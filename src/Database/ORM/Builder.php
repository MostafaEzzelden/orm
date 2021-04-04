<?php

namespace Core\Database\ORM;

use Closure;
use Core\Database\ORM\Model;
use Core\Database\CommandBuilder as QueryBuilder;
use Core\Database\ORM\Relations\Relation;

class Builder
{
    protected $query;

    protected $model;

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $eagerLoad = array();

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'toSql', 'insert', 'insertGetId',
        'count', 'min', 'max', 'avg', 'sum',
        'delete', 'update',
    ];

    public function __construct(QueryBuilder $query = null)
    {
        $this->query = $query ?: new QueryBuilder;
    }

    public function setModel(Model $model): self
    {
        $this->model = $model;

        $this->query->table($this->model->getTable());

        return $this;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function with($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $eagers = $this->parseRelations($relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagers);

        return $this;
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseRelations(array $relations)
    {
        $results = array();

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $f = function () {
                };

                list($name, $constraints) = array($constraints, $f);
            }

            $results = $this->parseNested($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Parse the nested relationships in a relation.
     *
     * @param  string  $name
     * @param  array   $results
     * @return array
     */
    protected function parseNested($name, $results)
    {
        $progress = array();

        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (!isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function () {
                };
            }
        }

        return $results;
    }

    public function get($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $models = $this->getModels($columns);

        if (count($models) > 0) {
            $models = $this->eagerLoadRelations($models);
        }

        return $this->model->newCollection($models);
    }

    public function getModels($columns = ['*'])
    {
        $results = $this->query->get($columns);

        $models = array();

        foreach ($results as $result) {
            $models[] = $this->model->newFromBuilder($result);
        }

        return $models;
    }

    public function eagerLoadRelations(array $models)
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (strpos($name, '.') === false) {
                $models = $this->loadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    protected function loadRelation(array $models, $name, Closure $constraints)
    {
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        call_user_func($constraints, $relation);

        $models = $relation->initRelation($models, $name);

        $results = $relation->get();

        return $relation->match($models, $results, $name);
    }

    public function getRelation($relation)
    {
        $me = $this;

        $query = Relation::noConstraints(function () use ($me, $relation) {
            return $me->getModel()->$relation();
        });

        $nested = $this->nestedRelations($relation);

        if (count($nested) > 0) {
            $query->getQuery()->with($nested);
        }

        return $query;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    protected function nestedRelations($relation)
    {
        $nested = array();

        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNested($name, $relation)) {
                $nested[substr($name, strlen($relation . '.'))] = $constraints;
            }
        }

        return $nested;
    }

    protected function isNested($name, $relation)
    {
        $dots = str_contains($name, '.');

        return $dots and $this->startsWith($name, $relation) and $name != $relation;
    }

    protected function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && strpos($haystack, $needle) === 0) return true;
        }

        return false;
    }

    public function first(array $columns = ['*'])
    {
        return $this->limit(1)->get($columns)->first();
    }

    public function find(int $id, array $columns = array('*'))
    {
        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        return in_array($method, $this->passthru) ? $result : $this;
    }
}
