<?php

namespace Core\Database\ORM;

use Closure;
use Countable;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Core\Database\ORM\ArrayableInterface;

class Collection implements ArrayAccess, ArrayableInterface, Countable, IteratorAggregate, JsonableInterface
{
    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Create a new collection.
     *
     * @param  mixed  $items
     * @return void
     */
    public function __construct($items = [])
    {
        $this->items = $items;
    }

    public static function make($items)
    {
        if (is_null($items)) return new static;

        if ($items instanceof Collection) return $items;

        return new static(is_array($items) ? $items : array($items));
    }

    public function has($key)
    {
        return array_key_exists($key, $this->items);
    }

    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return $default;
    }

    public function all()
    {
        return $this->items;
    }

    public function put($key, $value)
    {
        $this->items[$key] = $value;
    }

    public function first()
    {
        return count($this->items) > 0 ? reset($this->items) : null;
    }

    public function last()
    {
        return count($this->items) > 0 ? end($this->items) : null;
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    public function count()
    {
        return count($this->items);
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->map(function ($value) {
            return $value instanceof ArrayableInterface ? $value->toArray() : $value;
        })->all();
    }

    /**
     * Get an iterator for the items.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    public function reduce($callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function each(Closure $callback)
    {
        array_map($callback, $this->items);

        return $this;
    }

    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    public function filter(Closure $callback)
	{
		return new static(array_filter($this->items, $callback));
	}

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof ArrayableInterface) {
                return $value->toArray();
            }
            return $value;
        }, $this->all());
    }
}
