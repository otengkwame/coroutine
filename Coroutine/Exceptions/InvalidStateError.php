<?php

namespace Async;

use Async\RuntimeException;

class InvalidStateError extends RuntimeException
{
  public function __construct($msg = null)
  {
    parent::__construct(\sprintf('Invalid internal state called on: %s', $msg));
  }
}
