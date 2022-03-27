<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Kernel;
use Async\Network\AbstractOptions;

use function Async\Worker\awaitable_future;

/**
 * `SSLContext` holds various SSL-related configuration options and data, such as certificates and possibly a private key,
 * This class represent all `SSL context` _options_ as **methods**.
 *
 * - _Invoking_ a `$sslContext();` __instance__ returns a `stream_context` **resource**.
 *
 * @see  https://www.php.net/manual/en/context.ssl.php
 */
final class SSLContext extends AbstractOptions
{
  /**
   *  Setup encryption on the stream. Valid methods are:
   *
   * -  STREAM_CRYPTO_METHOD_SSLv2_CLIENT
   * -  STREAM_CRYPTO_METHOD_SSLv3_CLIENT
   * -  STREAM_CRYPTO_METHOD_SSLv23_CLIENT
   * -  STREAM_CRYPTO_METHOD_ANY_CLIENT
   * -  STREAM_CRYPTO_METHOD_TLS_CLIENT
   * -  STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
   * -  STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
   * -  STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
   * -  STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
   * -  STREAM_CRYPTO_METHOD_SSLv2_SERVER
   * -  STREAM_CRYPTO_METHOD_SSLv3_SERVER
   * -  STREAM_CRYPTO_METHOD_SSLv23_SERVER
   * -  STREAM_CRYPTO_METHOD_ANY_SERVER
   * -  STREAM_CRYPTO_METHOD_TLS_SERVER
   * -  STREAM_CRYPTO_METHOD_TLSv1_0_SERVER
   * -  STREAM_CRYPTO_METHOD_TLSv1_1_SERVER
   * -  STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
   * -  STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
   *
   * @var int[] \CLIENT_AUTH => int or \SERVER_AUTH => int]
   */
  protected $crypto_method;

  /**
   * Purpose `CLIENT_AUTH` or `SERVER_AUTH`
   *
   * @var string
   */
  protected $purpose;

  protected $type = 'ssl';

  /**
   * @var string
   */
  protected static $caPath = __DIR__ . \DS;

  /**
   * @var string
   */
  protected static $privatekey = 'privatekey.pem';

  /**
   * @var string
   */
  protected static $certificate = 'certificate.crt';

  /**
   * @var bool
   */
  protected static $secured = false;

  /**
   * @var string
   */
  protected static $hostname = null;

  /**
   * An initial _resource_ `context` or setup one from _associate array_.
   *
   * @param resource|array|null $options
   */
  public function __construct($options = null)
  {
    if (!\is_resource($options) && \is_array($options))
      $options = \stream_context_create($options);
    elseif ($options instanceof OptionsInterface)
      $options = $options();
    elseif (!\is_resource($options))
      $options = \stream_context_create();

    if (\is_resource($options) && \get_resource_type($options) === 'stream-context')
      $this->options = $options;
  }

  /**
   * Create a `SSLContext` object with default settings.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $purpose `CLIENT_AUTH` or `SERVER_AUTH`
   * @param string $name server `hostname`
   * @param resource|array|OptionsInterface $context A _resource_ `context` or create from _associate array_.
   * @param string $capath `directory` to hostname certificate
   * @param string $cafile hostname `certificate`
   * @param array $caSelfDetails - for a self signed certificate, will be created automatically if no hostname certificate available.
   *
   *```md
   *  [
   *      "countryName" =>  '',
   *      "stateOrProvinceName" => '',
   *      "localityName" => '',
   *      "organizationName" => '',
   *      "organizationalUnitName" => '',
   *      "commonName" => '',
   *      "emailAddress" => ''
   *  ];
   *```
   * @return SSLContext
   */
  public static function create_default_context(
    string $purpose,
    string $name = None,
    $context = [],
    string $capath = None,
    string $cafile = None,
    array $caSelfDetails = []
  ) {
    $options = new self($context);

    if ($purpose === \CLIENT_AUTH) {
      $method = \STREAM_CRYPTO_METHOD_SSLv23_CLIENT
        | \STREAM_CRYPTO_METHOD_TLS_CLIENT
        | \STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
        | \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

      if ((float) \phpversion() >= 7.4)
        $method |= \STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

      $options->ciphers('HIGH:!SSLv2:!SSLv3')
        ->allow_self_signed(true)
        ->disable_compression(true)
        ->capture_peer_cert(true)
        ->verify_peer(true)
        ->set_crypto(\CLIENT_AUTH, $method);
    } elseif ($purpose === \SERVER_AUTH) {
      if (!self::$secured) {
        yield self::certificate($name, $capath, $cafile, $caSelfDetails);
      }

      $selfSigned = yield await(exist, self::$caPath . self::$hostname . '.local');
      $method = \STREAM_CRYPTO_METHOD_SSLv23_SERVER
        | \STREAM_CRYPTO_METHOD_TLS_SERVER
        | \STREAM_CRYPTO_METHOD_TLSv1_1_SERVER
        | \STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;

      if ((float) \phpversion() >= 7.4)
        $method |= \STREAM_CRYPTO_METHOD_TLSv1_3_SERVER;

      $options->local_cert(self::$certificate)
        ->local_pk(self::$privatekey)
        ->capath(self::$caPath)
        ->passphrase()
        ->allow_self_signed($selfSigned)
        ->verify_peer(true)
        ->verify_peer_name(!$selfSigned)
        ->SNI_enabled(true)
        ->disable_compression(true)
        ->capture_peer_cert(true)
        ->set_crypto(\SERVER_AUTH, $method);
    }

    return $options;
  }

  /**
   * Check for `certificate`, setup static class variables, will create _self signed certificate_ if no `certificate` present.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $name defaults to system hostname if `blank`.
   * @param string $ssl_path defaults to current working `directory`.
   * @param string $ssl_file current `certificate`.
   * @param array $details - self certificate details.
   *
   *```md
   *  [
   *      "countryName" =>  '',
   *      "stateOrProvinceName" => '',
   *      "localityName" => '',
   *      "organizationName" => '',
   *      "organizationalUnitName" => '',
   *      "commonName" => '',
   *      "emailAddress" => ''
   *  ];
   *```
   */
  public static function certificate(
    string $name = null,
    string $ssl_path = null,
    string $ssl_file = null,
    array $details = []
  ) {
    if (empty($ssl_path)) {
      $ssl_path = \IS_UV ? \uv_cwd() : \getcwd();
      $ssl_path = \preg_replace('/\\\/', \DS, $ssl_path) . \DS;
    } elseif (\strpos($ssl_path, \DS, -1) === false) {
      $ssl_path = $ssl_path . \DS;
    }

    $hostname = self::$hostname = empty($name) ? yield \await('gethostname') : $name;
    $privatekeyFile = self::$privatekey = $hostname . '.pem';
    $certificateFile = self::$certificate = !empty($ssl_file) ? $ssl_file : $hostname . '.crt';
    $signingFile = $hostname . '.csr';
    self::$caPath = $ssl_path;
    self::$secured = true;

    $isSignedReady = yield await(exist, $ssl_path . $certificateFile);
    if (!$isSignedReady) {
      // @codeCoverageIgnoreStart
      $make = function () use ($ssl_path, $details, $signingFile, $privatekeyFile, $certificateFile, $hostname) {
        $opensslConfig = array("config" => $ssl_path . 'openssl.cnf');
        // Generate a new private (and public) key pair
        $privatekey = \openssl_pkey_new($opensslConfig);
        if (empty($details))
          $details = ["commonName" => $hostname];

        // Generate a certificate signing request
        $csr = \openssl_csr_new($details, $privatekey, $opensslConfig);
        // Create a self-signed certificate valid for 365 days
        $sslcert = \openssl_csr_sign($csr, null, $privatekey, 365, $opensslConfig);
        // Create key file. Note no passphrase
        \openssl_pkey_export_to_file($privatekey, $ssl_path . $privatekeyFile, null, $opensslConfig);
        // Create server certificate
        \openssl_x509_export_to_file($sslcert, $ssl_path . $certificateFile, false);
        // Create a signing request file
        \openssl_csr_export_to_file($csr, $ssl_path . $signingFile);
        \touch($ssl_path . $hostname . '.local');
      };
      // @codeCoverageIgnoreEnd

      yield awaitable_future(function () use ($make) {
        return yield Kernel::addFuture($make);
      });
    }
  }

  /**
   * @param resource $stream a `stream`
   * @param \Socket|resource $socket a `socket` object
   */
  public function wrap_socket($stream, $socket): SSLSockets
  {
    return SSLSockets::wrap($stream, $socket, $this);
  }

  /**
   *  Setup encryption on the stream.
   *
   * @param string $purpose `CLIENT_AUTH` or `SERVER_AUTH`
   * @param integer $crypto Valid methods are:
   * - STREAM_CRYPTO_METHOD_SSLv2_CLIENT
   * - STREAM_CRYPTO_METHOD_SSLv3_CLIENT
   * - STREAM_CRYPTO_METHOD_SSLv23_CLIENT
   * - STREAM_CRYPTO_METHOD_ANY_CLIENT
   * - STREAM_CRYPTO_METHOD_TLS_CLIENT
   * - STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
   * - STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
   * - STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
   * - If `PHP 7.4+` STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
   * - STREAM_CRYPTO_METHOD_SSLv2_SERVER
   * - STREAM_CRYPTO_METHOD_SSLv3_SERVER
   * - STREAM_CRYPTO_METHOD_SSLv23_SERVER
   * - STREAM_CRYPTO_METHOD_ANY_SERVER
   * - STREAM_CRYPTO_METHOD_TLS_SERVER
   * - STREAM_CRYPTO_METHOD_TLSv1_0_SERVER
   * - STREAM_CRYPTO_METHOD_TLSv1_1_SERVER
   * - STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
   * - If `PHP 7.4+` STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
   *
   * @return self
   */
  public function set_crypto(string $purpose, int $crypto): self
  {
    $this->purpose = $purpose;
    $this->crypto_method[$purpose] = $crypto;
    return $this;
  }

  /**
   *  Return the setup encryptions on the stream.
   *
   * @return integer
   */
  public function get_crypto(): int
  {
    return $this->crypto_method[$this->purpose];
  }

  /**
   * Peer `name` to be used. If this value is not set, then the name is guessed based on the hostname used when opening the stream.
   *
   * @param string $name
   * @return self
   */
  public function peer_name(string $name)
  {
    return $this->set_option('peer_name', $name);
  }

  /**
   * Require `verification` of SSL certificate used.
   *
   * @param boolean $verification Defaults to `true`.
   * @return self
   */
  public function verify_peer(bool $verification = true)
  {
    return $this->set_option('verify_peer', $verification);
  }

  /**
   * Require `verification` of peer name.
   *
   * @param boolean $verification Defaults to `true`.
   * @return self
   */
  public function verify_peer_name(bool $verification = true)
  {
    return $this->set_option('verify_peer_name', $verification);
  }

  /**
   * Allow self-`signed` certificates. Requires `verify_peer`.
   *
   * @param boolean $signed Defaults to `false`.
   * @return self
   */
  public function allow_self_signed(bool $signed = false)
  {
    return $this->set_option('allow_self_signed', $signed);
  }

  /**
   * Location of Certificate `Authority` file on local filesystem which should be used with the `verify_peer` context option to authenticate the identity of the remote peer.
   *
   * @param string $authority
   * @return self
   */
  public function cafile(string $authority)
  {
    return $this->set_option('cafile', $authority);
  }

  /**
   * If cafile is not specified or if the certificate is not found there, the `directory` pointed to by capath is searched for a suitable certificate. capath must be a correctly hashed certificate directory.
   *
   * @param string $directory
   * @return self
   */
  public function capath(string $directory)
  {
    return $this->set_option('capath', $directory);
  }

  /**
   * Path to local `certificate` file on filesystem. It must be a PEM encoded file which contains your certificate and private key.
   * It can optionally contain the certificate chain of issuers. The private key also may be contained in a separate file specified by local_pk.
   *
   * @param string $certificate SSL Cert in PEM format
   * @return self
   */
  public function local_cert(string $certificate)
  {
    return $this->set_option('local_cert', $certificate);
  }

  /**
   * Path to local `private` key file on filesystem in case of separate files for certificate (local_cert) and private key.
   *
   * @param string $privatekey RSA key in PEM format
   * @return self
   */
  public function local_pk(string $privatekey)
  {
    return $this->set_option('local_pk', $privatekey);
  }

  /**
   * Pass`phrase` with which your local_cert file was encoded.
   *
   * @param string $phrase Private key Password
   * @return self
   */
  public function passphrase(string $phrase = null)
  {
    return $this->set_option('passphrase', $phrase);
  }

  /**
   * Abort if the certificate chain is too deep.
   *
   * @param integer|null $depth Defaults to no verification.
   * @return self
   */
  public function verify_depth(int $depth = null)
  {
    return $this->set_option('verify_depth', $depth);
  }

  /**
   * Sets the list of available ciphers. The format of the string is described in » ciphers(1).
   *
   * @param string $ciphers_list Defaults to DEFAULT.
   * @return self
   * @see https://www.openssl.org/docs/manmaster/man1/openssl-ciphers.html
   */
  public function ciphers(string $ciphers_list)
  {
    return $this->set_option('ciphers', $ciphers_list);
  }

  /**
   * If set to `true` a peer_certificate context option will be created containing the peer certificate.
   *
   * @param boolean $create_cert
   * @return self
   */
  public function capture_peer_cert(bool $create_cert)
  {
    return $this->set_option('capture_peer_cert', $create_cert);
  }

  /**
   * If set to `true` a peer_certificate_chain context option will be created containing the certificate chain.
   *
   * @param boolean $create_chain
   * @return self
   */
  public function capture_peer_cert_chain(bool $create_chain)
  {
    return $this->set_option('capture_peer_cert_chain', $create_chain);
  }

  /**
   * If set to `true` server name indication will be enabled. Enabling SNI allows multiple certificates on the same IP address.
   *
   * @param boolean $sni_enabled
   * @return self
   */
  public function SNI_enabled(bool $enable_sni)
  {
    return $this->set_option('SNI_enabled', $enable_sni);
  }

  /**
   * If set, disable TLS compression. This can help mitigate the CRIME attack vector.
   *
   * @param boolean $compression
   * @return self
   */
  public function disable_compression(bool $compression)
  {
    return $this->set_option('disable_compression', $compression);
  }

  /**
   * Aborts when the remote certificate digest doesn't match the specified `hash`.
   *
   * @param string|array $hash
   * - When a string is used, the length will determine which hashing algorithm is applied, either "md5" (32) or "sha1" (40).
   * - When an array is used, the keys indicate the hashing algorithm name and each corresponding value is the expected digest.
   * @return self
   */
  public function peer_fingerprint($hash)
  {
    return $this->set_option('peer_fingerprint', $hash);
  }

  /**
   * Sets the security `level`. If not specified the library default security level is used.
   * The security levels are described in » SSL_CTX_get_security_level(3).
   *
   * Available as of PHP 7.2.0 and OpenSSL 1.1.0.
   *
   * @param integer $level
   * @return self
   * @see https://www.openssl.org/docs/man1.1.1/man3/SSL_CTX_get_security_level.html
   */
  public function security_level(int $level)
  {
    return $this->set_option('security_level', $level);
  }
}
