<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;

/**
 * `SetIterator` a element **array** of `items` that's  _unordered_, _unchangeable_, and _do not_ allow duplicate values.
 */
interface SetIterator extends \IteratorAggregate, \Countable
{
  /**
   * Returns a **shadow** copy of the __array__ in `Set`.
   *
   * @return array
   */
  public function __invoke(): array;

  /**
   * Return the number of element `items` in `Set`.
   *
   * @return integer
   */
  public function len(): int;

  /**
   * Test `item` for membership in `Set`.
   *
   * @param mixed $item
   * @return bool
   */
  public function in($item): bool;

  /**
   * Test `item`  for non-membership in `Set`.
   *
   * @param mixed $item
   * @return bool
   */
  public function not_in($item): bool;

  /**
   * Return `true` if the `Set` has no elements in common with `items`.
   * Sets are disjoint if and only if their intersection is the empty set.
   *
   * @param \Traversable|array $items
   * @return boolean
   */
  public function isDisjoint(...$items): bool;

  /**
   * Test whether every element in the `Set` is in `items`.
   * Tests whether the `Set` is a proper **subset** of `items`.
   *
   * @param \Traversable|array $items
   * @return boolean
   */
  public function isSubset(...$items): bool;

  /**
   * Test whether every element in `items` is in the `Set`.
   * Tests whether the `Set` is a proper **superset** of `items`.
   *
   * @param \Traversable|array $items
   * @return boolean
   */
  public function isSuperset(...$items): bool;

  /**
   * Returns a **shadow** copy of the __array__ in `Set`.
   *
   * @return array
   */
  public function copy(): array;

  /**
   * Return a new `items` __array__ with elements from the `Set` and all `items`.
   *
   * @param \Traversable|array $items
   * @return array
   */
  public function union(...$items): array;

  /**
   * Updates the element `Set`, by _union_ adding `items` from another `Set` (or any other **iterable**).
   *
   * @param \Traversable|array $items
   * @return self
   */
  public function update(...$items): self;

  /**
   * Return a new `items` __array__ with elements common to the `Set` and all `items`.
   *
   * @param \Traversable|array $items
   * @return array
   */
  public function intersection(...$items): array;

  /**
   * Update the `Set`, keeping only elements found in it and all `items`.
   *
   * @param \Traversable|array $items
   * @return self
   */
  public function intersection_update(...$items): self;

  /**
   * Return a new `items` __array__ with elements in the `Set` that are not in the `items`.
   *
   * @param \Traversable|array $items
   * @return array
   */
  public function difference(...$items): array;

  /**
   * Update the `Set`, removing elements found in `items`.
   *
   * @param \Traversable|array $items
   * @return self
   */
  public function difference_update(...$items): self;

  /**
   * Return a new `items` __array__ with elements in either the `Set` or `items` but not both.
   *
   * @param \Traversable|array $items
   * @return array
   */
  public function symmetric_difference(...$items): array;

  /**
   * Update the `Set`, keeping only element `items` found in either `Set`, but not in both.
   *
   * @param \Traversable|array $items
   * @return self
   */
  public function symmetric_difference_update(...$items): self;

  /**
   * Add element `item` to the `Set`.
   *
   * @param mixed $item
   * @return void
   */
  public function add($item): void;

  /**
   * Remove element `item` from the `Set`.
   *
   * @param mixed $item
   * @throws KeyError if `item` is not contained in the `Set`.
   * @return void
   */
  public function remove($item): void;

  /**
   * Remove element `item` from the `Set` if it is present.
   *
   * @param mixed $item
   * @return void
   */
  public function discard($item): void;

  /**
   * Remove and return an element `item` from the `Set`.
   *
   * @throws KeyError if the `Set` is empty.
   * @return mixed
   */
  public function pop();

  /**
   * Remove all element `items` from the `Set`.
   *
   * @return void
   */
  public function clear(): void;
}
