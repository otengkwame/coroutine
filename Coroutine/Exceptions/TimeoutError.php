<?php

namespace Async;

use Async\RuntimeException;

class TimeoutError extends RuntimeException
{
  public function __construct($time = null)
  {
    parent::__construct(\sprintf('The operation has exceeded the given deadline: %f', (float) $time));
  }
}
