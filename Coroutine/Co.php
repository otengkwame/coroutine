<?php

declare(strict_types=1);

namespace Async;

/**
 * Coroutine **global** variables and the _running_ instance `object`.
 */
class Co
{
  protected static $defined;
  protected static $timer;
  protected static $instance;

  public static function setLoop(CoroutineInterface $loop): void
  {
    self::$instance = $loop;
  }

  public static function getLoop(): ?CoroutineInterface
  {
    return self::$instance;
  }

  public static function set(?string $key, $value): void
  {
    if (isset($key))
      self::$defined[$key] = $value;
  }

  public static function get(string $key)
  {
    return self::$defined[$key];
  }

  public static function has(string $tag): bool
  {
    return isset(self::$defined[$tag]);
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

  public static function clear(): void
  {
    self::$instance = null;
    self::$timer = null;
    self::$defined = null;
  }
}
