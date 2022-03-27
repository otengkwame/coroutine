<?php

declare(strict_types=1);

namespace Async\Network;

use Async\Network\OptionsInterface;

/**
 * This class will represent any `stream context` _option_ as **methods**.
 *
 * - _Invoking_ a `$abstractOptions();` __instance__ returns a `stream_context` **resource**.
 *
 * @see https://www.php.net/manual/en/context.php
 */
abstract class AbstractOptions implements OptionsInterface
{
  /**
   * @var resource stream context
   */
  protected $options;

  /**
   * Context resource wrapper `type`, either **`socket`**, **`http`**, or **`ssl`**.
   *
   * @var string
   */
  protected $type = '';

  public function __invoke()
  {
    return $this->options;
  }

  public function set_option(string $option_name, $value): self
  {
    \stream_context_set_option($this->options, $this->type, $option_name, $value);
    return $this;
  }

  public function get_options(bool $isType = true): array
  {
    $options = \stream_context_get_options($this->options);
    if ($isType)
      return $options[$this->type] ?? [];

    return $options;
  }

  public function get_type(): string
  {
    return $this->type;
  }
}
