<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\KeyError;
use Async\Misc\DictIterator;

/**
 * An **associative** _array_ class that mimics Python's **Dict()** dictionary class, where as, `Dict` element **items**
 * are _ordered_, _changeable_, and _do not_ allow duplicates.
 *
 * - _Invoking_ a `$Dict();` instance will **return** a _shadow_ copy **associative** _array_ of `Dict` elements.
 * - _Adding/Updating_ a `key` by direct property `$Dict->key = $value;` same as `$Dict["key"] = $value;`
 * - _Returning_ a `$value` for a `key` by direct property `$Dict->key;` same as `$Dict["key"];`
 *
 * **Ordered**
 * - Dictionaries are ordered, means that the items have a defined order, and that order will not change.
 *
 * **Changeable**
 * - Dictionaries are changeable, meaning that we can change, add or remove items after the dictionary
 * has been created.
 *
 * **Duplicates Not Allowed**
 * - Dictionaries cannot have two items with the same key.
 *
 * @see https://docs.python.org/3.10/library/stdtypes.html#mapping-types-dict
 */
final class Dict implements DictIterator
{
  /**
   * @var array[]
   */
  protected $assoc = [];

  protected $internal = false;

  /**
   * @param \Iterator|array $items
   * @return array
   */
  protected function elements(iterable ...$items): array
  {
    $elements = [];
    if (isset($items[0]) && \is_array($items[0])) {
      foreach ($items as $key => $value) {
        $key = \array_key_first($value);
        $elements[$key] = $value[$key];
      }
    } elseif (isset($items[0]) && $items[0] instanceof \Traversable) {
      foreach ($items[0] as $key => $value) {
        $elements[$key] = $value;
      }
    }

    return $elements;
  }

  public function __destruct()
  {
    unset($this->assoc);
  }

  /**
   * The initial **associative** _array_ of elements.
   *
   * @param \Iterator|array $elements - `Iterators`, `[key => value]` *pairs*, or use the `kv(key, value)` *function* instead.
   */
  public function __construct(iterable ...$elements)
  {
    $this->assoc = $this->elements(...$elements);
  }

  public function __invoke(): array
  {
    return $this->copy();
  }

  public function __set($key, $value)
  {
    try {
      $this->assoc[$key] = $value;
    } catch (\Throwable $th) {
      throw new KeyError('`' . $key . '` key is invalid!');
    }
  }

  public function __get($key)
  {
    if ($this->in($key)) {
      return $this->assoc[$key];
    }

    throw new KeyError('The `' . $key . '` key is not in dictionary!');
  }

  public function get($key, $default = None)
  {
    if ($this->in($key)) {
      $default = $this->assoc[$key];
    }

    return $default;
  }

  public function setDefault($key, $default = None)
  {
    if ($this->in($key))
      $default = $this->assoc[$key];
    else
      $this->assoc[$key] = $default;

    return $default;
  }

  public function iter(): \Iterator
  {
    return $this->getIterator();
  }

  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->assoc, 1);
  }

  public function count(): int
  {
    return $this->len();
  }

  public function len(): int
  {
    return \count($this->assoc);
  }

  public function in($key): bool
  {
    return \array_key_exists($key, $this->assoc);
  }

  public function not_in($key): bool
  {
    return $this->in($key) === false;
  }

  public function list(): array
  {
    return \array_keys($this->assoc);
  }

  public static function fromKeys(iterable $iterable, $value = None): self
  {
    $elements = [];
    if ($iterable instanceof DictIterator) {
      foreach ($iterable->list() as $key)
        $elements[][$key] = $value;
    } elseif (isset($iterable[0]) && \is_array($iterable[0])) {
      foreach ($iterable as $item) {
        $key = \array_key_first($item);
        $elements[][$key] = $value;
      }
    } elseif (\is_array($iterable) && isset($iterable[0]) && \is_string($iterable[0])) {
      foreach ($iterable as $key)
        $elements[][$key] = $value;
    } elseif ($iterable instanceof \Traversable) {
      foreach ($iterable as $key => $nan)
        $elements[][$key] = $value;
    } else {
      foreach (\array_keys($iterable) as $key)
        $elements[][$key] = $value;
    }

    return new self(...$elements);
  }

  public function copy(): array
  {
    return $this->assoc;
  }

  public function update(...$items): self
  {
    $elements = $this->elements(...$items);
    if (\count($elements) > 0)
      $this->assoc = \array_merge($this->assoc, $elements);

    return $this;
  }

  public function del($key): void
  {
    if ($key instanceof $this) {
      unset($this->assoc);
    } elseif ($key instanceof DictIterator) {
      unset($key);
    } else {
      if ($this->not_in($key))
        throw new KeyError("The {$key} key is not in the dictionary!");

      unset($this->assoc[$key]);
    }
  }

  public function clear(): void
  {
    $this->assoc = [];
  }

  public function pop($key, $default = false)
  {
    if ($this->not_in($key) && $default === false)
      throw new KeyError('The `' . $key . '`key is not in the dictionary!');

    if ($this->in($key)) {
      $default = $this->assoc[$key];
      unset($this->assoc[$key]);
    }

    return $default;
  }

  public function popItem()
  {
    $item = \array_pop($this->assoc);
    if (empty($item))
      throw new KeyError('The dictionary is empty!');

    return $item;
  }
}
