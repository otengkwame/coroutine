<?php

namespace Async\Tests;

use Async\KeyError;
use Async\Misc\Set;
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
    $array = new Set("apple", "banana", "cherry", "apple", "banana");
    $array->add("cherry");
    $array->add("orange");
    $this->assertEquals(['apple', 'banana', 'cherry', 'orange'], $array());
    $array->clear();
    $this->assertEquals([], $array->copy());
  }

  public function testRemove()
  {
    $array = new Set("apple", "banana", "cherry");
    $array->remove("banana");
    $this->assertEquals(['apple', 'cherry'], $array());
  }

  public function testRemoveError()
  {
    $array = new Set("apple", "banana", "cherry");
    $this->expectException(KeyError::class);
    $array->remove("orange");
  }

  public function testDifference()
  {
    $array = new Set("apple", "banana", "cherry");
    $other = $array->difference("google", "microsoft", "apple");
    $this->assertEquals(['banana', 'cherry'], $other);
  }

  public function testDifference_update()
  {
    $array = new Set("apple", "banana", "cherry");
    $this->assertEquals(['banana', 'cherry'], $array->difference_update("google", "microsoft", "apple")());
  }

  public function testDiscard()
  {
    $array = new Set("apple", "banana", "cherry");
    $array->discard("banana");
    $this->assertEquals(['apple', 'cherry'], $array->copy());
  }

  public function testIntersection()
  {
    $array = new Set("apple", "banana", "cherry");
    $other = $array->intersection("google", "microsoft", "apple");
    $this->assertEquals(['apple'], $other);
  }

  public function testIntersection_update()
  {
    $array = new Set("apple", "banana", "cherry");
    $this->assertEquals(['apple'], $array->intersection_update("google", "microsoft", "apple")->copy());
  }

  public function testisDisjoint()
  {
    $array = new Set("apple", "banana", "cherry");;
    $this->assertTrue($array->isDisjoint("google", "microsoft", "facebook"));
  }

  public function testisSubset()
  {
    $array = new Set("a", "b", "c");;
    $this->assertTrue($array->isSubset("f", "e", "d", "c", "b", "a"));
  }

  public function testisSuperset()
  {
    $array = new Set("f", "e", "d", "c", "b", "a");
    $this->assertTrue($array->isSuperset("a", "b", "c"));
  }

  public function testPop()
  {
    $array = new Set("apple", "banana", "cherry");
    $this->assertEquals('cherry', $array->pop());
    $this->assertEquals(['apple', 'banana'], $array->copy());
  }

  public function testPopError()
  {
    $array = new Set();
    $this->expectException(KeyError::class);
    $array->pop();
  }

  public function testSymmetric_difference()
  {
    $array = new Set("apple", "banana", "cherry");
    $other = $array->symmetric_difference("google", "microsoft", "apple");
    $this->assertEquals(['banana', 'cherry', 'google', 'microsoft'], $other);
  }

  public function testSymmetric_difference_update()
  {
    $array = new Set("apple", "banana", "cherry");
    $this->assertEquals(
      ['banana', 'cherry', 'google', 'microsoft'],
      $array->symmetric_difference_update("google", "microsoft", "apple")->copy()
    );
  }

  public function testUnion()
  {
    $array = new Set("apple", "banana", "cherry");
    $other = $array->union("google", "microsoft", "apple");
    $array->union();
    $this->assertEquals(['apple', 'banana', 'cherry', 'google', 'microsoft'], $other);
  }

  public function testUpdate()
  {
    $array = new Set("apple", "banana", "cherry");
    $this->assertEquals(
      ['apple', 'banana', 'cherry', 'google', 'microsoft'],
      $array->update("google", "microsoft", "apple")()
    );
  }

  public function testForeachCount()
  {
    $array = new Set("apple", "banana", "cherry");
    $array2 = new Set("google", "microsoft", "apple");
    $array->update($array2);
    $set = [];
    foreach ($array as $value)
      $set[] = $value;

    $this->assertEquals(['apple', 'banana', 'cherry', 'google', 'microsoft'], $set);
    $this->assertEquals(5, \count($array));
    $this->assertEquals($set, $array());
  }
}
