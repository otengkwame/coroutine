<?php

namespace Async\Tests\Di;

use Async\Di\Injection;
use Async\ContainerException;
use Async\NotFoundException;
use Psr\Container\ContainerInterface;

use Async\Tests\Di\Baz;
use Async\Tests\Di\Bar;
use Async\Tests\Di\Foo;
use PHPUnit\Framework\TestCase;

class InjectionTest extends TestCase
{
    public function testSet()
    {
        $container = new Injection();
        $this->assertTrue($container instanceof ContainerInterface);
        $container->set('Baz');
        $this->assertTrue($container->has('Baz'));
    }

    public function testHas()
    {
        $container = new Injection();
        $container->set('Test', 'Test');
        $this->assertTrue($container->has('Test'));
        $this->assertFalse($container->has('TestOther'));
    }

    public function testAutoWire()
    {
        $container = new Injection();
        $container->set('Baz', 'Baz');
        $container->set('Async\Tests\Di\DiInterface', 'Async\Tests\Di\Foo');
        $baz = $container->autoWire('Async\Tests\Di\Baz');
        $this->assertTrue($baz instanceof Baz);
        $this->assertTrue($baz->foo instanceof Foo);
    }

    public function testAutoWire_Exception()
    {
        $container = new Injection();
        $this->expectException(\ReflectionException::class);
        $baz = $container->autoWire('Baz');
    }

    public function testAutoWire_Error()
    {
        $container = new Injection();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/[is not instantiable]/');
        $baz = $container->autoWire('Async\Tests\Di\Baz');
    }

    public function testGet()
    {
        $container = new Injection();
        $container->set('Async\Tests\Di\Baz', 'Async\Tests\Di\Baz');
        $container->set('Async\Tests\Di\DiInterface', 'Async\Tests\Di\Bar');
        $baz = $container->get('Async\Tests\Di\Baz');
        $this->assertTrue($baz instanceof Baz);
        $this->assertTrue($baz->foo instanceof Bar);
    }

    public function testGet_Error()
    {
        $container = new Injection();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessageMatches('/[does not exists]/');
        $baz = $container->get('Baz');
    }
}
