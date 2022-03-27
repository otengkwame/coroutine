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
   * a task's starting and uniqueId numbers
   *
   * @var array[int]
   */
  protected static $uniqueId;

  /**
   * @var \Closure[Generator]
   */
  protected static $functions;

  /**
   * @var bool
   */
  protected static $setMode = false;

  /**
   * Flag to control `libuv` feature usage.
   *
   * @var bool
   */
  protected static $useUv = false;

  public static function uvState($uv = 'toggle')
  {
    if ($uv === 'toggle')
      self::$useUv = !self::$useUv;
    elseif (\is_bool($uv))
      self::$useUv = $uv;
  }

  /**
   * Status to control general use of `libuv` features.
   *
   * @var bool
   */
  public static function uvNative(): bool
  {
    return self::$useUv;
  }

  public static function setLoop(CoroutineInterface $loop): void
  {
    self::$instance = $loop;
  }

  public static function getLoop(): ?CoroutineInterface
  {
    return self::$instance;
  }

  public static function setUnique(string $tag, int $number): void
  {
    if (!isset(self::$uniqueId[$tag]) || $tag === 'max' || $tag === 'supervisor')
      self::$uniqueId[$tag] = $number;
  }

  /**
   * A task's starting unique Id number.
   *
   * @param string $tag Either:
   * - `supervisor` task unique id, the event loop, or
   * - `parent` unique task id, the `async` task `coroutine_run` executed
   *
   * @return integer|null
   */
  public static function getUnique(string $tag): ?int
  {
    if (isset(self::$uniqueId[$tag]))
      return self::$uniqueId[$tag];

    return null;
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

  public static function setMode(bool $ordered = false): void
  {
    self::$setMode = $ordered;
  }

  public static function getSetMode(): bool
  {
    return self::$setMode;
  }

  public static function reset(): void
  {
    Log::resetLogs();
    Globals::reset();
    self::$instance = null;
    self::$timer = null;
    $parallel = self::has('debugging') && Co::get('debugging') === true;
    self::$parallel = null;
    self::$parallel['debugging'] = $parallel;
    self::$fibers = null;
    self::$uniqueId = null;
    self::$useUv = false;
  }
}
