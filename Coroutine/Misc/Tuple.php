<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;
use Async\Misc\TupleIterator;

/**
 * An `constant` **array** class that mimics Python's **tuple()** class, where as, `Tuple` element **items** are _ordered_,
 * _unchangeable_, and _allow_ duplicates. **Tuples** are immutable sequences, typically used to store collections of heterogeneous data.
 *
 * - _Invoking_ a `$Tuple();` instance will **return** a _shadow_ copy `constant` **array** of `Tuple` elements.
 * - _Getting_ a `$value` for a _numbered_ `index` by direct property `$Tuple->index[0];` same as `$Tuple[0];`
 *
 * **Ordered**
 * - When we say that tuples are ordered, it means that the items have a defined order, and that order will not change.
 *
 * **Unchangeable**
 * - Tuples are unchangeable, meaning that we cannot change, add or remove items after the tuple has been created.
 *
 * **Allow Duplicates**
 * - Since tuples are indexed, they can have items with the same value.
 *
 * @see https://docs.python.org/3.10/library/stdtypes.html#tuples
 * @property-read mixed $index
 */
final class Tuple implements TupleIterator
{
  /**
   * @var array
   */
  protected $constant = [];

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
        $elements = (isset($items[0]) && \is_string($items[0]) && \count($items) === 1) ? \str_split($items[0]) : $items;
      }
    }

    return $elements;
  }

  public function __destruct()
  {
    unset($this->constant);
  }

  /**
   * The initial `constant` **array** of elements.
   *
   * @param \Traversable|array $elements
   */
  public function __construct(...$elements)
  {
    $this->constant = $this->elements(...$elements);
  }

  public function __invoke(): array
  {
    return $this->constant;
  }

  public function __get($index)
  {
    if ($index === 'index')
      return $this->constant;

    throw new KeyError('The `' . $index . '` index not in `Tuple`!');
  }

  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->constant, 1);
  }

  public function count(): int
  {
    return $this->len();
  }

  public function counts($value): int
  {
    return \count(\array_keys($this->constant, $value, true));
  }

  public function len(): int
  {
    return \count($this->constant);
  }

  public function in($value): bool
  {
    return \in_array($value, $this->constant, true);
  }

  public function index($value): int
  {
    if ($this->in($value))
      return \array_search($value, $this->constant, true);

    throw new KeyError('Value not found!');
  }

  public function del(): void
  {
    unset($this->constant);
  }
}
