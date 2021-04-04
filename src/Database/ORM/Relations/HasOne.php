<?php

namespace Core\Database\ORM\Relations;

use Core\Database\ORM\Model;
use Core\Database\ORM\Builder;
use Core\Database\ORM\Collection;

class HasOne extends Relation
{
    private $foreignKey;

    public function __construct(Builder $query, Model $parent, $foreignKey)
    {
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $key = $this->parent->getKey();

            $this->query->where($this->foreignKey, '=', $key);
        }
    }

    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->foreignKey, $this->getKeys($models));
    }

    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getKey();

            if (isset($dictionary[$key])) {
                $value = $this->getRelationValue($dictionary, $key);

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    protected function getRelationValue(array $dictionary, $key)
    {
        $value = $dictionary[$key];
        return reset($value);
    }

    protected function buildDictionary($results)
    {
        $dictionary = array();

        $foreign = $this->getPlainForeignKey();

        foreach ($results as $result) {
            $dictionary[$result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    public function getPlainForeignKey()
    {
        $segments = explode('.', $this->getForeignKey());

        return $segments[count($segments) - 1];
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \Core\Database\ORM\Model
     */
    public function create(array $attributes)
    {
        $foreign = array(
            $this->getPlainForeignKey() => $this->parent->getKey()
        );

        $instance = $this->related->newInstance();

        $instance->setRawAttributes(array_merge($attributes, $foreign));

        $instance->save();

        return $instance;
    }

    public function save(Model $model)
    {
        $model->setAttribute($this->getPlainForeignKey(), $this->parent->getKey());

        return $model->save() ? $model : false;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first();
    }
}
