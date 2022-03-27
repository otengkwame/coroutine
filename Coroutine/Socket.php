<?php

declare(strict_types=1);

namespace Async\Socket;

use Async\Networks;
use Async\SocketMessage;
use Async\Network\Sockets;
use Async\Network\SSLContext;
use Async\Network\SocketsInterface;
use Async\Network\OptionsInterface;

if (!\function_exists('messenger_for')) {

  \define('RESPONSE', 'response');
  \define('REQUEST', 'request');
  \define('SERVER_AUTH', 'server');
  \define('CLIENT_AUTH', 'client');

  /**
   * Get array with contents of [`parse_url, uri, ip, addrInfo`] about the given hostname, **addrInfo** is a `getaddrinfo`
   * structure and requires `PHP 7.2+` which uses `socket_addrinfo_lookup()`.
   * - This function needs to be prefixed with `yield`
   *
   * _Example:_ `print_r(yield Sockets::addrInfo('google.com', 'http', $hints))`
   * - $hints = `['ai_flags' => AI_ADDRCONFIG | AI_PASSIVE | AI_CANONNAME, 'ai_family' => AF_INET,
   * 'ai_socktype' => 0, 'ai_protocol' => 0]`
   *
   * _Returns:_
   *```md
   * -Array(
   * - [0] => Array (
   * -    [scheme] => tcp
   * -    [host] => google.com
   * -    [port] => 80
   * -  )
   * - [1] => tcp://google.com:80
   * - [2] => 172.217.0.46
   * - [3] => Array(
   * -    [ai_flags] => 0
   * -    [ai_family] => 2
   * -    [ai_socktype] => 1
   * -    [ai_protocol] => 0
   * -    [ai_canonname] => google.com
   * -    [ai_addr] => Array(
   * -      [sin_port] => 80
   * -      [sin_addr] => 172.217.0.46
   * -    )
   * -  )
   * -)
   *```
   * @param string|int|null $host — Hostname to search.
   * @param string|null $service The service to connect to. If service is a name,
   * it is translated to the corresponding port number.
   * @param array $hints Hints provide criteria for selecting addresses returned.
   * You may specify the hints as defined by getaddrinfo.
   * @return array[ parse_url, uri, ip, addrInfo ] |`false`
   */
  function net_getaddrinfo(string $host, ?string $service = null, array $hints = Sockets::ADDR_INFO)
  {
    return yield \await(getaddrinfo, $host, $service, $hints);
  }

  /**
   * Create a `SSLContext` object with default settings.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $purpose `CLIENT_AUTH` or `SERVER_AUTH`
   * @param string $name server `hostname`
   * @param resource|array|OptionsInterface $context A _resource_ `context` or create from _associate array_.
   * @param string $capath `directory` to hostname certificate
   * @param string $cafile hostname `certificate`
   * @param array $caSelfDetails - for a self signed certificate, will be created automatically if no hostname certificate available.
   *
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
   * @return SSLContext
   */
  function create_ssl_context(
    string $purpose,
    string $name = None,
    $context = [],
    string $capath = None,
    string $cafile = None,
    array $caSelfDetails = []
  ) {
    return SSLContext::create_default_context($purpose, $name, $context, $capath, $cafile, $caSelfDetails);
  }

  /**
   * Get the IP `address` corresponding to Internet host name.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $hostname
   * @param bool $useUv
   *
   * @return string|bool
   */
  function dns_address(string $hostname, bool $useUv = true)
  {
    return Networks::gethostbyname($hostname, $useUv);
  }

  /**
   * Get the Internet host `name` corresponding to IP address.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $ipAddress
   *
   * @return string|bool
   */
  function dns_name(string $ipAddress)
  {
    return Networks::gethostbyaddr($ipAddress);
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
  function dns_record(string $hostname, int $options = \DNS_A)
  {
    return Networks::dns_get_record($hostname, $options);
  }

  /**
   * Add a `listen` task handler for the **socket/stream** server, that's continuously monitored.
   *
   * This function will return `int` immediately, use with `net_listen()`.
   * - The `$handler` function will be executed every time on new incoming connections or data.
   * - Expect the `$handler` to receive `(resource|Socket|\UVStream $newConnection)`.
   *
   *  Or
   * - Expect the `$handler` to receive `($data)`. If `UVUdp`
   * - This function needs to be prefixed with `yield`
   *
   * @param callable $handler
   *
   * @return int
   */
  function listen_task(callable $handler)
  {
    return Networks::listenerTask($handler);
  }

  /**
   * Stop/close the `net_listen` server __listening__ for new connection on specified `listen_task` **ID**.
   * - This function needs to be prefixed with `yield`
   *
   * @param int $listen_task ID
   */
  function net_stop(int $listen_task)
  {
    return yield Networks::stop($listen_task);
  }

  /**
   * - This function needs to be prefixed with `yield`
   */
  function net_listen(
    $handle,
    int $handlerTask,
    int $backlog = 0,
    string $ssl_path = None,
    string $ssl_file = None,
    $options = null,
    array $self_Details = []
  ) {
    if (((string) (int) $handle === (string) $handle) || \is_string($handle)) {
      $handle = yield net_server($handle, $ssl_path, $ssl_file, $options, $self_Details);
    }

    return yield Networks::listen($handle, $handlerTask, $backlog);
  }

  /**
   * - This function needs to be prefixed with `yield`
   *
   * @param string|null|int $uri
   * @param SSLContext $context
   *
   * @return resource|\UVTcp|\UVUdp|\UVPipe|SocketsInterface
   */
  function net_client($uri, $context = null)
  {
    return Networks::client($uri, $context);
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
  function net_server(
    $uri = null,
    OptionsInterface $context = null,
    string $capath = None,
    string $cafile = None,
    array $caSelfDetails = []
  ) {
    // Let's ensure we have optimal performance.
    \date_default_timezone_set('America/New_York');
    return Networks::server($uri, $context, $capath, $cafile, $caSelfDetails);
  }

  /**
   * - This function needs to be prefixed with `yield`
   * @param UVTcp|UVUdp|SocketsInterface $handle
   * @codeCoverageIgnore
   */
  function net_accept($handle)
  {
    return Networks::accept($handle);
  }

  /**
   * - This function needs to be prefixed with `yield`
   *
   * @internal
   *
   * @codeCoverageIgnore
   */
  function net_connect(string $scheme, string $address, int $port, $data = null)
  {
    return Networks::connect($scheme, $address, $port, $data);
  }

  /**
   * @internal
   *
   * @codeCoverageIgnore
   */
  function net_bind(string $scheme, string $address, int $port)
  {
    return Networks::bind($scheme, $address, $port);
  }

  /**
   * - This function needs to be prefixed with `yield`
   * @param UVTcp|UVUdp|SocketsInterface $handle
   */
  function net_read($handle, int $size = -1)
  {
    return Networks::read($handle, $size);
  }

  /**
   * - This function needs to be prefixed with `yield`
   * @param UVTcp|UVUdp|SocketsInterface $handle
   */
  function net_write($handle, string $response = '')
  {
    return Networks::write($handle, $response);
  }

  /**
   * - This function needs to be prefixed with `yield`
   * @param UVTcp|UVUdp|SocketsInterface $handle
   */
  function net_close($handle)
  {
    return Networks::close($handle);
  }

  /**
   * Get the address of the remote connected `socket/stream`.
   *
   * @param UVTcp|UVUdp|SocketsInterface $handle
   * @return string|bool
   */
  function net_peer($handle)
  {
    return Networks::peer($handle);
  }

  /**
   * Get the address of the local connected `socket/stream`.
   *
   * @param UVTcp|UVUdp|SocketsInterface $handle
   * @return string|bool
   */
  function net_local($handle)
  {
    return Networks::local($handle);
  }

  /**
   * Construct a new request string.
   *
   * @param object|SocketMessage $object
   * @param string $method
   * @param string $path
   * @param string|null $type
   * @param string|null $data
   * @param array $extra additional headers - associative array
   *
   * @return string
   */
  function net_request(
    $object,
    string $method = 'GET',
    string $path = '/',
    ?string $type = 'text/html',
    $data = null,
    array ...$extra
  ): string {
    if (
      $object instanceof SocketMessage
      || (\is_object($object) && \method_exists($object, 'request'))
    ) {
      return $object->request($method, $path, $type, $data, ...$extra);
    }
  }

  /**
   * Construct a new response string.
   *
   * @param object|SocketMessage $object
   * @param string $body defaults to `Not Found`, if empty and `$status`
   * @param int $status defaults to `404`, if empty and `$body`, otherwise `200`
   * @param string|null $type
   * @param array $extra additional headers - associative array
   *
   * @return string
   */
  function net_response(
    $object,
    ?string $body = null,
    ?int $status = null,
    ?string $type = 'text/html',
    array ...$extra
  ): string {
    if (
      $object instanceof SocketMessage
      || (\is_object($object) && \method_exists($object, 'response'))
    ) {
      return $object->response($body, $status, $type, ...$extra);
    }
  }

  /**
   * Returns and `SocketMessage` instance. A simple generic class for handling/constructing **client/server**
   * messages, following the https://tools.ietf.org/html/rfc2616.html specs.
   * - For use with `net_response()` and `net_request()`.
   *
   * @param string $action either RESPONSE or REQUEST
   * @param string $hostname for `Host:` header request, this will be ignored on `path/url` setting
   * @param string $protocol version for `HTTP/` header
   *
   * @return SocketMessage
   */
  function messenger_for(string $action, string $hostname = '', float $protocol = 1.1): SocketMessage
  {
    return new SocketMessage($action, $hostname, $protocol);
  }
}
