<?php

namespace Async;

use Async\Panicking;

class InvalidArgumentException extends \InvalidArgumentException implements Panicking
{
}
