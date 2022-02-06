<?php

namespace Async;

use Async\Panicking;

class RuntimeException extends \RuntimeException implements Panicking
{
}
