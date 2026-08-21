<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\Element\FunctionInterface;

/**
 * Value object used to store taintedness. This should always be used to manipulate taintedness values,
 * instead of directly using taint constants directly (except for comparisons etc.).
 *
 * Note that this class should be used as copy-on-write (like phan's UnionType), so in-place
 * manipulation should never be done on phan objects.
 */
class Taintedness {
	/** @var int Combination of the class constants */
	private $flags;

	/** @var self[] Taintedness for each possible array element */
	private $dimTaint = [];

	/** @var int Taintedness of the array keys */
	private $keysTaint = SecurityCheckPlugin::NO_TAINT;

	/**
	 * @var self|null Taintedness for array elements that we couldn't attribute to any key
	 */
	private $unknownDimsTaint;

	/**
	 * @param int $val One of the class constants
	 */
	public function __construct( int $val ) {
		$this->flags = $val;
	}

	// Common creation shortcuts

	public static function safeSingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self( SecurityCheckPlugin::NO_TAINT );
		}
		return $singleton;
	}

	public static function unknownSingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self( SecurityCheckPlugin::UNKNOWN_TAINT );
		}
		return $singleton;
	}

	public static function newTainted(): self {
		return new self( SecurityCheckPlugin::YES_TAINT );
	}

	/**
	 * @param self[] $dimTaint
	 * @param self|null $unknownDimsTaint Pass null for performance
	 * @param int $keysTaint
	 */
	public static function newFromShape(
		array $dimTaint,
		?self $unknownDimsTaint = null,
		int $keysTaint = SecurityCheckPlugin::NO_TAINT
	): self {
		if ( !$dimTaint && !$unknownDimsTaint && !$keysTaint ) {
			return self::safeSingleton();
		}

		$ret = new self( SecurityCheckPlugin::NO_TAINT );
		foreach ( $dimTaint as $key => $value ) {
			assert( $value instanceof self );
			$ret->dimTaint[$key] = $value;
		}
		$ret->unknownDimsTaint = $unknownDimsTaint;
		$ret->keysTaint = $keysTaint;
		return $ret;
	}

	/**
	 * Get a numeric representation of the taint stored in this object. This includes own taint,
	 * array keys and whatnot.
	 * @note This should almost NEVER be used outside of this class! Use accessors as much as possible!
	 */
	public function get(): int {
		$ret = $this->flags | $this->getAllKeysTaint() | $this->keysTaint;
		return $this->unknownDimsTaint ? ( $ret | $this->unknownDimsTaint->get() ) : $ret;
	}

	/**
	 * Get a flattened version of this object, with any taint from keys etc. collapsed into flags
	 */
	public function asCollapsed(): self {
		return new self( $this->get() );
	}

	/**
	 * Returns a copy of this object where the taintedness of every known key has been reassigned
	 * to unknown keys.
	 */
	public function asKnownKeysMadeUnknown(): self {
		$ret = new self( $this->flags );
		$ret->keysTaint = $this->keysTaint;
		$ret->unknownDimsTaint = $this->unknownDimsTaint;
		if ( $this->dimTaint ) {
			$ret->unknownDimsTaint ??= self::safeSingleton();
			foreach ( $this->dimTaint as $keyTaint ) {
				$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $keyTaint );
			}
		}
		return $ret;
	}

	/**
	 * Recursively extract the taintedness from each key.
	 */
	private function getAllKeysTaint(): int {
		$ret = SecurityCheckPlugin::NO_TAINT;
		foreach ( $this->dimTaint as $val ) {
			$ret |= $val->get();
		}
		return $ret;
	}

	// Value manipulation

	/**
	 * Returns a copy of this object, with the bits in $other added to flags.
	 * @see Taintedness::asMergedWith() if you want to preserve the whole shape
	 */
	public function with( int $other ): self {
		$ret = clone $this;
		// TODO: Should this clear UNKNOWN_TAINT if its present only in one of the args?
		$ret->flags |= $other;
		return $ret;
	}

	/**
	 * Returns a copy of this object, with the bits in $other removed recursively.
	 */
	public function without( int $other ): self {
		return $this->withOnly( ~$other );
	}

	/**
	 * Check whether this object has the given flag, recursively.
	 * @note If $taint has more than one flag, this will check for at least one, not all.
	 */
	public function has( int $taint ): bool {
		// Avoid using get() for performance
		if ( ( $this->flags & $taint ) !== SecurityCheckPlugin::NO_TAINT ) {
			return true;
		}
		if ( ( $this->keysTaint & $taint ) !== SecurityCheckPlugin::NO_TAINT ) {
			return true;
		}
		if ( $this->unknownDimsTaint && $this->unknownDimsTaint->has( $taint ) ) {
			return true;
		}
		foreach ( $this->dimTaint as $val ) {
			if ( $val->has( $taint ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns a copy of this object, with only the taint in $taint kept (recursively, preserving the shape)
	 */
	public function withOnly( int $other ): self {
		$ret = clone $this;

		$ret->flags &= $other;
		if ( $ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->withOnly( $other );
		}
		$ret->keysTaint &= $other;
		foreach ( $ret->dimTaint as $k => $val ) {
			$ret->dimTaint[$k] = $val->withOnly( $other );
		}

		return $ret;
	}

	/**
	 * Intersect the taintedness of a value against that of a sink, to later determine whether the
	 * expression is safe. In case of function calls, $sink is the param taint and $value is the arg taint.
	 *
	 * @note The order of the arguments is important! This method preserves the shape of $sink, not $value.
	 *
	 * @note The order of the arguments is important! This method preserves the shape of $sink, not $value.
	 */
	public static function intersectForSink( self $sink, self $value ): self {
		$intersect = new self( SecurityCheckPlugin::NO_TAINT );
		// If the sink has non-zero flags, intersect it with the whole other side. This particularly preserves
		// the shape of $sink, discarding anything from $value if the sink has a NO_TAINT in that position.
		if ( $sink->flags & SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ) {
			// Special case: NUMKEY is only for the outer array
			$rightFlags = $value->flags | $value->keysTaint;
			if ( $rightFlags & SecurityCheckPlugin::SQL_TAINT ) {
				// FIXME HACK: If keys are tainted, add numkey. This assumes that numkey is really only used for
				// Database methods, where keys are never escaped.
				$rightFlags |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			}
			$rightFlags |= ( $value->getAllKeysTaint() & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT );
			if ( $value->unknownDimsTaint ) {
				$rightFlags |= $value->unknownDimsTaint->get() & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			}
			$intersect->flags = $sink->flags & ( ( $rightFlags & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		} elseif ( $sink->flags ) {
			$intersect->flags = $sink->flags & ( ( $value->get() & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		}
		if ( $sink->unknownDimsTaint ) {
			$intersect->unknownDimsTaint = self::intersectForSink(
				$sink->unknownDimsTaint,
				$value->asValueFirstLevel()
			);
		}
		$valueKeysAsExec = ( ( $value->keysTaint | $value->flags ) & SecurityCheckPlugin::ALL_TAINT ) << 1;
		$intersect->keysTaint = $sink->keysTaint & $valueKeysAsExec;
		foreach ( $sink->dimTaint as $key => $dTaint ) {
			$intersect->dimTaint[$key] = self::intersectForSink(
				$dTaint,
				$value->getTaintednessForOffsetOrWhole( $key )
			);
		}
		return $intersect;
	}

	/**
	 * Returns a copy of $this without offset data from all known offsets of $other.
	 */
	public function withoutKnownKeysFrom( self $other ): self {
		$ret = clone $this;
		foreach ( $other->dimTaint as $key => $_ ) {
			unset( $ret->dimTaint[$key] );
		}
		if (
			( $ret->flags & SecurityCheckPlugin::SQL_NUMKEY_TAINT ) &&
			!$ret->has( SecurityCheckPlugin::SQL_TAINT )
		) {
			// Note that this adjustment is not guaranteed to happen immediately after the removal of the last
			// integer key. For instance, in [ 0 => unsafe, 'foo' => unsafe ], if only the element 0 is removed,
			// this branch will not run because 'foo' still contributes sql taint.
			$ret->flags &= ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
		}
		return $ret;
	}

	/**
	 * Merge this object with $other, recursively, creating a copy.
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;

		$ret->flags |= $other->flags;
		if ( $other->unknownDimsTaint && !$ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $other->unknownDimsTaint );
		}
		$ret->keysTaint |= $other->keysTaint;
		foreach ( $other->dimTaint as $key => $val ) {
			if ( !isset( $ret->dimTaint[$key] ) ) {
				$ret->dimTaint[$key] = $val;
			} else {
				$ret->dimTaint[$key] = $ret->dimTaint[$key]->asMergedWith( $val );
			}
		}

		return $ret;
	}

	// Offsets taintedness

	/**
	 * Returns a copy of $this, adding $value to the taintedness for $offset
	 *
	 * @param Node|mixed $offset Node or a scalar value, already resolved
	 * @param Taintedness $value
	 */
	public function withAddedOffsetTaintedness( mixed $offset, self $value ): self {
		$ret = clone $this;

		if ( is_scalar( $offset ) ) {
			$ret->dimTaint[$offset] = $value;
		} else {
			$ret->unknownDimsTaint ??= self::safeSingleton();
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $value );
		}

		return $ret;
	}

	/**
	 * Returns a copy of $this with the bits in $value added to the taintedness of the keys
	 */
	public function withAddedKeysTaintedness( int $value ): self {
		$ret = clone $this;
		$ret->keysTaint |= $value;
		return $ret;
	}

	public function asMergedForAssignment( self $other, int $depth ): self {
		if ( $depth === 0 ) {
			return $other;
		}
		$ret = clone $this;
		$ret->flags |= $other->flags;
		$ret->keysTaint |= $other->keysTaint;
		if ( !$ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $other->unknownDimsTaint );
		}
		foreach ( $other->dimTaint as $k => $v ) {
			$ret->dimTaint[$k] = isset( $ret->dimTaint[$k] )
				? $ret->dimTaint[$k]->asMergedForAssignment( $v, $depth - 1 )
				: $v;
		}
		return $ret;
	}

	/**
	 * Apply the effect of array addition and return a clone of $this
	 */
	public function asArrayPlusWith( self $other ): self {
		$ret = clone $this;

		$ret->flags |= $other->flags;
		if ( $other->unknownDimsTaint && !$ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $other->unknownDimsTaint );
		}
		$ret->keysTaint |= $other->keysTaint;
		// This is not recursive because array addition isn't
		$ret->dimTaint += $other->dimTaint;

		return $ret;
	}

	/**
	 * Get the taintedness for the given offset, if set. If $offset could not be resolved, this
	 * will return the whole object, with taint from unknown keys added. If the offset is not known,
	 * it will return a new Taintedness object without the original shape, and with taint from
	 * unknown keys added.
	 *
	 * @param Node|string|int|bool|float|null $offset
	 * @return self Always a copy
	 */
	public function getTaintednessForOffsetOrWhole( mixed $offset ): self {
		if ( !is_scalar( $offset ) ) {
			return $this->asValueFirstLevel();
		}
		if ( isset( $this->dimTaint[$offset] ) ) {
			if ( $this->unknownDimsTaint ) {
				$ret = $this->dimTaint[$offset]->asMergedWith( $this->unknownDimsTaint );
			} else {
				$ret = clone $this->dimTaint[$offset];
			}
		} elseif ( $this->unknownDimsTaint ) {
			$ret = clone $this->unknownDimsTaint;
		} else {
			return new self( $this->flags );
		}
		$ret->flags |= $this->flags;
		return $ret;
	}

	/**
	 * Create a new object with $this at the given $offset (if scalar) or as unknown object.
	 *
	 * @param Node|string|int|bool|float|null $offset
	 * @param ?int $offsetTaint If available, will be used as key taint
	 * @return self Always a copy
	 */
	public function asMaybeMovedAtOffset( mixed $offset, ?int $offsetTaint = null ): self {
		$ret = new self( SecurityCheckPlugin::NO_TAINT );
		if ( $offsetTaint !== null ) {
			$ret->keysTaint = $offsetTaint;
		}
		if ( $offset instanceof Node || $offset === null ) {
			$ret->unknownDimsTaint = $this;
		} else {
			$ret->dimTaint[$offset] = $this;
		}
		return $ret;
	}

	public function asMovedToKeys(): self {
		$ret = new self( SecurityCheckPlugin::NO_TAINT );
		$ret->keysTaint = $this->get();
		return $ret;
	}

	/**
	 * Get a representation of this taint at the first depth level. For instance, this can be used in a foreach
	 * assignment for the value. Own taint and unknown keys taint are preserved, and then we merge in recursively
	 * all the current keys.
	 */
	public function asValueFirstLevel(): self {
		$ret = new self( $this->flags & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT );
		if ( $this->unknownDimsTaint ) {
			$ret = $ret->asMergedWith( $this->unknownDimsTaint );
		}
		foreach ( $this->dimTaint as $val ) {
			$ret = $ret->asMergedWith( $val );
		}
		return $ret;
	}

	/**
	 * Creates a copy of this object without the given key
	 * @param string|int|bool|float|null $key
	 */
	public function withoutKey( mixed $key ): self {
		$ret = clone $this;
		unset( $ret->dimTaint[$key] );
		if (
			( $ret->flags & SecurityCheckPlugin::SQL_NUMKEY_TAINT ) &&
			!$ret->has( SecurityCheckPlugin::SQL_TAINT )
		) {
			// Note that this adjustment is not guaranteed to happen immediately after the removal of the last
			// integer key. For instance, in [ 0 => unsafe, 'foo' => unsafe ], if the element 0 is removed,
			// this branch will not run because 'foo' still contributes sql taint.
			$ret->flags &= ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
		}
		return $ret;
	}

	/**
	 * Creates a copy of this object without known offsets, and without keysTaint
	 */
	public function withoutKeys(): self {
		$ret = clone $this;
		$ret->keysTaint = SecurityCheckPlugin::NO_TAINT;
		if ( !$ret->dimTaint ) {
			return $ret;
		}
		$ret->unknownDimsTaint ??= self::safeSingleton();
		foreach ( $ret->dimTaint as $dim => $taint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $taint );
			unset( $ret->dimTaint[$dim] );
		}
		return $ret;
	}

	/**
	 * Get a representation of this taint to be used in a foreach assignment for the key
	 */
	public function asKeyForForeach(): self {
		return new self( ( $this->keysTaint | $this->flags ) & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT );
	}

	/**
	 * Returns a copy of $this, array_replace'd with $other.
	 */
	public function asArrayReplaceWith( self $other ): self {
		$ret = clone $this;

		$ret->flags |= $other->flags;
		$ret->dimTaint = array_replace( $ret->dimTaint, $other->dimTaint );
		if ( $other->unknownDimsTaint ) {
			if ( $ret->unknownDimsTaint ) {
				$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $other->unknownDimsTaint );
			} else {
				$ret->unknownDimsTaint = $other->unknownDimsTaint;
			}
		}

		return $ret;
	}

	/**
	 * Returns a copy of $this, array_merge'd with $other.
	 */
	public function asArrayMergeWith( self $other ): self {
		$ret = clone $this;
		// First merge the known elements
		$ret->dimTaint = array_merge( $ret->dimTaint, $other->dimTaint );
		// Then merge general flags, key flags, and any unknown keys
		$ret->flags |= $other->flags;
		$ret->keysTaint |= $other->keysTaint;
		$ret->unknownDimsTaint ??= self::safeSingleton();
		if ( $other->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $other->unknownDimsTaint );
		}
		// Finally, move taintedness from int keys to unknown
		foreach ( $ret->dimTaint as $k => $val ) {
			if ( is_int( $k ) ) {
				$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $val );
				unset( $ret->dimTaint[$k] );
			}
		}
		return $ret;
	}

	// Conversion/checks shortcuts

	/**
	 * Check whether this object has no taintedness.
	 */
	public function isSafe(): bool {
		// Don't use get() for performance
		if ( $this->flags !== SecurityCheckPlugin::NO_TAINT ) {
			return false;
		}
		if ( $this->keysTaint !== SecurityCheckPlugin::NO_TAINT ) {
			return false;
		}
		if ( $this->unknownDimsTaint && !$this->unknownDimsTaint->isSafe() ) {
			return false;
		}
		foreach ( $this->dimTaint as $val ) {
			if ( !$val->isSafe() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Convert exec to yes taint recursively. Special flags like UNKNOWN or INAPPLICABLE are discarded.
	 * Any YES flags are also discarded. Note that this returns a copy of the
	 * original object. The shape is preserved.
	 *
	 * @warning This function is nilpotent: f^2(x) = 0
	 */
	public function asExecToYesTaint(): self {
		$ret = new self( ( $this->flags & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1 );
		if ( $this->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $this->unknownDimsTaint->asExecToYesTaint();
		}
		$ret->keysTaint = ( $this->keysTaint & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1;
		foreach ( $this->dimTaint as $k => $val ) {
			$ret->dimTaint[$k] = $val->asExecToYesTaint();
		}
		return $ret;
	}

	/**
	 * Convert the yes taint bits to corresponding exec taint bits recursively.
	 * Any UNKNOWN_TAINT or INAPPLICABLE_TAINT is discarded. Note that this returns a copy of the
	 * original object. The shape is preserved.
	 *
	 * @warning This function is nilpotent: f^2(x) = 0
	 * @suppress PhanUnreferencedPublicMethod For consistency
	 */
	public function asYesToExecTaint(): self {
		$ret = new self( ( $this->flags & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		if ( $this->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $this->unknownDimsTaint->asYesToExecTaint();
		}
		$ret->keysTaint = ( $this->keysTaint & SecurityCheckPlugin::ALL_TAINT ) << 1;
		foreach ( $this->dimTaint as $k => $val ) {
			$ret->dimTaint[$k] = $val->asYesToExecTaint();
		}
		return $ret;
	}

	/**
	 * Utility method to convert some flags from EXEC to YES. Note that this is not used internally
	 * to avoid the unnecessary overhead of a function call in hot code.
	 */
	public static function flagsAsExecToYesTaint( int $flags ): int {
		return ( $flags & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1;
	}

	/**
	 * Utility method to convert some flags from YES to EXEC. Note that this is not used internally
	 * to avoid the unnecessary overhead of a function call in hot code.
	 */
	public static function flagsAsYesToExecTaint( int $flags ): int {
		return ( $flags & SecurityCheckPlugin::ALL_TAINT ) << 1;
	}

	/**
	 * @todo This method shouldn't be necessary (ideally)
	 */
	public function asPreservedTaintedness(): PreservedTaintedness {
		$ret = $this->flags
			? new PreservedTaintedness( ParamLinksOffsets::getInstance( $this->flags ) )
			: PreservedTaintedness::emptySingleton();

		foreach ( $this->dimTaint as $k => $val ) {
			$ret = $ret->withOffsetTaintedness( $k, $val->asPreservedTaintedness() );
		}
		if ( $this->unknownDimsTaint ) {
			$ret = $ret->withOffsetTaintedness( null, $this->unknownDimsTaint->asPreservedTaintedness() );
		}
		return $ret;
	}

	/**
	 * If this object represents the taintedness of a sink, and $links are the method links of the expression passed
	 * to the sink, return the exec taintedness that should be added to the given function parameter that the
	 * expression is linked to.
	 */
	public function appliedToLinksForBackprop( MethodLinks $links, FunctionInterface $func, int $param ): self {
		$ret = $links->asTaintednessForBackprop( $this->flags, $func, $param );
		foreach ( $this->dimTaint as $k => $dimTaint ) {
			$ret = $ret->asMergedWith( $dimTaint->appliedToLinksForBackprop( $links->getForDim( $k ), $func, $param ) );
		}
		if ( $this->unknownDimsTaint ) {
			$ret = $ret->asMergedWith(
				$this->unknownDimsTaint->appliedToLinksForBackprop(
					$links->getForDim( null ),
					$func,
					$param
				)
			);
		}
		if ( $this->keysTaint ) {
			$ret = $ret->asMergedWith(
				$links->asKeyForForeach()->asTaintednessForBackprop( $this->keysTaint, $func, $param )
			);
		}
		return $ret;
	}

	/**
	 * Return a copy of this object with SQL_NUMKEY taint added to every SQL element.
	 */
	public function withNumkeyAddedToSQL(): self {
		$ret = clone $this;
		if ( $ret->flags & SecurityCheckPlugin::SQL_TAINT ) {
			$ret->flags |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;
		}
		foreach ( $ret->dimTaint as $k => $dimTaint ) {
			$ret->dimTaint[$k] = $dimTaint->withNumkeyAddedToSQL();
		}
		$ret->unknownDimsTaint = $ret->unknownDimsTaint?->withNumkeyAddedToSQL();
		if ( $ret->keysTaint & SecurityCheckPlugin::SQL_TAINT ) {
			$ret->keysTaint |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;
		}
		return $ret;
	}

	/**
	 * Get a stringified representation of this taintedness, useful for debugging etc.
	 *
	 * @codeCoverageIgnore
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function toString( string $indent = '' ): string {
		$flags = SecurityCheckPlugin::taintToString( $this->flags );
		$keys = SecurityCheckPlugin::taintToString( $this->keysTaint );
		$ret = <<<EOT
{
$indent    Own taint: $flags
$indent    Keys: $keys
$indent    Elements: {
EOT;

		$kIndent = "$indent    ";
		$first = "\n";
		$last = '';
		foreach ( $this->dimTaint as $key => $taint ) {
			$ret .= "$first$kIndent    $key => " . $taint->toString( "$kIndent    " ) . "\n";
			$first = '';
			$last = $kIndent;
		}
		if ( $this->unknownDimsTaint ) {
			$ret .= "$first$kIndent    UNKNOWN => " . $this->unknownDimsTaint->toString( "$kIndent    " ) . "\n";
			$last = $kIndent;
		}
		$ret .= "$last}\n$indent}";
		return $ret;
	}

	/**
	 * Get a stringified representation of this taintedness suitable for the debug annotation
	 */
	public function toShortString(): string {
		$flags = SecurityCheckPlugin::taintToString( $this->flags );
		$ret = "{Own: $flags";
		if ( $this->keysTaint ) {
			$ret .= '; Keys: ' . SecurityCheckPlugin::taintToString( $this->keysTaint );
		}
		$keyParts = [];
		if ( $this->dimTaint ) {
			foreach ( $this->dimTaint as $key => $taint ) {
				$keyParts[] = "$key => " . $taint->toShortString();
			}
		}
		if ( $this->unknownDimsTaint ) {
			$keyParts[] = 'UNKNOWN => ' . $this->unknownDimsTaint->toShortString();
		}
		if ( $keyParts ) {
			$ret .= '; Elements: {' . implode( '; ', $keyParts ) . '}';
		}
		$ret .= '}';
		return $ret;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		return $this->toShortString();
	}
}
