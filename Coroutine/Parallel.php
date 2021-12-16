<?php

declare(strict_types=1);

namespace Async;

use InvalidArgumentException;
use Async\CoroutineInterface;
use Async\ParallelInterface;
use Async\Spawn\Parallel as Spawner;
use Async\Spawn\FutureInterface;
use Async\Spawn\ChanneledInterface;
use Async\Spawn\Globals;
use parallel\Channel;
use parallel\RuntimeInterface;

/**
 * @internal
 */
final class Parallel extends Spawner implements ParallelInterface
{
  /**
   *
   *
   * @var CoroutineInterface
   */
  protected $coroutine;

  public function adding(?\closure $future = null, ?string $include = null, ...$args): FutureInterface
  {
    $defined = Globals::get();

    if (!\is_callable($future) && !$future instanceof FutureInterface) {
      throw new InvalidArgumentException('The future passed to Parallel::adding should be callable.');
    }

    if (!$future instanceof FutureInterface) {
      $channel = null;
      foreach ($args as $isChannel) {
        if ($isChannel instanceof ChanneledInterface) {
          $channel = $isChannel;
          break;
        } elseif (\is_string($isChannel) && Channel::isChannel($isChannel)) {
          $channel = Channel::open($isChannel);
          break;
        }
      }

      $transfer = [];
      if (Co::has('run')) {
        $run = Co::get('run');
        if ($run instanceof RuntimeInterface)
          $transfer['run'] = true;
      }

      if (Co::has('bootstrap')) {
        $bootstrap = Co::get('bootstrap');
        if (\is_string($bootstrap))
          $transfer['bootstrap'] = $bootstrap;
      }

      // `ext-parallel` is internally passing globals around between threads, this mimics some of it's behavior.
      $transfer = \is_array($defined) ? \array_merge($defined, $transfer) : $transfer;

      // @codeCoverageIgnoreStart
      $executable = function () use ($future, $args, $include, $transfer) {
        \paralleling_setup($include, $transfer);
        return $future(...$args);
      };
      // @codeCoverageIgnoreEnd

      $future = \spawning($executable, 0, $channel, true);
      if ($channel !== null)
        $future->setChannel($channel);
    }

    $this->putInQueue($future, false, true);

    $this->parallel[] = $this->future = $future;

    return $future;
  }

  public function wait(): array
  {
    while (true) {
      if (!$this->coroutine instanceof CoroutineInterface)
        break;

      $this->coroutine->run();
      if ($this->futures->isEmpty()) {
        $this->coroutine->ioStop();
        break;
      }
    }

    return $this->results;
  }

  public function cancel(FutureInterface $future): void
  {
    $future->stop();
    $future->channelTick(1);
    while ($future->isRunning())
      $future->channelTick(0);
  }

  public function tick(FutureInterface $future)
  {
    if (!$this->coroutine instanceof CoroutineInterface || $this->isKilling)
      return;

    $this->coroutine->futureOn();
    $this->coroutine->run();
    $this->coroutine->futureOff();

    while ($future !== null && $future->isRunning()) {
      if ($future->isKilled())
        break;

      $this->coroutine->run();
    }
  }
}
