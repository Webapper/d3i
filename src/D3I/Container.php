<?php
/**
 * Created by PhpStorm.
 * User: assarte
 * Date: 2015.12.18.
 * Time: 14:12
 */

namespace Webapper\D3I;

use Webapper\D3I\Provider;
use Webapper\D3I\QueryParser;

/**
 * Class Container
 *
 * D3I Container is a dynamic decorator DI-container. Add any service provider callbacks to the container as
 * dynamic properties:
 *  * `$container->my_service = Provider::Create(function($c) {$o = new Service($c['my_service.settings'])})->share();`
 *
 * ...or add/get any values as config settings structures by using D3I queries eg.:
 *  * before service registered: `$config = $container['my_service.settings.anything']; // eg.: $container['my_service']['settings']->anything`
 *  * after service registered: `$config = $container['@my_service.settings.anything']; // eg.: $container->my_service['settings']->anything`
 *
 * Accessing to a service can be done only by property-access! You should use array-access (brackets) if you want to
 * access to the Provider of the service instead.
 *
 * You can document your services as magic properties using the `@property` PHP-Doc tag. Just use {@link getServiceNames}
 * method to list or debug which services are registered to the container if you lost tracking it.
 *
 * @package Webapper\D3I
 */
class Container extends \ArrayObject
{
	/**
	 * @var QueryParser
	 */
	protected $queryParser;

	/**
	 * Container constructor.
	 * @param array $input
	 */
	public function __construct(array $input=array())
	{
		$this->queryParser = new QueryParser($this);
		parent::__construct($input);
	}

	/**
	 * Sets a service provider dynamically as a property
	 * @param $attr
	 * @param Provider $value
	 */
	public function __set($attr, Provider $value) {
		$this[$attr] = $value;
	}

	/**
	 * Gets the given item or service
	 * @param $attr
	 * @return \Closure|mixed
	 */
	public function &__get($attr) {
		/** @var $service Provider|mixed */
		$service =& $this[$attr];
		if ($service === null) throw new \InvalidArgumentException('Service "'.$attr.'" is not exists');

		if (is_object($service) and $service instanceof Provider) {
			return $service($this);
		} else {
			return $service;
		}
	}

	/**
	 * @param string $attr
	 * @param mixed $value
	 * @return mixed|null
	 */
	public function offsetSet($attr, $value) {
		$parent =& $this->queryParser->parent($attr);
		if ($parent === $this or $parent === null or $this->offsetExists($attr)) {
			parent::offsetSet($attr, $value);
			return;
		}

		if ($this->queryParser->exists($attr)) {
			$old =& $this->queryParser->find($attr);
			$old = $value;
		} else {
			if ($parent === null and strlen(str_replace(array('.','#','!','@'), '', ltrim($attr, '.#!@'))) != strlen(ltrim($attr, '.#!@'))) throw new \RuntimeException('Unable to determine parent of '.$attr.' for change its value.');
			$qp = new QueryParser($parent);
			switch ($qp->getType()) {
				case QueryParser::TYPE_ARRAY:
				case QueryParser::TYPE_ACCESS: {
					$parent[$attr] = $value;
					break;
				}
				case QueryParser::TYPE_OBJECT: {
					$parent->$attr = $value;
					break;
				}
			}
		}
	}

	/**
	 * @param string $attr
	 * @return mixed|null
	 */
	public function &offsetGet($attr) {
		if ($this->offsetExists($attr)) {
			$result =& parent::offsetGet($attr);
			return $result;
		}

		$result =& $this->queryParser->find($attr);

		return $result;
	}

	/**
	 * Gets all of the services registered to this container.
	 * @return array
	 */
	public function getServiceNames() {
		$result = array();
		foreach ($this as $key=>$value) {
			if ($value instanceof Provider) $result[] = $key;
		}
		return $result;
	}
}