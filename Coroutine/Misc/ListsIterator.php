<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;

/**
 * `ListsIterator` a `simple` element **array** of `items` that's _ordered_, _changeable_, and _allow_ duplicates.
 */
interface ListsIterator extends \IteratorAggregate, \ArrayAccess, \Countable
{
  /**
   * Returns a _shadow_ copy `simple` __array__ of `Lists` elements.
   *
   * @return array
   */
  public function __invoke(): array;

  /**
   * Return the number of element `items` in `Lists`.
   *
   * @return integer
   */
  public function len(): int;

  /**
   * Test `value` for membership in `Lists`.
   *
   * @param mixed $value
   * @return bool
   */
  public function in($value): bool;

  /**
   * Test `value` for not a membership in `Lists`.
   *
   * @param mixed $value
   * @return bool
   */
  public function not_in($value): bool;

  /**
   * Delete the `Lists` instance completely.
   *
   * @throws KeyError - if __accessing__ a deleted `Lists` instance.
   * @return void
   */
  public function del(): void;

  /**
   * Finds the first occurrence of the specified `value`
   *
   * @param mixed $value
   * @return integer
   * @throws KeyError - if the `value` is not found.
   */
  public function index($value): int;

  /**
   * Returns the number of times a specified `value` appears in the `Lists`.
   *
   * @param mixed $value The item to search for
   * @return integer
   */
  public function counts($value): int;

  /**
   * Returns a _shadow_ copy `simple` __array__ of `Lists` elements.
   *
   * @return array
   */
  public function copy(): array;

  /**
   * Remove all element `items` from `Lists`.
   *
   * @return void
   */
  public function clear(): void;

  /**
   * Removes the first occurrence of the element with the specified `value`.
   *
   * @param mixed $value
   * @throws KeyError if `value` not contained in `Lists`.
   * @return void
   */
  public function remove($value): void;

  /**
   * Remove and `return` a element at the specified `index` position. If `-1`, returns the last item.
   *
   * @param int $index Default `-1`
   * @return mixed
   */
  public function pop(int $index = -1);

  /**
   * Appends `item` to the end of `Lists` sequence.
   *
   * @param mixed $item An element of any type (string, number, object etc.).
   * @return void
   */
  public function append(...$item): void;

  /**
   * Adds the specified `list` elements (or any `iterable`) to the end of the `Lists`.
   *
   * @param iterable $list Any `Iterator` (lists, set, tuple, etc.)
   * @return void
   */
  public function extend(iterable $list): void;

  /**
   * Inserts `value` into `Lists` at the `index`.
   *
   * @param int $index A number specifying in which position to insert the `value`
   * @param mixed $value An element of any type
   * @return void
   */
  public function insert(int $index, $value): void;

  /**
   * Reverse the items in `Lists` in place.
   *
   * @return void
   */
  public function reverse(): void;

  /**
   * Sorts `Lists`, _ascending_ by default.
   *
   * @param boolean $reverse if `true` will sort `Lists` _descending_.
   * @param callable|null $key A function to specify the sorting criteria(s).
   * @return void
   */
  public function sort(?bool $reverse = false, callable $key = null): void;
}
