<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;

/**
 * Tree-like object of possible offset combinations for partial links to a parameter
 */
class ParamLinksOffsets {
	/** @var int What taint flags are preserved for this offset */
	private $ownFlags;

	/** @var self[] */
	private $dims = [];

	/** @var self|null */
	private $unknown;

	/** @var int */
	private $keysFlags = SecurityCheckPlugin::NO_TAINT;

	public function __construct( int $flags ) {
		$this->ownFlags = $flags;
	}

	public static function getInstance( int $flags ): self {
		static $singletons = [];
		if ( !isset( $singletons[$flags] ) ) {
			$singletons[$flags] = new self( $flags );
		}
		return $singletons[$flags];
	}

	public function asMergedWith( self $other ): self {
		if ( $this === $other ) {
			return $this;
		}

		$ret = clone $this;

		$ret->ownFlags |= $other->ownFlags;
		if ( $other->unknown && !$ret->unknown ) {
			$ret->unknown = $other->unknown;
		} elseif ( $other->unknown ) {
			$ret->unknown = $ret->unknown->asMergedWith( $other->unknown );
		}
		foreach ( $other->dims as $key => $val ) {
			if ( !isset( $ret->dims[$key] ) ) {
				$ret->dims[$key] = $val;
			} else {
				$ret->dims[$key] = $ret->dims[$key]->asMergedWith( $val );
			}
		}
		$ret->keysFlags |= $other->keysFlags;

		return $ret;
	}

	public function withoutShape( self $other ): self {
		$ret = clone $this;

		$ret->ownFlags &= ~$other->ownFlags;
		foreach ( $other->dims as $key => $val ) {
			if ( isset( $ret->dims[$key] ) ) {
				$ret->dims[$key] = $ret->dims[$key]->withoutShape( $val );
			}
		}
		if ( $other->unknown && $ret->unknown ) {
			$ret->unknown = $ret->unknown->withoutShape( $other->unknown );
		}
		$ret->keysFlags &= ~$other->keysFlags;
		return $ret;
	}

	/**
	 * Pushes $offsets to all leaves.
	 * @param Node|string|int|bool|float|null $offset
	 */
	public function withOffsetPushed( mixed $offset ): self {
		$ret = clone $this;

		foreach ( $ret->dims as $key => $val ) {
			$ret->dims[$key] = $val->withOffsetPushed( $offset );
		}
		if ( $ret->unknown ) {
			$ret->unknown = $ret->unknown->withOffsetPushed( $offset );
		}

		if ( $ret->ownFlags === SecurityCheckPlugin::NO_TAINT ) {
			return $ret;
		}

		$ownFlags = $ret->ownFlags;
		$ret->ownFlags = SecurityCheckPlugin::NO_TAINT;
		if ( is_scalar( $offset ) && !isset( $ret->dims[$offset] ) ) {
			$ret->dims[$offset] = self::getInstance( $ownFlags );
		} elseif ( !is_scalar( $offset ) && !$ret->unknown ) {
			$ret->unknown = self::getInstance( $ownFlags );
		}

		return $ret;
	}

	public function asMovedToKeys(): self {
		$ret = new self( SecurityCheckPlugin::NO_TAINT );

		foreach ( $this->dims as $k => $val ) {
			$ret->dims[$k] = $val->asMovedToKeys();
		}
		if ( $this->unknown ) {
			$ret->unknown = $this->unknown->asMovedToKeys();
		}

		$ret->keysFlags = $this->ownFlags;

		return $ret;
	}

	public function appliedToTaintedness( Taintedness $taintedness ): Taintedness {
		if ( $this->ownFlags ) {
			$ret = $taintedness->withOnly( $this->ownFlags );
		} else {
			$ret = Taintedness::safeSingleton();
		}
		foreach ( $this->dims as $k => $val ) {
			$ret = $ret->asMergedWith(
				$val->appliedToTaintedness( $taintedness->getTaintednessForOffsetOrWhole( $k ) )
			);
		}
		if ( $this->unknown ) {
			$ret = $ret->asMergedWith(
				$this->unknown->appliedToTaintedness( $taintedness->getTaintednessForOffsetOrWhole( null ) )
			);
		}
		$ret = $ret->with( $taintedness->asKeyForForeach()->withOnly( $this->keysFlags )->get() );
		return $ret;
	}

	public function appliedToTaintednessForBackprop( Taintedness $taintedness ): Taintedness {
		if ( $this->ownFlags ) {
			$ret = $taintedness->withOnly( $this->ownFlags );
		} else {
			$ret = Taintedness::safeSingleton();
		}

		$dimTaint = [];
		foreach ( $this->dims as $k => $val ) {
			$dimTaint[$k] = $val->appliedToTaintednessForBackprop( $taintedness->getTaintednessForOffsetOrWhole( $k ) );
		}
		$unknownDimsTaint = $this->unknown?->appliedToTaintednessForBackprop(
			$taintedness->getTaintednessForOffsetOrWhole( null )
		);
		$keysTaint = $taintedness->asKeyForForeach()->withOnly( $this->keysFlags )->get();

		return $ret->asMergedWith( Taintedness::newFromShape( $dimTaint, $unknownDimsTaint, $keysTaint ) );
	}

	public function isEmpty(): bool {
		if ( $this->ownFlags || $this->keysFlags ) {
			return false;
		}
		foreach ( $this->dims as $val ) {
			if ( !$val->isEmpty() ) {
				return false;
			}
		}

		if ( $this->unknown && !$this->unknown->isEmpty() ) {
			return false;
		}

		return true;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		$ret = '<(own): ' . SecurityCheckPlugin::taintToString( $this->ownFlags );

		if ( $this->keysFlags ) {
			$ret .= ', keys: ' . SecurityCheckPlugin::taintToString( $this->keysFlags );
		}

		if ( $this->dims || $this->unknown ) {
			$ret .= ', dims: [';
			$dimBits = [];
			foreach ( $this->dims as $k => $val ) {
				$dimBits[] = "$k => " . $val->__toString();
			}
			if ( $this->unknown ) {
				$dimBits[] = '(unknown): ' . $this->unknown->__toString();
			}
			$ret .= implode( ', ', $dimBits ) . ']';
		}
		return $ret . '>';
	}
}
