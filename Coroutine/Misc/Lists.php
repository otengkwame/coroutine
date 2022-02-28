<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;
use Async\Misc\DictIterator;
use Async\Misc\SetIterator;
use Async\Misc\TupleIterator;
use Async\Misc\ListsIterator;

/**
 * An `simple` **array** class that mimics Python's **list()** class, where as, `Lists` element **items** are _ordered_,
 * _changeable_, and _allow_ duplicates. **Lists** are mutable sequences, typically used to store collections of homogeneous items.
 *
 * - _Invoking_ a `$Lists();` **instance** will **return** a _shadow_ copy `simple` **array** of `Lists` elements.
 * - _Adding_ or _Updating_ a _numbered_ `index` by direct **instance** `$Lists[number] = $value;`
 * - _Getting_ a `$value` for _numbered_ `index` by direct **instance** `$Lists[number];`
 *
 * **Ordered**
 * - When we say that lists are ordered, it means that the items have a defined order, and that order will not change.
 * - If you add new items to a lists, the new items will be placed at the end of lists.
 * - **Note:** There are some lists methods that will change the order.
 *
 * **Changeable**
 * - The lists is changeable, meaning that we can change, add, and remove items in a lists after it has been created.
 *
 * **Allow Duplicates**
 * - Since lists are indexed, lists can have items with the same value
 *
 * @see https://docs.python.org/3.10/library/stdtypes.html#lists
 */
final class Lists implements ListsIterator
{
  /**
   * @var array
   */
  protected $simple = [];

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
      } elseif (isset($items[0]) && \is_array($items[0])) {
        foreach ($items as $value)
          $elements[] = $value;
      } else {
        \array_push($elements, ...$items);
      }
    }

    return $elements;
  }

  public function __destruct()
  {
    unset($this->simple);
  }

  /**
   * The initial `simple` **array** of elements.
   *
   * @param \Traversable|mixed $elements
   */
  public function __construct(...$elements)
  {
    $this->simple = $this->elements($elements);
  }

  public function __invoke(): array
  {
    return $this->copy();
  }

  public function __get($index)
  {
    throw new KeyError('`' . $index . '` not in `Lists` instance!');
  }

  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->simple, 1);
  }

  public function count(): int
  {
    return $this->len();
  }

  public function counts($value): int
  {
    return \count(\array_keys($this->simple, $value, true));
  }

  public function len(): int
  {
    return \count($this->simple);
  }

  public function in($value): bool
  {
    return \in_array($value, $this->simple, true);
  }

  public function not_in($value): bool
  {
    return $this->in($value) === false;
  }

  public function index($value): int
  {
    if ($this->in($value))
      return \array_search($value, $this->simple, true);

    throw new KeyError('Value not found!');
  }

  public function del(): void
  {
    $this->__destruct();
  }

  public function clear(): void
  {
    $this->simple = [];
  }

  public function copy(): array
  {
    return $this->simple;
  }

  public function remove($value): void
  {
    $index = $this->index($value);
    unset($this->simple[$index]);
  }

  public function pop(int $index = -1)
  {
    if ($index === -1)
      return \array_pop($this->simple);

    if (\is_int($index)) {
      $item = $this->simple[$index];
      unset($this->simple[$index]);
      return $item;
    }
  }

  public function append(...$item): void
  {
    if (\count($item) === 1)
      $this->simple[] = $item[0];
    else
      \array_push($this->simple, ...$item[0]);
  }

  public function extend(iterable $list): void
  {
    $elements = [];
    if ($list instanceof DictIterator) {
      foreach ($list() as $key => $value)
        $elements[] = [$key => $value];
    } elseif (
      $list instanceof SetIterator
      || $list instanceof TupleIterator
      || $list instanceof ListsIterator
    ) {
      foreach ($list() as $value)
        $elements[] = $value;
    } elseif (isset($list[0]) && \is_array($list[0])) {
      foreach ($list as $key => $value)
        $elements[] = [$key => $value];
    } elseif ($list instanceof \Traversable || \is_array($list)) {
      foreach ($list as $value)
        $elements[] = $value;
    }

    \array_push($this->simple, ...$elements);
  }

  public function insert(int $index, $value): void
  {
    $slice = \array_slice($this->simple, $index);
    \array_unshift($slice, $value);
    \array_splice($this->simple, $index, \count($slice), $slice);
  }

  public function reverse(): void
  {
    $this->simple = \array_reverse($this->simple);
  }

  /**
   * Sorts `Lists`, _ascending_ by default.
   *
   * @param boolean $reverse if `true` will sort `Lists` _descending_.
   * @param callable|null $key A function to specify the sorting criteria(s).
   * @return void
   */
  public function sort(?bool $reverse = false, callable $key = null): void
  {
    if (\is_callable($key))
      \usort($this->simple, $key);
    elseif ($reverse)
      \rsort($this->simple, \SORT_REGULAR);
    else
      \sort($this->simple, \SORT_REGULAR);
  }

  public function offsetExists($offset): bool
  {
    return isset($this->simple[$offset]);
  }

  #[\ReturnTypeWillChange]
  public function offsetGet($offset)
  {
    if (isset($this->simple[$offset]))
      return $this->simple[$offset];

    throw new KeyError('`' . $offset . '` not in `Lists!`');
  }

  public function offsetSet($offset, $value): void
  {
    $this->simple[$offset] = $value;
  }

  public function offsetUnset($offset): void
  {
    unset($this->simple[$offset]);
  }
}
