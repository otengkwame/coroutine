<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\TaskInterface;

/**
 * A **context manager** can control a block of code using the `async_with()` and `__with()` functions.
 * Basically a `try {} catch {} finally {}` construct, with any `resource` or `object` used be automatically **closed**.
 *
 * @see https://realpython.com/python-with-statement/
 * @see https://book.pythontips.com/en/latest/context_managers.html
 * @see https://docs.python.org/3/glossary.html#term-context-manager
 * @see https://www.python.org/dev/peps/pep-0343/
 */
trait ContextTrait
{
  /**
   * @var resource|object
   */
  protected $context;

  /**
   * @var object
   */
  protected $instance;

  /**
   * @var TaskInterface
   */
  protected $withTask = null;

  /**
   * @var boolean
   */
  protected $withSet = false;

  /**
   * @var \Throwable
   */
  protected $error = null;
  protected $objectIs = [];
  protected $done = false;

  /**
   * `__enter()` execution status.
   *
   * @var boolean
   */
  protected $enter = false;

  /**
   * `__exit()` execution status.
   *
   * @var boolean
   */
  protected $exit = false;


  public function withTask(): ?TaskInterface
  {
    return $this->withTask;
  }

  public function withSet()
  {
    $task = \coroutine()->getTask(yield \current_task());
    if (!$task->isAsync()) {
      \panic(new \Error("Can only use `async_with()` or `with()` inside an `async()` created function!" . \EOL . "Use/call `yield task_type();` first inside a regular function/method." . \EOL));
    }

    $this->withTask = $task->setWith($this);
    $this->withSet = true;
  }

  public function clearWith(): void
  {
    if ($this->withTask instanceof TaskInterface)
      $this->withTask->setWith();

    $this->withTask = null;
    $this->withSet = false;
  }

  public function isWith(): bool
  {
    return $this->withSet;
  }

  public function entered(): bool
  {
    return $this->enter;
  }

  public function __enter()
  {
    $this->enter = true;
  }

  public function exited(): bool
  {
    return $this->exit;
  }

  public function __exit(\Throwable $type = null)
  {
    if (!empty($type)) {
      $this->error = $type;
    }

    $this->exit = true;
    $this->__destruct();
  }

  public function __destruct()
  {
    if (!$this->exit) {
      $this->__exit();
    }

    if (!$this->done)
      $this->close();
  }

  /**
   * @param resource $context
   * @param object|null ...$object
   */
  public function __construct($context, ...$object)
  {
    $this->context = $context;
    if (!empty($object))
      $this->instance = \array_shift($object);

    if (\is_object($this->context))
      $this->objectIs[0] = true;
    elseif (\is_object($this->instance))
      $this->objectIs[1] = true;
  }

  public function __invoke()
  {
    if (!$this->enter) {
      return $this->__enter();
    } elseif (!$this->exit) {
      return $this->__exit();
    }
  }

  public function __call($function, $args)
  {
    if (!$this->withSet) {
      yield $this->withSet();
    }

    $object = null;
    if (isset($this->objectIs[0]))
      $object = $this->context;
    elseif (isset($this->objectIs[1]))
      $object = $this->instance;

    if (\is_object($object) && \method_exists($object, $function)) {
      try {
        $this->done = null;
        return $object->$function(...$args);
      } catch (\Throwable $e) {
        $this->done = false;
        $this->error = $e;
      } finally {
        if ($this->done !== null)
          $this->close();
      }
    } else {
      $this->error = new \Error("$function does not exist");
      $this->close();
    }
  }

  public function getResource()
  {
    return $this->context;
  }

  public function close(): void
  {
    if ($this->withSet)
      $this->clearWith();

    if (isset($this->objectIs[0]) && \method_exists($this->context, 'close'))
      $this->context->close();
    elseif (\is_resource($this->context))
      \fclose($this->context);

    if (isset($this->objectIs[1]) && \method_exists($this->instance, 'close'))
      $this->instance->close();

    unset($this->context);
    unset($this->instance);

    $this->context = null;
    $this->instance = null;
    $this->done = true;

    if ($this->error) {
      $error = $this->error;
      $this->error = null;
      if ($error instanceof \Throwable)
        throw $error;
    }
  }
}
