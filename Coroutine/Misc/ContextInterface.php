<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\TaskInterface;

/**
 * Using Contexts to Manage Resources.
 *
 * The simplest use of context management is to strictly control the handling of key resources
 * (such as files, generators, database connections, synchronization locks).
 *
 * - Context managers provide `__enter()` and `__exit()` methods that are `__invoke()` on _entry_ **to** and _exit_ **from**
 * the body **between** `with()`, or `async_with()`, **and** `ending()` function statement.
 */
interface ContextInterface
{

  /**
   * Has `async_with()` or `with()` _assigned_ a context manager to a `Task` that _is_ **active**.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return boolean
   */
  public function isWith(): bool;

  /**
   * Return the `Task` instance a context manager _instance_ is **attached** to by `async_with()` or `with()`.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return TaskInterface|null
   */
  public function withTask(): ?TaskInterface;

  /**
   * Set a `async_with()` or `with()` **Task** _instance_ to **active** and assign a context manager _instance_ to it.
   * DO NOT OVERWRITE THIS METHOD.
   * - This function needs to be prefixed with `yield`
   *
   * @return void
   * @throws \Error If not call from inside an `async()` created function.
   * - Use/call `yield task_type('async');` first inside a regular function/method.
   */
  public function withSet();

  /**
   * Clear/remove a `async_with()` or `with()` **Task** _instance_ from context manager, and set to **not active**.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return void
   */
  public function clearWith(): void;

  /**
   * Returns the status of `__enter()` execution.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return boolean
   */
  public function entered(): bool;

  /**
   * Returns the status of `__exit()` execution.
   * DO NOT OVERWRITE THIS METHOD.
   *
   * @return boolean
   */
  public function exited(): bool;

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
  public function __enter();

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
  public function __exit(\Throwable $type = null);

  /**
   * Preform local context manager code block clean up, pre releasing memory and any additional resources.
   *
   * - When **overwriting** this method, `parent::__destruct()` _method_ SHOULD BE called
   * to **insure** proper code clean up is preformed.
   *
   * @return void
   */
  public function __destruct();

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
  public function __invoke();

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
  public function __call($function, $args);

  /**
   * Return any PHP builtin or resource like object that was supplied for _context_ at `__construct($context, $object)`.
   *
   * @return resource
   */
  public function getResource();

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
  public function close(): void;
}
