<?php

namespace Async;

use Async\Panicking;

class RuntimeError extends \RuntimeException implements Panicking
{
}
