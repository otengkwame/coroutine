<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\Misc\ContextInterface;

/**
 * A **context manager** can control a block of code using the `async_with()` and `ending()` functions.
 * Basically a `try {} catch {} finally {}` construct, with any `resource` or `object` used be automatically **closed**.
 *
 * @see https://realpython.com/python-with-statement/
 * @see https://book.pythontips.com/en/latest/context_managers.html
 * @see https://docs.python.org/3/glossary.html#term-context-manager
 * @see https://www.python.org/dev/peps/pep-0343/
 */
abstract class Context implements ContextInterface
{
  use \Async\Misc\ContextTrait;
}
