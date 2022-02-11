<?php

namespace Async\Tests;

use Async\Logger;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Provides a base test class for ensuring compliance with the LoggerInterface.
 *
 * Implementors can extend the class and implement abstract methods to run this
 * as part of their test suite.
 */
class LoggerTest extends TestCase
{
  protected $logger;
  protected $dest = 'stdout.log';

  /**
   * @var string
   */
  protected $testFile = 'logger-test.log';

  /**
   * @return LoggerInterface
   */
  public function getLogger()
  {
    return $this->logger;
  }

  /**
   * This must return the log messages in order.
   *
   * The simple formatting of the messages is: "<LOG LEVEL> <MESSAGE>".
   *
   * Example ->error('Foo') would yield "error Foo".
   *
   * @return string[]
   */
  public function getLogs()
  {
    return $this->logger->getLogs();
  }

  protected function setUp(): void
  {
    \coroutine_clear(false);

    if (\file_exists(__DIR__ . \DS . $this->dest)) {
      unlink(__DIR__ . \DS . $this->dest);
    }

    if (\file_exists(__DIR__ . \DS . $this->testFile)) {
      unlink(__DIR__ . \DS . $this->testFile);
    }

    $this->logger = new Logger("php-app");

    $this->logger->arrayWriter(Logger::ALL, 1, function ($level, $message) {
      return "$level $message";
    });
  }

  protected function tearDown(): void
  {
    if (\file_exists(__DIR__ . \DS . $this->dest)) {
      unlink(__DIR__ . \DS . $this->dest);
    }

    if (\file_exists(__DIR__ . \DS . $this->testFile)) {
      unlink(__DIR__ . \DS . $this->testFile);
    }

    $this->logger->resetLogs();
    $this->logger = null;
  }

  public function taskCreateInstance()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile);
    $this->assertInstanceOf(Logger::class, $log);
    $this->assertFileExists(__DIR__ . \DS . $this->testFile);
    yield $log->close();
  }

  public function testCreateInstance()
  {
    \coroutine_run($this->taskCreateInstance());
  }

  public function taskLogWithoutContext()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::DEBUG);
    yield $log->log(LogLevel::DEBUG, 'A log message');

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} DEBUG A log message]/', $content);
    yield $log->close();
  }

  public function testLogWithoutContext()
  {
    \coroutine_run($this->taskLogWithoutContext());
  }

  public function taskLogLevelInvalidArgumentException()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::DEBUG);
    $this->expectException(InvalidArgumentException::class);
    yield $log->log('LogLevel', 'A log message');
    yield $log->close();
  }

  public function testLogLevelInvalidArgumentException()
  {
    \coroutine_run($this->taskLogLevelInvalidArgumentException());
  }

  public function taskLogWithContext()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::WARNING, 2);
    yield $log->log(LogLevel::WARNING, 'Hello {name}', array('name' => 'World'));
    yield $log->log(LogLevel::WARNING, 'hi {name}', array('name' => 'Planet'));
    yield $log->close();

    $content = \file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} WARNING Hello World]/', $content);
    $this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} WARNING Hi Planet]/', $content);
  }

  public function testLogWithContext()
  {
    \coroutine_run($this->taskLogWithContext());
  }

  public function taskLogEmergencyUniqid()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::EMERGENCY);
    $log->addUniqueId();
    yield \gather($log->emergency('This is an emergency with {unique_id}'));
    yield $log->close();

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);

    $this->assertStringContainsString("] (log-app): EMERGENCY  'This is an emergency with ", $content);
  }

  public function testLogEmergencyUniqid()
  {
    \coroutine_run($this->taskLogEmergencyUniqid());
  }

  public function taskLogAlert()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::ALERT);
    $log->addPid();
    yield \gather($log->alert('This is an alert with {pid}'));
    yield $log->close();

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
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
  }

  public function testLogAlert()
  {
    \coroutine_run($this->taskLogAlert());
  }

  public function taskLogCritical()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::CRITICAL);
    $log->addTimestamp(true);
    yield \gather($log->critical('This is a critical situation happened at {timestamp}'));
    yield $log->close();

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
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
  }

  public function testLogCritical()
  {
    \coroutine_run($this->taskLogCritical());
  }

  public function taskLogError()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::ERROR);
    yield \gather($log->error('This is an error'));

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} ERROR This is an error]/',
      $content
    );
    yield $log->close();
  }

  public function testLogError()
  {
    \coroutine_run($this->taskLogError());
  }

  public function taskLogWarning()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::WARNING);
    yield \gather($log->warning('This is a warning'));

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} WARNING This is a warning]/',
      $content
    );
    yield $log->close();
  }

  public function testLogWarning()
  {
    \coroutine_run($this->taskLogWarning());
  }

  public function taskLogNotice()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::NOTICE);
    yield \gather($log->notice('This is just a notice'));

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} NOTICE This is just a notice]/',
      $content
    );
    yield $log->close();
  }

  public function testLogNotice()
  {
    \coroutine_run($this->taskLogNotice());
  }

  public function taskLogInfo()
  {
    $log = new Logger("log-app");
    yield $log->streamWriter(__DIR__ . \DS . $this->testFile, Logger::INFO);
    $log->addPhpSapi();
    $log->addPhpVersion();
    yield $log->addMemoryUsage('MB');
    yield \gather($log->info('This is an information memory usage {memory_usage}, sapi {php_sapi}, php {php_version}'));

    $content = file_get_contents(__DIR__ . \DS . $this->testFile);
    $this->assertRegExp("/[This is an information memory usage]/is",    $content);
    yield $log->close();
  }

  public function testLogInfo()
  {
    \coroutine_run($this->taskLogInfo());
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

  public function taskGetName()
  {
    $logger = new Logger('foo');
    $this->assertEquals('foo', $logger->getName());
    $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger->getLogger('foo'));
    $this->assertInstanceOf(Logger::class, $logger->getLogger('foo'));
    yield $logger->close();
  }

  public function testGetName()
  {
    \coroutine_run($this->taskGetName());
  }

  public function taskExceptionInContext()
  {
    $logger = $this->getLogger();
    $exceptionMsg = 'exceptional!';
    $exception = new \Exception($exceptionMsg);
    $input = 'foo %exception% foo';
    $context = ['exception' => $exception];
    $expected = 'foo ' . $exceptionMsg . ' foo';
    yield \gather($logger->emergency($input, $context));
    $this->assertNotEmpty($expected, $this->getOnlyLoggedMessage());
    yield $logger->close();
  }

  public function testExceptionInContext()
  {
    \coroutine_run($this->taskExceptionInContext());
  }

  public function taskThrowsInvalidArgumentExceptionWhenNull()
  {
    $logger = $this->getLogger();
    $this->expectException(InvalidArgumentException::class);
    yield $logger->mailWriter(null);
    yield $logger->close();
  }

  public function testThrowsInvalidArgumentExceptionWhenNull()
  {
    \coroutine_run($this->taskThrowsInvalidArgumentExceptionWhenNull());
  }

  public function taskThrowsInvalidArgumentException()
  {
    $logger = $this->getLogger();
    $this->expectException(InvalidArgumentException::class);
    yield $logger->mailWriter('foo');
    yield $logger->close();
  }

  public function testThrowsInvalidArgumentException()
  {
    \coroutine_run($this->taskThrowsInvalidArgumentException());
  }

  public function taskMail()
  {
    $logger = $this->getLogger();
    $this->expectException(InvalidArgumentException::class);
    yield $logger->mailWriter(1, 1, ['Cc: some@somewhere.com']);
    yield \gather($logger->info('Log me!'));
    yield \gather($logger->error('Log me too!'));
    yield $logger->close();
  }

  public function testMail()
  {
    \coroutine_run($this->taskMail());
  }

  public function taskErrorLog()
  {
    \ini_set('error_log', $this->dest);
    $log = new Logger("log-app");
    $log->errorLogWriter();
    yield \gather($log->debug('This is a debug message'));

    $content = file_get_contents($this->dest);
    $this->assertRegExp(
      '/[{^\[.+\] (\w+) (.+)?} DEBUG This is a debug message]/',
      $content
    );
    yield $log->close();

    if (\file_exists($this->dest)) {
      unlink($this->dest);
    }
  }

  public function testErrorLog()
  {
    \coroutine_run($this->taskErrorLog());
  }

  public function taskSysLog()
  {
    $log = new Logger("log-app");
    $this->assertTrue($log->isLogger('log-app'));
    $log->syslogWriter();
    yield \gather($log->debug('This is a debug message'));
    yield \gather($log->warning('This is a warning message'));

    yield $log->close();
    $this->assertFalse($log->isLogger('log-app'));
  }

  public function testSysLog()
  {
    \coroutine_run($this->taskSysLog());
  }

  public function taskLogsAtAllLevels($level, $message)
  {
    $logger = $this->getLogger();
    yield \gather($logger->{$level}($message, array('user' => 'Bob')));
    yield $logger->log($level, $message, array('user' => 'Bob'));

    $expected = array(
      $level . ' message of level ' . $level . ' with context: Bob',
      $level . ' message of level ' . $level . ' with context: Bob',
    );
    $this->assertEquals($expected, $this->getLogs());
    yield $logger->close();
  }

  /**
   * @dataProvider provideLevelsAndMessages
   */
  public function testLogsAtAllLevels($level, $message)
  {
    \coroutine_run($this->taskLogsAtAllLevels($level, $message));
  }

  public function provideLevelsAndMessages()
  {
    return array(
      LogLevel::EMERGENCY => array(LogLevel::EMERGENCY, 'message of level emergency with context: {user}'),
      LogLevel::ALERT => array(LogLevel::ALERT, 'message of level alert with context: {user}'),
      LogLevel::CRITICAL => array(LogLevel::CRITICAL, 'message of level critical with context: {user}'),
      LogLevel::ERROR => array(LogLevel::ERROR, 'message of level error with context: {user}'),
      LogLevel::WARNING => array(LogLevel::WARNING, 'message of level warning with context: {user}'),
      LogLevel::NOTICE => array(LogLevel::NOTICE, 'message of level notice with context: {user}'),
      LogLevel::INFO => array(LogLevel::INFO, 'message of level info with context: {user}'),
      LogLevel::DEBUG => array(LogLevel::DEBUG, 'message of level debug with context: {user}'),
    );
  }

  public function taskContextReplacement()
  {
    $logger = $this->getLogger();
    yield \gather($logger->info('{Message {nothing} {user} {foo.bar} a}', array('user' => 'Bob', 'foo.bar' => 'Bar')));

    $expected = array('info {Message {nothing} Bob Bar a}');
    $this->assertEquals($expected, $this->getLogs());
    yield $logger->close();
  }

  public function testContextReplacement()
  {
    \coroutine_run($this->taskContextReplacement());
  }

  public function taskObjectCastToString()
  {
    if (method_exists($this, 'createPartialMock')) {
      $dummy = $this->createPartialMock(DummyTest::class, array('__toString'));
    } else {
      $dummy = $this->getMockBuilder(DummyTest::class)
        ->onlyMethods(array('__toString'))
        ->disableOriginalConstructor()
        ->getMock();
    }

    $dummy->expects($this->once())
      ->method('__toString')
      ->will($this->returnValue('DUMMY'));

    yield \gather($this->getLogger()->warning($dummy));

    $expected = array('warning DUMMY');
    $this->assertEquals($expected, $this->getLogs());
  }

  public function testObjectCastToString()
  {
    \coroutine_run($this->taskObjectCastToString());
  }

  public function taskContextCanContainAnything()
  {
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

    yield \gather($this->getLogger()->warning('Crazy context data', $context));

    $expected = array('warning Crazy context data');
    $this->assertEquals($expected, $this->getLogs());
    yield $this->getLogger()->close();
  }

  public function testContextCanContainAnything()
  {
    \coroutine_run($this->taskContextCanContainAnything());
  }

  public function taskContextExceptionKeyCanBeExceptionOrOtherValues()
  {
    $logger = $this->getLogger();
    yield \gather($logger->warning('Random message', array('exception' => 'oops')));
    yield \gather($logger->critical('Uncaught Exception!', array('exception' => new \LogicException('Fail'))));

    $expected = array(
      'warning Random message',
      'critical Uncaught Exception!'
    );
    $this->assertEquals($expected, $this->getLogs());
    yield $logger->close();
  }

  public function testContextExceptionKeyCanBeExceptionOrOtherValues()
  {
    \coroutine_run($this->taskContextExceptionKeyCanBeExceptionOrOtherValues());
  }

  protected function getOnlyLoggedMessage()
  {
    $loggedMessages = $this->getLogs();
    $this->assertCount(1, $loggedMessages);
    $loggedMessage = reset($loggedMessages);
    return $loggedMessage;
  }
}

class DummyTest
{
  public function __toString()
  {
  }
}
