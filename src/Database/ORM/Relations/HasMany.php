<?php

namespace Core\Database\ORM\Relations;

use Core\Database\ORM\Model;
use Core\Database\ORM\ModelBuilder;
use Core\Database\ORM\Collection;

class HasMany extends Relation
{
    private $foreignKey;

    public function __construct(ModelBuilder $query, Model $parent, $foreignKey)
    {
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->parent->getKey());
        }
    }

    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->foreignKey, $this->getKeys($models));
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->get();
    }

    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
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
        return $this->related->newCollection($value);
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

    public function save(Model $model)
    {
        $model->setAttribute($this->getPlainForeignKey(), $this->parent->getKey());

        return $model->save() ? $model : false;
    }


    /**
     * Attach an array of models to the parent instance.
     *
     * @param  array  $models
     * @return array
     */
    public function saveMany(array $models)
    {
        array_walk($models, array($this, 'save'));

        return $models;
    }

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

    /**
     * Create an array of new instances of the related model.
     *
     * @param  array  $records
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = array();

        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }

        return $instances;
    }
}
