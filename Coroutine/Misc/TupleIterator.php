<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;

/**
 * `TupleIterator` a `constant` element **array** of `items` that's _ordered_, _unchangeable_, and _allow_ duplicates.
 */
interface TupleIterator extends \IteratorAggregate, \Countable
{
  /**
   * Returns a _shadow_ copy `constant` _array_ of `Tuple` elements.
   *
   * @return array
   */
  public function __invoke(): array;

  /**
   * Return the number of element `items` in `Tuple`.
   *
   * @return integer
   */
  public function len(): int;

  /**
   * Test `value` for membership in `Tuple`.
   *
   * @param mixed $value
   * @return bool
   */
  public function in($value): bool;

  /**
   * Test `value` for not a membership in `Tuple`.
   *
   * @param mixed $value
   * @return bool
   */
  public function not_in($value): bool;

  /**
   * Delete the `Tuple` instance completely.
   *
   * @throws KeyError - if __accessing__ a deleted `Tuple` instance.
   * @return void
   */
  public function del(): void;

  /**
   * Finds the first occurrence of the specified `value`
   *
   * @param mixed $value
   * @return int
   * @throws KeyError - if the `value` is not found.
   */
  public function index($value): int;

  /**
   * Returns the number of times a specified `value` appears in the `Tuple`.
   *
   * @param mixed $value The item to search for
   * @return integer
   */
  public function counts($value): int;
}
