<?php

namespace Async\Tests;

use function Async\Socket\{
  create_ssl_context,
  listen_task,
  net_accept,
  net_client,
  net_close,
  net_listen,
  net_local,
  net_read,
  net_response,
  net_server,
  net_stop,
  net_write
};

use Async\Network\Sockets;
use Async\Network\SocketsInterface;
use Async\Network\SSLSockets;
use Async\SocketMessage;
use PHPUnit\Framework\TestCase;

class SocketsListenServerTest extends TestCase
{
  protected $taskId = null;

  protected function setUp(): void
  {
    \coroutine_clear(false);
  }

  public function taskListen($client)
  {
    $this->assertTrue((\IS_WINDOWS || \IS_PHP8 ? $client instanceof SocketsInterface : $client instanceof \UV));
    yield net_stop($this->taskId);
  }

  public function taskListenClientCommand($port)
  {
    yield \sleep_for(.005);
    #Connect to Server
    try {
      $client = yield net_client($port);
      $this->assertTrue((\IS_WINDOWS || \IS_PHP8 ? $client instanceof SocketsInterface : $client instanceof \UV));
    } catch (\RuntimeException $e) {
      $this->assertRegExp('/[Failed to connect to: tcp:]/', $e->getMessage());
      yield shutdown();
    }
  }

  public function taskServerListen($port)
  {
    $lid = yield listen_task([$this, 'taskListen']);
    $this->taskId = $lid;
    $this->expectOutputRegex('/[Listening to ' . $port . 'for connections]/');

    // Will connection to this server in .005 seconds.
    yield \away($this->taskListenClientCommand($port));
    $exited = yield net_listen($port, $lid);
    $this->assertTrue(\is_bool($exited));
    $this->expectOutputRegex('/[Listening stopped at: ]/');
  }

  public function testServerListen()
  {
    \coroutine_run($this->taskServerListen((\IS_WINDOWS ? 9398 : (\IS_MACOS ? 7089 : 6290))));
  }

  public function taskFakeClientCommand($port)
  {
    yield \sleep_for(.005);
    #Connect to Server
    $client = yield net_client($port);
    $this->assertTrue((\IS_WINDOWS || \IS_PHP8 ? $client instanceof SocketsInterface : $client instanceof \UV));
    #Send a command
    $wrote = yield net_write($client, 'hi');
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
        yield net_stop($this->taskId);
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
}
