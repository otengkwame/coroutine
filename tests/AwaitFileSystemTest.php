<?php

namespace Async\Tests;

use Async\Panic;
use Async\CancelledError;
use PHPUnit\Framework\TestCase;

use function Async\Path\file_meta;
use function Async\Path\watch_dir;
use function Async\Path\watch_task;

class AwaitFileSystemTest extends TestCase
{
  protected $counterResult = null;

  protected function setUp(): void
  {
    \coroutine_clear();
    if (!defined('FIXTURE_PATH'))
      \define("FIXTURE_PATH", dirname(__FILE__) . \DS . "libuv" . \DS . "fixtures" . \DS . "hello.data");
    if (!defined('FIXTURES'))
      \define("FIXTURES", dirname(__FILE__) . \DS . "libuv" . \DS . "fixtures" . \DS);
    if (!defined('DIRECTORY_PATH'))
      \define("DIRECTORY_PATH", dirname(__FILE__) . \DS . "libuv" . \DS . "fixtures" . \DS . "example_directory");
    @rmdir(DIRECTORY_PATH);
  }

  public function counterTask()
  {
    $counter = 0;
    while (true) {
      $counter++;
      $this->counterResult = $counter;
      yield;
    }
  }

  public function taskOpenRead()
  {
    yield \away($this->counterTask());
    $fd = yield await(open, FIXTURE_PATH, 'r');
    $this->assertEquals('resource', \is_type($fd));

    $data = yield await(read, $fd, 0, 32);
    $this->assertEquals('string', \is_type($data));

    $this->assertEquals('Hello', \rtrim($data));
    $this->assertGreaterThanOrEqual(3, $this->counterResult);

    $bool = yield await(close, $fd);
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(4, $this->counterResult);
    yield \shutdown();
  }

  public function testOpenRead()
  {
    \coroutine_run($this->taskOpenRead());
  }

  public function taskOpenReadOffsetFstat()
  {
    yield \away($this->counterTask());
    $fd = yield await(open, FIXTURE_PATH, 'r');
    $this->assertEquals('resource', \is_type($fd));

    $size = yield await(fstat, $fd, 'size');
    $this->assertEquals('int', \is_type($size));
    $this->assertGreaterThanOrEqual(2, $this->counterResult);

    $data = yield await(read, $fd, 1, $size);
    $this->assertEquals('string', \is_type($data));

    $this->assertEquals('ello', \rtrim($data));
    $this->assertGreaterThanOrEqual(3, $this->counterResult);

    $bool = yield await(close, $fd);
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(4, $this->counterResult);
    yield \shutdown();
  }

  public function testOpenReadOffsetFstat()
  {
    \coroutine_run($this->taskOpenReadOffsetFstat());
  }

  public function taskWrite()
  {
    yield \away($this->counterTask());
    $fd = yield await(open, "./temp", 'a');
    $this->assertEquals('resource', \is_type($fd));

    $data = yield await(write, $fd, "hello");
    $this->assertEquals('int', \is_type($data));

    $this->assertEquals(5, $data);
    $this->assertGreaterThanOrEqual(3, $this->counterResult);

    if (\IS_UV) {
      $fd = yield await(fdatasync, $fd);
      $this->assertEquals('resource', \is_type($fd));
      $this->assertGreaterThanOrEqual(8, $this->counterResult);

      $bool = yield await(close, $fd);
      $this->assertTrue($bool);
      $this->assertGreaterThanOrEqual(9, $this->counterResult);

      $size = yield await(size, "./temp");
      $this->assertEquals('int', \is_type($size));
      $this->assertGreaterThanOrEqual(10, $this->counterResult);

      $bool = yield await(rename, "./temp", "./tmpNew");
      $this->assertTrue($bool);

      $bool = yield await(touch, './tmpNew');
      $this->assertTrue($bool);
      $this->assertGreaterThanOrEqual(12, $this->counterResult);

      $bool = yield await(unlink, "./tmpNew");
      $this->assertTrue($bool);
      $this->assertGreaterThanOrEqual(13, $this->counterResult);

      $bool = yield await(mkdir, DIRECTORY_PATH);
      $this->assertTrue($bool);
      $this->assertGreaterThanOrEqual(14, $this->counterResult);

      $bool = yield await(rmdir, DIRECTORY_PATH);
      $this->assertTrue($bool);
      $this->assertGreaterThanOrEqual(15, $this->counterResult);
    }

    $fd = yield await(open, "tmp", 'bad');
    $this->assertFalse($fd);

    yield \shutdown();
  }

  public function testWrite()
  {
    \coroutine_run($this->taskWrite());
  }

  public function taskFilePut()
  {
    $contents1 = "put test";
    $new = FIXTURES . "put.txt";

    $count = yield await(put_contents, $new, $contents1);
    $this->assertEquals(8, $count);

    $contents2 = yield await(get_contents, $new);

    $this->assertSame($contents1, $contents2);

    yield await(unlink, $new);
    yield \shutdown();
  }

  public function testFilePut()
  {
    \coroutine_run($this->taskFilePut());
  }

  public function taskFileLink()
  {
    $original = FIXTURES . "link.txt";
    $link = FIXTURES . "symlink.txt";

    $bool = yield await(symlink, $original, $link);
    $this->assertTrue($bool);

    $array = yield await(lstat, $link);
    $this->assertIsArray($array);

    $result = yield await(readlink, $link);
    $this->assertSame($original, $result);

    yield await('unlink', $link);
    yield \shutdown();
  }

  public function testFileLink()
  {
    if (!\function_exists('uv_loop_new'))
      $this->markTestSkipped('Test skipped "uv_loop_new" missing.');

    \coroutine_run($this->taskFileLink());
  }

  public function taskFileContents()
  {
    yield \away($this->counterTask());

    $data = yield await(contents, null);
    $this->assertFalse($data);

    $text = \str_repeat('abcde', 256);
    $fd = yield await(open, 'php://temp', 'w+');
    $written = yield await(write, $fd, $text);
    yield await(fdatasync, $fd);
    $this->assertEquals(\strlen($text), $written);

    $data = yield await('contents', $fd);
    if (!\IS_PHP8)
      $this->assertEquals($text, $data);

    $this->assertGreaterThanOrEqual(\IS_PHP8 ? 5 : 8, $this->counterResult);

    $moreData = yield await('contents', $fd);
    $this->assertEquals('', $moreData);

    $this->assertGreaterThanOrEqual(\IS_PHP8 ? 6 : 9, $this->counterResult);

    $bool = yield await(close, $fd);
    $this->assertTrue($bool);

    yield \shutdown();
  }

  public function testFileContents()
  {
    \coroutine_run($this->taskFileContents());
  }

  public function taskFileSystem()
  {
    yield \away($this->counterTask());
    $bool = yield await(touch, './tmpTouch');
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(5, $this->counterResult);

    $size = yield await(size, "./tmpTouch");
    $this->assertEquals(0, $size);
    $this->assertGreaterThanOrEqual(7, $this->counterResult);

    $bool = yield await(exist, "./tmpTouch");
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(10, $this->counterResult);

    $bool = yield await(rename, "./tmpTouch", "./tmpRename");
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(13, $this->counterResult);

    $bool = yield await(unlink, "./tmpRename");
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(16, $this->counterResult);

    $bool = yield await(mkdir, DIRECTORY_PATH);
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(19, $this->counterResult);

    $bool = yield await(rmdir, DIRECTORY_PATH);
    $this->assertTrue($bool);
    $this->assertGreaterThanOrEqual(25, $this->counterResult);

    uv_native();
    $bool = yield await(touch, "./tmpNew");
    $this->assertTrue($bool);
    $result = yield await(utime, "./tmpNew");
    $this->assertTrue($result);
    $bool = yield await(unlink, "./tmpNew");
    $this->assertTrue($bool);

    yield \shutdown();
  }

  public function testFileSystem()
  {
    \coroutine_run($this->taskFileSystem());
  }

  public function taskFileSystemScandir()
  {
    yield \away($this->counterTask());
    $array = yield await('scandir', '.');
    $this->assertTrue(\is_array($array));
    $this->assertTrue(\count($array) > 1);
    $this->assertGreaterThanOrEqual(2, $this->counterResult);

    yield \shutdown();
  }

  public function testFileSystemScandir()
  {
    \coroutine_run($this->taskFileSystemScandir());
  }

  public function taskFileSystemSendfile()
  {
    yield \away($this->counterTask());
    $fd = yield await(open, FIXTURE_PATH, 'r');
    $size = yield await('fstat', $fd, 'size');
    $outFd = yield await(open, 'php://temp', 'w+');
    $written = yield await('sendfile', $outFd, $fd, 0, $size);

    $this->assertEquals($size, $written);
    $data = yield await(contents, $outFd);
    $this->assertEquals('Hello', \trim($data));
    $this->assertGreaterThanOrEqual(6, $this->counterResult);
    yield await(close, $fd);
    yield await(close, $outFd);

    yield shutdown();
  }

  public function testFileSystemSendfile()
  {
    \coroutine_run($this->taskFileSystemSendfile());
  }

  public function taskFileSendfile()
  {
    uv_native();
    yield \away($this->counterTask());
    $fd = yield await(open, FIXTURE_PATH, 'r');
    $size = yield await(fstat, $fd, 'size');
    $outFd = yield await(open, 'php://temp', 'w+');
    $written = yield await(sendfile, $outFd, $fd, 0, $size);
    $this->assertEquals($size, $written);
    $data = yield await(contents, $outFd);
    $this->assertEquals('Hello', \trim($data));
    $this->assertGreaterThanOrEqual(7, $this->counterResult);
    yield await(close, $fd);
    yield await(close, $outFd);

    yield \shutdown();
  }

  public function testFileSendfile()
  {
    \coroutine_run($this->taskFileSendfile());
  }

  public function taskSystemScandir()
  {
    uv_native();
    yield \away($this->counterTask());
    $array = yield await(scandir, '.');
    $this->assertTrue(\is_array($array));
    $this->assertTrue(\count($array) > 1);
    $this->assertGreaterThanOrEqual(5, $this->counterResult);

    yield \shutdown();
  }

  public function testSystemScandir()
  {
    \coroutine_run($this->taskSystemScandir());
  }

  public function taskSystemError()
  {
    $this->expectException(Panic::class);
    yield await('/');

    yield \shutdown();
  }

  public function testSystemError()
  {
    \coroutine_run($this->taskSystemError());
  }

  public function taskFileGet()
  {
    $contents = yield await('get', '.' . \DS . 'list.txt');
    $this->assertTrue(\is_type($contents, 'bool'));
    $contents = yield await('get', __DIR__ . \DS . 'list.txt');
    $this->assertEquals('string', \is_type($contents));

    yield \shutdown();
  }

  public function testFileGet()
  {
    \coroutine_run($this->taskFileGet());
  }

  public function taskFileGetSize()
  {
    $contents = yield await(get_contents, "https://httpbin.org/get");
    $this->assertEquals('string', \is_type($contents));
    $this->assertGreaterThanOrEqual(230, \strlen($contents));
    $fd = yield await(fopen, "https://httpbin.org/get");
    $this->assertTrue(\is_resource($fd));
    $size = file_meta($fd, 'size');
    $this->assertGreaterThanOrEqual(230, $size);
    $bool = yield await(close, $fd);
    $this->assertTrue($bool);
    $fd = yield await(uri, "http://ltd.123/", \stream_context_create());
    $this->assertFalse($fd);
    $size = file_meta($fd, 'size');
    $this->assertEquals(0, $size);
    $status = file_meta($fd, 'status');
    $this->assertEquals(400, $status);
    $meta = file_meta($fd);
    $this->assertFalse($meta);
    $bool = yield await(close, $fd);
    $this->assertFalse($bool);

    yield \shutdown();
  }

  public function testFileGetSize()
  {
    \coroutine_run($this->taskFileGetSize());
  }

  public function getStatuses($websites)
  {
    $statuses = ['200' => 0, '400' => 0];
    foreach ($websites as $website) {
      $tasks[] = yield \away($this->getWebsiteStatus($website));
    }

    $taskStatus = yield \gather_wait($tasks, 2);
    $this->assertEquals(2, \count($taskStatus));
    \array_map(function ($status) use (&$statuses) {
      if ($status == 200)
        $statuses[$status]++;
      elseif ($status == 400)
        $statuses[$status]++;
    }, $taskStatus);
    return \json_encode($statuses);
  }

  public function getWebsiteStatus($url)
  {
    $fd = yield await(fopen, $url);
    $this->assertTrue(\is_resource($fd), $url);
    $status = file_meta($fd, 'status');
    $this->assertEquals(200, $status);
    $bool = yield await(close, $fd);
    $this->assertTrue($bool);
    return yield $status;
  }

  public function taskFileLines()
  {
    $websites = yield await(file, __DIR__ . \DS . 'list.txt');
    $this->assertCount(5, $websites);
    if ($websites !== false) {
      $this->expectOutputString('{"200":2,"400":0}');
      $data = yield from $this->getStatuses($websites);
      print $data;
    }

    yield \shutdown();
  }

  public function testFileOpenLineUri()
  {
    \coroutine_run($this->taskFileLines());
  }

  public function taskWatch()
  {
    $watchTask = yield await(watch_task, function (?string $filename, int $events, int $status) {
      if ($status == 0) {
        //if ($events & \UV::RENAME)
        //    $this->assertTrue(\is_type($filename, 'string'));
        //if ($events & \UV::CHANGE)
        //    $this->assertEmpty($filename);
      } elseif ($status < 0) {
        $tid = yield \current_task();
        $handle = \coroutine()->getTask($tid)->getCustomData();
        $this->assertInstanceOf(\UVFsEvent::class, $handle);
        yield \kill_task();
      }
    });

    yield watch_dir('watching/temp', $watchTask);

    yield \away(function () {
      yield \sleep_for(0.2);
      yield await(\put_contents, "watching/temp/new.txt", 'here');
      yield \sleep_for(0.2);
      yield await(unlink, "watching/temp/new.txt");
      yield \sleep_for(0.2);
      yield await(delete, 'watching');
    });

    yield \gather_wait([$watchTask], 0, false);

    yield await(delete, 'watching');
    yield \shutdown();
  }

  public function testWatch()
  {
    if (\IS_LINUX)
      $this->markTestSkipped('For Windows.');

    if (!\function_exists('uv_loop_new'))
      $this->markTestSkipped('Test skipped "uv_loop_new" missing.');

    \coroutine_run($this->taskWatch());
  }

  public function taskWatchDir()
  {
    $watchTask = yield watch_task(function (?string $filename, int $events, int $status) {
      if ($status == 0) {
        //if ($events & \UV::RENAME)
        //    $this->assertTrue(\is_type($filename, 'string'));
        //if ($events & \UV::CHANGE)
        //    $this->assertEmpty($filename);
      } elseif ($status < 0) {
        yield \kill_task();
      }
    });
    $this->assertTrue(\is_type($watchTask, 'int'));

    $bool = yield await(watch_dir, 'watching/temp', $watchTask);
    $this->assertTrue($bool);

    yield \away(function () {
      yield \sleep_for(0.2);
      yield await(put_contents, "watching/temp/new.txt", 'here');
      yield \sleep_for(0.2);
      yield await(delete, 'watching');
    });

    $result = yield \gather_wait([$watchTask], 0, false);
    $this->assertNotNull($result[$watchTask]);
    $this->assertInstanceOf(CancelledError::class, $result[$watchTask]);

    yield await('delete', 'watching');
    yield \shutdown();
  }

  public function testWatchDir()
  {
    if (\IS_LINUX)
      $this->markTestSkipped('For Windows.');

    if (!\function_exists('uv_loop_new'))
      $this->markTestSkipped('Test skipped "uv_loop_new" missing.');

    \coroutine_run($this->taskWatchDir());
  }

  public function taskWatchDirLinux()
  {
    $that = &$this;
    $watchTask = yield watch_task(function (?string $filename, int $events, int $status) use (&$that) {
      if ($status == 0) {
        if ($events & \UV::RENAME)
          $that->watchData['RENAME'][] = [$filename, $events];
        elseif ($events & \UV::CHANGE)
          $that->watchData['CHANGE'][] =  [$filename, $events];
      } elseif ($status < 0) {
        yield \kill_task();
      }
    });
    $this->assertTrue(\is_type($watchTask, 'int'));

    $bool = yield watch_dir('watching/temp', $watchTask);
    $this->assertTrue($bool);

    yield await('touch', "watching/temp/new.txt");

    $wait = yield \away(function () {
      yield await(touch, "watching/temp/new.txt");
    });

    yield;
    yield;
    /*if (!\IS_MACOS) {
      $this->assertEquals([
        'CHANGE' =>
        [
          0 => [
            0 => 'new.txt',
            1 => 2
          ]
        ],
        'RENAME' =>
        [
          0 => [
            0 => 'new.txt',
            1 => 1
          ]
        ],
      ], $that->watchData);
    }*/
    $bool = yield await(delete, 'watching');
    $this->assertTrue($bool);

    yield \shutdown();
  }

  public function testWatchLinux()
  {
    if (\IS_WINDOWS)
      $this->markTestSkipped('For Linux.');

    if (!\function_exists('uv_loop_new'))
      $this->markTestSkipped('Test skipped "uv_loop_new" missing.');

    \coroutine_run($this->taskWatchDirLinux());
  }
}
