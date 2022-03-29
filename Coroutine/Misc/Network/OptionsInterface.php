<?php

declare(strict_types=1);

namespace Async\Network;

/**
 * A `stream context` options Interface for `set_option()`,`get_options()` and `get_type()`.
 *
 * - Invoking a `instance()` returns a `stream_context` **resource**.
 */
interface OptionsInterface
{
  /**
   * @return resource stream context
   */
  public function __invoke();

  /**
   * Sets an option for `stream-context` _type_ resource.
   *
   * @param string $option_name
   * @param mixed $value
   * @return self
   */
  public function set_option(string $option_name, $value): OptionsInterface;

  /**
   * Return `array` of options set for `stream-context` resource.
   *
   * @param bool $isType
   * - `true` returns only `this` instance `type` inuse.
   * - `false` returns `all` _stream-context_ `types` found.
   * @return array
   */
  public function get_options(bool $isType = true): array;

  /**
   * Return _type_ inuse, either **`socket`**, **`http`**, or **`ssl`** for `stream-context`.
   *
   * @return string
   */
  public function get_type(): string;
}
