<?php

declare(strict_types=1);

namespace Async\Network;

/**
 *
 *
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/io.py#L317
 * @codeCoverageIgnore
 */
trait StreamBase
{
  /**
   * Read up to max bytes of data on the file. If omitted, reads as much data as is currently available.
   *
   * @param integer $maxBytes
   * @return string
   */
  public function read(int $maxBytes = -1)
  {
  }

  /**
   * Return all data up to EOF.
   *
   * @return string
   */
  public function readAll()
  {
  }

  /**
   * Read exactly `$n` bytes of data.
   *
   * @param int $n
   * @return string
   */
  public function read_exactly(int $n)
  {
  }

  /**
   * Read a single line of data.
   *
   * @return string
   */
  public function readline()
  {
  }

  /**
   * Read all of the lines. If cancelled, the lines_read attribute of the exception contains all lines read.
   *
   * @return string
   */
  public function readLines()
  {
  }

  /**
   * Write all of the data in bytes.
   *
   * @param string $bytes
   * @return void
   */
  public function write(string $bytes)
  {
  }

  /**
   * Writes all of the lines in lines. If cancelled, the bytes_written attribute of the exception contains the total bytes written so far.
   *
   * @param [type] $lines
   * @return void
   */
  public function writeLines($lines)
  {
  }

  /**
   * Flush any unwritten data from buffers.
   *
   * @return void
   */
  public function flush()
  {
    \fflush($this->stream);
  }

  /**
   * Flush any unwritten data and close the file. Not called on garbage collection.
   *
   * @return void
   */
  public function close()
  {
  }

  /**
   * A context manager that temporarily places the stream into blocking mode and returns the raw file object used internally.
   * Note: for SocketStream this creates a file using open($sock->fileno(), 'rb+', closeFd=False) which is not supported on Windows.
   *
   * @return void
   */
  public function blocking()
  {
  }
}
