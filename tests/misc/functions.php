<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!\function_exists('test_raises')) {

  /**
   * Assertions about _raised_ **throw** exceptions.
   *
   * @param TestCase $test
   * @param string $exception
   * @param callable $function
   * @param mixed ...$arguments
   * @see https://docs.pytest.org/en/6.2.x/reference.html#pytest-raises
   */
  function test_raises(TestCase $test, string $exception, callable $function, ...$arguments)
  {
    $test->expectException($exception);
    return $function(...$arguments);
  }

  /**
   * Assertions about _raised_ **throw** exceptions.
   * - This function needs to be prefixed with `yield`
   *
   * @param TestCase $test
   * @param string $exception
   * @param callable $function
   * @param mixed ...$arguments
   * @see https://docs.pytest.org/en/6.2.x/reference.html#pytest-raises
   */
  function test_raises_async(TestCase $test, string $exception, callable $function, ...$arguments)
  {
    $test->expectException($exception);
    return yield $function(...$arguments);
  }

  function fib(int $n)
  {
    if ($n <= 2)
      return 1;
    else
      return fib($n - 1) + fib($n - 2);
  }
}
