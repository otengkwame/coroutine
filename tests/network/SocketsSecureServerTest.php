<?php

namespace Async\Tests;

use function Async\Socket\{
  create_ssl_context,
  net_accept,
  net_client,
  net_close,
  net_local,
  net_server,
};

use Async\Network\SSLSockets;
use Async\Network\OptionsInterface;
use Async\Network\SSLContext;
use PHPUnit\Framework\TestCase;

class SocketsSecureServerTest extends TestCase
{
  protected $loopController = true;
  protected $taskId = null;

  protected function setUp(): void
  {
    \coroutine_clear();
  }

  public function taskSecureServer($port)
  {
    $this->expectOutputRegex('/[Listening to ' . $port . 'for connections]/');
    $context = yield create_ssl_context(\SERVER_AUTH);
    $this->assertTrue($context instanceof OptionsInterface);
    $this->assertEquals('ssl', $context->get_type());

    $server = yield net_server($port, $context);
    $this->assertTrue($server instanceof SSLSockets);

    $ip = net_local($server);
    $this->assertEquals('string', \is_type($ip));

    // Will connection to this server in .005 seconds.
    yield \away($this->taskFakeSecureClientCommand($port));

    // Will pause current task and wait for connection, all others tasks will continue to run
    $connected = yield net_accept($server);
    $this->assertTrue($connected instanceof SSLSockets);

    yield net_close($server);
  }

  public function taskFakeSecureClientCommand($port)
  {
    yield \sleep_for(.005);
    try {
      #Connect to Server
      $client = yield net_client($port, yield create_ssl_context(\CLIENT_AUTH));
      $this->assertFalse($client instanceof SSLSockets);
      yield net_close($client);
    } catch (\RuntimeException $th) {
      $this->assertRegExp('/[Failed to enable socket encryption: stream_socket_enable_crypto(): SSL: ]/', $th->getMessage());
    }
  }

  public function testSecureServer()
  {
    \coroutine_run($this->taskSecureServer((\IS_WINDOWS ? 9290 : 9190)));
  }
}
