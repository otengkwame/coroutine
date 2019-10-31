<?php

declare(strict_types=1);

namespace Async\Coroutine;

use ArrayAccess;
use InvalidArgumentException;
use Async\Coroutine\ParallelStatus;
use Async\Coroutine\ParallelInterface;
use Async\Coroutine\CoroutineInterface;
use Async\Processor\Processor;
use Async\Processor\ProcessInterface;

class Parallel implements ArrayAccess, ParallelInterface
{
    private $coroutine = null;
    private $processor = null;
    private $status;
    private $process;
    private $concurrency = 20;
    private $queue = [];
    private $results = [];
    private $finished = [];
    private $failed = [];
    private $timeouts = [];
    private $parallel = [];

    public function __construct(CoroutineInterface $coroutine = null)
    {
        $this->coroutine = empty($coroutine) ? \coroutine_instance() : $coroutine;
        $this->processor = $this->coroutine->processInstance(
            [$this, 'markAsTimedOut'],
            [$this, 'markAsFinished'],
            [$this, 'markAsFailed']
        );

        $this->status = new ParallelStatus($this);
    }

    /**
     * @return static
     */
    public static function create(): ParallelInterface
    {
        return new static();
    }

    public function concurrency(int $concurrency): ParallelInterface
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function sleepTime(int $sleepTime)
    {
        $this->processor->sleepTime($sleepTime);
    }

    public function results(): array
    {
        return $this->results;
    }

    public function isPcntl(): bool
    {
        return $this->processor->isPcntl();
    }

    public function status(): ParallelStatus
    {
        return $this->status;
    }

    /**
     * @param ProcessInterface|callable $process
     *
     * @return ProcessInterface
     */
    public function add($process, int $timeout = 300): ProcessInterface
    {
        if (!is_callable($process) && !$process instanceof ProcessInterface) {
            throw new InvalidArgumentException('The process passed to Parallel::add should be callable.');
        }

        if (!$process instanceof ProcessInterface) {
            $process = Processor::create($process, $timeout);
        }

        $this->putInQueue($process);

        $this->parallel[] = $this->process = $process;

        return $process;
    }

    private function notify($restart = false)
    {
        if ($this->processor->count() >= $this->concurrency) {
            return;
        }

        $process = \array_shift($this->queue);

        if (!$process) {
            return;
        }

        $this->putInProgress($process, $restart);
    }

    public function retry(ProcessInterface $process = null): ProcessInterface
    {
        $this->putInQueue((empty($process) ? $this->process : $process), true);

        return $this->process;
    }

    public function wait(): array
    {
        while (true) {
            $this->coroutine->run();
            if ($this->processor->isEmpty())
                break;
        }

        return $this->results;
    }

    /**
     * @return ProcessInterface[]
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    private function putInQueue(ProcessInterface $process, $restart = false)
    {
        $this->queue[$process->getId()] = $process;

        $this->notify($restart);
    }

    private function putInProgress(ProcessInterface $process, $restart = false)
    {
        unset($this->queue[$process->getId()]);

        if ($restart) {
            $process = $process->restart();
            $this->process = $process;
        } else {
            $process->start();
        }

        $this->processor->add($process);
    }

    public function markAsFinished(ProcessInterface $process)
    {
        $this->notify();

        $this->results[] = yield from $process->yieldSuccess();

        $this->finished[$process->getPid()] = $process;
    }

    public function markAsTimedOut(ProcessInterface $process)
    {
        $this->notify();

        yield $process->yieldTimeout();

        $this->timeouts[$process->getPid()] = $process;
    }

    public function markAsFailed(ProcessInterface $process)
    {
        $this->notify();

        yield $process->yieldError();

        $this->failed[$process->getPid()] = $process;
    }

    public function getFinished(): array
    {
        return $this->finished;
    }

    public function getFailed(): array
    {
        return $this->failed;
    }

    public function getTimeouts(): array
    {
        return $this->timeouts;
    }

    public function offsetExists($offset)
    {
        return isset($this->parallel[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->parallel[$offset]) ? $this->parallel[$offset] : null;
    }

    public function offsetSet($offset, $value, int $timeout = 300)
    {
        $this->add($value, $timeout);
    }

    public function offsetUnset($offset)
    {
        $this->processor->remove($this->parallel[$offset]);
        unset($this->parallel[$offset]);
    }
}
