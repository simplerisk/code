<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\Element\FunctionInterface;

/**
 * Value object that represents method links.
 * @todo We might store links inside Taintedness, but the memory usage might skyrocket
 */
class MethodLinks {
	/** @var LinksMap */
	private $links;

	/** @var self[] */
	private $dimLinks = [];

	/** @var self|null */
	private $unknownDimLinks;

	/** @var LinksMap|null */
	private $keysLinks;

	public function __construct( ?LinksMap $links = null ) {
		$this->links = $links ?? new LinksMap();
	}

	public static function emptySingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self( new LinksMap );
		}
		return $singleton;
	}

	/**
	 * @param self[] $dimLinks
	 * @param self|null $unknownDimLinks Pass null for performance
	 * @param LinksMap|null $keysLinks
	 */
	public static function newFromShape(
		array $dimLinks,
		?self $unknownDimLinks = null,
		?LinksMap $keysLinks = null
	): self {
		// Don't add empty link sets, for performance
		if ( $keysLinks && !count( $keysLinks ) ) {
			$keysLinks = null;
		}
		if ( !$dimLinks && !$unknownDimLinks && !$keysLinks ) {
			return self::emptySingleton();
		}

		$ret = new self();
		foreach ( $dimLinks as $key => $value ) {
			assert( $value instanceof self );
			$ret->dimLinks[$key] = $value;
		}
		$ret->unknownDimLinks = $unknownDimLinks;
		$ret->keysLinks = $keysLinks;
		return $ret;
	}

	/**
	 * @note This returns a clone
	 */
	public function getForDim( mixed $dim, bool $pushOffsets = true ): self {
		if ( $this === self::emptySingleton() ) {
			return $this;
		}
		if ( !is_scalar( $dim ) ) {
			$ret = ( new self( $this->links ) );
			if ( $pushOffsets ) {
				$ret = $ret->withAddedOffset( $dim );
			}
			if ( $this->unknownDimLinks ) {
				$ret = $ret->asMergedWith( $this->unknownDimLinks );
			}
			foreach ( $this->dimLinks as $links ) {
				$ret = $ret->asMergedWith( $links );
			}
			return $ret;
		}
		if ( isset( $this->dimLinks[$dim] ) ) {
			$ret = ( new self( $this->links ) );
			if ( $pushOffsets ) {
				$ret = $ret->withAddedOffset( $dim );
			}
			if ( $this->unknownDimLinks ) {
				$offsetLinks = $this->dimLinks[$dim]->asMergedWith( $this->unknownDimLinks );
			} else {
				$offsetLinks = $this->dimLinks[$dim];
			}
			return $ret->asMergedWith( $offsetLinks );
		}
		if ( $this->unknownDimLinks ) {
			$ret = clone $this->unknownDimLinks;
			$ret->links = $ret->links->asMergedWith( $this->links );
		} else {
			$ret = new self( $this->links );
		}

		return $pushOffsets ? $ret->withAddedOffset( $dim ) : $ret;
	}

	public function asValueFirstLevel(): self {
		if ( $this === self::emptySingleton() ) {
			return $this;
		}
		$ret = ( new self( $this->links ) )->withAddedOffset( null );
		if ( $this->unknownDimLinks ) {
			$ret = $ret->asMergedWith( $this->unknownDimLinks );
		}
		foreach ( $this->dimLinks as $links ) {
			$ret = $ret->asMergedWith( $links );
		}
		return $ret;
	}

	public function asKeyForForeach(): self {
		$emptySingleton = self::emptySingleton();
		if ( $this === $emptySingleton ) {
			return $this;
		}

		$hasBaseLinks = count( $this->links ) !== 0;
		$hasKeyLinks = $this->keysLinks && count( $this->keysLinks ) !== 0;

		if ( $hasBaseLinks ) {
			$newLinks = $this->links->asAllMovedToKeys();
			if ( $hasKeyLinks ) {
				$newLinks = $newLinks->asMergedWith( $this->keysLinks );
			}
		} elseif ( $hasKeyLinks ) {
			$newLinks = $this->keysLinks;
		} else {
			return $emptySingleton;
		}

		return new self( $newLinks );
	}

	public function withLinksAtDim( mixed $dim, self $links ): self {
		$ret = clone $this;
		if ( is_scalar( $dim ) ) {
			$ret->dimLinks[$dim] = $links;
		} elseif ( $ret->unknownDimLinks ) {
			$ret->unknownDimLinks = $ret->unknownDimLinks->asMergedWith( $links );
		} else {
			$ret->unknownDimLinks = $links;
		}
		return $ret;
	}

	public function withKeysLinks( LinksMap $links ): self {
		if ( !count( $links ) ) {
			return $this;
		}
		$ret = clone $this;
		if ( !$ret->keysLinks ) {
			$ret->keysLinks = $links;
		} else {
			$ret->keysLinks = $ret->keysLinks->asMergedWith( $links );
		}
		return $ret;
	}

	public function asCollapsed(): self {
		if ( $this === self::emptySingleton() ) {
			return $this;
		}
		$ret = new self( $this->links );
		foreach ( $this->dimLinks as $links ) {
			$ret = $ret->asMergedWith( $links->asCollapsed() );
		}
		if ( $this->unknownDimLinks ) {
			$ret = $ret->asMergedWith( $this->unknownDimLinks->asCollapsed() );
		}
		return $ret;
	}

	/**
	 * Merge this object with $other, recursively, creating a copy.
	 */
	public function asMergedWith( self $other ): self {
		$emptySingleton = self::emptySingleton();
		if ( $other === $emptySingleton ) {
			return $this;
		}
		if ( $this === $emptySingleton ) {
			return $other;
		}
		$ret = clone $this;

		$ret->links = $ret->links->asMergedWith( $other->links );
		foreach ( $other->dimLinks as $key => $links ) {
			if ( isset( $ret->dimLinks[$key] ) ) {
				$ret->dimLinks[$key] = $ret->dimLinks[$key]->asMergedWith( $links );
			} else {
				$ret->dimLinks[$key] = $links;
			}
		}
		if ( $other->unknownDimLinks && !$ret->unknownDimLinks ) {
			$ret->unknownDimLinks = $other->unknownDimLinks;
		} elseif ( $other->unknownDimLinks ) {
			$ret->unknownDimLinks = $ret->unknownDimLinks->asMergedWith( $other->unknownDimLinks );
		}
		if ( $other->keysLinks && !$ret->keysLinks ) {
			$ret->keysLinks = $other->keysLinks;
		} elseif ( $other->keysLinks ) {
			$ret->keysLinks = $ret->keysLinks->asMergedWith( $other->keysLinks );
		}

		return $ret;
	}

	public function withoutShape( self $other ): self {
		$ret = clone $this;

		$ret->links = $ret->links->withoutShape( $other->links );
		foreach ( $other->dimLinks as $key => $val ) {
			if ( isset( $ret->dimLinks[$key] ) ) {
				$ret->dimLinks[$key] = $ret->dimLinks[$key]->withoutShape( $val );
			}
		}
		if ( $ret->unknownDimLinks && $other->unknownDimLinks ) {
			$ret->unknownDimLinks = $ret->unknownDimLinks->withoutShape( $other->unknownDimLinks );
		}
		if ( $ret->keysLinks && $other->keysLinks ) {
			$ret->keysLinks = $ret->keysLinks->withoutShape( $other->keysLinks );
		}
		return $ret;
	}

	/**
	 * @param Node|mixed $offset
	 */
	public function withAddedOffset( mixed $offset ): self {
		$ret = clone $this;
		$ret->links = clone $ret->links;
		foreach ( $ret->links as $func ) {
			$ret->links[$func] = $ret->links[$func]->withOffsetPushedToAll( $offset );
		}
		return $ret;
	}

	/**
	 * Create a new object with $this at the given $offset (if scalar) or as unknown object.
	 *
	 * @param Node|string|int|bool|float|null $offset
	 * @param LinksMap|null $keyLinks
	 * @return self Always a copy
	 */
	public function asMaybeMovedAtOffset( mixed $offset, ?LinksMap $keyLinks = null ): self {
		$ret = new self;
		if ( $offset instanceof Node || $offset === null ) {
			$ret->unknownDimLinks = $this;
		} else {
			$ret->dimLinks[$offset] = $this;
		}
		$ret->keysLinks = $keyLinks;
		return $ret;
	}

	public function asMovedToKeys(): self {
		$ret = new self;
		$ret->keysLinks = $this->getLinksCollapsing();
		return $ret;
	}

	public function asMergedForAssignment( self $other, int $depth ): self {
		if ( $depth === 0 ) {
			return $other;
		}
		$ret = clone $this;
		$ret->links = $ret->links->asMergedWith( $other->links );
		if ( !$ret->keysLinks ) {
			$ret->keysLinks = $other->keysLinks;
		} elseif ( $other->keysLinks ) {
			$ret->keysLinks = $ret->keysLinks->asMergedWith( $other->keysLinks );
		}
		if ( !$ret->unknownDimLinks ) {
			$ret->unknownDimLinks = $other->unknownDimLinks;
		} elseif ( $other->unknownDimLinks ) {
			$ret->unknownDimLinks = $ret->unknownDimLinks->asMergedWith( $other->unknownDimLinks );
		}
		foreach ( $other->dimLinks as $k => $v ) {
			$ret->dimLinks[$k] = isset( $ret->dimLinks[$k] )
				? $ret->dimLinks[$k]->asMergedForAssignment( $v, $depth - 1 )
				: $v;
		}
		$ret->normalize();
		return $ret;
	}

	/**
	 * Remove offset links which are already present in the "main" links. This is done for performance
	 * (see test backpropoffsets-blowup).
	 *
	 * @todo Improve (e.g. recurse)
	 * @todo Might happen sometime earlier
	 */
	private function normalize(): void {
		if ( !count( $this->links ) ) {
			return;
		}
		foreach ( $this->dimLinks as $k => $links ) {
			$alreadyCloned = false;
			foreach ( $links->links as $func ) {
				if ( $this->links->offsetExists( $func ) ) {
					$dimParams = array_keys( $links->links[$func]->getParams() );
					$thisParams = array_keys( $this->links[$func]->getParams() );
					$keepParams = array_diff( $dimParams, $thisParams );
					if ( !$alreadyCloned ) {
						$this->dimLinks[$k] = clone $links;
						$this->dimLinks[$k]->links = clone $links->links;
						$alreadyCloned = true;
					}
					if ( !$keepParams ) {
						unset( $this->dimLinks[$k]->links[$func] );
					} else {
						$this->dimLinks[$k]->links[$func] = $this->dimLinks[$k]->links[$func]
							->withOnlyParams( $keepParams );
					}
				}
			}
			if ( $this->dimLinks[$k]->isEmpty() ) {
				unset( $this->dimLinks[$k] );
			}
		}
		if ( $this->unknownDimLinks ) {
			$alreadyCloned = false;
			foreach ( $this->unknownDimLinks->links as $func ) {
				if ( $this->links->offsetExists( $func ) ) {
					$dimParams = array_keys( $this->unknownDimLinks->links[$func]->getParams() );
					$thisParams = array_keys( $this->links[$func]->getParams() );
					$keepParams = array_diff( $dimParams, $thisParams );
					if ( !$alreadyCloned ) {
						$this->unknownDimLinks = clone $this->unknownDimLinks;
						$this->unknownDimLinks->links = clone $this->unknownDimLinks->links;
						$alreadyCloned = true;
					}
					if ( !$keepParams ) {
						unset( $this->unknownDimLinks->links[$func] );
					} else {
						$this->unknownDimLinks->links[$func] = $this->unknownDimLinks->links[$func]
							->withOnlyParams( $keepParams );
					}
				}
			}
			if ( $this->unknownDimLinks->isEmpty() ) {
				$this->unknownDimLinks = null;
			}
		}
	}

	/**
	 * Returns all the links stored in this object as a single LinkSet object, destroying the shape. This should only
	 * be used when the shape is not relevant.
	 */
	public function getLinksCollapsing(): LinksMap {
		$ret = clone $this->links;
		foreach ( $this->dimLinks as $link ) {
			$ret->mergeWith( $link->getLinksCollapsing() );
		}
		if ( $this->unknownDimLinks ) {
			$ret->mergeWith( $this->unknownDimLinks->getLinksCollapsing() );
		}
		if ( $this->keysLinks ) {
			$ret->mergeWith( $this->keysLinks );
		}
		return $ret;
	}

	/**
	 * @return array[]
	 * @phan-return array<array{0:FunctionInterface,1:int}>
	 */
	public function getMethodAndParamTuples(): array {
		$ret = [];
		foreach ( $this->links as $func ) {
			$info = $this->links[$func];
			foreach ( $info->getParams() as $i => $_ ) {
				$ret[] = [ $func, $i ];
			}
		}
		foreach ( $this->dimLinks as $link ) {
			array_push( $ret, ...$link->getMethodAndParamTuples() );
		}
		if ( $this->unknownDimLinks ) {
			array_push( $ret, ...$this->unknownDimLinks->getMethodAndParamTuples() );
		}
		foreach ( $this->keysLinks ?? [] as $func ) {
			$info = $this->keysLinks[$func];
			foreach ( $info->getParams() as $i => $_ ) {
				$ret[] = [ $func, $i ];
			}
		}
		return array_unique( $ret, SORT_REGULAR );
	}

	public function isEmpty(): bool {
		if ( count( $this->links ) ) {
			return false;
		}
		foreach ( $this->dimLinks as $links ) {
			if ( !$links->isEmpty() ) {
				return false;
			}
		}
		if ( $this->unknownDimLinks && !$this->unknownDimLinks->isEmpty() ) {
			return false;
		}
		if ( $this->keysLinks && count( $this->keysLinks ) ) {
			return false;
		}
		return true;
	}

	public function hasDataForFuncAndParam( FunctionInterface $func, int $i ): bool {
		if ( $this->links->offsetExists( $func ) && $this->links[$func]->hasParam( $i ) ) {
			return true;
		}
		foreach ( $this->dimLinks as $dimLinks ) {
			if ( $dimLinks->hasDataForFuncAndParam( $func, $i ) ) {
				return true;
			}
		}
		if ( $this->unknownDimLinks && $this->unknownDimLinks->hasDataForFuncAndParam( $func, $i ) ) {
			return true;
		}
		if ( $this->keysLinks && $this->keysLinks->offsetExists( $func ) && $this->keysLinks[$func]->hasParam( $i ) ) {
			return true;
		}
		return false;
	}

	public function withFuncAndParam(
		FunctionInterface $func,
		int $i,
		bool $isVariadic,
		int $initialFlags = SecurityCheckPlugin::ALL_TAINT
	): self {
		$ret = clone $this;

		if ( $isVariadic ) {
			$baseUnkLinks = $ret->unknownDimLinks ?? self::emptySingleton();
			$ret->unknownDimLinks = $baseUnkLinks->withFuncAndParam( $func, $i, false, $initialFlags );
			return $ret;
		}

		$ret->links = clone $ret->links;
		if ( $ret->links->offsetExists( $func ) ) {
			$ret->links[$func] = $ret->links[$func]->withParam( $i, $initialFlags );
		} else {
			$ret->links[$func] = SingleMethodLinks::instanceWithParam( $i, $initialFlags );
		}
		return $ret;
	}

	public function asPreservedTaintednessForFuncParam( FunctionInterface $func, int $param ): PreservedTaintedness {
		$ret = null;
		if ( $this->links->offsetExists( $func ) ) {
			$ownInfo = $this->links[$func];
			if ( $ownInfo->hasParam( $param ) ) {
				$ret = new PreservedTaintedness( $ownInfo->getParamOffsets( $param ) );
			}
		}
		if ( !$ret ) {
			$ret = PreservedTaintedness::emptySingleton();
		}
		foreach ( $this->dimLinks as $dim => $dimLinks ) {
			$ret = $ret->withOffsetTaintedness( $dim, $dimLinks->asPreservedTaintednessForFuncParam( $func, $param ) );
		}
		if ( $this->unknownDimLinks ) {
			$ret = $ret->withOffsetTaintedness(
				null,
				$this->unknownDimLinks->asPreservedTaintednessForFuncParam( $func, $param )
			);
		}
		if ( $this->keysLinks && $this->keysLinks->offsetExists( $func ) ) {
			$keyInfo = $this->keysLinks[$func];
			if ( $keyInfo->hasParam( $param ) ) {
				$ret = $ret->withKeysOffsets( $keyInfo->getParamOffsets( $param ) );
			}
		}
		return $ret;
	}

	/**
	 * If $taintFlags are the taintedness flags of a sink, and $this are the links passed to that sink, return a
	 * Taintedness object representing the backpropagated exec taintedness to be added to the given function parameter.
	 */
	public function asTaintednessForBackprop( int $taintFlags, FunctionInterface $func, int $param ): Taintedness {
		$ret = Taintedness::safeSingleton();
		if ( !$taintFlags ) {
			return $ret;
		}
		$allLinks = $this->getLinksCollapsing();
		if ( $allLinks->offsetExists( $func ) ) {
			$paramInfo = $allLinks[$func];
			if ( $paramInfo->hasParam( $param ) ) {
				$paramOffsets = $paramInfo->getParamOffsets( $param );
				$taintAsYes = new Taintedness( Taintedness::flagsAsExecToYesTaint( $taintFlags ) );
				$ret = $paramOffsets->appliedToTaintednessForBackprop( $taintAsYes )->asYesToExecTaint();
			}
		}

		return $ret;
	}

	public function asFilteredForFuncAndParam( FunctionInterface $func, int $param ): self {
		if ( $this === self::emptySingleton() ) {
			return $this;
		}
		$retLinks = new LinksMap();
		if ( $this->links->offsetExists( $func ) ) {
			$retLinks->offsetSet( $func, $this->links[$func] );
		}
		$ret = new self( $retLinks );

		$dimLinksShape = [];
		foreach ( $this->dimLinks as $dim => $dimLinks ) {
			$dimLinksShape[$dim] = $dimLinks->asFilteredForFuncAndParam( $func, $param );
		}
		$unknownDimLinks = $this->unknownDimLinks?->asFilteredForFuncAndParam( $func, $param );
		$keysLinks = new LinksMap();
		if ( $this->keysLinks && $this->keysLinks->offsetExists( $func ) ) {
			$keysLinks->offsetSet( $func, $this->keysLinks[$func] );
		}

		return $ret->asMergedWith( self::newFromShape( $dimLinksShape, $unknownDimLinks, $keysLinks ) );
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function toString( string $indent = '' ): string {
		if ( $this === self::emptySingleton() ) {
			return '(empty)';
		}
		$elementsIndent = $indent . "\t";
		$ret = "{\n$elementsIndent" . 'OWN: ' . $this->links->__toString() . ',';
		if ( $this->keysLinks ) {
			$ret .= "\n{$elementsIndent}KEYS: " . $this->keysLinks->__toString() . ',';
		}
		if ( $this->dimLinks || $this->unknownDimLinks ) {
			$ret .= "\n{$elementsIndent}CHILDREN: {";
			$childrenIndent = $elementsIndent . "\t";
			foreach ( $this->dimLinks as $key => $links ) {
				$ret .= "\n$childrenIndent$key: " . $links->toString( $childrenIndent ) . ',';
			}
			if ( $this->unknownDimLinks ) {
				$ret .= "\n$childrenIndent(UNKNOWN): " . $this->unknownDimLinks->toString( $childrenIndent );
			}
			$ret .= "\n$elementsIndent}";
		}
		return $ret . "\n$indent}";
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		return $this->toString();
	}
}
