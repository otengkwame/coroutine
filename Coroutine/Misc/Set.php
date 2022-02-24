<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\Co;
use Async\KeyError;
use Async\Misc\SetIterator;

/**
 * An **array** class that mimics Python's **set()** class, where as, `Set` element **items** are _unordered_,
 * _unchangeable_, and _do not_ allow duplicate values.
 *
 * - Invoking a `$Set();` instance WILL **return** a _shadow_ copy **array** of `Set` elements.
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
  protected $array = [];

  protected $internal = false;

  /**
   * @param \Traversable|array $items
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
      } else {
        $elements = $items;
      }

      $elements = \array_unique($elements, \SORT_REGULAR);

      if (!Co::getSetMode())
        \shuffle($elements);
    }

    return $elements;
  }

  public function __destruct()
  {
    unset($this->array);
  }

  /**
   * The initial **array** of elements.
   *
   * @param \Traversable|array $elements
   */
  public function __construct(...$elements)
  {
    $this->array = $this->elements(...$elements);
  }

  public function __invoke(): array
  {
    return $this->copy();
  }

  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->array, 1);
  }

  public function count(): int
  {
    return $this->len();
  }

  public function len(): int
  {
    return \count($this->array);
  }

  public function in($item): bool
  {
    return \in_array($item, $this->array, true);
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
    return (bool)!\array_diff($elements, $this->array);
  }

  public function copy(): array
  {
    return \array_values($this->array);
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

      $current = \array_merge($this->array, $current);
    }

    return $this->internal ? $current : \array_values($current);
  }

  public function update(...$items): self
  {
    $this->internal = true;
    $elements = $this->union(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->array = $elements;

    return $this;
  }

  public function intersection(...$items): array
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0)
      $elements = \array_intersect($this->array, $elements);

    return $this->internal ? $elements : \array_values($elements);
  }

  public function intersection_update(...$items): self
  {
    $this->internal = true;
    $elements = $this->intersection(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->array = $elements;

    return $this;
  }

  public function difference(...$items): array
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0)
      $elements = \array_diff($this->array, $elements);

    return $this->internal ? $elements : \array_values($elements);
  }

  public function difference_update(...$items): self
  {
    $this->internal = true;
    $elements = $this->difference(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->array = $elements;

    return $this;
  }

  public function symmetric_difference(...$items): array
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0) {
      $elements1 = \array_diff($this->array, $elements);
      $elements2 = \array_diff($elements, $this->array);
      $elements = \array_merge($elements1, $elements2);
    }

    return $this->internal ? $elements : \array_values($elements);
  }

  public function symmetric_difference_update(...$items): self
  {
    $this->internal = true;
    $elements = $this->symmetric_difference(...$items);
    $this->internal = false;
    if (\count($elements) > 0)
      $this->array = $elements;

    return $this;
  }

  public function add($item): void
  {
    if ($this->not_in($item))
      $this->array[] = $item;
  }

  public function remove($item): void
  {
    if ($this->not_in($item))
      throw new KeyError('The element is not in the Set!');

    $index = \array_search($item, $this->array, true);
    unset($this->array[$index]);
  }

  public function discard($item): void
  {
    $index = \array_search($item, $this->array, true);
    if ($index)
      unset($this->array[$index]);
  }

  public function pop()
  {
    $item = \array_pop($this->array);
    if (empty($item))
      throw new KeyError('The element Set is empty!');

    return $item;
  }

  public function clear(): void
  {
    $this->array = [];
  }
}
