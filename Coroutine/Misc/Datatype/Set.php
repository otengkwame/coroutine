<?php

declare(strict_types=1);

namespace Async\Datatype;

use Async\Co;
use Async\KeyError;
use Async\Datatype\SetIterator;

/**
 * An `unique` **array** class that mimics Python's **set()** class, where as, `Set` element **items** are _unordered_,
 * _unchangeable_, and _do not_ allow duplicate values. **Sets** do not support indexing, slicing, or other sequence-like behavior.
 *
 * - _Invoking_ a `$set();` **instance** returns a _shadow_ copy `unique` **array** of `Set` elements.
 *
 * **Unordered**
 * - Unordered means that the items in a set do not have a defined order.
 * - Set items can appear in a different order every time you use them, and cannot be referred to by index or key.
 *
 * **Unchangeable**
 * - Set items are unchangeable, meaning that we cannot change the items after the set has been created.
 * - Once a set is created, you cannot change its items, but you can remove items and add new items.
 *
 * **Duplicates Not Allowed**
 * - Sets cannot have two items with the same value.
 *
 * @see https://docs.python.org/3.10/library/stdtypes.html#set
 */
final class Set implements SetIterator
{
  /**
   * @var array
   */
  protected $unique = [];

  protected $internal = false;

  /**
   * @param \Traversable|mixed $items
   * @return array
   */
  protected function elements(...$items): array
  {
    $elements = [];
    if (\is_iterable($items)) {
      $items = isset($items[0]) && \is_array($items[0]) ? $items[0] : $items;
      if (isset($items[0]) && $items[0] instanceof \Traversable) {
        foreach ($items[0] as $value)
          $elements[] = $value;
      } elseif (\count($items) > 0) {
        \array_push($elements, ...$items);
      }

      $elements = \array_unique($elements, \SORT_REGULAR);

      if (!Co::getSetMode())
        \shuffle($elements);
    }

    return $elements;
  }

  public function __destruct()
  {
    unset($this->unique);
  }

  /**
   * The initial `unique` **array** of elements.
   *
   * @param \Traversable|mixed $elements
   */
  public function __construct(...$elements)
  {
    $this->unique = $this->elements(...$elements);
  }

  public function __invoke(): array
  {
    return $this->copy();
  }

  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->unique, 1);
  }

  public function count(): int
  {
    return $this->len();
  }

  public function len(): int
  {
    return \count($this->unique);
  }

  public function in($item): bool
  {
    return \in_array($item, $this->unique, true);
  }

  public function not_in($item): bool
  {
    return $this->in($item) === false;
  }

  public function isDisjoint(...$items): bool
  {
    return \count($this->intersection(...$items)) == 0;
  }

  public function isSubset(...$items): bool
  {
    $elements = $this->elements(...$items);
    return \count($this->intersection($items)) <= \count($elements);
  }

  public function isSuperset(...$items): bool
  {
    $elements = $this->elements(...$items);
    return (bool)!\array_diff($elements, $this->unique);
  }

  public function copy(): array
  {
    return \array_values($this->unique);
  }

  public function union(...$items): array
  {
    $current = [];
    $elements = $this->elements(...$items);
    if (\count($elements) > 0) {
      foreach ($elements as $value) {
        if ($this->not_in($value))
          $current[] = $value;
      }

      $current = \array_merge($this->unique, $current);
    }

    return $this->internal ? $current : \array_values($current);
  }

  public function update(...$items): SetIterator
  {
    $this->internal = true;
    $elements = $this->union(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->unique = $elements;

    return $this;
  }

  public function intersection(...$items): array
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0)
      $elements = \array_intersect($this->unique, $elements);

    return $this->internal ? $elements : \array_values($elements);
  }

  public function intersection_update(...$items): SetIterator
  {
    $this->internal = true;
    $elements = $this->intersection(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->unique = $elements;

    return $this;
  }

  public function difference(...$items): array
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0)
      $elements = \array_diff($this->unique, $elements);

    return $this->internal ? $elements : \array_values($elements);
  }

  public function difference_update(...$items): SetIterator
  {
    $this->internal = true;
    $elements = $this->difference(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->unique = $elements;

    return $this;
  }

  public function symmetric_difference(...$items): array
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0) {
      $elements1 = \array_diff($this->unique, $elements);
      $elements2 = \array_diff($elements, $this->unique);
      $elements = \array_merge($elements1, $elements2);
    }

    return $this->internal ? $elements : \array_values($elements);
  }

  public function symmetric_difference_update(...$items): SetIterator
  {
    $this->internal = true;
    $elements = $this->symmetric_difference(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->unique = $elements;

    return $this;
  }

  public function add($item): void
  {
    if ($this->not_in($item))
      $this->unique[] = $item;
  }

  public function remove($item): void
  {
    if ($this->not_in($item))
      throw new KeyError('The element not in `Set`!');

    $this->discard($item);
  }

  public function discard($item): void
  {
    $index = \array_search($item, $this->unique, true);
    if ($index)
      unset($this->unique[$index]);
  }

  public function pop()
  {
    $item = \array_pop($this->unique);
    if (empty($item))
      throw new KeyError('The element `Set` empty!');

    return $item;
  }

  public function clear(): void
  {
    $this->unique = [];
  }
}
