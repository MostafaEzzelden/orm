<?php

namespace Core\Database\ORM\Relations;

use Core\Database\ORM\Model;
use Core\Database\ORM\ModelBuilder;
use Core\Database\ORM\Collection;

class BelongsTo extends Relation
{
    protected $foreignKey;

    public function __construct(ModelBuilder $query, Model $parent, $foreignKey)
    {
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        return $this->query->first();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $key = $this->related->getKeyName();

            $table = $this->related->getTable();

            $this->query->where($table . '.' . $key, '=', $this->parent->{$this->foreignKey});
        }
    }

    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    public function addEagerConstraints(array $models)
    {
        $key = $this->related->getKeyName();

        $key = $this->related->getTable() . '.' . $key;

        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

    protected function getEagerModelKeys(array $models)
    {
        $keys = array();

        foreach ($models as $model) {
            if (!is_null($value = $model->{$this->foreignKey})) {
                $keys[] = $value;
            }
        }

        if (count($keys) == 0) {
            return array(0);
        }

        return array_values(array_unique($keys));
    }

    public function match(array $models, Collection $results, $relation)
    {
        $foreign = $this->foreignKey;

        $dictionary = array();

        foreach ($results as $result) {
            $dictionary[$result->getKey()] = $result;
        }

        foreach ($models as $model) {
            if (isset($dictionary[$model->$foreign])) {
                $model->setRelation($relation, $dictionary[$model->$foreign]);
            }
        }

        return $models;
    }

    public function update(array $attributes)
    {
        $instance = $this->getResults();

        return $instance->fill($attributes)->save();
    }

    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}
