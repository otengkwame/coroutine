<?php

namespace Async;

use Async\Panicking;

class LengthException extends \LengthException implements Panicking
{
}
