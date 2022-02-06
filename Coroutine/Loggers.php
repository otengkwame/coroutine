<?php

declare(strict_types=1);

use Async\Log;
use Async\Logger;
use Async\AsyncLoggerInterface;
use Psr\Log\LoggerInterface;

if (!\function_exists('logger_instance')) {
  /**
   * Returns an logger instance by `name`.
   */
  function logger_instance($name = null)
  {
    if ($name instanceof LoggerInterface) {
      return $name;
    }

    return Log::getLog($name);
  }

  /**
   * Create, and return an logger instance by `name`.
   */
  function logger_create($name = null): AsyncLoggerInterface
  {
    if (!empty($name)) {
      Log::setLog($name, Logger::getLogger($name));
    } else {
      Log::setLog(null, Logger::getLogger($name));
    }

    return log::getLog($name);
  }

  /**
   * Close, and clears out an logger instance by `name`.
   *
   * @param string $name - logger name
   * @param bool $clearLogs - should `arrayWriter` logs be cleared?
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_close($name = null, $clearLogs = true)
  {
    $records = [];

    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      $records = yield $logger->close($clearLogs);

    Log::clearLog($name);

    return $records;
  }

  /**
   * Wait for logs to commit and remove finished logs from logging tasks list
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_commit($name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface) {
      yield $logger->commit();
    }
  }

  /**
   * Shutdown Logger.
   * Commit, close and clear out `All` `logger` instances.
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_shutdown()
  {
    $loggerTags = Log::allLogs();
    if (!empty($loggerTags)) {
      $names = \array_keys((array) $loggerTags);
      foreach ($names as $name) {
        yield \logger_close($name);
      }
    }

    if (Log::hasLog()) {
      yield \logger_close();
    }

    yield Log::resetLogs();
  }

  /**
   * Logs a message. Will pause current task, continue other tasks,
   * resume current task when the `logger` operation completes.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @param string $level
   * @param string $message
   * @param array $context
   * @param string $name
   * @return void
   */
  function logger($level, $message, array $context, ...$name)
  {
    $logger = \logger_instance(\array_shift($name));
    if ($logger instanceof LoggerInterface)
      return $logger->log($level, $message, $context);
  }

  /**
   * Returns the array of `arrayWriter()` Logs.
   */
  function logger_arrayLogs($name = null): array
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->getLogs();

    return [];
  }

  /**
   * Will wait until any pending logs are committed, and printout the `arrayWriter()` Logs.
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_printLogs($name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface) {
      yield $logger->commit();
      foreach ($logger->getLogs() as $output) {
        print $output . \EOL;
      }
    }
  }

  /**
   * Creates/uses an custom backend `writer`
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_writer(
    callable $writer,
    $levels = Logger::ALL,
    int $interval = 1,
    callable $formatter = null,
    $name = null
  ) {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->setWriter($writer, $levels, $interval, $formatter);
  }

  /**
   * Creates/uses O.S. syslog as backend `writer`
   */
  function logger_syslog(
    $logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
    $facility = \LOG_USER,
    $levels = Logger::ALL,
    callable $formatter = null,
    $name = null
  ) {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->syslogWriter($logOpts, $facility, $levels, $formatter);
  }

  /**
   * Creates/uses O.S. error_log as backend `writer`
   */
  function logger_errorlog($type = 0, $levels = Logger::ALL, callable $formatter = null, $name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->errorLogWriter($type, $levels, $formatter);
  }

  /**
   * Creates/uses an memory array as backend `writer`
   */
  function logger_array(
    $levels = Logger::ALL,
    int $interval = 1,
    callable $formatter = null,
    $name = null
  ) {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->arrayWriter($levels, $interval, $formatter);
  }

  /**
   * Creates/uses an file/stream as backend `writer`
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_stream(
    $stream = 'php://stdout',
    $levels = Logger::ALL,
    int $interval = 1,
    callable $formatter = null,
    $name = null
  ) {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->streamWriter($stream, $levels, $interval, $formatter);
  }

  /**
   * Creates/sends an email as backend `writer`
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_mail(
    string $to = '',
    $subject = '',
    array $headers = [],
    $levels = Logger::ALL,
    int $interval = 1,
    callable $formatter = null,
    $name = null
  ) {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->mailWriter($to, $subject, $headers, $levels, $interval, $formatter);
  }

  /**
   * Logs an `EMERGENCY` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_emergency(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->emergency($message, $context);
  }

  /**
   * Logs an `ALERT` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_alert(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->alert($message, $context);
  }

  /**
   * Logs an `CRITICAL` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_critical(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->critical($message, $context);
  }

  /**
   * Logs an `ERROR` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_error(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->error($message, $context);
  }

  /**
   * Logs an `WARNING` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_warning(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->warning($message, $context);
  }

  /**
   * Logs an `NOTICE` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_notice(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->notice($message, $context);
  }

  /**
   * Logs an `INFO` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_info(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->info($message, $context);
  }

  /**
   * Logs an `DEBUG` message.
   * Will not pause current task.
   * Will create new background task to complete the `logger` operation.
   *
   * - This function needs to be prefixed with `yield`
   */
  function log_debug(string $message = '', $context = [], $name = null)
  {
    if (Logger::isName($context, $name)) {
      $name = $context;
      $context = [];
    }

    $logger = \logger_instance($name);
    if ($logger instanceof LoggerInterface)
      return $logger->debug($message, $context);
  }

  /**
   * Add custom context data to the message to be logged.
   * Make an context processor on the `{`$key`}` placeholder.
   */
  function logger_processor($key, callable $processor, $name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addProcessor($key, $processor);
  }

  /**
   * Adds Unique Id to the message to be logged.
   * An concrete context processor on `{unique_id}` placeholder.
   */
  function logger_uniqueId($prefix = '', $name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addUniqueId($prefix);
  }

  /**
   * Adds PHP's process ID to the message to be logged.
   * An concrete context processor on `{pid}` placeholder.
   */
  function logger_pid($name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addPid();
  }

  /**
   * Adds timestamp to the message to be logged.
   * An concrete context processor on `{timestamp}` placeholder.
   */
  function logger_timestamp($micro = false, $name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addTimestamp($micro);
  }

  /**
   * Adds memory usage to the message to be logged.
   * An concrete context processor on `{memory_usage}` placeholder.
   *
   * - This function needs to be prefixed with `yield`
   */
  function logger_memoryUsage($format = null, $real = false, $peak = false, $name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addMemoryUsage($format, $real, $peak);
  }

  /**
   * Adds type of PHP interface to the message to be logged.
   * An concrete context processor on `{php_sapi}` placeholder.
   */
  function logger_phpSapi($name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addPhpSapi();
  }

  /**
   * Adds PHP version to the message to be logged.
   * An concrete context processor on `{php_version}` placeholder.
   */
  function logger_phpVersion($name = null)
  {
    $logger = \logger_instance($name);
    if ($logger instanceof AsyncLoggerInterface)
      return $logger->addPhpVersion();
  }
}
