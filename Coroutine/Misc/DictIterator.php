<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;

/**
 * `DictIterator` a **associative** element _array_ of `items` that's _ordered_, _changeable_, and _do not_ allow duplicates.
 */
interface DictIterator extends \IteratorAggregate, \ArrayAccess, \Countable
{
  /**
   * Returns a _shadow_ copy **associative** `array` of `Dict` elements.
   *
   * @return array
   */
  public function __invoke(): array;

  /**
   * Return an `iterator` of the dictionary. This is a shortcut for `getIterator()`.
   *
   * @return \Iterator
   */
  public function iter(): \Iterator;

  /**
   * Return the number of items in `Dict` dictionary.
   *
   * @return integer
   */
  public function len(): int;

  /**
   * Return `true` if _Dict_ has **key**, else `false`.
   *
   * @param string|int $key
   * @return bool
   */
  public function in($key): bool;

  /**
   * Return `true` if **key** _not in_ `Dict`.
   *
   * @param string|int $key
   * @return bool
   */
  public function not_in($key): bool;

  /**
   * Return a `array` of all the keys used in `Dict`.
   *
   * @return array
   */
  public function list(): array;

  /**
   * Create a `new` dictionary with _keys_ from **iterable** and _values_ set to `value`.
   * - All of the values refer to just a single instance.
   *
   * @param \Iterator|array $iterable
   * @param mixed $value Defaults to `None`.
   * @return DictIterator
   */
  public static function fromKeys(iterable $iterable, $value = None): self;

  /**
   * Returns a _shadow_ copy **associative** `array` of `Dict` elements.
   *
   * @return array
   */
  public function copy(): array;

  /**
   * Return the `value` for _key_ if `key` is in the dictionary, else `default`.
   *
   * @param string|int $key
   * @param mixed $default - If _default_ is not given, it defaults to `None`, so that this method never _throws_ a `KeyError`.
   * @return mixed
   */
  public function get($key, $default = None);

  /**
   * If `key` is in the dictionary, return its `value`. If _not_, insert `key` with a `value` of `default` and return _default_.
   *
   * @param string|int $key
   * @param mixed $default Defaults to `None`
   * @return mixed
   */
  public function setDefault($key, $default = None);

  /**
   * Update the dictionary with the _key/value_ pairs from `items`, overwriting existing keys.
   *
   * @param \Traversable|array $items
   * @return self
   */
  public function update(...$items): self;

  /**
   * Remove `key` from the `Dict`, or delete the `Dict` instance completely.
   *
   * @param string|int|DictIterator $key - if `DictIterator` will delete that `Dict` instance.
   * @throws KeyError - if `key` is not in `Dict`, or if __accessing__ a deleted `Dict` instance.
   * @return void
   */
  public function del($key): void;

  /**
   * If `key` is in the dictionary, remove it and return its `value`, else return `default`.
   *
   * @param string|int $key
   * @param mixed $default - If _default_ is not given and `key` is not in the dictionary, a `KeyError` is _throw_.
   * @return mixed
   * @throws KeyError if `key` is not in the dictionary, and no `default`.
   */
  public function pop($key, $default = false);

  /**
   * Remove and return a `(key, value)` _pair_ from the dictionary. Pairs are returned in `LIFO` order.
   *
   * @throws KeyError if the `Dict` is empty.
   * @return mixed
   */
  public function popItem();

  /**
   * Remove all element `items` from `Dict`.
   *
   * @return void
   */
  public function clear(): void;
}
