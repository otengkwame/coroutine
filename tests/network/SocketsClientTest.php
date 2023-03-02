<?php

namespace Async\Tests;

use function Async\Socket\{net_client, net_close, net_peer, net_read, net_write};

use Async\SocketMessage;
use Async\Network\Sockets;
use PHPUnit\Framework\TestCase;

class SocketsClientTest extends TestCase
{
  protected function setUp(): void
  {
    \coroutine_clear();
  }

  public function taskClient($hostname, $port = 80, $command = '/')
  {
    #Connect to Server
    $client = yield net_client("$hostname:$port");
    $this->assertTrue((!\IS_UV || \IS_PHP8 ? $client instanceof Sockets : $client instanceof \UV));

    if ($client instanceof \UV) {
      $request = new SocketMessage('request', $hostname);
      $command = $request->request('get', $command);
    }

    #Send a command
    $written = yield net_write($client, $command);
    $this->assertEquals('int', \is_type($written));

    $remote = net_peer($client);
    $this->assertEquals('string', \is_type($remote));

    #Receive response from server. Loop until the response is finished
    $response = yield net_read($client);
    $this->assertEquals('string', \is_type($response));

    if ($client instanceof \UV) {
      $request->parse($response);
      $this->assertEquals('array', \is_type($request->getHeader('all')));
      $this->assertEquals('array', \is_type($request->getParameter('all')));
      $this->assertEquals('string', \is_type($request->getProtocol()));
      $this->assertEquals('int', \is_type($request->getCode()));
      $this->assertEquals('string', \is_type($request->getMessage()));
      $this->assertEquals('string', \is_type($request->getUri()));
    }

    #close connection
    yield net_close($client);
    $this->assertFalse(yield net_write($client));
    $this->assertFalse(yield net_read($client));
  }

  public function testClientAgain()
  {
    \coroutine_run($this->taskClient('msn.com', 80, '/'));
  }
}
