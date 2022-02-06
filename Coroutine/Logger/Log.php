<?php

declare(strict_types=1);

namespace Async;

use Psr\Log\LoggerInterface;

final class Log
{
  /**
   * @var LoggerInterface[]
   */
  protected static $logs = [];

  /**
   * @var LoggerInterface
   */
  protected static $log = null;


  public static function setLog($key = null, LoggerInterface $value): void
  {
    if ($key === null)
      self::$log = $value;
    else
      self::$logs[$key] = $value;
  }

  public static function clearLog($key = null): void
  {
    if ($key === null) {
      $log = self::$log;
      self::$log = null;
      unset($log);
    } elseif (self::hasLog($key)) {
      $log = self::$logs[$key];
      self::$logs[$key] = null;
      unset($log);
    }
  }

  public static function getLog($key = null): ?LoggerInterface
  {
    if ($key === null) {
      return self::$log;
    } elseif (self::hasLog($key)) {
      return self::$logs[$key];
    }
  }

  public static function hasLog($key = null): bool
  {
    if ($key === null)
      return isset(self::$log);

    return isset(self::$logs[$key]);
  }

  public static function allLogs(): ?array
  {
    return self::$logs;
  }

  public static function resetLogs(): void
  {
    self::$logs = null;
    self::$log = null;
  }
}
