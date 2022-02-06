<?php

namespace Async;

use Async\CancelledError;

class TaskTimeout extends CancelledError
{
  public function __construct($time = null)
  {
    parent::__construct(\sprintf('The operation has exceeded the given deadline: %f', (float) $time));
  }
}
