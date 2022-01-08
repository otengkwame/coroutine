<?php

declare(strict_types=1);

namespace Async;

use Async\Co;
use Async\Kernel;
use Async\Coroutine;
use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

class AsyncLogger extends AbstractLogger
{
  /**
   * For tracking logger tasks id
   *
   * @var array
   */
  protected $loggerTaskId = [];

  /**
   * Creates an async task for a message if logging is enabled for level.
   */
  protected function _make_log_task($level, $message, array $context = array())
  {
    $loggerId = yield \away($this->log($level, $message, $context), 'true');
    Co::getLoop()->getTask($loggerId)->taskType('networked');
    $this->loggerTaskId[$loggerId] = $loggerId;
  }

  /**
   * Wait for all pending logging tasks to commit, then
   * remove finish logger tasks from current logger tasks list.
   */
  public function commit()
  {
    if (\is_array($this->loggerTaskId) && (\count($this->loggerTaskId) > 0)) {
      foreach (\range(0, \count($this->loggerTaskId)) as $nan)
        yield;

      $current = Co::getLoop()->currentList();
      foreach ($this->loggerTaskId as $index => $task) {
        if (!isset($current[$index])) {
          unset($this->loggerTaskId[$index]);
        }
      }

      $loggerTaskId = yield \gather($this->loggerTaskId);
      if (\is_array($loggerTaskId)) {
        foreach ($loggerTaskId as $id => $null) {
          unset($this->loggerTaskId[$id]);
        }
      }
    }
  }

  public function emergency($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::EMERGENCY, $message, $context);
  }

  public function alert($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::ALERT, $message, $context);
  }

  public function critical($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::CRITICAL, $message, $context);
  }

  public function error($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::ERROR, $message, $context);
  }

  public function warning($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::WARNING, $message, $context);
  }

  public function notice($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::NOTICE, $message, $context);
  }

  public function info($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::INFO, $message, $context);
  }

  public function debug($message, array $context = array())
  {
    return $this->_make_log_task(LogLevel::DEBUG, $message, $context);
  }

  public function log($level, $message, array $context = array())
  {
  }

  public static function write($stream, $string)
  {
    yield Kernel::writeWait($stream);
    yield Coroutine::value(\fwrite($stream, $string));
  }
}
