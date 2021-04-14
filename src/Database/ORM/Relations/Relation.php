<?php

namespace Core\Database\ORM\Relations;

use Closure;
use Core\Database\ORM\Model;
use Core\Database\ORM\ModelBuilder;
use Core\Database\ORM\Collection;

abstract class Relation
{
    protected $query;

    protected $parent;

    protected $related;

    protected static $constraints = true;

    public function __construct(ModelBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    abstract public function addConstraints();

    public static function noConstraints(Closure $callback)
    {
        static::$constraints = false;

        // When resetting the relation where clause, we want to shift the first element
        // off of the bindings, leaving only the constraints that the developers put
        // as "extra" on the relationships, and not original relation constraints.
        $results = call_user_func($callback);

        static::$constraints = true;

        return $results;
    }

    abstract public function addEagerConstraints(array $models);

    abstract public function initRelation(array $models, $relation);

    abstract public function match(array $models, Collection $results, $relation);

    public function rawUpdate(array $attributes = array())
    {
        return $this->query->update($attributes);
    }

    public function getBaseQuery()
    {
        return $this->query->getQuery();
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getRelated()
    {
        return $this->related;
    }

    protected function getKeys(array $models)
    {
        return array_values(array_map(function ($value) {
            return $value->getKey();
        }, $models));
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        if ($result === $this->query) return $this;

        return $result;
    }
}
