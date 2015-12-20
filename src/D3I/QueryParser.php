<?php
/**
 * Created by PhpStorm.
 * User: assarte
 * Date: 2015.12.20.
 * Time: 7:30
 */

namespace Webapper\D3I;

/**
 * Class QueryParser
 *
 * Querying an object or array structure. A query can contain alpha-numeric keys prefixed by "." (dot) and integer keys
 * prefixed by "#" (hash mark).
 *
 * Example: a query `"foo.bar#2.baz"` represents eg. `$obj->foo['bar'][2]->baz` or `$obj->{'foo.bar'}[2]->baz`, etc.
 *
 * You can use prefixes for indicating type of (sub-)elements:
 *  * "." (dot) indicates string index
 *  * "#" (hash mark) indicates integer index
 *  * "@" (at) indicates a {@link Provider} instance (parser will call the instance and using its returned service as the item)
 *
 * @package Webapper\D3I
 */
class QueryParser {
	const TYPE_ARRAY = 'Array';
	const TYPE_ACCESS = 'Access';
	const TYPE_OBJECT = 'Object';

	/**
	 * @var object|\ArrayAccess|array
	 */
	protected $object;
	/**
	 * @var string
	 */
	protected $type;

	public function __construct(&$object) {
		if (!is_array($object) and !is_object($object)) throw new \InvalidArgumentException('Argument 1 must be object or array, '.gettype($object).' given.');

		$this->object =& $object;
		if (is_array($object)) {
			$this->type = static::TYPE_ARRAY;
		} else if (is_object($object)) {
			if ($object instanceof \ArrayAccess) {
				$this->type = static::TYPE_ACCESS;
			} else {
				$this->type = static::TYPE_OBJECT;
			}
		}
	}

	/**
	 * @param $object
	 * @return QueryParser
	 */
	public static function Create(&$object) {
		return new static($object);
	}

	/**
	 * Finds an item by query and returns it by its reference
	 * @param string $query
	 * @return mixed|null
	 */
	public function &find($query) {
		if (empty($query)) throw new \InvalidArgumentException('Argument 1 should not be empty.');

		$keyExists = 'exists'.$this->type;
		$key = '';
		$result = null;
		while (strlen($key .= $this->shiftKey($query)) > 0 and $result === null) {
			$realKey = $this->parseKey($key);
			if ($this->$keyExists($realKey)) {
				switch ($this->type) {
					case static::TYPE_ARRAY: {
						$result =& $this->object[$realKey];
						break;
					}
					case static::TYPE_ACCESS: {
						if ($realKey{0} !== '@' and $realKey{0} !== '!' and $this->object->offsetExists($realKey)) {
							$result =& $this->object[$realKey];
							break;
						}
					}
					case static::TYPE_OBJECT: {
						if ($key{0} === '@') {
							$call = $this->object->$realKey;
							if (!($this->object instanceof Provider)) throw new \RuntimeException('"'.$key.'" is not a Provider. Using "@" (service-getter) prefix only allowed on Provider item.');
							$result = $call($this->object);
						} else if ($key{0} == '!') {
							if (!($this->object instanceof Provider)) throw new \RuntimeException('"'.$key.'" is not a Provider. Using "!" (mutation-getter) prefix only allowed on Provider item.');
							if (!$this->object->isMutated()) throw new \RuntimeException('Provider "'.$key.'" is not mutated.');
							$result =& $this->object->getMutatedItem();
						} else {
							$result =& $this->object->$realKey;
						}
						break;
					}
				}
				if ($query !== '' and (is_array($result) or is_object($result))) {
					$result =& static::Create($result)->find($query);
				}
			} else if ($query === '') {
				break;
			}
		}

		return $result;
	}

	/**
	 * Checks whether if an item exists on the queried path or not
	 * @param string $query
	 * @return bool
	 */
	public function exists($query) {
		if (empty($query)) throw new \InvalidArgumentException('Argument 1 should not be empty.');

		$keyExists = 'exists'.$this->type;
		$key = '';
		$result = null;
		while (strlen($key .= $this->shiftKey($query)) > 0 and $result === null) {
			$realKey = $this->parseKey($key);
			if ($this->$keyExists($realKey)) {
				switch ($this->type) {
					case static::TYPE_ARRAY: {
						$result =& $this->object[$realKey];
						break;
					}
					case static::TYPE_ACCESS: {
						if ($realKey{0} !== '@' and $realKey{0} !== '!' and $this->object->offsetExists($realKey)) {
							$result =& $this->object[$realKey];
							break;
						}
					}
					case static::TYPE_OBJECT: {
						if ($key{0} === '@') {
							$call = $this->object->$realKey;
							if (!($this->object instanceof Provider)) throw new \RuntimeException('"'.$key.'" is not a Provider. Using "@" (service-getter) prefix only allowed on Provider item.');
							$result = $call($this->object);
						} else if ($key{0} == '!') {
							if (!($this->object instanceof Provider)) throw new \RuntimeException('"'.$key.'" is not a Provider. Using "!" (mutation-getter) prefix only allowed on Provider item.');
							if (!$this->object->isMutated()) throw new \RuntimeException('Provider "'.$key.'" is not mutated.');
							$result =& $this->object->getMutatedItem();
						} else {
							$result =& $this->object->$realKey;
						}
						break;
					}
				}
				if ($query !== '' and (is_array($result) or is_object($result))) {
					$q = new static($result);
					if ($q->exists($query)) {
						$result =& QueryParser::Create($result)->find($query);
					} else {
						return false;
					}
				}
			} else if ($query === '') {
				return false;
			}
		}

		return true;
	}

	public function &parent($query) {
		if (empty($query)) throw new \InvalidArgumentException('Argument 1 should not be empty.');

		$re = '#^([\.\#\@\!]?.+)[\.\#\@\!]#';
		$result = null;
		$match = null;
		if (preg_match($re, $query, $match)) {
			$result =& $this->find($match[1]);
		} else {
			$result =& $this->object;
		}
		return $result;
	}

	public function getType() {
		return $this->type;
	}

	protected function shiftKey(&$key) {
		$re = '#^([\.\#\@\!]?[^\.\#\@\!]+?)(([\.\#\@\!]|$).*)$#';
		$matches = null;
		if (preg_match($re, $key, $matches)) {
			$key = $matches[2];
			return $matches[1];
		}
		$result = substr($key, 0);
		$key = '';
		return $result;
	}

	protected function parseKey($key) {
		$key = ltrim($key, '.@!');
		if ($key{0} == '#') return (int)substr($key, 1);
		return $key;
	}

	protected function existsArray($key) {
		return array_key_exists($key, $this->object);
	}

	protected function existsAccess($key) {
		if ($this->object->offsetExists($key)) return true;

		return $this->existsObject($key);
	}

	protected function existsObject($key) {
		if (isset($this->object->$key)) return true;
		if (@$this->object->$key === null) return false;
		return true;
	}
}