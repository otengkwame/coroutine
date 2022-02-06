<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\Misc\AsyncIterator;
use Async\Misc\ContextInterface;

abstract class ContextAsyncIterator extends AsyncIterator implements ContextInterface, \Iterator
{
  use \Async\Misc\ContextTrait;
}
