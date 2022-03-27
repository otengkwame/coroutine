<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Datatype\TupleIterator;

/**
 * A wrapper for the standard PHP `Socket` library.
 * Certain blocking operations are replaced by versions safe to use in `async`.
 *
 * - The original `socket` is put into a non-blocking mode when it's wrapped.
 */
interface SocketsInterface
{
  /**
   * Create a socket (endpoint for communication)
   *
   * @param int $domain
   * - AF_INET - The Internet Protocol version 4 (IPv4) address family.
   * - AF_UNIX - Local communication protocol family. High efficiency and low overhead make it a great form of IPC (Interprocess Communication).
   * - AF_INET6 - The Internet Protocol version 6 (IPv6) address family.
   *
   * @param int $type
   * - SOCK_STREAM - Provides sequenced, reliable, two-way, connection-based byte streams with an OOB data transmission mechanism. Uses the Transmission Control Protocol (TCP) for the Internet address family (AF_INET or AF_INET6).
   *
   * - SOCK_DGRAM - Supports datagrams, which are connectionless, unreliable buffers of a fixed (typically small) maximum length. Uses the User Datagram Protocol (UDP) for the Internet address family (AF_INET or AF_INET6).
   *
   * - SOCK_RAW - Provides a raw socket that allows an application to manipulate the next upper-layer protocol header. To manipulate the IPv4 header, the IP_HDRINCL socket option must be set on the socket. To manipulate the IPv6 header, the IPV6_HDRINCL socket option must be set on the socket.
   *
   * - SOCK_SEQPACKET - Provides a pseudo-stream packet based on datagrams.
   *
   * @param int $protocol
   * - SOL_TCP - The Transmission Control Protocol (TCP).
   * - SOL_UDP - The User Datagram Protocol (UDP).
   */
  public function create(int $domain = \AF_INET, int $type = \SOCK_STREAM, int $protocol = \SOL_TCP): SocketsInterface;

  /**
   * Sets options for the `Socket`.
   *
   * @param integer $level The level parameter specifies the protocol level at which the option resides.
   * - For example, to retrieve options at the socket level, a level parameter of `SOL_SOCKET` would be used.
   * - Other levels, such as TCP, can be used by specifying the protocol number of that level.
   * Protocol numbers can be found by using the `getprotobyname` function.
   * @param integer $option The available socket options are the same as those for the `socket_get_option()` function.
   * - `SO_DEBUG` | `SO_BROADCAST` | `SO_REUSEADDR` | `SO_REUSEPORT` | `SO_KEEPALIVE` | `SO_LINGER`
   * - `SO_OOBINLINE` | `SO_SNDBUF` | `SO_RCVBUF` | `SO_ERROR` | `SO_TYPE` | `SO_DONTROUTE` | `SO_RCVLOWAT`
   * - `SO_RCVTIMEO` | `SO_SNDTIMEO` | `SO_SNDLOWAT` | `TCP_NODELAY` | `MCAST_JOIN_GROUP` | `MCAST_LEAVE_GROUP`
   * - `MCAST_BLOCK_SOURCE` | `MCAST_UNBLOCK_SOURCE` | `MCAST_JOIN_SOURCE_GROUP` | `MCAST_LEAVE_SOURCE_GROUP`
   * - `IP_MULTICAST_IF` | `IPV6_MULTICAST_IF` | `IP_MULTICAST_LOOP` | `IPV6_MULTICAST_LOOP`
   * - `IP_MULTICAST_TTL` | `IPV6_MULTICAST_HOPS` | `SO_MARK` | `SO_ACCEPTFILTER` | `SO_USER_COOKIE`
   * - `SO_DONTTRUNC`  | `SO_WANTMORE` | `TCP_DEFER_ACCEPT`
   * @param array|string|integer $value The option value.
   * @return bool
   */
  public function setopt(int $level = \SOL_SOCKET, int $option = \SO_REUSEADDR, $value = 1): bool;

  /**
   * Receive up to `maxBytes` of data.
   * - This function needs to be prefixed with `yield`
   *
   * @param integer $maxBytes Up to max bytes will be fetched from remote host.
   * @param integer $flags The value of flags can be any combination of the following flags, joined with the binary OR `|` operator.
   * - `MSG_OOB` - Process out-of-band data.
   * - `MSG_PEEK`	- Receive data from the beginning of the receive queue without removing it from the queue.
   * - `MSG_WAITALL` - Block until at least len are received. However, if a signal is caught or the remote host disconnects,
   * the function may return less data.
   * - `MSG_DONTWAIT` - With this flag set, the function returns even if it would normally have blocked.
   *
   * @return string|null
   */
  public function recv($maxBytes, $flags = \MSG_DONTWAIT);

  /**
   * Receive up to `nBytes` of data into a `buffer`.
   * - This function needs to be prefixed with `yield`
   *
   * @param mixed $buffer
   * @param integer $nBytes
   * @param integer $flags
   * @return void
   */
  public function recv_into(&$buffer, $nBytes = 0, $flags = \PHP_BINARY_READ);

  /**
   * Receives data from a socket whether or not it is connection-oriented.
   * Receive up to `maxsize` of data.
   * - This function needs to be prefixed with `yield`
   *
   * @param integer $maxsize
   * @param integer $flags
   * @return TupleIterator a `tuple(data, client_address)`.
   */
  public function recvfrom($maxsize, $flags = \MSG_DONTWAIT, &$address = null, &$port = null);

  /**
   * Receive up to `nBytes` of data into a `buffer`.
   * - This function needs to be prefixed with `yield`
   *
   * @param mixed $buffer
   * @param integer $nBytes
   * @param integer $flags
   * @param mixed $address
   * @param mixed $port
   * @return void
   */
  public function recvfrom_into(&$buffer, $nBytes, $flags = \MSG_DONTWAIT, &$address = null, &$port = null);

  /**
   * Receive normal and ancillary data.
   * - This function needs to be prefixed with `yield`
   *
   * @param integer $bufSize
   * @param integer $flags
   * @return void
   */
  public function recvmsg($bufSize, $flags = \MSG_DONTWAIT);

  /**
   * Receive normal and ancillary data into a buffer.
   * - This function needs to be prefixed with `yield`
   *
   * @param array $buffers
   * @param integer $flags
   * @return void
   */
  public function recvmsg_into(array &$buffers, $flags = \MSG_DONTWAIT);

  /**
   * Send data. Returns the number of bytes sent.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $data
   * @param integer $length
   * @param integer $flags
   * @return int
   */
  public function send($data, int $length, int $flags = 0);

  /**
   * Send all of the data in data. If cancelled, the bytes_sent attribute of the exception contains the number of bytes sent.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $data
   * @param integer $flags
   * @return void
   */
  public function sendAll($data, $flags = 0);

  /**
   * Send `data` to the specified `address`.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $data
   * @param mixed $address
   * @return void
   */
  public function sendto($data, $address);

  /**
   * Send `data` to the specified `address` (alternate).
   * - This function needs to be prefixed with `yield`
   *
   * @param string $data
   * @param integer $flags
   * @param mixed $address
   * @return void
   */
  public function sendto_alt($data, $flags, $address);

  /**
   * Send normal and ancillary data to the socket address.
   * - This function needs to be prefixed with `yield`
   *
   * @param mixed $buffers
   * @param TupleIterator|null $ancData
   * @param integer $flags
   * @param mixed $address
   * @return void
   */
  public function sendmsg($buffers, TupleIterator $ancData = null, $flags = 0, $address = None);

  /**
   * Wait for a new connection.
   * - This function needs to be prefixed with `yield`
   *
   * @return SocketsInterface
   */
  public function accept();

  /**
   * Reads a maximum of length bytes from a socket/stream.
   * - This function needs to be prefixed with `yield`
   *
   * @param integer $length
   * @param integer $mode
   * @return string|false
   */
  public function read(int $length = -1, int $mode = \PHP_BINARY_READ);

  /**
   * Binary-safe write.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $data
   * @param integer|null $length
   * @return int|false
   */
  public function write(string $data, ?int $length = null);

  /**
   * Make a connection.
   *
   * @param mixed $address
   * @return bool
   */
  public function connect($address, ?int $port = 0): bool;

  /**
   * Make a connection and return an error code instead of raising an exception.
   *
   * @param mixed $address
   * @return int|bool
   */
  public function connect_ex($address, ?int $port = 0): int;

  /**
   * Close the connection.
   *
   * @return void
   */
  public function close(): void;

  /**
   * Shutdown the socket. how is one of SHUT_RD, SHUT_WR, or SHUT_RDWR.
   *
   * @param int $how
   * @return void
   */
  public function shutdown($how);

  public function get_peer();

  public function get_local();

  /**
   * Perform an SSL client handshake (only on SSL sockets).
   *
   * @return SSLSockets
   */
  public function do_handshake();

  /**
   * Make a `FileStream` instance wrapping the socket.
   * Prefer to use `Sockets->as_stream()` instead.
   * - Not supported on Windows.
   *
   * @param mixed $mode
   * @param integer $buffering
   * @return FileStream
   */
  public function makefile($mode, $buffering = 0);

  /**
   * Return the `Socket` as a stream.
   *
   * @return resource
   */
  public function as_stream();

  /**
   * A context manager that returns the internal socket placed into blocking mode.
   *
   * @return void
   */
  public function blocking();
}
