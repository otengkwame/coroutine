<?php

declare(strict_types=1);

namespace Async;

use Psr\Log\LoggerInterface;

interface AsyncLoggerInterface extends LoggerInterface
{
  const NULL      = 0;
  const DEBUG     = 0x01;
  const INFO      = 0x02;
  const NOTICE    = 0x04;
  const WARNING   = 0x08;
  const ERROR     = 0x10;
  const CRITICAL  = 0x20;
  const ALERT     = 0x40;
  const EMERGENCY = 0x80;
  const ALL       = 0xff;

  public static function write($stream, $string);

  public static function getLogger($name): AsyncLoggerInterface;

  public static function isLogger($name): bool;

  /**
   * Wait for all pending logging tasks to commit, then
   * remove finish logger tasks from current logger tasks list.
   */
  public function commit();

  /**
   * Returns the array of `arrayWriter()` Logs.
   * This will return the log messages in order.
   *
   * @return array
   */
  public function getLogs(): array;

  /**
   * Clear the `arrayWriter()` Logs.
   */
  public function resetLogs();

  public function getName(): string;

  public function defaultFormatter(callable $formatter): AsyncLoggerInterface;

  public function disable($levels): AsyncLoggerInterface;

  public function enable($levels): AsyncLoggerInterface;

  /**
   * Setup the writer handler, Set the logging level of the handler.
   */
  public function setWriter(
    callable $writer,
    $levels = self::ALL,
    $interval = 1,
    callable $formatter = null
  );

  public function log($level, $message, array $context = []);

  /**
   * Close and perform any cleanup actions.
   *
   * @param bool $clearLogs - should `arrayWriter` logs be cleared?
   *
   * @return array the logs of `arrayWriter()`
   */
  public function close($clearLogs = true);

  /**
   * Ensure all logging output has been flushed
   *
   * @return \Generator<int, mixed>
   */
  public function flush();

  /**
   * Adds additional context data to the level message
   *
   * @param string $key
   * @param callable $processor
   */
  public function addProcessor($key, callable $processor): AsyncLoggerInterface;

  /**
   * concrete writer
   *
   * @param string|resource id $stream
   * @param int $mask
   * @param int $interval
   * @param callable $formatter
   */
  public function streamWriter($stream = 'php://stdout', $levels = self::ALL, $interval = 1, callable $formatter = null);

  /**
   * concrete writer
   */
  public function syslogWriter(
    $logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
    $facility = \LOG_USER,
    $levels = self::ALL,
    callable $formatter = null
  );

  /**
   * concrete writer
   */
  public function errorLogWriter($type = 0, $levels = self::ALL, callable $formatter = null);

  /**
   * concrete writer
   */
  public function mailWriter(
    $to,
    $subject = null,
    array $headers = [],
    $levels = self::ALL,
    $interval = 1,
    callable $formatter = null
  );

  /**
   * concrete writer
   */
  public function arrayWriter($levels = self::ALL, $interval = 1, callable $formatter = null);

  /**
   * concrete context processor
   */
  public function addUniqueId($prefix = ''): AsyncLoggerInterface;

  /**
   * concrete context processor
   */
  public function addPid(): AsyncLoggerInterface;

  /**
   * concrete context processor
   */
  public function addTimestamp($micro = false): AsyncLoggerInterface;

  /**
   * concrete context processor
   */
  public function addMemoryUsage($format = null, $real = false, $peak = false);

  /**
   * concrete context processor
   */
  public function addPhpSapi(): AsyncLoggerInterface;

  /**
   * concrete context processor
   */
  public function addPhpVersion(): AsyncLoggerInterface;
}
