<?php

namespace Webapper\D3I\Tests;
use Webapper\D3I\Container;
use Webapper\D3I\Provider;

/**
 * Created by PhpStorm.
 * User: assarte
 * Date: 2015.12.20.
 * Time: 17:56
 */
class D3ITest extends \PHPUnit_Framework_TestCase {
	public function testSimpleKeyScalar() {
		$d3i = new Container();
		$d3i['test'] = 'test';

		$this->assertEquals('test', $d3i['test']);
	}

	public function testSimpleInitScalar() {
		$d3i = new Container(['test'=>'test']);

		$this->assertEquals('test', $d3i['test']);
	}

	public function testSimplePropertyScalar() {
		$d3i = new Container();
		$d3i['test'] = 'test';

		$this->assertEquals('test', $d3i->test);
	}

	public function testSimpleKeyArray() {
		$d3i = new Container();
		$d3i['test'] = ['test'];

		$this->assertEquals('test', $d3i['test'][0]);
	}

	public function testSimplePropertyArray() {
		$d3i = new Container();
		$d3i['test'] = ['test'];

		$this->assertEquals('test', $d3i->test[0]);
	}

	public function testQueryKeyArray() {
		$d3i = new Container();
		$d3i['test'] = ['test'];

		$this->assertEquals('test', $d3i['test#0']);
	}

	public function testQueryPropertyArray() {
		$d3i = new Container();
		$d3i['test'] = ['test'];

		$this->assertEquals('test', $d3i->{'test#0'});
	}

	public function testQueryKeyDeepObjectInArray() {
		$d3i = new Container();
		$obj = new \stdClass();
		for ($i=0; $i<5; $i++) {
			$value = [];
			for ($j=0; $j<3; $j++) {
				$value[] = 'test';
			}
			$obj->{'property'.$i} = $value;
		}
		$d3i['test'] = $obj;

		$this->assertEquals('test', $d3i['test.property4#2']);
	}

	public function testSharedService() {
		$d3i = new Container();
		$d3i->test = Provider::Create(function(Container $c) {
			$service = new Service();
			$service->settings = 'test';
			return $service;
		})->share();

		$this->assertInstanceOf('Webapper\D3I\Provider', $d3i['test']);
		$this->assertInstanceOf('Webapper\D3I\Tests\Service', $d3i->test);

		$service1 = $d3i->test;
		$service2 = $d3i->test;
		$this->assertSame($service1, $service2);

		$this->assertEquals('test', $service1->settings);
	}

	public function testQuerySharedServiceProperty() {
		$d3i = new Container();
		$d3i->test = Provider::Create(function(Container $c) {
			$service = new Service();
			$service->settings = 'test';
			return $service;
		})->share();

		$this->assertInstanceOf('Webapper\D3I\Provider', $d3i['test']);
		$this->assertInstanceOf('Webapper\D3I\Tests\Service', $d3i->test);

		$service1 = $d3i->test;
		$service2 = $d3i->test;
		$this->assertSame($service1, $service2);

		$this->assertEquals('test', $d3i['@test.settings']);
	}

	public function testQuerySharedServiceMutatedProperty() {
		$d3i = new Container(['test'=>['settings'=>'test']]);
		$d3i->test = Provider::Create(function(Container $c) {
			$service = new Service();
			$service->settings = $c['!test.settings'];
			return $service;
		})->mutate($d3i['test'])->share();

		$this->assertInstanceOf('Webapper\D3I\Provider', $d3i['test']);
		$this->assertInstanceOf('Webapper\D3I\Tests\Service', $d3i->test);

		$service1 = $d3i->test;
		$service2 = $d3i->test;
		$this->assertSame($service1, $service2);

		$this->assertEquals('test', $d3i['@test.settings']);
	}
}