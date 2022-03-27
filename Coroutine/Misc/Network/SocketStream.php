<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Network\Sockets;

/**
 * Create a file-like wrapper around a socket. The `socket` is an existing socket-like object.
 * The `socket` is put into non-blocking mode, is not closed unless the resulting instance is explicitly
 * closed or used as a `context manager`. Instantiated by `Socket->as_stream()`.
 *
 * @see https://curio.readthedocs.io/en/latest/reference.html?highlight=TaskError#SocketStream
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/io.py#L583
 * @codeCoverageIgnore
 */
final class SocketStream extends Sockets
{
  use \Async\Network\StreamBase;
}
