<?php

namespace Async\Tests;

use function Async\Socket\{net_client, net_close, net_peer, net_read, net_write, create_ssl_context, dns_name};

use Async\SocketMessage;
use Async\Network\SSLSockets;
use PHPUnit\Framework\TestCase;

class SocketsSecureClientTest extends TestCase
{
  protected function setUp(): void
  {
    \coroutine_clear();
  }

  public function taskClient($hostname, $port, $command = '/')
  {
    $contextOptions = yield create_ssl_context(\CLIENT_AUTH);
    #Connect to Server
    #Start SSL
    $client = yield net_client("$hostname:$port", $contextOptions);
    $this->assertTrue($client instanceof SSLSockets);

    if (!\IS_PHP8)
      $this->assertTrue(\is_resource($client->getPeerCert()));
    else
      $this->assertTrue(\is_object($client->getPeerCert()));

    $this->assertTrue($client->verifyPeerCert('facebook.com'));

    #Send a command
    $written = yield net_write($client, $command);
    $this->assertEquals('int', \is_type($written));

    $remote = net_peer($client);
    $this->assertEquals('string', \is_type($remote));

    #Receive response from server. Loop until the response is finished
    $response = yield net_read($client);
    $this->assertEquals('string', \is_type($response));

    #close connection
    yield net_close($client);
    $this->assertFalse(yield net_write($client));
    $this->assertFalse(yield net_read($client));
  }

  public function testClient()
  {
    \coroutine_run($this->taskClient('https://facebook.com', 443, '/'));
  }
}
