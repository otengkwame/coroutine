<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\TaskInterface;

/**
 * A **context manager** can control a block of code using the `async_with()` and `ending()` functions.
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
  protected $isObject = false;
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


  /**
   * Return the `Task` instance a context manager _instance_ is **attached** to by `async_with()` or `with()`.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return TaskInterface|null
   */
  public function withTask(): ?TaskInterface
  {
    return $this->withTask;
  }

  /**
   * Set a `async_with()` or `with()` **Task** _instance_ to **active** and assign a context manager _instance_ to it.
   * DO NOT OVERWRITE THIS METHOD.
   * - This function needs to be prefixed with `yield`
   *
   * @return void
   * @throws \Error If not call from inside an `async()` created function, or method.
   * - Use/call `yield method_task();` first inside a regular function/method.
   */
  public function withSet()
  {
    $task = \coroutine()->getTask(yield \current_task());
    if (!$task->isAsync() && !$task->isAsyncMethod()) {
      \panic(new \Error("Can only use `async_with()` or `with()` inside an `async()` created function, or method!" . \EOL . "Use/call `yield method_task();` first inside a regular function/method." . \EOL));
    }

    $this->withTask = $task->setWith($this);
    $this->withSet = true;
  }

  /**
   * Clear/remove a `async_with()` or `with()` **Task** _instance_ from context manager, and set to **not active**.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return void
   */
  public function clearWith(): void
  {
    if ($this->withTask instanceof TaskInterface)
      $this->withTask->setWith();

    $this->withTask = null;
    $this->withSet = false;
  }

  /**
   * Has `async_with()` or `with()` _assigned_ a context manager to a `Task` that _is_ **active**.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return boolean
   */
  public function isWith(): bool
  {
    return $this->withSet;
  }

  /**
   * Returns the status of `__enter()` execution.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return boolean
   */
  public function entered(): bool
  {
    return $this->enter;
  }

  /**
   * Context managers use this method to create the desired context for the execution of the contained code.
   * This method WILL BE called by `__invoke()`.
   *
   * - When **overwriting** this method, `$this->enter` _property_ MUST BE set to `true`, otherwise a `Panic` exception will be **throw**.
   *
   *```php
   *    // This check SHOULD BE added to SOME METHOD or ALL `YIELDING` METHODS to insure `with` is set.
   *    if (!$this->isWith()) {
   *      yield $this->withSet();
   *    }
   *```
   *
   * @return mixed
   */
  public function __enter()
  {
    $this->enter = true;
  }

  /**
   * Returns the status of `__exit()` execution.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return boolean
   */
  public function exited(): bool
  {
    return $this->exit;
  }

  /**
   * Context managers use this method to clean up after execution of the contained code.
   * This method WILL BE called by `__invoke()`.
   *
   * - When **overwriting** this method, `$this->exit` _property_ MUST BE set to `true`, otherwise a `Panic` exception will be **throw**.
   * - When **overwriting** this method, `$this->error` _property_ MUST BE set to `$type`, to propagate _errors_, if **caller** not handling.
   * - When **overwriting** this method, `parent::__destruct()` _method_ SHOULD BE called to **insure** proper code clean up is preformed.
   *
   * @param \Throwable|null $type
   * @return mixed
   */
  public function __exit(\Throwable $type = null)
  {
    if (!empty($type)) {
      $this->error = $type;
    }

    $this->exit = true;
    $this->__destruct();
  }

  /**
   * Preform local context manager code block clean up, pre releasing memory and any additional resources.
   *
   * - When **overwriting** this method, `parent::__destruct()` _method_ SHOULD BE called
   * to **insure** proper code clean up is preformed.
   *
   * @return void
   */
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
   * @param object|null $object with a `close()` method defined.
   */
  public function __construct($context, ...$object)
  {
    $this->context = $context;
    if (!empty($object))
      $this->instance = \array_shift($object);

    if (\is_object($this->instance)) {
      if (\method_exists($this->instance, 'close'))
        $this->isObject = true;
      else
        \panic('Not valid object instance, missing `close()` method!');
    }
  }

  /**
   * This method forms the heart of a context manager execution flow. Called by `async_with()`, `with()`, and `ending()` functions.
   *
   * This method then executes either `__enter()` or `__exit()` depending on state, only once.
   * - When **overwriting** this method, insure the replacement action executes the methods to at least set the proper state as in changeable section below:
   *
   *```php
   * public function __invoke()
   *  {
   * // Do not change!
   * if (!$this->withSet) {
   *   yield $this->withSet();
   * }
   *
   * // Is changeable...
   *    if (!$this->enter) {
   *      return $this->__enter();
   *    } elseif (!$this->exit) {
   *      return $this->__exit();
   *    }
   *  }
   *```
   *
   * @return mixed
   */
  public function __invoke()
  {
    // Do not change!
    if (!$this->withSet) {
      yield $this->withSet();
    }

    // Is changeable!
    if (!$this->enter) {
      return $this->__enter();
    } elseif (!$this->exit) {
      return $this->__exit();
    }
  }

  /**
   * Will execute the methods in a `DI` container or `object` supplied at `__construct($context, $object)`.
   * DO NOT OVERWRITE THIS METHOD.
   * - Future version of this _context_ manager will have preset `asynchronous` **magic method** actions for various resource types.
   *
   * @param string $function
   * @param mixed $args
   *
   * @return mixed
   * @throws \Error If `function` _method_ does not exist
   */
  public function __call($function, $args)
  {
    if (!$this->withSet) {
      yield $this->withSet();
    }

    if ($this->isObject && \method_exists($this->instance, $function)) {
      try {
        $this->done = null;
        return $this->instance->$function(...$args);
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

  /**
   * Return any PHP builtin or resource like object that was supplied for _context_ at `__construct($context, $object)`.
   *
   * @return resource
   */
  public function getResource()
  {
    return $this->context;
  }

  /**
   * Preforms the proper context managers code block clean up.
   * **Closing** resources and **execute** any _instance/object_ `close()` method that was
   * supplied at `__construct($context, $object)` from a **DI** Container.
   *
   * - When **overwriting** this method, `parent::__destruct()` _method_ SHOULD BE called
   * to **insure** proper code clean up is preformed.
   *
   * @return void
   */
  public function close(): void
  {
    if ($this->withSet)
      $this->clearWith();

    if (\is_resource($this->context) && \get_resource_type($this->context) == 'stream')
      \fclose($this->context);

    if ($this->isObject)
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
