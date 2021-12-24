<?php

declare(strict_types=1);

namespace Async;

use Fiber;
use Async\Spawn\Globals;
use Async\FiberInterface;

/**
 * The needed **global** variables, storing `async` functions, `Fiber` **tag** instances, timing, and `ext-parallel` behavior.
 * - Also holding the _running_ **Coroutine** instance.
 *
 * @internal
 */
final class Co
{
  /**
   * @var array[]
   */
  protected static $parallel;

  /**
   * @var array<float|null|bool]
   */
  protected static $timer;

  /**
   * @var CoroutineInterface|null
   */
  protected static $instance;

  /**
   * @var Fiber[]|FiberInterface[]
   */
  protected static $fibers;

  /**
   * @var \Closure[Generator]
   */
  protected static $functions;

  public static function setLoop(CoroutineInterface $loop): void
  {
    self::$instance = $loop;
  }

  public static function getLoop(): ?CoroutineInterface
  {
    return self::$instance;
  }

  public static function addFiber(string $tag, $fiber): void
  {
    if (self::isFiber($tag))
      \panic("Fiber named: '{$tag}' already exists!");

    if ($fiber instanceof FiberInterface || $fiber instanceof Fiber) {
      self::$fibers[$tag] = $fiber;
    } else {
      // @codeCoverageIgnoreStart
      \panic('Not an instance of `FiberInterface` or `PHP built-in Fibers`!');
      // @codeCoverageIgnoreEnd
    }
  }

  public static function isFiber(string $tag): bool
  {
    return isset(self::$fibers[$tag]);
  }

  public static function getFiber(string $tag)
  {
    return self::$fibers[$tag];
  }

  public static function clearFiber(string $tag): void
  {
    self::$fibers[$tag] = null;
  }

  public static function addFunction(string $label, \Closure $coroutine): void
  {
    if (self::isFunction($label))
      \panic("Function named: '{$label}' already exists!");

    self::$functions[$label] = $coroutine;
  }

  public static function isFunction(string $label): bool
  {
    return isset(self::$functions[$label]);
  }

  public static function getFunction(string $label): \Closure
  {
    return self::$functions[$label];
  }

  public static function resetAsync(): void
  {
    self::$functions = null;
  }

  public static function set(?string $key, $value): void
  {
    if (isset($key))
      self::$parallel[$key] = $value;
  }

  public static function get(string $key)
  {
    return self::$parallel[$key];
  }

  public static function has(string $tag): bool
  {
    return isset(self::$parallel[$tag]);
  }

  public static function hasTiming(string $tag): bool
  {
    return isset(self::$timer[$tag]);
  }

  public static function setTiming(string $tag, $value): void
  {
    self::$timer[$tag] = $value;
  }

  public static function clearTiming(string $tag): void
  {
    self::$timer[$tag] = null;
  }

  public static function getTiming(string $tag): float
  {
    return self::$timer[$tag];
  }

  public static function reset(): void
  {
    self::$instance = null;
    self::$timer = null;
    self::$parallel = null;
    self::$fibers = null;

    Globals::reset();
  }
}
