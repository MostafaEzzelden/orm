<?php

namespace Core\Database\ORM;

use Exception;
use ArrayAccess;
use Core\Database\ORM\Builder;
use Core\Database\ORM\ArrayableInterface;
use Core\Database\ORM\Collection;
use Core\Database\ORM\Relations\HasOne;
use Core\Database\ORM\Relations\HasMany;
use Core\Database\ORM\Relations\BelongsTo;

abstract class Model implements ArrayAccess, ArrayableInterface, JsonableInterface
{
    /**
     * The model attribute's original state.
     *
     * @var array
     */
    protected $original = [];

    /**
     * the db table name
     *
     * @var string
     */
    protected $table = '';

    /**
     * The with relations
     *
     * @var array
     */
    protected $with = [];

    /**
     * the primary key for table
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes.
     *
     * @var array
     */
    protected $attributes = [];

    protected $relations = [];

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    protected $visible = [];

    protected $hidden = [];

    protected $incrementing = true;

    protected static $booted = [];

    public function __construct(array $attributes = [])
    {
        if (!isset(static::$booted[get_class($this)])) {
            static::$booted[get_class($this)] = true;

            static::boot();
        }

        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        //
    }

    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * get table
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array $attributes
     */
    public function fill(array $attributes)
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $key = $this->removeTableFromKey($key);

            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function fillableFromArray(array $attributes)
    {
        return array_intersect_key($attributes, array_flip($this->fillable));
    }
    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     */
    public function newInstance(array $attributes = [], $exists = false)
    {
        $model = new static((array) $attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Being querying a model with eager loading.
     *
     * @param  array|string  $relations
     */
    public static function with($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $instance = new static;

        return $instance->newQuery()->with($relations);
    }

    public function newQuery()
    {
        $builder = new Builder();

        $builder->setModel($this)->with($this->with);

        return $builder;
    }

    public function setRelation(string $relation_name, $data): self
    {
        $this->relations[$relation_name] = $data;

        return $this;
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     */
    public static function create(array $attributes)
    {
        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * Get all of the models from the database.
     *
     * @param array $columns
     */
    public static function all($columns = array('*'))
    {
        $instance = new static;

        return $instance->newQuery()->get($columns);
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     */
    public static function find($id, $columns = array('*'))
    {
        $instance = new static;

        return $instance->newQuery()->find($id, $columns);
    }

    public function delete()
    {
        if ($this->exists) {

            $this->performDeleteOnModel();

            $this->exists = false;

            return true;
        }
    }

    protected function performDeleteOnModel()
    {
        $query = $this->newQuery()->where($this->getKeyName(), '=', $this->getKey());
        $query->delete();
    }

    public static function destroy($ids)
    {
        $ids = is_array($ids) ? $ids : func_get_args();

        $instance = new static;

        $key = $instance->getKeyName();

        foreach ($instance->whereIn($key, $ids)->get() as $model) {
            $model->delete();
        }
    }

    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function update(array $attributes = array())
    {
        if (!$this->exists) {
            return $this->newQuery()->update($attributes);
        }

        return $this->fill($attributes)->save();
    }

    public function save()
    {
        $query = $this->newQuery();

        if ($this->exists) {
            $saved = $this->performUpdate($query);
        } else {
            $saved = $this->performInsert($query);
        }

        if ($saved) $this->finishSave();

        return $saved;
    }

    protected function finishSave()
    {
        $this->syncOriginal();
    }

    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    protected function getKeyForSaveQuery()
    {
        if (isset($this->original[$this->getKeyName()])) {
            return $this->original[$this->getKeyName()];
        } else {
            return $this->getAttribute($this->getKeyName());
        }
    }

    public function getAttribute($key)
    {
        $inAttributes = array_key_exists($key, $this->attributes);

        if ($inAttributes) {
            return $this->getAttributeValue($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
    }

    protected function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        return $value;
    }

    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Perform a model update operation.
     *
     * @return bool
     */
    protected function performUpdate(Builder $query)
    {
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $this->setKeysForSaveQuery($query)->update($dirty);
        }

        return true;
    }

    protected function performInsert(Builder $query)
    {
        $attributes = $this->attributes;

        if ($this->incrementing) {
            $this->insertAndSetId($query, $attributes);
        } else {
            $query->insert($attributes);
        }

        $this->exists = true;

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param  array  $attributes
     * @return void
     */
    protected function insertAndSetId(Builder $query, $attributes)
    {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }

    public function getDirty()
    {
        $dirty = array();

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) or $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    protected function isFillable(string $prop)
    {
        return in_array($prop, $this->fillable);
    }

    // Relations ...

    public function hasOne($related, string $foreignKey)
    {
        $instance = new $related;

        return new HasOne($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey);
    }

    public function hasMany(string $related, string $foreignKey)
    {
        $instance = new $related;

        return new HasMany($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey);
    }

    public function belongsTo(string $related, string $foreignKey)
    {
        $instance = new $related;

        return new BelongsTo($instance->newQuery(), $this, $foreignKey);
    }

    /**
     * Remove the table name from a given key.
     *
     * @param  string  $key
     * @return string
     */
    protected function removeTableFromKey($key)
    {
        if (!str_contains($key, '.')) return $key;

        return end(explode('.', $key));
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  array  $attributes
     * @return Model
     */
    public function newFromBuilder(array $attributes = [])
    {
        $instance = $this->newInstance(array(), true);

        $instance->setRawAttributes((array) $attributes, true);

        return $instance;
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool   $sync
     * @return void
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) $this->syncOriginal();
    }

    /**
     * Convert the model instance to array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();

        return array_merge($attributes, $this->relationsToArray());
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }


    protected function attributesToArray()
    {
        return $this->getArrayableItems($this->attributes);
    }

    protected function relationsToArray()
    {
        $attributes = array();

        foreach ($this->getArrayableItems($this->relations) as $key => $value) {
            if (in_array($key, $this->hidden)) continue;

            if ($value instanceof ArrayableInterface) {
                $relation = $value->toArray();
            } elseif (is_null($value)) {
                $relation = $value;
            }

            if (isset($relation) or is_null($value)) {
                $attributes[$key] = $relation;
            }
        }

        return $attributes;
    }

    protected function getArrayableItems(array $values)
    {
        if (count($this->visible) > 0) {
            return array_intersect_key($values, array_flip($this->visible));
        }

        return array_diff_key($values, array_flip($this->hidden));
    }

    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $attribute)
    {
        return $this->attributes[$attribute] ?? $this->relations[$attribute] ?? null;
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set(string $attribute, $value)
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]) or isset($this->relations[$key]);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);

        unset($this->relations[$key]);
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $query = $this->newQuery();

        return call_user_func_array(array($query, $method), $parameters);
    }

    /**
     * Undocumented function
     *
     * @param string $method_name
     * @param array $arguments
     * @return void
     */
    static public function __callStatic(string $method, array $parameters)
    {
        $instance = new static;

        return call_user_func_array(array($instance, $method), $parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
