<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use Phan\Language\Element\TypedElementInterface;
use SplObjectStorage;

/**
 * Convenience class for better type inference.
 *
 * @extends SplObjectStorage<TypedElementInterface,PreservedTaintedness>
 * @method PreservedTaintedness offsetGet( TypedElementInterface $object )
 * @method TypedElementInterface current()
 * XXX: the above `@method` annotations are needed for PHPStorm...
 */
class VarLinksMap extends SplObjectStorage {
	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		$children = [];
		foreach ( $this as $var ) {
			$children[] = $var->getName() . ': ' . $this[$var]->toShortString();
		}
		return '[' . implode( ',', $children ) . ']';
	}

	public function __clone() {
		foreach ( $this as $var ) {
			$this[$var] = clone $this[$var];
		}
	}
}
