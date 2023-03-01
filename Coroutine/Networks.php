<?php

declare(strict_types=1);

namespace Async;

use Async\Kernel;
use Async\TaskInterface;
use Async\CoroutineInterface;
use Async\Network\OptionsInterface;
use Async\Network\Sockets;
use Async\Network\SSLContext;
use Async\Network\SocketsInterface;

use function Async\Socket\net_getaddrinfo;
use function Async\Socket\create_ssl_context;

/**
 * A general purpose networking class where the `uri` _scheme_ is detected and direct usage.
 * - This allows either a **libuv** object or built-in **native** resource/object usage.
 */
final class Networks
{
  protected static $isRunning = [];

  /**
   * Check for `libuv` for network operations.
   *
   * @return bool
   */
  protected static function isUv(): bool
  {
    $co = \coroutine();
    return ($co instanceof CoroutineInterface && $co->isUv() && Co::uvNative());
  }

  /**
   * Connect to *address* and return the `sockets` object.
   *
   * - Connect to *address* (a 2-tuple ``(host, port)``) and return the socket object.
   * - Passing the optional *timeout* parameter will set the timeout on the socket instance
   * before attempting to connect. If no *timeout* is supplied, the
   * global default timeout setting is used.
   * - If *source_address* is set it must be a tuple of (host, port) for the socket to `bind` as a source address
   * before making the connection.
   *
   * A _host_ of `''` or _port_ `0` tells the OS to use the default, `localhost`.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $address
   * @param integer $timeout
   * @param string $source_address
   * @return Sockets
   * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/socket.py#L41
   */
  public static function create_connection(string $address, int $timeout = 1, string $source_address = None)
  {
    [$parts, $uri, $ip, $addrInfo] = yield net_getaddrinfo($address);
    $isPipe = ($parts['scheme'] == 'file') || ($parts['scheme'] == 'unix');
    $host = $isPipe ? $parts['host'] : $ip;
    $port = $parts['port'];
    $af = $addrInfo['ai_family'];
    $socktype = $addrInfo['ai_socktype'];
    $proto = $addrInfo['ai_protocol'];
    $canonname = $addrInfo['ai_canonname'];
    $sa = $addrInfo['ai_addr']['sin_addr'];
    $sock = new Sockets(None, $timeout, $host, $port);
    $sock->create($af, $socktype, $proto);
    if ($source_address) {
      $sock->bind($source_address, $port);
    }

    yield $sock->connect($sa);
    return $sock;
  }

  /**
   * Get the IP `address` corresponding to Internet host name.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $hostname
   * @param bool $useUv
   */
  public static function gethostbyname(string $hostname, bool $useUv = true)
  {
    if (!\filter_var($hostname, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME)) {
      return \value(false);
    }

    $co = \coroutine();
    if ($co instanceof CoroutineInterface && $co->isUv() && $useUv) {
      return new Kernel(
        function (TaskInterface $task, CoroutineInterface $coroutine) use ($hostname) {
          $coroutine->ioAdd();
          \uv_getaddrinfo(
            $coroutine->getUV(),
            function (int $status, $result) use ($task, $coroutine) {
              $coroutine->ioRemove();
              $task->sendValue(($status < 0 ? false : $result[0]));
              $coroutine->schedule($task);
            },
            $hostname,
            '',
            [
              "ai_family" => \UV::AF_UNSPEC
            ]
          );
        }
      );
    }

    return \await('gethostbyname', $hostname);
  }

  /**
   * Get the Internet host `name` corresponding to IP address.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $ipAddress
   *
   * @return string|bool
   */
  public static function gethostbyaddr(string $ipAddress)
  {
    if (!\filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
      return \value(false);
    }

    return \await('gethostbyaddr', $ipAddress);
  }

  /**
   * Get DNS Resource Records associated with hostname.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $hostname
   * @param int $options Constant type:
   * - `DNS_A` IPv4 Address Resource
   * - `DNS_CAA` Certification Authority Authorization Resource (available as of PHP 7.0.16 and 7.1.2)
   * - `DNS_MX` Mail Exchanger Resource
   * - `DNS_CNAME` Alias (Canonical Name) Resource
   * - `DNS_NS` Authoritative Name Server Resource
   * - `DNS_PTR` Pointer Resource
   * - `DNS_HINFO` Host Info Resource (See IANA Operating System Names for the meaning of these values)
   * - `DNS_SOA` Start of Authority Resource
   * - `DNS_TXT` Text Resource
   * - `DNS_ANY` Any Resource Record. On most systems this returns all resource records,
   * however it should not be counted upon for critical uses. Try DNS_ALL instead.
   * - `DNS_AAAA` IPv6 Address Resource
   * - `DNS_ALL` Iteratively query the name server for each available record type.
   *
   * @return array|bool
   */
  public static function dns_get_record(string $hostname, int $options = \DNS_A)
  {
    if (!\filter_var($hostname, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME)) {
      return \value(false);
    }

    return \await('dns_get_record', $hostname, $options);
  }

  /**
   * Stop/close the `net_listen` server __listening__ for new connection on specified `listen_task` **ID**.
   * - This function needs to be prefixed with `yield`
   *
   * @param int $listener Task ID
   */
  public static function stop(int $listener)
  {
    try {
      $instance = \coroutine()->getTask($listener)->getCustomData();
      self::$isRunning[$listener] = false;
      self::$isRunning['stopped'] = $listener;
      yield self::close($instance);
      yield \cancel_task($listener);
    } catch (\Throwable $e) {
    }
  }

  /**
   * Add a `listen` task handler for the **socket/stream** server, that's continuously monitored.
   *
   * This function will return `int` immediately, use with `net_listen()`.
   * - The `$handler` function will be executed every time on new incoming connections or data.
   * - Expect the `$handler` to receive `(resource|\UVStream $newConnection)`.
   *
   *  Or
   * - Expect the `$handler` to receive `($data)`. If `UVUdp`
   * - This function needs to be prefixed with `yield`
   *
   * @param callable $handler
   *
   * @return int
   */
  public static function listenerTask(callable $handler)
  {
    return Kernel::away(function () use ($handler) {
      $coroutine = \coroutine();
      $tid = yield \stateless_task();
      self::$isRunning[$tid] = true;
      while (self::$isRunning[$tid]) {
        $received = yield;
        if (\is_array($received) && (\count($received) === 3 && $received[0] === $tid)) {
          [, $callerID, $clientConnectionOrData] = $received;
          $received = null;
          $newId = yield \away(function () use ($handler, $clientConnectionOrData) {
            return yield $handler($clientConnectionOrData);
          });

          $coroutine->getTask($newId)->taskType('stateless');
        }
      }

      $task = $coroutine->getTask($callerID);
      if ($task instanceof TaskInterface) {
        $server = $coroutine->getTask($tid)->getCustomData();
        if ($server instanceof \UVStream || $server instanceof \UVUdp)
          $coroutine->ioRemove();

        $task->sendValue(true);
        $coroutine->schedule($task);
      }
    });
  }

  /**
   * @param SocketsInterface|\UVTcp|\UVUdp|\UVPipe $server
   * @param int $listenerTask
   * @param int $backlog
   */
  public static function listen($server, int $listenerTask, int $backlog = 0)
  {
    if (self::isUv() && ($server instanceof \UVStream || $server instanceof \UVUdp)) {
      return yield new Kernel(
        function (TaskInterface $task, CoroutineInterface $coroutine) use ($server, $listenerTask, $backlog) {
          $task->taskType('stateless');
          $coroutine->ioAdd();
          if ($server instanceof \UVUdp) {
            \uv_udp_recv_start($server, function ($stream, $status, $data) use ($task, $coroutine, $listenerTask, $server) {
              $listen = $coroutine->getTask($listenerTask);
              $listen->customData($server);
              $listen->sendValue([$listenerTask, $task->taskId(), $data]);
              $coroutine->schedule($listen);
            });
          } else {
            $backlog = empty($backlog) ? ($server instanceof \UVTcp ? 1024 : 8192) : $backlog;
            \uv_listen($server, $backlog, function ($server, $status) use ($task, $coroutine, $listenerTask) {
              $listen = $coroutine->getTask($listenerTask);
              $uv = $coroutine->getUV();
              if ($server instanceof \UVTcp) {
                $client = \uv_tcp_init($uv);
              } elseif ($server instanceof \UVPipe) {
                $client = \uv_pipe_init($uv, \IS_WINDOWS);
              }

              \uv_accept($server, $client);
              $listen->customData($server);
              $listen->sendValue([$listenerTask, $task->taskId(), $client]);
              $coroutine->schedule($listen);
            });
          }
        }
      );
    }

    if (!$server instanceof SocketsInterface)
      return false;

    try {
      while (true) {
        $client = yield self::accept($server);
        yield self::listening($client, $listenerTask, $server);
        $isTrue = yield;
        if ($isTrue === true) {
          break;
        }
      }

      if (isset(self::$isRunning['stopped']) && self::$isRunning['stopped'] === $listenerTask)
        \debugging_info('Listening stopped at: ' . \gmdate('D, d M Y H:i:s T') . \CRLF);

      return $isTrue;
    } catch (\Throwable $error) {
      \debugging_info('Error: ' . $error->getMessage() . \CRLF);
      return false;
    }
  }

  protected static function listening($client, int $listenerTask, $server = null)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($client, $listenerTask, $server) {
        $task->taskType('stateless');
        $listen = $coroutine->getTask($listenerTask);
        if ($listen instanceof TaskInterface) {
          if (!empty($server))
            $listen->customData($server);

          $listen->sendValue([$listenerTask, $task->taskId(), $client]);
          $coroutine->schedule($listen);
        }

        $coroutine->schedule($task);
      }
    );
  }

  /**
   * @param \UVTcp|\UVTty|\UVPipe|SocketsInterface $handle
   * @param int $size
   *
   * @return string|bool
   */
  public static function read($handle, $size = -1)
  {
    if (self::isUv() && $handle instanceof \UV) {
      if (\uv_is_closing($handle))
        return false;

      return yield new Kernel(
        function (TaskInterface $task, CoroutineInterface $coroutine) use ($handle) {
          if (!\uv_is_closing($handle)) {
            $coroutine->ioAdd();
            \uv_read_start(
              $handle,
              function ($handle, $nRead, $data) use ($task, $coroutine) {
                if ($nRead > 0) {
                  $coroutine->ioRemove();
                  $task->sendValue($data);
                  $coroutine->schedule($task);
                  \uv_read_stop($handle);
                }
              }
            );
          } else {
            $task->sendValue(false);
            $coroutine->schedule($task);
          }
        }
      );
    }

    if ($handle instanceof SocketsInterface)
      return yield $handle->read($size);

    return false;
  }

  /**
   * @param \UV|SocketsInterface $handle
   * @param mixed $data
   *
   * @return int|bool
   */
  public static function write($handle, $data = '')
  {
    if (self::isUv() && $handle instanceof \UV) {
      if (\uv_is_closing($handle))
        return false;

      return yield new Kernel(
        function (TaskInterface $task, CoroutineInterface $coroutine) use ($handle, $data) {
          if (!\uv_is_closing($handle)) {
            $size = \strlen($data);
            $coroutine->ioAdd();
            \uv_write(
              $handle,
              $data,
              function ($handle, $status) use ($task, $coroutine, $size) {
                $coroutine->ioRemove();
                $task->sendValue(($status === 0 ? $size : $status));
                $coroutine->schedule($task);
              }
            );
          } else {
            $task->sendValue(false);
            $coroutine->schedule($task);
          }
        }
      );
    }

    if ($handle instanceof SocketsInterface)
      return yield $handle->write($data);

    return false;
  }

  /**
   * @param \UV|SocketsInterface $handle
   * @return bool
   */
  public static function close($handle)
  {
    if ($handle instanceof \UV) {
      return new Kernel(
        function (TaskInterface $task, CoroutineInterface $coroutine) use ($handle) {
          $coroutine->ioAdd();
          \uv_close(
            $handle,
            function ($handle, $status = null) use ($task, $coroutine) {
              $coroutine->ioRemove();
              $task->sendValue($status);
              $coroutine->schedule($task);
              unset($handle);
            }
          );
        }
      );
    }

    if ($handle instanceof SocketsInterface)
      return $handle->close();

    return false;
  }

  /**
   * - This function needs to be prefixed with `yield`
   *
   * @param string|null|int $uri
   * @param OptionsInterface $context
   *
   * @return \UVTcp|\UVUdp|\UVPipe|SocketsInterface
   */
  public static function client($uri = null, OptionsInterface $context = null)
  {
    [$parts, $uri, $ip,] = yield net_getaddrinfo((string)$uri);
    $isSSL = \in_array($parts['scheme'], ['https', 'wss', 'tls', 'ssl']) || $parts['port'] === 443
      || ($context instanceof SSLContext);
    $retry = 0;
    while (true) {
      if (self::isUv() && !$isSSL) {
        $isPipe = ($parts['scheme'] == 'file') || ($parts['scheme'] == 'unix');
        $address = $isPipe ? $parts['host'] : $ip;
        $client = yield self::connect($parts['scheme'], $address, $parts['port'], $context);
      } else {
        $ctx = \stream_context_create();
        if ($isSSL) {
          $context = ($context instanceof SSLContext)
            ? $context : yield create_ssl_context(
              \CLIENT_AUTH,
              $parts['host'],
              (empty($context) ? [] : $context)
            );

          $mask = \STREAM_CLIENT_ASYNC_CONNECT | \STREAM_CLIENT_CONNECT;
        } else {
          $mask = \STREAM_CLIENT_CONNECT;
        }

        $ctx = $context instanceof OptionsInterface ? $context() : $ctx;
        $client = @\stream_socket_client(
          $uri,
          $errNo,
          $errStr,
          1,
          $mask,
          $ctx
        );

        if (\is_resource($client)) {
          \stream_set_blocking($client, false);

          if ($context instanceof SSLContext) {
            $clientSocket = \socket_import_stream($client);
            while (true) {
              yield Kernel::writeWait($client);
              $enabled = @\stream_socket_enable_crypto($client, true, $context->get_crypto());
              if ($enabled === false)
                throw new \RuntimeException(\sprintf('Failed to enable socket encryption: %s', \error_get_last()['message'] ?? ''));
              if ($enabled === true)
                break;
            }
          }
        }
      }

      if (!$client) {
        if ($retry < 3) {
          $retry++;
          yield;
          yield;
          continue;
        }

        if (isset($errStr))
          throw new \RuntimeException(\sprintf('Failed to connect to %s: %s, %d', $uri, $errStr, $errNo));
        else
          throw new \RuntimeException(\sprintf('Failed to connect to: %s', $uri));
      } else {
        break;
      }
    }

    if (!$client instanceof \UV)
      $client = ($context instanceof SSLContext) ? $context->wrap_socket($client, $clientSocket) : new Sockets($client);

    return $client;
  }

  /**
   * Creates a `secure` or `plaintext` server and starts listening on the given address.
   * This starts accepting new incoming connections on the given address.
   *
   * - The TCP/IP socket server `type` depends on _uri_ **scheme**, `wss, https, udp, etc...` used.
   * - This function needs to be prefixed with `yield`
   *
   * @param string|null|int $uri
   * @param OptionsInterface $context
   * @param string $capath `directory` to hostname certificate
   * @param string $cafile hostname `certificate`
   * @param array $caSelfDetails - for a self signed certificate, will be created automatically if no hostname certificate available.
   *```md
   *  [
   *      "countryName" =>  '',
   *      "stateOrProvinceName" => '',
   *      "localityName" => '',
   *      "organizationName" => '',
   *      "organizationalUnitName" => '',
   *      "commonName" => '',
   *      "emailAddress" => ''
   *  ];
   *```
   * @throws InvalidArgumentException if the listening address is invalid
   * @throws RuntimeException if listening on this address fails (already in use etc.)
   *
   * @return \UVTcp|\UVUdp|\UVPipe|SocketsInterface
   */
  public static function server(
    $uri,
    ?OptionsInterface $context = null,
    ?string $capath = None,
    ?string $cafile = None,
    array $caSelfDetails = []
  ) {
    [$parts, $uri, $ip,] = yield net_getaddrinfo((string)$uri);
    $isSSL = \in_array($parts['scheme'], ['https', 'wss', 'tls', 'ssl']) || $parts['port'] === 443
      || ($context instanceof SSLContext);
    if (self::isUv() && !$isSSL) {
      $isPipe = ($parts['scheme'] == 'file') || ($parts['scheme'] == 'unix');
      $address = $isPipe ? $parts['host'] : $ip;
      $server = self::bind($parts['scheme'], $address, $parts['port']);
    } else {
      $ctx = \stream_context_create();
      if ($isSSL)
        $context = ($context instanceof SSLContext)
          ? $context : yield create_ssl_context(
            \SERVER_AUTH,
            $parts['host'],
            (empty($context) ? [] : $context),
            $capath,
            $cafile,
            $caSelfDetails
          );

      if ($context instanceof OptionsInterface)
        $ctx = $context();

      $flags = $parts['scheme'] === 'udp' ? \STREAM_SERVER_BIND : \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN;
      #create a stream server on IP:Port
      $server = \stream_socket_server(
        $uri,
        $errNo,
        $errStr,
        $flags,
        $ctx
      );

      \stream_set_blocking($server, false);
    }

    if (!$server) {
      if (isset($errStr))
        throw new \RuntimeException('Failed to listen on "' . $uri . '": ' . $errStr, $errNo);
      else
        throw new \RuntimeException('Failed to listen on "' . $uri);
    }

    if ($context instanceof SSLContext) {
      $serverSocket = \socket_import_stream($server);
      \stream_socket_enable_crypto($server, false, $context->get_crypto());
    }

    \debugging_info("Listening to {$uri} for connections started at: " . \gmdate('D, d M Y H:i:s T') . \CRLF);
    if (!$server instanceof \UV)
      $server = ($context instanceof SSLContext) ? $context->wrap_socket($server, $serverSocket) : new Sockets($server);

    return $server;
  }

  public static function bind(string $scheme, string $address, int $port)
  {
    $uv = \coroutine()->getUV();
    $ip = (\strpos($address, ':') === false)
      ? \uv_ip4_addr($address, $port)
      : \uv_ip6_addr($address, $port);

    switch ($scheme) {
      case 'file':
      case 'unix':
        $handle = \uv_pipe_init($uv, \IS_WINDOWS);
        \uv_pipe_bind($handle, $address);
        break;
      case 'udp':
        $handle = \uv_udp_init($uv);
        \uv_udp_bind($handle, $ip);
        break;
      case 'tcp':
      case 'tls':
      case 'ftp':
      case 'ftps':
      case 'ssl':
      case 'http':
      case 'https':
      default:
        $handle = \uv_tcp_init($uv);
        \uv_tcp_bind($handle, $ip);
        break;
    }

    return $handle;
  }

  public static function accept($server)
  {
    if (self::isUv() && $server instanceof \UV) {
      return yield new Kernel(
        function (TaskInterface $task, CoroutineInterface $coroutine) use ($server) {
          $task->customData($server);
          $task->taskType('stateless');
          $coroutine->ioAdd();
          if ($server instanceof \UVUdp) {
            \uv_udp_recv_start($server, function ($stream, $status, $data) use ($task, $coroutine) {
              $task->customData($stream);
              $task->sendValue($data);
              $coroutine->ioRemove();
              $coroutine->schedule($task);
            });
          } else {
            $backlog = $server instanceof \UVTcp ? 1024 : 8192;
            \uv_listen($server, $backlog, function ($server, $status) use ($task, $coroutine) {
              $coroutine->ioRemove();
              $uv = $coroutine->getUV();
              if ($server instanceof \UVTcp) {
                $client = \uv_tcp_init($uv);
              } elseif ($server instanceof \UVPipe) {
                $client = \uv_pipe_init($uv, \IS_WINDOWS);
              }

              \uv_accept($server, $client);
              $task->customData($server);
              $task->sendValue($client);
              $coroutine->ioRemove();
              $coroutine->schedule($task);
            });
          }
        }
      );
    }

    if ($server instanceof SocketsInterface)
      return yield $server->accept();

    return false;
  }

  /**
   * Get the address of the remote connected handle.
   *
   * @param UVTcp|UVUdp|SocketsInterface $handle
   * @return string|bool
   */
  public static function peer($handle)
  {
    if ($handle instanceof \UVTcp) {
      $peer = \uv_tcp_getpeername($handle);
      return $peer['address'] . ':' . $peer['port'];
    } elseif ($handle instanceof \UVUdp) {
      $peer = \uv_udp_getsockname($handle);
      return $peer['address'] . ':' . $peer['port'];
    }

    if ($handle instanceof SocketsInterface)
      return $handle->get_peer();

    return false;
  }

  /**
   * Get the address of the local handle.
   *
   * @param UVTcp|SocketsInterface $handle
   * @return string|bool
   */
  public static function local($handle)
  {
    if ($handle instanceof \UVTcp) {
      $local = \uv_tcp_getsockname($handle);
      return $local['address'] . ':' . $local['port'];
    } elseif ($handle instanceof \UVUdp) {
      $peer = \uv_udp_getsockname($handle);
      return $peer['address'] . ':' . $peer['port'];
    }

    if ($handle instanceof SocketsInterface)
      return $handle->get_local();

    return false;
  }

  public static function connect(string $scheme, string $address, int $port, $data = null)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($scheme, $address, $port, $data) {
        $callback =  function ($client, $status) use ($task, $coroutine) {
          $coroutine->ioRemove();
          if (\is_int($status))
            $task->sendValue(($status < 0 ? false : $client));
          else
            $task->sendValue(($client < 0 ? false : $status));

          $coroutine->schedule($task);
        };

        $coroutine->ioAdd();
        $uv = $coroutine->getUV();
        $ip = (\strpos($address, ':') === false)
          ? @\uv_ip4_addr($address, $port)
          : @\uv_ip6_addr($address, $port);

        switch ($scheme) {
          case 'file':
          case 'unix':
            $client = \uv_pipe_init($uv, \IS_WINDOWS);
            \uv_pipe_connect($client, $address, $callback);
            break;
          case 'udp':
            $client = \uv_udp_init($uv);
            \uv_udp_send($client, $data, $ip, $callback);
            break;
          case 'tcp':
          case 'tls':
          case 'ftp':
          case 'ftps':
          case 'ssl':
          case 'http':
          case 'https':
          default:
            $client = \uv_tcp_init($uv);
            \uv_tcp_connect($client, $ip, $callback);
            break;
        }
      }
    );
  }
}
