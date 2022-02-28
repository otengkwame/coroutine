<?php

namespace Async\Tests;

use Async\KeyError;
use Async\Misc\Dict;
use PHPUnit\Framework\TestCase;

class DictTest extends TestCase
{
  protected function setUp(): void
  {
    coroutine_clear(false);
  }

  public function testAddClearCopy()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 2020));
    $this->assertEquals(['brand' => 'Ford', 'model' => 'Mustang', 'year' => 2020], $assoc());

    $assoc->engine = '6.0 awd';
    $assoc['color'] = 'red';
    $this->assertEquals(['brand' => 'Ford', 'model' => 'Mustang', 'year' => 2020, 'engine' => '6.0 awd', 'color' => 'red'], $assoc());
    $this->assertEquals(5, $assoc->len());
    $this->assertEquals('Mustang', $assoc->model);
    $this->assertEquals('red', $assoc['color']);

    $assoc->clear();
    $this->assertEquals([], $assoc());
  }

  public function testDictIterator()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 2020));
    $this->assertEquals(['brand', 'model', 'year'], $assoc->list());

    $assoc2 = new Dict($assoc);
    $this->assertEquals('Ford', $assoc2->brand);
    $this->assertEquals(['brand' => 'Ford', 'model' => 'Mustang', 'year' => 2020], $assoc2());
  }

  public function testGet()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 1964));
    $this->assertEquals('Mustang', $assoc->get('model'));
    $this->assertEquals(3, \count($assoc));
  }

  public function testUpdate()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 1964));
    $assoc->update(kv('year', 2020));
    $this->assertEquals(['brand' => 'Ford', 'model' => 'Mustang', 'year' => 2020], $assoc());
  }

  public function testPop()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 1964));
    $item = $assoc->pop('brand');
    $this->assertEquals('Ford', $item);
    $this->assertEquals(['model' => 'Mustang', 'year' => 1964], $assoc());

    $item = $assoc->pop('car', 'green');
    $this->assertEquals('green', $item);

    $this->expectException(KeyError::class);
    $assoc->pop('manufacture');
  }

  public function testPopItem()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 1964));
    $item = $assoc->popItem();
    $this->assertEquals(1964, $item);
    $this->assertEquals(['brand' => 'Ford', 'model' => 'Mustang'], $assoc());

    $assoc->clear();
    $this->expectException(KeyError::class);
    $assoc->popItem();
  }

  public function testDel()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 1964));
    $assoc->del('model');
    $this->assertEquals(['brand' => 'Ford', 'year' => 1964], $assoc());

    $assoc->del($assoc);
    $this->expectException(KeyError::class);
    $assoc->clear();
  }

  public function testSetDefault()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 2020));
    $this->assertEquals(2020, $assoc->setDefault('year'));
    $this->assertEquals(6.0, $assoc->setDefault('engine', 6.0));
    $this->assertEquals(4, \count($assoc));
    $this->assertEquals(['brand' => 'Ford', 'model' => 'Mustang', 'year' => 2020, 'engine' => 6.0], $assoc());
  }

  public function testFromKeys()
  {
    $assoc = new Dict(kv('brand', 'Ford'), kv('model', 'Mustang'), kv('year', 2020));
    $dict = Dict::fromKeys($assoc, 0);
    $this->assertInstanceOf(Dict::class, $dict);
    $this->assertEquals(['brand' => 0, 'model' => 0, 'year' => 0], $dict());
  }

  public function testFromKeysNone()
  {
    $dict = Dict::fromKeys(['key1', 'key2', 'key3']);
    $this->assertEquals(['key1' => null, 'key2' => null, 'key3' => null], $dict());
  }
}
