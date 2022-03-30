<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Kernel;
use Async\Coroutine;
use Async\Misc\Context;
use Async\Datatype\Tuple;
use Async\Datatype\TupleIterator;
use Async\Network\SocketsInterface;

/**
 * **Non-blocking** wrapper around a `socket` object.
 * A `Sockets` _object_ may also be used as an asynchronous `context manager` where as the underlying `socket` will automatically be closed when done.
 *
 * @see https://curio.readthedocs.io/en/latest/reference.html?highlight=TaskError#socket
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/io.py#L89
 */
class Sockets extends Context implements SocketsInterface
{
  /**
   * Hints for **AddressInfo**, a `addrinfo` structure used by the `socket_addrinfo_lookup()` function to hold host address information.
   *
   *```md
   * - ai_flags:
   * AI_PASSIVE - The socket address will be used in a call to the bind function.
   * AI_CANONNAME - The canonical name is returned in the first ai_canonname member.
   * AI_NUMERICHOST - The address parameter passed to the getaddrinfo function must be a numeric string.
   * AI_ADDRCONFIG - The getaddrinfo will resolve only if a global address is configured. The IPv6 and IPv4 loopback address is not considered a valid global address.
   *
   * - ai_family:
   * AF_INET - The Internet Protocol version 4 (IPv4) address family.
   * AF_UNIX - Local communication protocol family. High efficiency and low overhead make it a great form of IPC (Interprocess Communication).
   * AF_INET6 - The Internet Protocol version 6 (IPv6) address family.
   *
   * - ai_socktype:
   * SOCK_STREAM - Provides sequenced, reliable, two-way, connection-based byte streams with an OOB data transmission mechanism. Uses the Transmission Control Protocol (TCP) for the Internet address family (AF_INET or AF_INET6).
   *
   * SOCK_DGRAM - Supports datagrams, which are connectionless, unreliable buffers of a fixed (typically small) maximum length. Uses the User Datagram Protocol (UDP) for the Internet address family (AF_INET or AF_INET6).
   *
   * SOCK_RAW - Provides a raw socket that allows an application to manipulate the next upper-layer protocol header. To manipulate the IPv4 header, the IP_HDRINCL socket option must be set on the socket. To manipulate the IPv6 header, the IPV6_HDRINCL socket option must be set on the socket.
   *
   * SOCK_SEQPACKET - Provides a pseudo-stream packet based on datagrams.
   *
   * - ai_protocol:
   * IPPROTO_TCP - The Transmission Control Protocol (TCP). This is a possible value when the ai_family member is AF_INET or AF_INET6 and the ai_socktype member is SOCK_STREAM.
   *
   * IPPROTO_UDP - The User Datagram Protocol (UDP). This is a possible value when the ai_family member is AF_INET or AF_INET6 and the type parameter is SOCK_DGRAM.
   *
   * - ai_canonname: The canonical name for the host.
   * - ai_addr: socket address array
   * -        ['sin_port' => 0, 'sin_addr' => -1]
   * -        ['sin6_port' => 0, 'sin6_addr' => -1]
   *```
   * @var array[]
   */
  const ADDR_INFO = [
    'ai_flags' => \AI_ADDRCONFIG | \AI_PASSIVE | \AI_CANONNAME,
    'ai_family' => \AF_INET,
    'ai_socktype' => \SOCK_STREAM,
    'ai_protocol' => 0
  ];

  /**
   * @var \Socket|resource
   */
  protected $socket;

  /**
   * Stream-based interface to the `socket`.
   *
   * @var resource
   */
  protected $stream;

  /**
   * The `fd` file descriptor of the underlying `socket`.
   *
   * @var resource
   */
  protected $fileno;

  /**
   * Last error on the socket
   *
   * @var integer
   */
  protected $last_error = 0;

  /**
   * `getaddrinfo`
   *
   * @var array
   */
  protected $info = [];

  protected $closed = false;
  protected $secured = false;

  /**
   * @var SSLContext
   */
  protected $instance;
  protected $address;
  protected $port;

  /**
   * @var float
   */
  protected $timeout = 1.0;

  /**
   * @param resource|\Socket $socket
   * @param int $timeout
   * @param int $port
   */
  public function __construct($socket = None, $timeout = 1)
  {
    if (\get_resource_type($socket) === 'stream') {
      $stream = \socket_import_stream($socket);
      if ($stream === false)
        $this->error();

      \socket_set_nonblock($stream);
      $this->socket = $stream;
      \stream_set_read_buffer($socket, 0);
      \stream_set_write_buffer($socket, 0);
      \stream_set_timeout($socket, $timeout);
      $this->stream = $socket;
      \stream_set_blocking($this->stream, false);
      if (\stream_is_local($socket))
        $this->fileno = $socket;
    } elseif (\get_resource_type($socket) === 'Socket') {
      \socket_set_nonblock($socket);
      $this->socket = $socket;
      $stream = \socket_export_stream($socket);
      if ($stream === false)
        $this->error();

      \stream_set_read_buffer($stream, 0);
      \stream_set_write_buffer($stream, 0);
      \stream_set_timeout($stream, $timeout);
      $this->stream = $stream;
      \stream_set_blocking($this->stream, false);
      if (\stream_is_local($stream))
        $this->fileno = $stream;
    }

    $this->timeout = $timeout;
  }

  public function create(int $domain = \AF_INET, int $type = \SOCK_STREAM, int $protocol = \SOL_TCP): SocketsInterface
  {
    $this->close();
    $socket = \socket_create($domain, $type, $protocol);
    if ($socket === false)
      $this->error();

    $this->info = self::ADDR_INFO;
    $this->info['ai_family'] = $domain;
    $this->info['ai_socktype'] = $type;
    $this->info['ai_protocol'] = $protocol;
    \socket_set_nonblock($socket);
    $this->socket = $socket;
    $this->stream = \socket_export_stream($socket);
    \stream_set_read_buffer($this->stream, 0);
    \stream_set_write_buffer($this->stream, 0);
    \stream_set_blocking($this->stream, false);
    $this->closed = false;

    return $this;
  }

  /**
   * @param \Socket|resource|null $socket
   * @return void
   */
  protected function error($socket = null)
  {
    $code = \socket_last_error($socket);
    $error = $this->getError($code);
    if (!empty($error)) {
      if (empty($this->socket))
        \panic('Failed: ' . $error . \EOL);
      else
        \debugging_info('Failed: ' . $error . \EOL);

      $this->last_error = $code;
    }
  }

  public function getError(int $code = 0): ?string
  {
    $code = empty($code) ? $this->last_error : $code;
    if ($code > 0)
      return \socket_strerror($code);

    return null;
  }

  public function clearError(): void
  {
    $this->last_error = 0;
    if ($this->socket !== false)
      \socket_clear_error($this->socket);
  }

  public function setopt(int $level = \SOL_SOCKET, int $option = \SO_REUSEADDR, $value = 1): bool
  {
    return \socket_set_option($this->socket, $level, $option, $value);
  }

  public function recv($maxBytes, $flags = \MSG_DONTWAIT)
  {
    yield;
    $buffer = '';
    if (\is_resource($this->stream)) {
      yield Kernel::readWait($this->stream);
      if (\socket_recv($this->socket, $buffer, $maxBytes, $flags) === false)
        $this->error($this->socket);
    }

    return $buffer;
  }

  public function recv_into(&$buffer, $nBytes = 0, $flags = \PHP_BINARY_READ)
  {
    yield;
    if (\is_resource($this->stream)) {
      yield Kernel::readWait($this->stream);
      $buffer = \socket_read($this->socket, $nBytes, $flags);
      if ($buffer === false)
        $this->error($this->socket);
    }
  }

  public function recvfrom($maxsize, $flags = \MSG_DONTWAIT, &$address = null, &$port = null)
  {
    yield;
    $buffer = '';
    if (\is_resource($this->stream)) {
      yield Kernel::readWait($this->stream);
      if (\socket_recvfrom($this->socket, $buffer, $maxsize, $flags, $address, $port) === false)
        $this->error($this->socket);

      return new Tuple($buffer, $address);
    }
  }

  public function recvfrom_into(&$buffer, $nBytes, $flags = \MSG_DONTWAIT, &$address = null, &$port = null)
  {
    yield;
    if (\is_resource($this->stream)) {
      yield Kernel::readWait($this->stream);
      if (\socket_recvfrom($this->socket, $buffer, $nBytes, $flags, $address, $port) === false)
        $this->error($this->socket);
    }
  }

  public function recvmsg($bufSize, $flags = \MSG_DONTWAIT)
  {
    yield;
    $buffer = [
      "name" => ["family" => \AF_INET6, "addr" => "::1"],
      "buffer_size" => $bufSize,
      "controllen" => \socket_cmsg_space(IPPROTO_IPV6, IPV6_PKTINFO)
    ];

    if (\is_resource($this->stream)) {
      yield Kernel::readWait($this->stream);
      if (\socket_recvmsg($this->socket, $buffer, $flags) === false)
        $this->error($this->socket);

      return $buffer;
    }
  }

  public function recvmsg_into(array &$buffers, $flags = \MSG_DONTWAIT)
  {
    yield;
    if (\is_resource($this->stream)) {
      yield Kernel::readWait($this->stream);
      if (\socket_recvmsg($this->socket, $buffers, $flags) === false)
        $this->error($this->socket);
    }
  }

  public function send($data, int $length, int $flags = 0)
  {
    \socket_send($this->socket, $data, $length, $flags);
  }

  public function sendAll($data, $flags = 0)
  {
  }

  public function sendto($data, $address)
  {
  }

  public function sendto_alt($data, $flags, $address)
  {
  }

  public function sendmsg($buffers, TupleIterator $ancData = null, $flags = 0, $address = None)
  {
  }

  public function read(int $length = -1, int $mode = \PHP_BINARY_READ)
  {
    $data = false;
    if ($this->secured) {
      if (\is_resource($this->stream)) {
        yield Kernel::readWait($this->stream);
        $data = \stream_get_contents($this->stream, $length);
      }
    } elseif ($this->socket) {
      $length = ($length === -1) ? 1024 * 16 : $length;
      yield Kernel::readWait($this->stream);
      $data = \socket_read($this->socket, $length, $mode);
      if (false === $data)
        $this->error($this->socket);

      $data = (false === $data && \socket_last_error($this->socket) === 0) ? '' : $data;
    }

    return $data;
  }

  public function write(string $data, ?int $length = 0)
  {
    $count = false;
    $length = (empty($length) ? \strlen($data) : $length);
    if ($this->secured) {
      if (\is_resource($this->stream)) {
        yield Kernel::writeWait($this->stream);
        $count = \fwrite($this->stream, $data,  $length);
      }
    } elseif ($this->socket) {
      yield Kernel::writeWait($this->stream);
      $count = \socket_write($this->socket, $data, $length);
      if (false === $count)
        $this->error($this->socket);
    }

    return $count;
  }

  public function accept()
  {
    yield \stateless_task();
    if (!\is_resource($this->stream))
      return false;

    yield Kernel::readWait($this->stream);
    if ($this->secured)
      return $this->do_handshake();
    else
      return new Sockets($this->accepting());
  }

  protected function accepting()
  {
    if ($this->secured) {
      $client = \stream_socket_accept($this->stream, 0);
      if (false === $client)
        throw new \RuntimeException('Error accepting new connection');
    } else {
      $client = \socket_accept($this->socket);
      if (false === $client)
        $this->error($this->socket);
    }

    return $client;
  }

  public function do_handshake()
  {
    \stream_set_blocking($this->stream, true);
    $secure = self::accepting();
    \stream_set_blocking($this->stream, false);

    $error = null;
    \set_error_handler(function ($_, $errstr) use (&$error) {
      $error = \str_replace(array("\r", "\n"), ' ', $errstr);
      // remove useless function name from error message
      if (($pos = \strpos($error, "): ")) !== false) {
        $error = \substr($error, $pos + 3);
      }
    });

    $socket = \socket_import_stream($secure);
    \stream_set_blocking($secure, true);
    $result = @\stream_socket_enable_crypto($secure, true, $this->instance->get_crypto());

    \restore_error_handler();

    if (false === $result) {
      if (\feof($secure) || $error === null) {
        // EOF or failed without error => connection closed during handshake
        if ($error !== 'SSL: The operation completed successfully.  ')
          \debugging_info(\sprintf("Connection lost during TLS handshake with: %s\n", \stream_socket_get_name($secure, true)));
      } else {
        // handshake failed with error message
        \debugging_info(\sprintf("Unable to complete TLS handshake: %s\n", $error));
      }
    }

    return $this->instance->wrap_socket($secure, $socket);
  }

  public function bind($address, ?int $port = 0): bool
  {
    if ($this->socket === false)
      return false;

    $status = \socket_bind($this->socket, $address, $port);
    if (false === $status)
      $this->error($this->socket);

    return $status;
  }

  public function listen(int $backlog = 0): bool
  {
    if ($this->socket === false)
      return false;

    $status = \socket_listen($this->socket, $backlog);
    if (false === $status)
      $this->error($this->socket);

    return $status;
  }

  public function connect($address, ?int $port = 0): bool
  {
    if ($this->socket === false)
      return false;

    $status = \socket_connect($this->socket, $address, $port);
    if ($status === false)
      throw new \Exception($this->getError(\socket_last_error($this->socket)));

    return $status;
  }

  public function connect_ex($address, ?int $port = None): int
  {
    if ($this->socket === false)
      return false;

    $status = \socket_connect($this->socket, $address, $port);
    if ($status === false)
      $status = \socket_last_error($this->socket);

    return $status;
  }

  public function close(): void
  {
    if (!$this->closed) {
      $this->shutdown(2);
      $this->clearError();

      if ($this->secured) {
        if (\is_resource($this->stream))
          \fclose($this->stream);
      } elseif ($this->socket)
        \socket_close($this->socket);

      $this->stream = null;
      $this->socket = null;
      $this->closed = true;

      parent::close();
    }
  }

  public function shutdown($how)
  {
    if ($this->secured) {
      if (\is_resource($this->stream))
        \stream_socket_shutdown($this->stream, 2);
    } else {
      if (\is_resource($this->stream))
        \stream_socket_shutdown($this->stream, 2);
      elseif ($this->socket)
        \socket_shutdown($this->socket, 2);
    }
  }

  public function get_peer()
  {
    if ($this->secured) {
      if (\is_resource($this->stream))
        return \stream_socket_get_name($this->stream, true);

      return false;
    } else {
      if ($this->socket) {
        $status = \socket_getpeername($this->socket, $this->address, $this->port);
        if (false === $status)
          $this->error($this->socket);
      }

      return $this->address . ':' . (string) $this->port;
    }
  }

  public function get_local()
  {
    if ($this->secured) {
      if (\is_resource($this->stream))
        return \stream_socket_get_name($this->stream, false);

      return false;
    } else {
      if ($this->socket) {
        $status = \socket_getsockname($this->socket, $this->address, $this->port);
        if (false === $status)
          $this->error($this->socket);
      }

      return $this->address . ':' . (string) $this->port;
    }
  }

  public function makefile($mode, $buffering = 0)
  {
    return new FileStream();
  }

  public function as_stream()
  {
    return $this->stream;
  }

  public function blocking()
  {
  }
}
