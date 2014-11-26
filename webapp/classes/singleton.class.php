<?php
namespace lessrisk;

class singleton
{
	private static $instances;

	public function __construct() {
		$c = get_class($this);
		if(isset(self::$instances[$c])) {
			throw new Exception('You can not create more than one copy of a singleton.');
		} else {
			self::$instances[$c] = $this;
		}
	}

	public static function get_instance() {
		$c = get_called_class();
		if (!isset(self::$instances[$c])) {
			$args = func_get_args();
			$reflection_object = new \ReflectionClass($c);
			self::$instances[$c] = $reflection_object->newInstanceArgs($args);
		}
	return self::$instances[$c];
	}

	public function __clone() {
		throw new Exception('You can not clone a singleton.');
	}
}?>