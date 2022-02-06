<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\ContainerException;
use Psr\Container\ContainerInterface;

interface InjectionInterface extends ContainerInterface
{
	/**
	 * Register a service with the container.
	 *
	 * @param string $id - className
	 * @param string $concrete - friendlyName
	 */
	public function set(string $id, $concrete = NULL);

	/**
	 * Auto setup, execute, or resolve any dependencies.
	 *
	 * @param $id
	 * @param array $values
	 *
	 * @return mixed|null|object
	 * @throws ContainerException
	 */
	public function autoWire(string $id, $values = []);
}
