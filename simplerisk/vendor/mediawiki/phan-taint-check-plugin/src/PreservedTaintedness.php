<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;

/**
 * This class represents what taintedness is passed through (=preserved) by a function parameter
 */
class PreservedTaintedness {
	/** @var ParamLinksOffsets */
	private $ownOffsets;

	/** @var self[] Taintedness for each possible array element */
	private $dimTaint = [];

	/** @var ParamLinksOffsets|null */
	private $keysOffsets;

	/**
	 * @var self|null Taintedness for array elements that we couldn't attribute to any key
	 */
	private $unknownDimsTaint;

	public function __construct( ParamLinksOffsets $offsets ) {
		$this->ownOffsets = $offsets;
	}

	public static function emptySingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self( ParamLinksOffsets::getInstance( SecurityCheckPlugin::NO_TAINT ) );
		}
		return $singleton;
	}

	/**
	 * Set the taintedness for $offset to $value, in place
	 *
	 * @param Node|mixed $offset Node or a scalar value, already resolved
	 * @param self $value
	 */
	public function withOffsetTaintedness( mixed $offset, self $value ): self {
		$ret = clone $this;
		if ( is_scalar( $offset ) ) {
			$ret->dimTaint[$offset] = $value;
		} else {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint
				? $ret->unknownDimsTaint->asMergedWith( $value )
				: $value;
		}
		return $ret;
	}

	public function withKeysOffsets( ParamLinksOffsets $offsets ): self {
		$ret = clone $this;
		$ret->keysOffsets = $offsets;
		return $ret;
	}

	/**
	 * @param self $other
	 * @suppress PhanUnreferencedPublicMethod Kept for consistency
	 */
	public function asMergedWith( self $other ): self {
		$emptySingleton = self::emptySingleton();
		if ( $this === $emptySingleton ) {
			return $other;
		}
		if ( $other === $emptySingleton ) {
			return $this;
		}

		$ret = clone $this;
		$ret->ownOffsets = $ret->ownOffsets->asMergedWith( $other->ownOffsets );

		if ( $other->keysOffsets && !$ret->keysOffsets ) {
			$ret->keysOffsets = $other->keysOffsets;
		} elseif ( $other->keysOffsets ) {
			$ret->keysOffsets = $ret->keysOffsets->asMergedWith( $other->keysOffsets );
		}

		if ( $other->unknownDimsTaint && !$ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asMergedWith( $other->unknownDimsTaint );
		}
		foreach ( $other->dimTaint as $key => $val ) {
			if ( !array_key_exists( $key, $ret->dimTaint ) ) {
				$ret->dimTaint[$key] = $val;
			} else {
				$ret->dimTaint[$key] = $ret->dimTaint[$key]->asMergedWith( $val );
			}
		}

		return $ret;
	}

	public function asTaintednessForArgument( Taintedness $argTaint ): Taintedness {
		$safeTaint = Taintedness::safeSingleton();
		if ( $argTaint === $safeTaint || $this === self::emptySingleton() ) {
			return $safeTaint;
		}

		$ret = $this->ownOffsets->appliedToTaintedness( $argTaint );

		$dimTaint = [];
		foreach ( $this->dimTaint as $k => $val ) {
			$dimTaint[$k] = $val->asTaintednessForArgument( $argTaint );
		}
		$unknownDimsTaint = $this->unknownDimsTaint?->asTaintednessForArgument( $argTaint );
		$keysTaint = $this->keysOffsets
			? $this->keysOffsets->appliedToTaintedness( $argTaint )->get()
			: SecurityCheckPlugin::NO_TAINT;
		return $ret->asMergedWith( Taintedness::newFromShape( $dimTaint, $unknownDimsTaint, $keysTaint ) );
	}

	public function asTaintednessForBackpropError( Taintedness $sinkTaint ): Taintedness {
		$safeTaint = Taintedness::safeSingleton();
		if ( $sinkTaint === $safeTaint || $this === self::emptySingleton() ) {
			return $safeTaint;
		}

		$ret = $this->ownOffsets->appliedToTaintednessForBackprop( $sinkTaint );

		foreach ( $this->dimTaint as $val ) {
			$ret = $ret->asMergedWith( $val->asTaintednessForBackpropError( $sinkTaint ) );
		}
		if ( $this->unknownDimsTaint ) {
			$ret = $ret->asMergedWith(
				$this->unknownDimsTaint->asTaintednessForBackpropError( $sinkTaint )
			);
		}
		if ( $this->keysOffsets ) {
			$ret = $ret->asMergedWith(
				$this->keysOffsets->appliedToTaintednessForBackprop( $sinkTaint )
			);
		}
		return $ret;
	}

	public function asTaintednessForVarBackpropError( Taintedness $newTaint ): Taintedness {
		$safeTaint = Taintedness::safeSingleton();
		if ( $newTaint === $safeTaint || $this === self::emptySingleton() ) {
			return $safeTaint;
		}

		$ret = $this->ownOffsets->appliedToTaintednessForBackprop( $newTaint );

		$dimTaint = [];
		foreach ( $this->dimTaint as $key => $val ) {
			$dimTaint[$key] = $val->asTaintednessForVarBackpropError(
				$newTaint->getTaintednessForOffsetOrWhole( $key )
			);
		}
		$unknownDimsTaint = $this->unknownDimsTaint?->asTaintednessForVarBackpropError(
			$newTaint->getTaintednessForOffsetOrWhole( null )
		);
		$keysTaint = $this->keysOffsets
			? $this->keysOffsets->appliedToTaintednessForBackprop( $newTaint )->get()
			: SecurityCheckPlugin::NO_TAINT;
		return $ret->asMergedWith( Taintedness::newFromShape( $dimTaint, $unknownDimsTaint, $keysTaint ) );
	}

	public function isEmpty(): bool {
		if ( !$this->ownOffsets->isEmpty() || ( $this->keysOffsets && !$this->keysOffsets->isEmpty() ) ) {
			return false;
		}
		foreach ( $this->dimTaint as $val ) {
			if ( !$val->isEmpty() ) {
				return false;
			}
		}
		if ( $this->unknownDimsTaint && !$this->unknownDimsTaint->isEmpty() ) {
			return false;
		}
		return true;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function toShortString(): string {
		$ret = "{Own: " . $this->ownOffsets->__toString();
		if ( $this->keysOffsets ) {
			$ret .= '; Keys: ' . $this->keysOffsets->__toString();
		}
		$keyParts = [];
		if ( $this->dimTaint ) {
			foreach ( $this->dimTaint as $key => $taint ) {
				$keyParts[] = "$key => " . $taint->toShortString();
			}
		}
		if ( $this->unknownDimsTaint ) {
			$keyParts[] = 'Unknown => ' . $this->unknownDimsTaint->toShortString();
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
