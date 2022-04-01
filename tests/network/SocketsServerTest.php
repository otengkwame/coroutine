<?php

namespace Async\Tests;

use function Async\Socket\{
  net_accept,
  net_client,
  net_close,
  net_read,
  net_response,
  net_server,
  net_write
};

use Async\SocketMessage;
use Async\Network\Sockets;
use PHPUnit\Framework\TestCase;

class SocketsServerTest extends TestCase
{
  protected $loopController = true;
  protected $taskId = null;

  protected function setUp(): void
  {
    \coroutine_clear();
  }

  public function taskServer($port)
  {
    \error_reporting(-1);
    \ini_set("display_errors", 1);

    $this->expectOutputRegex('/[Listening to ' . $port . 'for connections]/');
    $serverInstance = yield net_server($port);
    $this->assertTrue((\IS_WINDOWS || \IS_PHP8 ? $serverInstance instanceof Sockets : $serverInstance instanceof \UV));

    $fakeClientSkipped = false;
    while ($this->loopController) {
      if (!$fakeClientSkipped) {
        $fakeClientSkipped = true;
        // Will connection to this server in .005 seconds.
        yield \away($this->taskFakeClientCommand($port));
      }

      // Will pause current task and wait for connection, all others tasks will continue to run
      $connectedServer = yield net_accept($serverInstance);
      $this->assertTrue((\IS_WINDOWS || \IS_PHP8 ? $connectedServer instanceof Sockets : $connectedServer instanceof \UV));
      // Once an connection is made, will create new task and continue execution there, will not block
      yield \away($this->taskHandleClient($connectedServer));
    }

    yield net_close($serverInstance);
  }

  public function taskFakeClientCommand($port)
  {
    yield \sleep_for(.005);
    #Connect to Server
    $client = yield net_client($port);
    #Send a command
    $wrote = yield net_write($client, 'hi');
    if (!$client instanceof \UV)
      $this->assertEquals(2, $wrote);

    #Receive response from server. Loop until the response is finished
    $response = yield net_read($client);
    $this->assertEquals('Hello, This is our command run!', $response);

    yield net_close($client);
    // make an new client connection to this server.
    yield \away($this->taskFakeClientDefault($port));
  }

  public function taskFakeClientDefault($port)
  {
    #Connect to Server
    $client = yield net_client($port);
    #Send a command
    yield net_write($client, 'help');
    #Receive response from server. Loop until the response is finished
    $response = yield net_read($client);
    $this->assertEquals('string', \is_type($response));
    $this->assertRegExp('/[The file you requested does not exist. Sorry!]/', $response);

    yield net_close($client);
    // make an new client connection to this server.
    yield \away($this->taskFakeClientExit($port));
  }

  public function taskFakeClientExit($port)
  {
    $this->loopController = false;
    #Connect to Server
    $client = yield net_client($port);
    #Send a command
    yield net_write($client, 'exit');
    yield net_close($client);
  }

  public function taskHandleClient($server)
  {
    yield \stateless_task();
    $data = yield net_read($server);
    $this->assertEquals('string', \is_type($data));

    switch ($data) {
        #exit command will cause this script to quit out
      case 'exit';
        print "exit command received \n";
        $this->loopController = false;
        break;
        #hi command
      case 'hi';
        #write back to the client a response.
        $written = yield net_write($server, 'Hello, This is our command run!');
        $this->assertEquals('int', \is_type($written));
        print "hi command received \n";
        break;
      default:
        $responser = new SocketMessage('response');
        $output = net_response($responser, 'The file you requested does not exist. Sorry!', 404);
        $this->assertEquals('string', \is_type($output));
        yield net_write($server, $output);
    }

    yield net_close($server);
    $this->assertFalse(yield net_write($server, 'null'));
    $this->assertFalse(yield net_read($server));
  }

  public function testServer()
  {
    \coroutine_run($this->taskServer((\IS_WINDOWS ? 9199 : 9099)));
  }
}
