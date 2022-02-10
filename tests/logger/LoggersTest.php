<?php

namespace Async\Tests;

use Async\Logger;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LoggersTest extends TestCase
{
  protected $dest = 'stdout2.log';
  protected $testFile = 'logger-test2.log';

  protected function setUp(): void
  {
    \coroutine_clear(false);

    if (\file_exists(__DIR__ . \DS . $this->dest)) {
      unlink(__DIR__ . \DS . $this->dest);
    }

    if (\file_exists(__DIR__ . \DS . $this->testFile)) {
      unlink(__DIR__ . \DS . $this->testFile);
    }
  }

  protected function tearDown(): void
  {
    if (\file_exists(__DIR__ . \DS . $this->dest)) {
      unlink(__DIR__ . \DS . $this->dest);
    }

    if (\file_exists(__DIR__ . \DS . $this->testFile)) {
      unlink(__DIR__ . \DS . $this->testFile);
    }
  }

  public function taskGlobalCreate()
  {
    $log = \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile);
    $this->assertInstanceOf(Logger::class, $log);
    $this->assertSame($log, \logger_instance());
    $this->assertFileExists(__DIR__ . \DS . $this->testFile);
    yield \logger_shutdown();
    $this->assertEquals([], \logger_arrayLogs());
  }

  public function testGlobalCreate()
  {
    \coroutine_run($this->taskGlobalCreate());
  }

  public function taskGlobalLogWithoutContext()
  {
    \logger_create("log-app");
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::DEBUG, 1, null, "log-app");
    yield \logger(LogLevel::DEBUG, 'A log message', [], "log-app");

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} DEBUG A log message]/', $content);
    yield \logger_shutdown();
  }

  public function testGlobalLogWithoutContext()
  {
    \coroutine_run($this->taskGlobalLogWithoutContext());
  }

  public function taskGlobalLogWithContext()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::WARNING, 2);
    yield \logger(LogLevel::WARNING, 'Hello {name}', array('name' => 'World'));
    yield \logger(LogLevel::WARNING, 'hi {name}', array('name' => 'Planet'));

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} WARNING Hello World]/', $content);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} WARNING Hi Planet]/', $content);
    yield \logger_shutdown();
  }

  public function testGlobalLogWithContext()
  {
    \coroutine_run($this->taskGlobalLogWithContext());
  }

  public function taskGlobalLogEmergencyUniqid()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::EMERGENCY);
    \logger_uniqueId();
    yield \log_emergency('This is an emergency with {unique_id}');
    yield \logger_commit();

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    /**
     * @see http://txt2re.com/
     */
    $re1 = '(\\[.*?\\])';    # Square Braces 1
    $re2 = '(\\s+)';    # White Space 1
    $re3 = '(\\(.*\\))';    # Round Braces 1
    $re4 = '(.)';    # Any Single Character 1
    $re5 = '(\\s+)';    # White Space 2
    $re6 = '((?:[a-z][a-z]+))';    # Word 1
    $re7 = '(\\s+)';    # White Space 3
    $re8 = '(.)';    # Any Single Character 2
    $re9 = '((?:[a-z][a-z]+))';    # Word 2
    $re10 = '(\\s+)';    # White Space 4
    $re11 = '((?:[a-z][a-z]+))';    # Word 3
    $re12 = '(\\s+)';    # White Space 5
    $re13 = '((?:[a-z][a-z]+))';    # Word 4
    $re14 = '(\\s+)';    # White Space 6
    $re15 = '((?:[a-z][a-z]+))';    # Word 5
    $re16 = '(\\s+)';    # White Space 7
    $re17 = '((?:[a-z][a-z]+))';    # Word 6
    $re18 = '(\\s+)';    # White Space 8
    $re19 = '.*?';    # Non-greedy match on filler
    $re20 = '((?:[a-z][a-z]*[0-9]+[a-z0-9]*))';    # Alphanumeric 1

    $this->assertRegExp(
      "/" . $re1 . $re2 . $re3 . $re4 . $re5 . $re6 . $re7 . $re8 . $re9 . $re10 . $re11 . $re12 . $re13 . $re14 . $re15 . $re16 . $re17 . $re18 . $re19 . $re20 . "/is",
      $content
    );
    yield \logger_close();
  }

  public function testGlobalLogEmergencyUniqid()
  {
    \coroutine_run($this->taskGlobalLogEmergencyUniqid());
  }

  public function taskGlobalLogAlert()
  {
    \logger_create("log-app");
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::ALERT, 1, null, "log-app");
    \logger_pid("log-app");
    yield \log_alert('This is an alert with {pid}', "log-app");
    yield \logger_commit('log-app');

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    /**
     * @see http://txt2re.com/
     */
    $re1 = '(\\[.*?\\])';    # Square Braces 1
    $re2 = '(\\s+)';    # White Space 1
    $re3 = '(\\(.*\\))';    # Round Braces 1
    $re4 = '(.)';    # Any Single Character 1
    $re5 = '(\\s+)';    # White Space 2
    $re6 = '((?:[a-z][a-z]+))';    # Word 1
    $re7 = '(\\s+)';    # White Space 3
    $re8 = '(.)';    # Any Single Character 2
    $re9 = '((?:[a-z][a-z]+))';    # Word 2
    $re10 = '(\\s+)';    # White Space 4
    $re11 = '((?:[a-z][a-z]+))';    # Word 3
    $re12 = '(\\s+)';    # White Space 5
    $re13 = '((?:[a-z][a-z]+))';    # Word 4
    $re14 = '(\\s+)';    # White Space 6
    $re15 = '((?:[a-z][a-z]+))';    # Word 5
    $re16 = '(\\s+)';    # White Space 7
    $re17 = '((?:[a-z][a-z]+))';    # Word 6
    $re18 = '(\\s+)';    # White Space 8
    $re19 = '.*?';    # Non-greedy match on filler
    $re20 = '(\\d+)';    # Integer Number 1
    $this->assertRegExp(
      "/" . $re1 . $re2 . $re3 . $re4 . $re5 . $re6 . $re7 . $re8 . $re9 . $re10 . $re11 . $re12 . $re13 . $re14 . $re15 . $re16 . $re17 . $re18 . $re19 . $re20 . "/is",
      $content
    );
    yield \logger_close("log-app");
  }

  public function testGlobalLogAlert()
  {
    \coroutine_run($this->taskGlobalLogAlert());
  }

  public function taskGlobalLogCritical()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::CRITICAL);
    \logger_timestamp(true);
    yield \log_critical('This is a critical situation happened at {timestamp}');

    yield \logger_commit();

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    /**
     * @see http://txt2re.com/
     */
    $re1 = '(\\[.*?\\])';    # Square Braces 1
    $re2 = '(\\s+)';    # White Space 1
    $re3 = '(\\(.*\\))';    # Round Braces 1
    $re4 = '(.)';    # Any Single Character 1
    $re5 = '(\\s+)';    # White Space 2
    $re6 = '((?:[a-z][a-z]+))';    # Word 1
    $re7 = '(\\s+)';    # White Space 3
    $re8 = '(.)';    # Any Single Character 2
    $re9 = '((?:[a-z][a-z]+))';    # Word 2
    $re10 = '(\\s+)';    # White Space 4
    $re11 = '((?:[a-z][a-z]+))';    # Word 3
    $re12 = '(\\s+)';    # White Space 5
    $re13 = '((?:[a-z][a-z0-9_]*))';    # Variable Name 1
    $re14 = '(\\s+)';    # White Space 6
    $re15 = '((?:[a-z][a-z]+))';    # Word 4
    $re16 = '(\\s+)';    # White Space 7
    $re17 = '((?:[a-z][a-z]+))';    # Word 5
    $re18 = '(\\s+)';    # White Space 8
    $re19 = '((?:[a-z][a-z]+))';    # Word 6
    $re20 = '(\\s+)';    # White Space 9
    $re21 = '((?:[a-z][a-z]+))';    # Word 7
    $re22 = '(\\s+)';    # White Space 10
    $re23 = '([+-]?\\d*\\.\\d+)(?![-+0-9\\.])';    # Float 1
    $this->assertRegExp(
      "/" . $re1 . $re2 . $re3 . $re4 . $re5 . $re6 . $re7 . $re8 . $re9 . $re10 . $re11 . $re12 . $re13 . $re14 . $re15 . $re16 . $re17 . $re18 . $re19 . $re20 . $re21 . $re22 . $re23 . "/is",
      $content
    );
    yield logger_close();
  }

  public function testGlobalLogCritical()
  {
    \coroutine_run($this->taskGlobalLogCritical());
  }

  public function taskGlobalLogError()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::ERROR);
    yield \log_error('This is an error');

    yield \logger_commit();

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} ERROR This is an error]/',
      $content
    );
    yield \logger_close();
  }

  public function testGlobalLogError()
  {
    \coroutine_run($this->taskGlobalLogError());
  }

  public function taskGlobalLogWarning()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::WARNING);
    yield \log_warning('This is a warning');

    yield \logger_commit();

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} WARNING This is a warning]/',
      $content
    );
    yield \logger_close();
  }

  public function testGlobalLogWarning()
  {
    \coroutine_run($this->taskGlobalLogWarning());
  }

  public function taskGlobalLogNotice()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::NOTICE);
    yield \log_notice('This is just a notice');

    yield \logger_commit();

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} NOTICE This is just a notice]/',
      $content
    );
    yield \logger_close();
  }

  public function testGlobalLogNotice()
  {
    \coroutine_run($this->taskGlobalLogNotice());
  }

  public function taskGlobalLogInfo()
  {
    \logger_create();
    yield \logger_stream(__DIR__ . \DS . $this->testFile, Logger::INFO);
    \logger_phpSapi();
    \logger_phpVersion();
    yield \logger_memoryUsage('MB');
    yield \log_info('This is an information memory usage {memory_usage}, sapi {php_sapi}, php {php_version}');

    yield \logger_commit();

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp("/[This is an information memory usage]/is",    $content);
    yield \logger_close();
  }

  public function testGlobalLogInfo()
  {
    \coroutine_run($this->taskGlobalLogInfo());
  }

  public function taskLogDebug()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::DEBUG);
    yield \gather($log->debug('This is a debug message'));

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} DEBUG This is a debug message]/',
      $content
    );
    yield $log->close();
  }

  public function testLogDebug()
  {
    \coroutine_run($this->taskLogDebug());
  }

  public function taskGlobalExceptionInContext()
  {
    $logger = \logger_create();
    \logger_array(Logger::ALL, 1, function ($level, $message) {
      return "$level $message";
    });
    $exceptionMsg = 'exceptional!';
    $exception = new \Exception($exceptionMsg);
    $input = 'foo %exception% foo';
    $context = ['exception' => $exception];
    $expected = 'foo ' . $exceptionMsg . ' foo';
    yield \log_emergency($input, $context);

    yield \logger_commit();

    $this->assertNotEmpty($expected, $this->getOnlyLoggedMessage($logger));
    $this->assertCount(1, \logger_arrayLogs());
    yield \logger_close();
  }

  public function testGlobalExceptionInContext()
  {
    \coroutine_run($this->taskGlobalExceptionInContext());
  }

  public function taskGlobalThrowsInvalidArgumentExceptionWhenNull()
  {
    \logger_create();
    $this->expectException(InvalidArgumentException::class);
    yield \logger_mail('');
    yield \logger_close();
  }

  public function testGlobalThrowsInvalidArgumentExceptionWhenNull()
  {
    \coroutine_run($this->taskGlobalThrowsInvalidArgumentExceptionWhenNull());
  }

  public function taskGlobalThrowsInvalidArgumentException()
  {
    \logger_create();
    $this->expectException(InvalidArgumentException::class);
    yield \logger_mail('foo');
    yield \logger_close();
  }

  public function testGlobalThrowsInvalidArgumentException()
  {
    \coroutine_run($this->taskGlobalThrowsInvalidArgumentException());
  }

  public function taskGlobalErrorLog()
  {
    \logger_create();
    \ini_set('error_log', $this->dest);
    \logger_errorLog();
    yield \log_debug('This is a debug message');
    yield \logger_commit();
    $content = \file_get_contents($this->dest);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} DEBUG This is a debug message]/', $content);
    yield \logger_close();

    if (\file_exists($this->dest)) {
      unlink($this->dest);
    }
  }

  public function testGlobalErrorLog()
  {
    \coroutine_run($this->taskGlobalErrorLog());
  }

  public function taskGlobalContextReplacement()
  {
    $logger = \logger_create();
    \logger_array(Logger::ALL, 1, function ($level, $message) {
      return "$level $message";
    });
    yield \log_info('{Message {nothing} {user} {foo.bar} a}', array('user' => 'Bob', 'foo.bar' => 'Bar'));
    $this->expectOutputString('info {Message {nothing} Bob Bar a}' . \EOL);
    yield \logger_printLogs();

    $expected = array('info {Message {nothing} Bob Bar a}');
    $this->assertEquals($expected, $logger->getLogs());
    $logger->resetLogs();
    $this->assertEquals([], \logger_arrayLogs());
    yield \logger_close();
  }

  public function testGlobalContextReplacement()
  {
    \coroutine_run($this->taskGlobalContextReplacement());
  }

  public function taskGlobalObjectCastToString()
  {
    $logger = \logger_create();
    \logger_array(Logger::ALL, 1, function ($level, $message) {
      return "$level $message";
    });
    if (method_exists($this, 'createPartialMock')) {
      $dummy = $this->createPartialMock(CoreDummyTest::class, array('__toString'));
    } else {
      $dummy = $this->getMockBuilder(CoreDummyTest::class)
        ->onlyMethods(array('__toString'))
        ->disableOriginalConstructor()
        ->getMock();
    }

    $dummy->expects($this->once())
      ->method('__toString')
      ->will($this->returnValue('DUMMY'));

    yield \gather(\log_warning($dummy));

    $expected = array('warning DUMMY');
    $this->assertEquals($expected, $logger->getLogs());
    $this->assertCount(1, yield \logger_close());
    $this->assertCount(0, yield \logger_close());
  }

  public function testGlobalObjectCastToString()
  {
    \coroutine_run($this->taskGlobalObjectCastToString());
  }

  public function taskGlobalContextCanContainAnything()
  {
    $logger = \logger_create();
    \logger_array(Logger::ALL, 1, function ($level, $message) {
      return "$level $message";
    });

    $closed = \fopen('php://memory', 'r');
    \fclose($closed);

    $context = array(
      'bool' => true,
      'null' => null,
      'string' => 'Foo',
      'int' => 0,
      'float' => 0.5,
      'nested' => array('with object' => new DummyTest),
      'object' => new \DateTime,
      'resource' => \fopen('php://memory', 'r'),
      'closed' => $closed,
    );

    yield \gather(\log_warning('Crazy context data', $context));

    $expected = array('warning Crazy context data');
    $this->assertEquals($expected, $logger->getLogs());
    yield \logger_close();
  }

  public function testGlobalContextCanContainAnything()
  {
    \coroutine_run($this->taskGlobalContextCanContainAnything());
  }

  public function taskGlobalContextExceptionKeyCanBeExceptionOrOtherValues()
  {
    $logger = \logger_create();
    \logger_array(Logger::ALL, 1, function ($level, $message) {
      return "$level $message";
    });
    yield \gather(\log_warning('Random message', array('exception' => 'oops')));
    yield \gather(\log_critical('Uncaught Exception!', array('exception' => new \LogicException('Fail'))));

    $expected = array(
      'warning Random message',
      'critical Uncaught Exception!'
    );
    $this->assertEquals($expected, $logger->getLogs());
    yield \logger_close();
  }

  public function testGlobalContextExceptionKeyCanBeExceptionOrOtherValues()
  {
    \coroutine_run($this->taskGlobalContextExceptionKeyCanBeExceptionOrOtherValues());
  }

  protected function getOnlyLoggedMessage($logger)
  {
    $loggedMessages = $logger->getLogs();
    $this->assertCount(1, $loggedMessages);
    $loggedMessage = reset($loggedMessages);
    return $loggedMessage;
  }
}

class CoreDummyTest
{
  public function __toString()
  {
  }
}
