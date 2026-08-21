<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

/**
 * Value object used to store taintedness of functions.
 * The $overall prop specifies what taint the function returns
 *   irrespective of its arguments.
 *
 *   For 'overall': only TAINT flags, what taint the output has
 *   For param keys: EXEC flags for what taints are unsafe here
 *                     TAINT flags for what taint gets passed through func.
 * As a special case, if the overall key has self::PRESERVE_TAINT
 * then any unspecified keys behave like they are self::YES_TAINT
 *
 * If func has no info for a parameter, the UnionType will be used to determine its taintedness.
 * The $overall taintedness must always be set.
 */
class FunctionTaintedness {
	/** @var Taintedness Overall taintedness of the func */
	private $overall;
	/** @var Taintedness[] EXEC taintedness for each param */
	private $paramSinkTaints = [];
	/** @var PreservedTaintedness[] Preserved taintedness for each param */
	private $paramPreserveTaints = [];
	/** @var int|null Index of a variadic parameter, if any */
	private $variadicParamIndex;
	/** @var Taintedness|null EXEC taintedness for a variadic parameter, if any */
	private $variadicParamSinkTaint;
	/** @var PreservedTaintedness|null Preserved taintedness for a variadic parameter, if any */
	private $variadicParamPreserveTaint;
	/** @var int Special overall flags */
	private $overallFlags = 0;
	/** @var int[] Special flags for parameters */
	private $paramFlags = [];
	/** @var int */
	private $variadicParamFlags = 0;

	public function __construct( Taintedness $overall, int $overallFlags = 0 ) {
		$this->overall = $overall;
		$this->overallFlags = $overallFlags;
	}

	public static function emptySingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self( Taintedness::safeSingleton() );
		}
		return $singleton;
	}

	public function withOverall( Taintedness $val, int $flags = 0 ): self {
		$ret = clone $this;
		$ret->overall = $val;
		$ret->overallFlags |= $flags;
		return $ret;
	}

	/**
	 * Get the overall taint (NOT a clone)
	 */
	public function getOverall(): Taintedness {
		return $this->overall;
	}

	public function canOverrideOverall(): bool {
		return ( $this->overallFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0;
	}

	/**
	 * Set the sink taint for a given param
	 */
	public function withParamSinkTaint( int $param, Taintedness $taint, int $flags = 0 ): self {
		$ret = clone $this;
		assert( $param !== $ret->variadicParamIndex );
		$ret->paramSinkTaints[$param] = $taint;
		$ret->paramFlags[$param] = ( $ret->paramFlags[$param] ?? 0 ) | $flags;
		return $ret;
	}

	/**
	 * Set the preserved taint for a given param
	 */
	public function withParamPreservedTaint( int $param, PreservedTaintedness $taint, int $flags = 0 ): self {
		$ret = clone $this;
		assert( $param !== $ret->variadicParamIndex );
		$ret->paramPreserveTaints[$param] = $taint;
		$ret->paramFlags[$param] = ( $ret->paramFlags[$param] ?? 0 ) | $flags;
		return $ret;
	}

	public function withVariadicParamSinkTaint( int $index, Taintedness $taint, int $flags = 0 ): self {
		$ret = clone $this;
		assert( !isset( $ret->paramPreserveTaints[$index] ) && !isset( $ret->paramSinkTaints[$index] ) );
		$ret->variadicParamIndex = $index;
		$ret->variadicParamSinkTaint = $taint;
		$ret->variadicParamFlags |= $flags;
		return $ret;
	}

	public function withVariadicParamPreservedTaint( int $index, PreservedTaintedness $taint, int $flags = 0 ): self {
		$ret = clone $this;
		assert( !isset( $ret->paramPreserveTaints[$index] ) && !isset( $ret->paramSinkTaints[$index] ) );
		$ret->variadicParamIndex = $index;
		$ret->variadicParamPreserveTaint = $taint;
		$ret->variadicParamFlags |= $flags;
		return $ret;
	}

	/**
	 * Get the sink taintedness of the given param (NOT a clone), and NO_TAINT if not set.
	 */
	public function getParamSinkTaint( int $param ): Taintedness {
		if ( isset( $this->paramSinkTaints[$param] ) ) {
			return $this->paramSinkTaints[$param];
		}
		if (
			$this->variadicParamIndex !== null && $param >= $this->variadicParamIndex &&
			$this->variadicParamSinkTaint
		) {
			return $this->variadicParamSinkTaint;
		}
		return Taintedness::safeSingleton();
	}

	/**
	 * Get the preserved taintedness of the given param (NOT a clone), and NO_TAINT if not set.
	 */
	public function getParamPreservedTaint( int $param ): PreservedTaintedness {
		if ( isset( $this->paramPreserveTaints[$param] ) ) {
			return $this->paramPreserveTaints[$param];
		}
		if (
			$this->variadicParamIndex !== null && $param >= $this->variadicParamIndex &&
			$this->variadicParamPreserveTaint
		) {
			return $this->variadicParamPreserveTaint;
		}
		return PreservedTaintedness::emptySingleton();
	}

	public function getParamFlags( int $param ): int {
		if ( isset( $this->paramFlags[$param] ) ) {
			return $this->paramFlags[$param];
		}
		if ( $this->variadicParamIndex !== null && $param >= $this->variadicParamIndex ) {
			return $this->variadicParamFlags;
		}
		return 0;
	}

	public function canOverrideNonVariadicParam( int $param ): bool {
		return ( ( $this->paramFlags[$param] ?? 0 ) & SecurityCheckPlugin::NO_OVERRIDE ) === 0;
	}

	public function getVariadicParamSinkTaint(): ?Taintedness {
		return $this->variadicParamSinkTaint;
	}

	/**
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function getVariadicParamPreservedTaint(): ?PreservedTaintedness {
		return $this->variadicParamPreserveTaint;
	}

	public function getVariadicParamIndex(): ?int {
		return $this->variadicParamIndex;
	}

	public function canOverrideVariadicParam(): bool {
		return ( $this->variadicParamFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0;
	}

	/**
	 * Get the *keys* of the params for which we have sink data, excluding variadic parameters
	 *
	 * @return int[]
	 */
	public function getSinkParamKeysNoVariadic(): array {
		return array_keys( $this->paramSinkTaints );
	}

	/**
	 * Get the *keys* of the params for which we have preserve data, excluding variadic parameters
	 *
	 * @return int[]
	 */
	public function getPreserveParamKeysNoVariadic(): array {
		return array_keys( $this->paramPreserveTaints );
	}

	/**
	 * Check whether we have preserve taint data for the given param
	 */
	public function hasParamPreserve( int $param ): bool {
		if ( isset( $this->paramPreserveTaints[$param] ) ) {
			return true;
		}
		if ( $this->variadicParamIndex !== null && $param >= $this->variadicParamIndex ) {
			return (bool)$this->variadicParamPreserveTaint;
		}
		return false;
	}

	/**
	 * Merge this object with another. This respects NO_OVERRIDE, since it doesn't touch any element
	 * where it's set. If the overall taint has UNKNOWN, it's cleared if we're setting it now.
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;

		foreach ( $other->paramSinkTaints as $index => $baseT ) {
			if ( ( ( $ret->paramFlags[$index] ?? 0 ) & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
				if ( isset( $ret->paramSinkTaints[$index] ) ) {
					$ret->paramSinkTaints[$index] = $ret->paramSinkTaints[$index]->asMergedWith( $baseT );
				} else {
					$ret->paramSinkTaints[$index] = $baseT;
				}
				$ret->paramFlags[$index] = ( $ret->paramFlags[$index] ?? 0 ) | ( $other->paramFlags[$index] ?? 0 );
			}
		}
		foreach ( $other->paramPreserveTaints as $index => $baseT ) {
			if ( ( ( $ret->paramFlags[$index] ?? 0 ) & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
				if ( isset( $ret->paramPreserveTaints[$index] ) ) {
					$ret->paramPreserveTaints[$index] = $ret->paramPreserveTaints[$index]->asMergedWith( $baseT );
				} else {
					$ret->paramPreserveTaints[$index] = $baseT;
				}
				$ret->paramFlags[$index] = ( $ret->paramFlags[$index] ?? 0 ) | ( $other->paramFlags[$index] ?? 0 );
			}
		}

		if ( ( $ret->variadicParamFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
			$variadicIndex = $other->variadicParamIndex;
			if ( $variadicIndex !== null ) {
				$ret->variadicParamIndex = $variadicIndex;
				$sinkVariadic = $other->variadicParamSinkTaint;
				if ( $sinkVariadic ) {
					if ( $ret->variadicParamSinkTaint ) {
						$ret->variadicParamSinkTaint = $ret->variadicParamSinkTaint->asMergedWith( $sinkVariadic );
					} else {
						$ret->variadicParamSinkTaint = $sinkVariadic;
					}
				}
				$presVariadic = $other->variadicParamPreserveTaint;
				if ( $presVariadic ) {
					if ( $ret->variadicParamPreserveTaint ) {
						$ret->variadicParamPreserveTaint = $ret->variadicParamPreserveTaint
							->asMergedWith( $presVariadic );
					} else {
						$ret->variadicParamPreserveTaint = $presVariadic;
					}
				}
				$ret->variadicParamFlags |= $other->variadicParamFlags;
			}
		}

		if ( ( $ret->overallFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
			// Remove UNKNOWN, which could be added e.g. when building func taint from the return type.
			$ret->overall = $ret->overall->without( SecurityCheckPlugin::UNKNOWN_TAINT )
				->asMergedWith( $other->overall );
			$ret->overallFlags |= $other->overallFlags;
		}

		return $ret;
	}

	public function withoutPreserved(): self {
		$ret = clone $this;
		$ret->paramPreserveTaints = [];
		$ret->variadicParamPreserveTaint = null;
		return $ret;
	}

	public function asOnlyPreserved(): self {
		$ret = new self( Taintedness::safeSingleton() );
		$ret->paramPreserveTaints = $this->paramPreserveTaints;
		$ret->variadicParamPreserveTaint = $this->variadicParamPreserveTaint;
		return $ret;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function toString(): string {
		$str = "[\n\toverall: " . $this->overall->toShortString() .
			self::flagsToString( $this->overallFlags ) . ",\n";
		$parKeys = array_unique( array_merge(
			array_keys( $this->paramSinkTaints ),
			array_keys( $this->paramPreserveTaints )
		) );
		foreach ( $parKeys as $par ) {
			$str .= "\t$par: {";
			if ( isset( $this->paramSinkTaints[$par] ) ) {
				$str .= "Sink: " . $this->paramSinkTaints[$par]->toShortString() . ', ';
			}
			if ( isset( $this->paramPreserveTaints[$par] ) ) {
				$str .= "Preserve: " . $this->paramPreserveTaints[$par]->toShortString();
			}
			$str .= '} ' . self::flagsToString( $this->paramFlags[$par] ?? 0 ) . ",\n";
		}
		if ( $this->variadicParamIndex !== null ) {
			$str .= "\t...{$this->variadicParamIndex}: {";
			if ( $this->variadicParamSinkTaint ) {
				 $str .= "Sink: " . $this->variadicParamSinkTaint->toShortString() . ', ';
			}
			if ( $this->variadicParamPreserveTaint ) {
				$str .= "Preserve: " . $this->variadicParamPreserveTaint->toShortString();
			}
			$str .= '} ' . self::flagsToString( $this->variadicParamFlags ) . "\n";
		}
		return "$str]";
	}

	/**
	 * @codeCoverageIgnore
	 */
	private static function flagsToString( int $flags ): string {
		$bits = [];
		if ( $flags & SecurityCheckPlugin::NO_OVERRIDE ) {
			$bits[] = 'no override';
		}
		if ( $flags & SecurityCheckPlugin::ARRAY_OK ) {
			$bits[] = 'array ok';
		}
		return $bits ? ' (' . implode( ', ', $bits ) . ')' : '';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		return $this->toString();
	}
}
