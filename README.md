# D3I
D3I (Dynamic Decorator Dependency Injection) is a replacement of Pimple which I suggest to use for Silex-alike frameworks.

# Main Definitions

## Container

The Container objects are for keeping services and other values (eg. configuration setting values). You can access
for all values by using property access (arrow-style: `->key`) and array item access (brackets-style: `[key]`).

When you accessing an item as a property and its a Provider, the provider will be called and you will give its service:
```php
$container = new Container();
$container->my_service = Provider::Create(function(Container $c) {...return $service;})->share(); // registering a service
$service = $container->my_service; // getting the unique instance of the "my_service" service
```

## Provider

This is for encapsulating a service to be registered. You can get, share, protect, or extend a service by using this
class.

Getting a service:
```php
$service = $provider($container);
```
Sharing a service:
```php
$provider->share();
```
Protecting a service:
```php
$provider->protect();
```
Extending a service:
```php
$extendedProvider = $provider->extend(function(Container $c, $p) {
	// ...using of parent container
	$foo = $c['foo'] + 1;
	// ...using of parent service
	$p->setFoo($foo);
	return $p;
});
```
...or using a provider extension:
```php
$container->my_extension = Provider::Create(function(Container $c, $p=null) {
	// checking whether if extending a service or not:
	if ($p === null) return new Service(); // returning a simple service rather than extending it

	// ...using of parent container
	$foo = $c['foo'] + 1;
	// ...using of parent service
	$p->setFoo($foo);
	return $p; // returns the extended service
})->share();
...
$extendedProvider = $container['my_service']->extend($container['my_extension']);
```
This last way of extending is useful when you want to use an extension as a standalone service too.

## Query Parser
D3I uses a special accessing style, let we call as _D3I queries_. You can use it by both property and array access
but we suggest to use it only by array access.

By D3I queries you can access to sub-items by using a single-line query as a container key, without the need of
accessing it by level to level. Useful when you want to refer to services or settings in a setting and want to access
to the referred item quickly.

For example, let we have a configuration setting, creating a service based on the setting, and accessing a setting by
using a D3I query:
```php
$container = Container::Create([
	'my_service'	=> [
		'settings'	=> [
			'welcome_str' => 'Hello %s!'
		]
	]
]);

class MyService {
	protected $settings;
	function __construct($settings) {
		$this->settings = $settings;
	}
	function getWelcome($to = 'World') {
		// getting the welcome_str property by querying service
		return sprintf($container['@my_service.settings.welcome_str'], $to);
	}
}

$container->my_service = Provider::Create(function(Container $c) {
	// getting original settings by accessing the mutated original value of this provider
	return new MyService($c['!my_service.settings']);
})
	->mutate($container['my_service']) // mutate the original value before of overlapping it
	->share();

echo $container->my_service->getWelcome();
// -> "Hello World!"
```

You can also use "#" (hash mark) for annotate an integer key of an array.

## Contribution and Legal info
Please, feel free to contribute!

Choosed licence for this project is <a href="http://www.wtfpl.net/"><img
       src="http://www.wtfpl.net/wp-content/uploads/2012/12/wtfpl-badge-4.png"
       width="80" height="15" alt="WTFPL" /></a>, therefore see it to know the conditions. Thank you!