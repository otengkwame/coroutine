<?php

declare(strict_types=1);

namespace Async\Path;

use function Async\Worker\awaitable_future;

use Async\Kernel;
use Async\FileSystem;

if (!\function_exists('watch_task')) {
  /**
   * Add a file change event handler for the path being watched, that's continuously monitored.
   * This function will return `int` immediately, use with `monitor()`, `monitor_file()`, `monitor_dir()`.
   * - The `$handler` function will be executed every time theres activity with the path being watched.
   * - Expect the `$handler` to receive `(?string $filename, int $events, int $status)`.
   * - This function needs to be prefixed with `yield`
   *
   * @param callable $handler
   *
   * @return int
   */
  function watch_task(callable $handler)
  {
    return FileSystem::watchTask($handler);
  }

  /**
   * Add a file change event handler for the path being watched, that's continuously watched/monitored.
   */
  \define('watch_task', 'watch_task');

  /**
   * Monitor/watch the specified path for changes, switching to `watch_task()` by id to handle any changes.
   * - The `watch_task` will receive `(?string $filename, int $events, int $status)`.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param integer $watchTask
   *
   * @return bool
   */
  function watch(string $path, int $watchTask)
  {
    return FileSystem::watch($path, $watchTask);
  }

  /**
   * Monitor/watch the specified path for changes, switching to `watch_task()` by id to handle any changes.
   */
  \define('watch', 'watch');

  /**
   * Monitor/watch the specified file for changes, switching to `watch_task()` by id to handle any changes.
   * - The `watch_task` will receive `(?string $filename, int $events, int $status)`.
   * - This function needs to be prefixed with `yield`
   *
   * `Note:` The `file` will be created if does not already exists.
   *
   * @param string $file
   * @param integer $watchTask
   *
   * @return bool
   *
   * @codeCoverageIgnore
   */
  function watch_file(string $file, int $watchTask)
  {
    $file = slash_switch($file);
    $check = yield file_exist($file);
    if (!$check)
      yield file_touch($file);

    return yield watch($file, $watchTask);
  }

  /**
   * Monitor/watch the specified file for changes, switching to `watch_task()` by id to handle any changes.
   */
  \define('watchr_file', 'watch_file');

  /**
   * Monitor/watch the specified directory for changes, switching to `watch_task()` by id to handle any changes.
   * - The `watch_task` will receive `(?string $filename, int $events, int $status)`.
   * - This function needs to be prefixed with `yield`
   *
   * `Note:` The `directory` will be created `recursively` if does not already exists.
   *
   * @param string $directory
   * @param integer $watchTask
   *
   * @return bool
   */
  function watch_dir(string $directory, int $watchTask)
  {
    $directory = slash_switch($directory);
    yield FileSystem::internal('mkdir', $directory, 0777, true);

    return yield watch($directory, $watchTask);
  }

  /**
   * Monitor/watch the specified directory for changes, switching to `watch_task()` by id to handle any changes.
   */
  \define('watch_dir', 'watch_dir');

  function slash_switch(string $path)
  {
    if (\IS_WINDOWS && (\strpos($path, '/') !== false))
      $path = \str_replace('/', \DS, $path);
    elseif (\IS_LINUX && (\strpos($path, '\\') !== false))
      $path = \str_replace('\\', \DS, $path);

    return $path;
  }

  /**
   * Recursively delete files/folders asynchronously in a **child/subprocess**.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $directory
   *
   * @return bool
   */
  function file_delete($dir)
  {
    $dir = slash_switch($dir);

    // @codeCoverageIgnoreStart
    $system = function ($dirFile) use ($dir, &$system) {
      // Need to check for string type. All child/subprocess automatically
      // have a Channel instance passed in on `future` execution.
      $dir = \is_string($dirFile) ? $dirFile : $dir;
      if (\is_dir($dir)) {
        $files = @\glob($dir . '*', \GLOB_MARK);
        foreach ($files as $file) {
          $system($file);
        }

        return @\rmdir($dir);
      } elseif (\is_file($dir)) {
        return @\unlink($dir);
      }
    };
    // @codeCoverageIgnoreEnd

    yield awaitable_future(function () use ($system) {
      return yield Kernel::addFuture($system);
    });

    $bool = yield file_exist($dir);

    return ($bool === false);
  }

  /**
   * Recursively delete files/folders asynchronously in a **child/subprocess**.
   */
  \define('delete', 'file_delete');

  /**
   * Sets access and modification time of file.
   * - This function needs to be prefixed with `yield`
   *
   * @param mixed $path
   * @param mixed|null $time
   * @param mixed|null $atime
   *
   * @return bool
   */
  function file_touch($path = null, $time = null, $atime = null)
  {
    return FileSystem::touch($path, $time, $atime);
  }


  /**
   * Sets access and modification time of file.
   */
  \define('touch', 'file_touch');

  /**
   * Renames a file or directory.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $from
   * @param string $to
   *
   * @return bool
   */
  function file_rename($from, $to)
  {
    return FileSystem::rename($from, $to);
  }

  /**
   * Renames a file or directory.
   */
  \define('rename', 'file_rename');

  /**
   * Deletes a file.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   *
   * @return bool
   */
  function file_unlink($path)
  {
    return FileSystem::unlink($path);
  }

  /**
   * Deletes a file.
   */
  \define('unlink', 'file_unlink');

  /**
   * Create a hard link.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $from
   * @param string $to
   * @return bool
   * @codeCoverageIgnore
   */
  function file_link($from, $to)
  {
    return FileSystem::link($from, $to);
  }

  /**
   * Create a hard link.
   */
  \define('link', 'file_link');


  /**
   * Creates a symbolic link.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $from
   * @param string $to
   * @param int $flag
   *
   * @return bool
   */
  function file_symlink(?string $from = null, ?string $to = null, int $flag = 0)
  {
    return FileSystem::symlink($from, $to);
  }

  /**
   * Creates a symbolic link.
   */
  \define('symlink', 'file_symlink');

  /**
   * Read value of a symbolic link.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   *
   * @return string|bool
   */
  function file_readlink($path)
  {
    return FileSystem::readlink($path);
  }

  /**
   * Read value of a symbolic link.
   */
  \define('readlink', 'file_readlink');

  /**
   * Attempts to create the directory specified by pathname.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param integer $mode
   * @param boolean $recursive
   *
   * @return bool
   */
  function file_mkdir($path, $mode = 0777, $recursive = false)
  {
    return FileSystem::mkdir($path, $mode, $recursive);
  }

  /**
   * Attempts to create the directory specified by pathname.
   */
  \define('mkdir', 'file_mkdir');

  /**
   * Removes directory.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   *
   * @return bool
   */
  function file_rmdir($path)
  {
    return FileSystem::rmdir($path);
  }

  /**
   * Removes directory.
   */
  \define('rmdir', 'file_rmdir');

  /**
   * Changes file mode.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $filename
   * @param int $mode
   * @return bool
   * @codeCoverageIgnore
   */
  function file_chmod($filename, $mode)
  {
    return FileSystem::chmod($filename, $mode);
  }

  /**
   * Changes file mode.
   */
  \define('chmod', 'file_chmod');

  /**
   * Changes file owner.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param int $uid
   * @param int $gid
   * @return bool
   * @codeCoverageIgnore
   */
  function file_chown($path, $uid, $gid)
  {
    return FileSystem::chown($path, $uid, $gid);
  }

  /**
   * Changes file owner.
   */
  \define('chown', 'file_chown');

  /**
   * List files and directories inside the specified path.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param mixed $flagSortingOrder
   *
   * @return array|bool
   */
  function file_scandir($path, $sortingOrder = 0)
  {
    return FileSystem::scandir($path, $sortingOrder);
  }

  /**
   * List files and directories inside the specified path.
   */
  \define('scandir', 'file_scandir');

  /**
   * Gives information about a file.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param string $info
   * - Numeric    `$info` Description
   *````
   * 0    dev     device number
   * 1	ino	inode number
   * 2	mode	inode protection mode
   * 3	nlink	number of links
   * 4	uid	userid of owner
   * 5	gid	groupid of owner
   * 6	rdev	device type, if inode device
   * 7	size	size in bytes
   * 8	atime	time of last access (Unix timestamp)
   * 9	mtime	time of last modification (Unix timestamp)
   * 10	ctime	time of last inode change (Unix timestamp)
   * 11	blksize	blocksize of filesystem IO
   * 12	blocks	number of 512-byte blocks allocated
   *````
   * @return array|bool
   */
  function file_stat(string $path = '', ?string $info = null)
  {
    return FileSystem::stat($path, $info);
  }

  /**
   * Gives information about a file.
   */
  \define('stat', 'file_stat');

  /**
   * Gives information about a file symbolic link, returns same data as `stat()`.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param string $info
   * - Numeric    `$info` Description
   *````
   * 0    dev     device number
   * 1	ino	inode number
   * 2	mode	inode protection mode
   * 3	nlink	number of links
   * 4	uid	userid of owner
   * 5	gid	groupid of owner
   * 6	rdev	device type, if inode device
   * 7	size	size in bytes
   * 8	atime	time of last access (Unix timestamp)
   * 9	mtime	time of last modification (Unix timestamp)
   * 10	ctime	time of last inode change (Unix timestamp)
   * 11	blksize	blocksize of filesystem IO
   * 12	blocks	number of 512-byte blocks allocated
   *````
   * @return array|bool
   */
  function file_lstat(string $path = '', ?string $info = null)
  {
    return FileSystem::lstat($path, $info);
  }

  /**
   * Gives information about a file symbolic link, returns same data as `stat()`.
   */
  \define('lstat', 'file_lstat');

  /**
   * Gets information about a file using an open file pointer.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $fd
   * @param string $info
   *
   * @return array|bool
   */
  function file_fstat($fd = null, ?string $info = null)
  {
    return FileSystem::fstat($fd, $info);
  }

  /**
   * Gets information about a file using an open file pointer.
   */
  \define('fstat', 'file_fstat');

  /**
   * Transfer data between file descriptors.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $out_fd
   * @param resource $in_fd
   * @param int $offset
   * @param int $length
   *
   * @return int|bool
   */
  function file_sendfile($out_fd = null, $in_fd = null, int $offset = 0, int $length = 8192)
  {
    $written = yield FileSystem::sendfile($out_fd, $in_fd, $offset, $length);
    if (FileSystem::isUv()) {
      yield FileSystem::fdatasync($out_fd);
    }

    return $written;
  }

  /**
   * Transfer data between file descriptors.
   */
  \define('sendfile', 'file_sendfile');

  /**
   * Read file pointed to by the `resource` file descriptor.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $fd
   * @param int $offset
   * @param int $length
   *
   * @return string
   * @throws Exception
   */
  function file_read($fd = null, int $offset = 0, int $length = 8192)
  {
    return FileSystem::read($fd, $offset, $length);
  }

  /**
   * Read file pointed to by the `resource` file descriptor.
   */
  \define('read', 'file_read');

  /**
   * Write to file pointed to by the `resource` file descriptor.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $fd
   * @param string $buffer
   * @param int|bool $offset if not `UV` set to schedule immediately
   *
   * @return int|bool
   */
  function file_write($fd = null, string $buffer = null, $offset = -1)
  {
    return FileSystem::write($fd, $buffer, $offset);
  }

  /**
   * Write to file pointed to by the `resource` file descriptor.
   */
  \define('write', 'file_write');

  /**
   * Close file pointed to by the `resource` file descriptor.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $fd
   *
   * @return bool
   */
  function file_close($fd)
  {
    return FileSystem::close($fd);
  }

  /**
   * Close file pointed to by the `resource` file descriptor.
   */
  \define('close', 'file_close');

  /**
   * Synchronize a file's in-core state with storage device by file descriptor.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $fd
   *
   * @return resource|bool
   */
  function file_fdatasync($fd)
  {
    return FileSystem::fdatasync($fd);
  }

  /**
   * Synchronize a file's in-core state with storage device by file descriptor.
   */
  \define('fdatasync', 'file_fdatasync');

  /**
   * Return file size.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   *
   * @return int|bool
   */
  function file_size($path)
  {
    return file_stat($path, 'size');
  }

  /**
   * Return file size.
   */
  \define('size', 'file_size');

  /**
   * Check if file exists.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   *
   * @return bool
   */
  function file_exist($path)
  {
    $status = yield file_size($path);
    return \is_int($status);
  }

  /**
   * Check if file exists.
   */
  \define('exist', 'file_exist');

  /**
   * Open specified `$path` file with access `$flag` and mode.
   * - This function needs to be prefixed with `yield`
   * - Will delay and retry 3 times before returning `false` on failure
   *
   * @param string $path
   * @param string $flag either 'r', 'r+', 'w', 'w+', 'a', 'a+', 'x', 'x+':
   * - "`r`"	`read`: Open file for input operations. The file must exist.
   * - "`w`"	`write`: Create an empty file for output operations.
   * If a file with the same name already exists, its contents are discarded and the
   * file is treated as a new empty file.
   * - "`a`"	`append`: Open file for output at the end of a file.
   * Output operations always write data at the end of the file, expanding it.
   * Repositioning operations (fseek, fsetpos, rewind) are ignored.
   * The file is created if it does not exist.
   * - "`r+`" `read/update`: Open a file for update (both for input and output). The file must exist.
   * - "`w+`" `write/update`: Create an empty file and open it for update (both for input and output).
   * If a file with the same name already exists its contents are discarded and the file is
   * treated as a new empty file.
   * - "`a+`" `append/update`: Open a file for update (both for input and output) with all output
   * operations writing data at the end of the file. Repositioning operations (fseek, fsetpos,
   * rewind) affects the next input operations, but output operations move the position back
   * to the end of file. The file is created if it does not exist.
   * - "`x`" `Write only`: Creates a new file. Returns `FALSE` and an error if file already exists.
   * - "`x+`" `Read/Write`: Creates a new file. Returns `FALSE` and an error if file already exists
   * @param int $mode
   *
   * @return resource|bool stream
   */
  function file_open(string $path = null, string $flag = 'r', int $mode = \S_IRWXU)
  {
    return yield _fopen($path, $flag, $mode);
  }

  /**
   * Open specified `path` file with access `flag` and `mode`.
   */
  \define('open', 'file_open');

  /**
   * Open url/uri, with a resource `context`.
   * - This function needs to be prefixed with `yield`
   * - Will delay and retry 3 times before returning `false` on failure
   *
   * @param string $url
   * @param resource|array|null $context
   *
   * @return resource stream
   */
  function file_uri(string $url = '', $contexts = null)
  {
    return yield _fopen($url, 'r', 0, $contexts);
  }

  /**
   * Open url/uri, with a resource `context`.
   */
  \define('uri', 'file_uri');

  /**
   * Open `location` by access `flag`, with a resource `context`.
   * - This function needs to be prefixed with `yield`
   * - Will delay and retry 3 times before returning `false` on failure
   *
   * @param string $location open url/uri, or file.
   * @param string $flag either 'r', 'r+', 'w', 'w+', 'a', 'a+', 'x', 'x+':
   * - "`r`"	`read`: Open file for input operations. The file must exist.
   * - "`w`"	`write`: Create an empty file for output operations.
   * If a file with the same name already exists, its contents are discarded and the
   * file is treated as a new empty file.
   * - "`a`"	`append`: Open file for output at the end of a file.
   * Output operations always write data at the end of the file, expanding it.
   * Repositioning operations (fseek, fsetpos, rewind) are ignored.
   * The file is created if it does not exist.
   * - "`r+`" `read/update`: Open a file for update (both for input and output). The file must exist.
   * - "`w+`" `write/update`: Create an empty file and open it for update (both for input and output).
   * If a file with the same name already exists its contents are discarded and the file is
   * treated as a new empty file.
   * - "`a+`" `append/update`: Open a file for update (both for input and output) with all output
   * operations writing data at the end of the file. Repositioning operations (fseek, fsetpos,
   * rewind) affects the next input operations, but output operations move the position back
   * to the end of file. The file is created if it does not exist.
   * - "`x`" `Write only`: Creates a new file. Returns `FALSE` and an error if file already exists.
   * - "`x+`" `Read/Write`: Creates a new file. Returns `FALSE` and an error if file already exists
   * @param resource|array|null $context - `http/ssl` stream context.
   *
   * @return resource|bool stream
   */
  function file_fopen(string $location, string $flag = 'r', $context = null)
  {
    return yield _fopen($location, $flag, 0, $context);
  }

  /**
   * Open `location` by access `flag`, with a optional resource `context`.
   * @return resource|bool stream
   */
  \define('fopen', 'file_fopen');

  /**
   * - This function needs to be prefixed with `yield`
   * - Will delay and retry 3 times before returning `false` on failure
   *
   * @internal
   *
   * @return resource stream
   */
  function _fopen(string $path = null, string $flag = 'r', int $mode = \S_IRWXU, $contexts = null)
  {
    $retry = 0;
    while (true) {
      $fd  = yield FileSystem::open($path, $flag, $mode, $contexts);
      if (!\is_resource($fd) && $retry < 3) {
        $retry++;
        yield;
        continue;
      } else {
        break;
      }
    }

    return $fd;
  }

  /**
   * Reads remainder of a stream/file pointer by size into a string,
   * will stop if timeout seconds lapse.
   * - This function needs to be prefixed with `yield`
   *
   * @param resource $fd
   * @param integer $size
   * @param float $timeout_seconds
   *
   * @return string|bool
   */
  function file_contents($fd = null, int $size = 256, float $timeout_seconds = 0.5)
  {
    return FileSystem::contents($fd, $size, $timeout_seconds);
  }

  /**
   * Reads remainder of a stream/file pointer by size into a string,
   * will stop if timeout seconds lapse.
   */
  \define('contents', 'file_contents');

  /**
   * Reads entire file into a string.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $filename
   * @param int $offset
   * @param int $max
   *
   * @return string|bool
   */
  function file_get(string $filename)
  {
    $fd = yield file_open($filename, 'r');
    if (\is_resource($fd)) {
      if (file_meta($fd, 'wrapper_type') === 'http') {
        $max = -1;
      } else {
        if (\IS_LINUX)
          $max = yield file_fstat($fd, 'size');
        else
          $max = yield file_stat($filename, 'size');
      }

      $contents = yield file_read($fd, 0, (empty($max) ? 8192 * 2 : $max));
      yield file_close($fd);
      return $contents;
    }

    return false;
  }

  /**
   * Reads entire file into a string.
   */
  \define('get_contents', 'file_get');

  /**
   * Write a string to a file.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $filename
   * @param mixed $contents
   *
   * @return int|bool
   */
  function file_put(string $filename, $contents)
  {
    $fd = yield file_open($filename, 'w');
    if (\is_resource($fd)) {
      $written = yield file_write($fd, $contents);
      yield file_fdatasync($fd);
      yield file_close($fd);
      return $written;
    }

    return false;
  }

  /**
   * Write a string to a file.
   */
  \define('put_contents', 'file_put');

  /**
   * Reads entire file into an array.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   *
   * @return array
   */
  function file_file($path)
  {
    return FileSystem::internal('file', $path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
  }

  /**
   * Reads entire file into an array.
   */
  \define('file', 'file_file');

  /**
   * Change file last access and modification times.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $path
   * @param int $utime
   * @param int $atime
   */
  function file_utime(string $path, ?int $utime = null, ?int $atime = null)
  {
    return FileSystem::utime($path, $utime, $atime);
  }

  /**
   * Change file last access and modification times.
   */
  \define('utime', 'file_utime');

  /**
   * Retrieves header/meta data from streams/file pointers.
   *
   * @param resource $fd
   * @param null|string $info
   * - Can be: `timed_out`, `blocked`, `eof`, `unread_bytes`, `stream_type`, `wrapper_type`,
   * `mode`, `seekable`, `uri`, `wrapper_data`
   * - and `status` for **HTTP Status Code** from `wrapper_data`
   * - and `size` for **HTTP Content Length** from `wrapper_data`
   *
   * @return array|string|int|bool
   */
  function file_meta($fd = null, ?string $info = null)
  {
    return FileSystem::meta($fd, $info);
  }
}
