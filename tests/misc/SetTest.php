<?php

namespace Async\Tests;

use Async\KeyError;
use Async\Datatype\Set;
use PHPUnit\Framework\TestCase;

class SetTest extends TestCase
{
  protected $results = null;

  protected function setUp(): void
  {
    coroutine_clear(false);
  }

  public function testAddClearCopy()
  {
    $unique = new Set("apple", "banana", "cherry", "apple", "banana");
    $unique->add("cherry");
    $unique->add("orange");
    $this->assertEquals(['apple', 'banana', 'cherry', 'orange'], $unique());
    $unique->clear();
    $this->assertEquals([], $unique->copy());
  }

  public function testRemove()
  {
    $unique = new Set("apple", "banana", "cherry");
    $unique->remove("banana");
    $this->assertEquals(['apple', 'cherry'], $unique());
  }

  public function testRemoveError()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->expectException(KeyError::class);
    $unique->remove("orange");
  }

  public function testDifference()
  {
    $unique = new Set("apple", "banana", "cherry");
    $other = $unique->difference("google", "microsoft", "apple");
    $this->assertEquals(['banana', 'cherry'], $other);
  }

  public function testDifference_update()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->assertEquals(['banana', 'cherry'], $unique->difference_update("google", "microsoft", "apple")());
  }

  public function testDiscard()
  {
    $unique = new Set("apple", "banana", "cherry");
    $unique->discard("banana");
    $this->assertEquals(['apple', 'cherry'], $unique());
  }

  public function testIntersection()
  {
    $unique = new Set("apple", "banana", "cherry");
    $other = $unique->intersection("google", "microsoft", "apple");
    $this->assertEquals(['apple'], $other);
  }

  public function testIntersection_update()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->assertEquals(['apple'], $unique->intersection_update("google", "microsoft", "apple")->copy());
  }

  public function testisDisjoint()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->assertTrue($unique->isDisjoint("google", "microsoft", "facebook"));
  }

  public function testisSubset()
  {
    $unique = new Set("a", "b", "c");;
    $this->assertTrue($unique->isSubset("f", "e", "d", "c", "b", "a"));
  }

  public function testisSuperset()
  {
    $unique = new Set("f", "e", "d", "c", "b", "a");
    $this->assertTrue($unique->isSuperset("a", "b", "c"));
  }

  public function testPop()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->assertEquals('cherry', $unique->pop());
    $this->assertEquals(['apple', 'banana'], $unique());
  }

  public function testPopError()
  {
    $unique = new Set();
    $this->expectException(KeyError::class);
    $unique->pop();
  }

  public function testSymmetric_difference()
  {
    $unique = new Set("apple", "banana", "cherry");
    $other = $unique->symmetric_difference("google", "microsoft", "apple");
    $this->assertEquals(['banana', 'cherry', 'google', 'microsoft'], $other);
  }

  public function testSymmetric_difference_update()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->assertEquals(
      ['banana', 'cherry', 'google', 'microsoft'],
      $unique->symmetric_difference_update("google", "microsoft", "apple")->copy()
    );
  }

  public function testUnion()
  {
    $unique = new Set("apple", "banana", "cherry");
    $other = $unique->union("google", "microsoft", "apple");
    $unique->union();
    $this->assertEquals(['apple', 'banana', 'cherry', 'google', 'microsoft'], $other);
  }

  public function testUpdate()
  {
    $unique = new Set("apple", "banana", "cherry");
    $this->assertEquals(
      ['apple', 'banana', 'cherry', 'google', 'microsoft'],
      $unique->update("google", "microsoft", "apple")()
    );
  }

  public function testForeachCount()
  {
    $unique = new Set("apple", "banana", "cherry");
    $unique2 = new Set("google", "microsoft", "apple");
    $unique->update($unique2);
    $set = [];
    foreach ($unique as $value)
      $set[] = $value;

    $this->assertEquals(['apple', 'banana', 'cherry', 'google', 'microsoft'], $set);
    $this->assertEquals(5, \count($unique));
    $this->assertEquals($set, $unique());
  }
}
