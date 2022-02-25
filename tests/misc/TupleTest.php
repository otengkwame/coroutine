<?php

namespace Async\Tests;

use Async\KeyError;
use Async\Misc\Tuple;
use PHPUnit\Framework\TestCase;

class TupleTest extends TestCase
{
  protected function setUp(): void
  {
    coroutine_clear(false);
  }

  public function testTuple()
  {
    $constant = new Tuple("apple", "banana", "cherry", "apple", "banana");
    $this->assertEquals(["apple", "banana", "cherry", "apple", "banana"], $constant());
    $this->assertEquals("cherry", $constant->index[2]);
    $this->assertEquals(1, $constant->index('banana'));
    $this->assertEquals(2, $constant->counts('apple'));
    $this->assertEquals(5, \count($constant));
  }

  public function testForeachLen()
  {
    $constant = new Tuple("apple", "banana", "cherry");
    $tuple = [];
    foreach ($constant as $value)
      $tuple[] = $value;

    $this->assertEquals(['apple', 'banana', 'cherry'], $tuple);
    $this->assertEquals(3, $constant->len());
    $this->assertEquals($tuple, $constant());
  }

  public function testInIndexError()
  {
    $constant = new Tuple("apple", "banana", "cherry");
    $this->assertTrue($constant->in('banana'));
    $this->assertTrue($constant->not_in('orange'));

    $this->expectException(KeyError::class);
    $constant->index('orange');
  }

  public function testDel()
  {
    $constant = new Tuple();
    $this->assertEquals([], $constant());
    $constant->del();
    $this->expectException(KeyError::class);
    $constant();
  }

  public function testTupleString()
  {
    $constant = new Tuple("abc");
    $this->assertEquals(['a', 'b', 'c'], $constant());
    $constant->del();
    $this->expectException(KeyError::class);
    $constant = new Tuple(1);
  }

  public function testTupleSingle()
  {
    $constant = new Tuple(1, null);
    $this->assertEquals([1], $constant());
    $constant->del();
    $constant = new Tuple("abc", '');
    $this->assertEquals(['abc'], $constant());
  }
}
