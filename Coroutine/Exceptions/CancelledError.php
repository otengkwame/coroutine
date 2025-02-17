<?php

namespace Async;

use Async\RuntimeException;

class CancelledError extends RuntimeException
{
  public function __construct($msg = null)
  {
    parent::__construct(\sprintf('The operation has been cancelled, with: %s', $msg));
  }
}
