<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;

/**
 * Links for a single method
 */
class SingleMethodLinks {
	/**
	 * @var ParamLinksOffsets[]
	 */
	private $params = [];

	public static function instanceWithParam( int $i, int $initialFlags ): self {
		static $singletons = [];
		$singletonKey = "$i-$initialFlags";
		if ( !isset( $singletons[$singletonKey] ) ) {
			$singletons[$singletonKey] = ( new self() )->withParam( $i, $initialFlags );
		}
		return $singletons[$singletonKey];
	}

	public function withParam( int $i, int $flags ): self {
		$ret = clone $this;
		$ret->params[$i] = ParamLinksOffsets::getInstance( $flags );
		return $ret;
	}

	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		foreach ( $other->params as $i => $otherPar ) {
			if ( isset( $ret->params[$i] ) ) {
				$ret->params[$i] = $ret->params[$i]->asMergedWith( $otherPar );
			} else {
				$ret->params[$i] = $otherPar;
			}
		}
		return $ret;
	}

	public function withoutShape( self $other ): self {
		$ret = clone $this;
		foreach ( $other->params as $i => $otherParam ) {
			if ( isset( $ret->params[$i] ) ) {
				$newParamData = $ret->params[$i]->withoutShape( $otherParam );
				if ( !$newParamData->isEmpty() ) {
					$ret->params[$i] = $newParamData;
				} else {
					unset( $ret->params[$i] );
				}
			}
		}
		return $ret;
	}

	/**
	 * @param Node|string|int|bool|float|null $offset
	 */
	public function withOffsetPushedToAll( mixed $offset ): self {
		$ret = clone $this;
		foreach ( $ret->params as $i => $_ ) {
			$ret->params[$i] = $ret->params[$i]->withOffsetPushed( $offset );
		}
		return $ret;
	}

	public function asAllParamsMovedToKeys(): self {
		$ret = new self;
		foreach ( $this->params as $i => $offsets ) {
			$ret->params[$i] = $offsets->asMovedToKeys();
		}
		return $ret;
	}

	/**
	 * @todo Try to avoid this method
	 * @return ParamLinksOffsets[]
	 */
	public function getParams(): array {
		return $this->params;
	}

	public function hasParam( int $x ): bool {
		return isset( $this->params[$x] );
	}

	/**
	 * @note This will fail hard if unset.
	 */
	public function getParamOffsets( int $x ): ParamLinksOffsets {
		return $this->params[$x];
	}

	/**
	 * @param int[] $params
	 */
	public function withOnlyParams( array $params ): self {
		$ret = clone $this;
		$ret->params = array_intersect_key( $this->params, array_fill_keys( $params, 1 ) );
		return $ret;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		$paramBits = [];
		foreach ( $this->params as $k => $paramOffsets ) {
			$paramBits[] = "$k: { " . $paramOffsets->__toString() . ' }';
		}
		return '[ ' . implode( ', ', $paramBits ) . ' ]';
	}
}
