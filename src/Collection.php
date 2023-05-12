<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

//
/**
 * This class was extracted from illuminate.
 * This will suppress all the PMD warnings in this class.
 *
 * @SuppressWarnings(PHPMD)
 */
final class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @var array<mixed>
     */
    private array $items;

    /**
     * @param mixed $items
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return $default;
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @return $this
     */
    public function transform(callable $callback)
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * @return static
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new self(array_combine($keys, $items));
    }

    /**
     * Run a filter over each of the items.
     *
     * @return static
     */
    public function filter(callable $callback = null)
    {
        if (null !== $callback) {
            return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new self(array_filter($this->items));
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param callable|mixed $callback
     *
     * @return static
     */
    public function reject($callback = true)
    {
        $useAsCallable = $this->useAsCallable($callback);

        return $this->filter(fn ($value, $key) => $useAsCallable ? !$callback($value, $key) : $value != $callback);
    }

    /**
     * @param string|int|array $keys
     *
     * @return $this
     */
    public function forget($keys): self
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this as $key => $item) {
            if (false === $callback($item, $key)) {
                break;
            }
        }

        return $this;
    }

    /**
     * @param string|callable|null $key
     *
     * @return static
     */
    public function unique($key = null, bool $strict = false)
    {
        if (is_null($key) && false === $strict) {
            return new self(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Get the first item from the collection passing the given truth test.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($this->items)) {
                return $default instanceof \Closure ? $default() : $default;
            }

            foreach ($this->items as $item) {
                return $item;
            }
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    /**
     * Get the first item by the given key value pair.
     *
     * @param string $key
     * @param mixed  $operator
     * @param mixed  $value
     *
     * @return mixed
     */
    public function firstWhere($key, $operator = null, $value = null)
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @param mixed $items
     */
    private function getArrayableItems($items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if ($items instanceof self) {
            return $items->all();
        }

        if ($items instanceof \JsonSerializable) {
            return (array) $items->jsonSerialize();
        }

        if ($items instanceof \Traversable) {
            return iterator_to_array($items);
        }

        if ($items instanceof \UnitEnum) {
            return [$items];
        }

        return (array) $items;
    }

    /**
     * @param mixed $value
     */
    private function useAsCallable($value): bool
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * @param callable|string|null $value
     *
     * @return callable
     */
    private function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return fn ($item) => self::getDeepData($item, $value);
    }

    /**
     * @param mixed                 $target
     * @param string|array|int|null $key
     * @param mixed                 $default
     *
     * @return mixed
     */
    private static function getDeepData($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ('*' === $segment) {
                if ($target instanceof self) {
                    $target = $target->all();
                } elseif (!is_array($target)) {
                    return $default instanceof \Closure ? $default() : $default;
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = self::getDeepData($item, $key);
                }

                return in_array('*', $key, true) ? self::arrayCollapse($result) : $result;
            }

            if (self::accessible($target) && self::existsInArray($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default instanceof \Closure ? $default() : $default;
            }
        }

        return $target;
    }

    public static function arrayCollapse(iterable $array): array
    {
        $results = [];

        foreach ($array as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    /**
     * @param mixed $value
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof \ArrayAccess;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param \ArrayAccess|array $array
     * @param string|int         $key
     */
    private static function existsInArray($array, $key): bool
    {
        if ($array instanceof \ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * @param mixed $value
     */
    private function operatorForWhere(string $key, ?string $operator = null, $value = null): \Closure
    {
        if (1 === func_num_args()) {
            $value = true;

            $operator = '=';
        }

        if (2 === func_num_args()) {
            $value = $operator;

            $operator = '=';
        }

        return static function ($item) use ($key, $operator, $value) {
            $retrieved = self::getDeepData($item, $key);

            $strings = array_filter([$retrieved, $value], fn ($value) => is_string($value) || (is_object($value) && method_exists($value, '__toString')));

            if (count($strings) < 2 && 1 === count(array_filter([$retrieved, $value], 'is_object'))) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':  return $retrieved == $value;
                case '!=':
                case '<>':  return $retrieved != $value;
                case '<':   return $retrieved < $value;
                case '>':   return $retrieved > $value;
                case '<=':  return $retrieved <= $value;
                case '>=':  return $retrieved >= $value;
                case '===': return $retrieved === $value;
                case '!==': return $retrieved !== $value;
            }
        };
    }
}
