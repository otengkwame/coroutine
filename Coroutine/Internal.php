<?php

declare(strict_types=1);

if (!\function_exists('array_key_first')) {
  function array_key_first(array $arr)
  {
    return \array_keys($arr)[0];
  }
}

if (!\function_exists('is_type')) {
  /**
   * Returns `array` with normalize keys (without prefix) from a _class_ `instance` recursively.
   *
   * @param object $value
   * @return array
   */
  function to_array_recursive($value): array
  {
    if (!\is_object($value)) {
      return (array) $value;
    }

    $class = \get_class($value);
    $arr = [];
    foreach ((array)  $value as $key => $val) {
      $key = \str_replace(["\0*\0", "\0{$class}\0"], '', $key);
      $arr[$key] = \is_object($val) ? \to_array_recursive($val) : $val;
    }

    return $arr;
  }

  /**
   * Returns `array` with normalize keys (without prefix) from a _class_ `instance`.
   *
   * @param object $value
   * @return array
   */
  function to_array($value): array
  {
    $arr = (array) $value;
    if (!\is_object($value)) {
      return $arr;
    }

    $class = \get_class($value);
    $keys = \str_replace(["\0*\0", "\0{$class}\0"], '', \array_keys($arr));
    return \array_combine($keys, $arr);
  }

  /**
   * Return the `string` of a variable type, or does a check, compared with string of the `type`.
   * Types are: `callable`, `string`, `int`, `float`, `null`, `bool`, `array`, `scalar`,
   * `object`, or `resource`
   *
   * @param mixed $variable
   * @param string|null $type
   * @return string|bool
   */
  function is_type($variable, string $type = null)
  {
    $checks = [
      'is_callable' => 'callable',
      'is_string' => 'string',
      'is_integer' => 'int',
      'is_float' => 'float',
      'is_null' => 'null',
      'is_bool' => 'bool',
      'is_scalar' => 'scalar',
      'is_array' => 'array',
      'is_object' => 'object',
      'is_resource' => 'resource',
    ];

    foreach ($checks as $func => $val) {
      if ($func($variable)) {
        return (empty($type)) ? $val : ($type == $val);
      }
    }

    // @codeCoverageIgnoreStart
    return 'unknown';
    // @codeCoverageIgnoreEnd
  }

  /**
   * Get array with contents of [`parse_url, uri, ip, addrInfo`] about the given hostname, **addrInfo** is a `getaddrinfo`
   * structure and requires `PHP 7.2+` which uses `socket_addrinfo_lookup()`.
   *
   * _Example:_ `print_r(getaddrinfo('google.com', 'http', $hints))`
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
   * @param string|null $service The service to connect to. If service is a name, it is translated to the corresponding port number.
   * @param array $hints Hints provide criteria for selecting addresses returned. You may specify the hints as defined by getadrinfo.
   * @return array[ parse_url, uri, ip, addrInfo ] |`false`
   */
  function getaddrinfo($host = null, ?string $service = null, array $hints = [])
  {
    $address = $host;
    $uri = (empty($host) || $host === 'localhost') ? 0 : $host;
    $ip = null;
    // a single port has been given => assume localhost
    if ((string) (int) $uri === (string) $uri) {
      $hostname = \gethostname();
      $ip = \gethostbyname($hostname);
      // @codeCoverageIgnoreStart
      if (!\is_int(\ip2long($ip)))
        throw new \Exception('Could not attain hostname IP!');
      // @codeCoverageIgnoreEnd

      $uri = $ip . ':' . $uri;
    }

    // assume default scheme if none has been given
    if (\strpos($uri, '://') === false) {
      $uri = 'tcp://' . $uri;
    }

    // parse_url() does not accept null ports (random port assignment) => manually remove
    if (\substr($uri, -2) === ':0') {
      $parts = \parse_url(\substr($uri, 0, -2));
      if ($parts) {
        $parts['port'] = 0;
      }
    } else {
      $parts = \parse_url($uri);
      if (!isset($parts['port'])) {
        $parts['port'] = isset($parts['scheme']) ? \getservbyname($parts['scheme'], 'tcp') : 0;
        $parts['port'] = !$parts['port'] && !empty($service) ? \getservbyname($service, 'tcp') : $parts['port'];
        $uri = $uri . ':' . $parts['port'];
      }
    }

    // ensure URI contains scheme, host and port
    if (
      !$parts || !isset($parts['scheme'], $parts['host'], $parts['port'])
      || !\in_array($parts['scheme'], ['file', 'tcp', 'tls', 'http', 'https', 'ssl', 'udp', 'unix', 'ws', 'wss'])
    ) {
      throw new \InvalidArgumentException('Invalid URI "' . $uri . '" given');
    } elseif (\in_array($parts['scheme'], ['ws', 'wss']) && !$parts['port']) {
      throw new \InvalidArgumentException('Invalid URI "' . $uri . '" given has no PORT assigned for `' . $parts['scheme'] . '`');
    }

    $host = \trim($parts['host'], '[]');
    $isScheme = ($parts['scheme'] == 'file') || ($parts['scheme'] == 'unix') && (bool) \filter_var($host, \FILTER_VALIDATE_DOMAIN);
    if (\ip2long($host) || (\strpos($host, ':') !== false) || @\inet_pton($host)) {
      if (false === \filter_var($host, \FILTER_VALIDATE_IP))
        throw new \InvalidArgumentException('Given URI "' . $uri . '" does not contain a valid host IP');
    } elseif (false === \filter_var($host, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME)) {
      if ($isScheme === false)
        throw new \InvalidArgumentException('Given URI "' . $uri . '" does not contain a valid host DOMAIN');
    }

    if ($ip === null)
      $ip = \gethostbyname($parts['host']);

    $uri = \str_replace(['https', 'http', 'wss', 'ws'], 'tcp', $uri);
    if ($parts['port'] === 0 && \strpos($uri, ':0') === false) {
      $uri = $uri . ':0';
    }

    $addrInfo = null;
    if ((float) \phpversion() >= 7.2) {
      $addrInfo = @\socket_addrinfo_lookup(($address === 'localhost' ? $address : $host),
        (empty($service) ? (string)$parts['port'] : $service),
        $hints
      );

      if (\is_array($addrInfo)) {
        $addrInfo = \socket_addrinfo_explain(\reset($addrInfo));
      }
    }

    return [$parts, $uri, $ip, $addrInfo];
  }

  /**
   * Get array with contents of [`parse_url, uri, ip, addrInfo`] about the given hostname, **addrInfo** is a `getaddrinfo`
   * structure and requires `PHP 7.2+` which uses `socket_addrinfo_lookup()`.
   */
  \define('getaddrinfo', 'getaddrinfo');
}

use PHPUnit\Framework\TestCase;

if (!\function_exists('test_raises')) {
  /**
   * Assertions about _raised_ **throw** exceptions.
   *
   * @param TestCase $test
   * @param string $exception
   * @param callable $function
   * @param mixed ...$arguments
   * @see https://docs.pytest.org/en/6.2.x/reference.html#pytest-raises
   */
  function test_raises(TestCase $test, string $exception, callable $function, ...$arguments)
  {
    $test->expectException($exception);
    return $function(...$arguments);
  }

  /**
   * Assertions about _raised_ **throw** exceptions.
   * - This function needs to be prefixed with `yield`
   *
   * @param TestCase $test
   * @param string $exception
   * @param callable $function
   * @param mixed ...$arguments
   * @see https://docs.pytest.org/en/6.2.x/reference.html#pytest-raises
   */
  function test_raises_async(TestCase $test, string $exception, callable $function, ...$arguments)
  {
    $test->expectException($exception);
    return yield $function(...$arguments);
  }

  function fib(int $n)
  {
    if ($n <= 2)
      return 1;
    else
      return fib($n - 1) + fib($n - 2);
  }
}
