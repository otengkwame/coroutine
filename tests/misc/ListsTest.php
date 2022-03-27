<?php

namespace Async\Tests;

use Async\KeyError;
use Async\Datatype\Lists;
use Async\Datatype\Set;
use PHPUnit\Framework\TestCase;

class ListsTest extends TestCase
{
  protected function setUp(): void
  {
    coroutine_clear(false);
  }

  public function testLists()
  {
    $simple = new Lists("apple", "banana", "cherry", "apple", "banana");
    $this->assertEquals(["apple", "banana", "cherry", "apple", "banana"], $simple());
    $this->assertTrue(isset($simple[2]));
    $this->assertEquals("cherry", $simple[2]);
    $this->assertEquals("apple", $simple->min());
    $this->assertEquals("cherry", $simple->max());

    $simple[2] = 'orange';
    $this->assertEquals("orange", $simple[2]);
    $this->assertEquals(1, $simple->index('banana'));
    $this->assertEquals(2, $simple->counts('apple'));
    $this->assertEquals("orange", $simple->max());

    unset($simple[2]);
    $this->assertEquals(4, \count($simple));
    $this->assertEquals(["apple", "banana", 3 => "apple", 4 => "banana"], $simple());
  }

  public function testForeachLen()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $array = [];
    foreach ($simple as $value)
      $array[] = $value;

    $this->assertEquals(['apple', 'banana', 'cherry'], $array);
    $this->assertEquals(3, $simple->len());
    $this->assertEquals($array, $simple());
  }

  public function testInIndexError()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $this->assertTrue($simple->in('banana'));
    $this->assertTrue($simple->not_in('orange'));

    $this->expectException(KeyError::class);
    $simple->index('orange');
  }

  public function testDel()
  {
    $simple = new Lists();
    $this->assertEquals([], $simple());
    $simple->del();
    $this->expectException(KeyError::class);
    $simple();
  }

  public function testRemove()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $simple->remove("banana");
    $this->assertEquals(['apple', 2 => 'cherry'], $simple());
  }

  public function testRemoveError()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $this->expectException(KeyError::class);
    $simple->remove("orange");
  }

  public function testPop()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $this->assertEquals('cherry', $simple->pop());
    $this->assertEquals(['apple', 'banana'], $simple());
    $this->assertEquals('apple', $simple->pop(0));
    $this->assertEquals([1 => 'banana'], $simple());
  }

  public function testInsert()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $simple->insert(1, "orange");
    $this->assertEquals(['apple', 'orange', 'banana', "cherry"], $simple());
  }

  public function testAppend()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $simple->append('orange');
    $this->assertEquals(['apple', 'banana', 'cherry', 'orange'], $simple());
    $simple->del();

    $simple = new Lists("apple", "banana", "cherry");
    $simple->append(["Ford", "BMW", "Volvo"]);
    $this->assertEquals(['apple', 'banana', 'cherry', ["Ford", "BMW", "Volvo"]], $simple());
  }

  public function testExtend()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $simple->extend(['Ford', 'BMW', 'Volvo']);
    $this->assertEquals(['apple', 'banana', 'cherry', 'Ford', 'BMW', 'Volvo'], $simple());
    $simple->del();

    $simple = new Lists("apple", "banana", "cherry");
    $simple->extend(new Set(1, 4, 5, 9));
    $this->assertEquals(['apple', 'banana', 'cherry', 1, 4, 5, 9], $simple());
  }

  public function testReverse()
  {
    $simple = new Lists("apple", "banana", "cherry");
    $simple->reverse();
    $this->assertEquals(['cherry', 'banana', 'apple'], $simple());
  }

  public function testSort()
  {
    $simple = new Lists('Ford', 'BMW', 'Volvo');
    $simple->sort();
    $this->assertEquals(['BMW', 'Ford', 'Volvo'], $simple());
    $simple->del();

    $simple = new Lists('Ford', 'BMW', 'Volvo');
    $simple->sort(true);
    $this->assertEquals(['Volvo', 'Ford', 'BMW'], $simple());
  }

  public function testSortFunc()
  {
    $myFun = function ($a, $b) {
      return (\strlen($a) < \strlen($b)) ? -1 : 1;
    };

    $myFun2 = function ($a, $b) {
      return ($a[1]['year'] < $b[1]['year']) ? -1 : 1;
    };

    $simple = new Lists('Ford', 'Mitsubishi', 'BMW', 'VW');
    $simple->sort(null, $myFun);
    $this->assertEquals(['VW', 'BMW', 'Ford', 'Mitsubishi'], $simple());
    $simple->del();

    $simple = new Lists(
      [kv('car', 'Ford'), kv('year', 2005)],
      [kv('car', 'Mitsubishi'), kv('year', 2000)],
      [kv('car', 'BMW'), kv('year', 2019)],
      [kv('car', 'VW'), kv('year', 2011)]
    );

    $simple->sort(null, $myFun2);
    $this->assertEquals([
      [kv('car', 'Mitsubishi'), kv('year', 2000)],
      [kv('car', 'Ford'), kv('year', 2005)],
      [kv('car', 'VW'), kv('year', 2011)],
      [kv('car', 'BMW'), kv('year', 2019)]
    ], $simple());
  }
}
