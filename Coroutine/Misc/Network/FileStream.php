<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Network\Sockets;

/**
 * Create a file-like wrapper around an existing file as might be created by the built-in `fopen()`,
 * must be in in binary mode and must support non-blocking I/O. The `file` is not closed unless the resulting
 * instance is explicitly closed or used as a `context manager`.
 *
 * @see https://curio.readthedocs.io/en/latest/reference.html?highlight=TaskError#FileStream
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/io.py#L503
 * @codeCoverageIgnore
 */
final class FileStream extends Sockets
{
  use \Async\Network\StreamBase;
}
