<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Network\Sockets;
use Async\Network\SSLContext;

/**
 * **Non-blocking** wrapper around a **already** _connected_ `socket` object. This class can't be directly instantiated, only creatable by using `SSLContext->wrap_socket();`
 *
 * @see https://curio.readthedocs.io/en/latest/reference.html?highlight=TaskError#socket
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/io.py#L89
 */
final class SSLSockets extends Sockets
{
  /**
   * @var SSLContext
   */
  protected $instance;

  /**
   * @var  \OpenSSLCertificate|resource of type OpenSSL X.509
   */
  protected $peerCertificate;

  /**
   * @param resource $stream a `stream`
   * @param \Socket|resource $socket a `socket` object
   * @param SSLContext $options a `stream context` resource **instance**
   * @param resource|null $peerCertificate
   */
  private function __construct(
    $stream,
    $socket,
    SSLContext $options,
    $peerCertificate = null
  ) {
    if (!\is_resource($stream)) {
      throw new \InvalidArgumentException(\sprintf('Resource expected, got "%s"', \gettype($stream)));
    }

    $this->socket = $socket;
    $this->secured = true;
    $this->instance = $options;
    \stream_set_read_buffer($stream, 0);
    \stream_set_write_buffer($stream, 0);
    \stream_set_blocking($stream, false);
    \stream_set_timeout($stream, 0, 500000);
    $this->stream = $stream;
    $this->setCertificate($peerCertificate);
  }

  /**
   * @param resource $stream a `stream`
   * @param \Socket|resource $socket a `socket` object
   * @param SSLContext $context a `stream context` **instance**
   */
  public static function wrap($stream, $socket, SSLContext $context): SSLSockets
  {
    if (\is_resource($stream)) {
      $options = \stream_context_get_options($stream);
      if (isset($options['ssl']['peer_certificate'])) {
        $peerCertificate = $options['ssl']['peer_certificate'];
      } else {
        $peerCertificate = null;
      }

      return new self($stream, $socket, $context, $peerCertificate);
    }
  }

  protected function setCertificate($peerCertificate = null)
  {
    if ($peerCertificate !== null) {
      $this->assertOpenSSLx509($peerCertificate);
      $this->peerCertificate = $peerCertificate;
    }
  }

  /**
   * Obtain server-side certificate information.
   * This method can only be used once SSL has been completed and the handshake is successful.
   *
   * - The function `openssl_x509_parse()` can be used to parse the certificate information.
   *
   * @return null|resource (OpenSSL x509)
   */
  public function getPeerCert()
  {
    return $this->peerCertificate;
  }

  /**
   * Verify server-side certificate `subject` _CN_.
   *
   * @param string $name
   * @return boolean
   */
  public function verifyPeerCert(string $name): bool
  {
    if ($this->peerCertificate === null)
      return false;

    return \substr(\openssl_x509_parse($this->peerCertificate)["subject"]["CN"], -\strlen($name)) === $name;
  }

  public function __destruct()
  {

    if ($this->peerCertificate) {
      if (!\IS_PHP8)
        \openssl_x509_free($this->peerCertificate);

      $this->peerCertificate = null;
    }

    if (\is_resource($this->stream)) {
      \stream_socket_shutdown($this->stream, \STREAM_SHUT_RDWR);
      \fclose($this->stream);
    }

    $this->close();
  }

  protected function assertOpenSSLx509($resource)
  {
    if (!\is_resource($resource)) {
      throw new \InvalidArgumentException(\sprintf('Resource expected, got "%s"', \gettype($resource)));
    }

    if (\get_resource_type($resource) !== 'OpenSSL X.509') {
      throw new \InvalidArgumentException(\sprintf(
        'Resource of type "OpenSSL X.509" expected, got "%s"',
        \get_resource_type($resource)
      ));
    }
  }
}
