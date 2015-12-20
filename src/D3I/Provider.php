<?php
/**
 * Created by PhpStorm.
 * User: assarte
 * Date: 2015.12.19.
 * Time: 5:13
 */

namespace Webapper\D3I;


use Webapper\D3I\Container;

/**
 * Class Provider
 *
 * Encapsulates the given service and its provider callback. Provider is callable, accepts its container and
 * (optionally) the Provider to be extended as its arguments.
 *
 * @package Webapper\D3I
 */
class Provider
{
	/**
	 * @var \Closure
	 */
	protected $service;

	/**
	 * @var \Closure
	 */
	protected $injector;

	protected $mutated;

	/**
	 * @var bool
	 */
	protected $isMutated = false;

	/**
	 * Provider constructor.
	 * @param callable $service `function(Container $c, Provider $p=null)`
	 */
	public function __construct($service) {
		$this->service = $this->castToClosure($service);
	}

	/**
	 * @param $service
	 * @return Provider
	 */
	public static function Create($service) {
		return new static($service);
	}

	/**
	 * Casts the given callable to Closure
	 * @param callable $callable
	 * @return \Closure
	 * @throws \InvalidArgumentException
	 */
	protected function castToClosure($callable) {
		if (!is_callable($callable)) throw new \InvalidArgumentException('Argument 1 must be callable, '.gettype($callable).' given.');

		$result = null;

		if (is_object($callable)) {
			if ($callable instanceof \Closure) {
				$result = $callable;
			} else {
				if (method_exists($callable, '__invoke')) {
					$r = new \ReflectionObject($callable);
					$result = $r->getMethod('__invoke')->getClosure($callable);
				}
			}
		} else {
			try {
				$r = new \ReflectionMethod($callable);
				$result = $r->getClosure();
			} catch (\Exception $e) {
				try {
					$r = new \ReflectionFunction($callable);
					$result = $r->getClosure();
				} catch (\Exception $e) {
					if (is_array($callable)) {
						$r = new \ReflectionObject($callable[0]);
						$result = $r->getMethod($callable[1])->getClosure($callable[0]);
					}
				}
			}
		}

		if ($result === null) throw new \InvalidArgumentException('Unsupported callable given.');

		return $result;
	}

	/**
	 * Help lazy execution of the service definition callable
	 *
	 * @return Provider $this
	 */
	public function share()
	{
		$this->injector = function ($c) {
			static $object;

			if ($object === null) {
				$service = $this->service;
				$object = $service($c);
			}

			return $object;
		};
		return $this;
	}

	/**
	 * Help avoiding execution of the service definition callable
	 *
	 * @return Provider $this
	 */
	public function protect()
	{
		$this->injector = function ($c) {
			return $this->service;
		};
		return $this;
	}

	/**
	 * Lazy extend of a service definition
	 *
	 * @param callable $service A service definition to extend the original
	 * @return Provider $this
	 * @throws \InvalidArgumentException if unsupported callable or not a callable
	 */
	public function extend($service)
	{
		if (!is_callable($service)) throw new \InvalidArgumentException('Service must be callable, '.gettype($service).' given.');

		$source = $this->service;
		$this->service = $this->castToClosure($service);

		$this->injector = function($c) use ($source) {
			static $object;

			if ($object === null) {
				$service = $this->service;
				$object = $service($c, $source($c));
			}

			return $object;
		};

		return $this;
	}

	/**
	 * Help mutating a container item. You can access the passed item by querying this provider using "!"-prefix.
	 * @param mixed $item
	 * @return $this
	 */
	public function mutate(&$item) {
		$this->mutated =& $item;
		$this->isMutated = true;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function &getMutatedItem() {
		return $this->mutated;
	}

	/**
	 * @return bool
	 */
	public function isMutated() {
		return $this->isMutated;
	}

	/**
	 * Executes the service definition
	 * @param Container $d3i
	 * @param object $extends
	 * @return mixed The service
	 */
	public function __invoke(Container $d3i, $extends=null) {
		if ($this->injector === null) throw new \RuntimeException('Provider does not initialized. Use one of the methods share(), protect(), or extend() to initialize the provided service!');

		$injector = $this->injector;
		if ($extends === null) {
			return $injector($d3i);
		} else {
			return $injector($d3i, $extends);
		}
	}
}