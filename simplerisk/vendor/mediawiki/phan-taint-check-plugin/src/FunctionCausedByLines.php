<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

/**
 * This class represents caused-by lines for a function-like:
 * - Lines in genericLines are for taintedness that is added inside the function, regardless of parameters; these
 *   have a Taintedness object associated, while the associated links are always empty.
 * - Lines in (variadic)paramSinkLines are those inside a function that EXEC the arguments. These also have a
 *   Taintedness object associated, and no links.
 * - Lines in (variadic)paramPreservedLines are responsible for putting a parameter inside the return value. These have
 *   a safe Taintedness associated, and usually non-empty links.
 */
class FunctionCausedByLines {
	/** @var CausedByLines */
	private $genericLines;
	/** @var CausedByLines[] */
	private $paramSinkLines = [];
	/** @var CausedByLines[] */
	private $paramPreservedLines = [];
	/** @var int|null Index of a variadic parameter, if any */
	private $variadicParamIndex;
	/** @var CausedByLines|null */
	private $variadicParamSinkLines;
	/** @var CausedByLines|null */
	private $variadicParamPreservedLines;

	public function __construct() {
		$this->genericLines = CausedByLines::emptySingleton();
	}

	public static function emptySingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self();
		}
		return $singleton;
	}

	/**
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function getGenericLines(): CausedByLines {
		return $this->genericLines;
	}

	/**
	 * @param string[] $lines
	 * @param Taintedness $taint
	 * @param ?MethodLinks $links
	 */
	public function withAddedGenericLines( array $lines, Taintedness $taint, ?MethodLinks $links = null ): self {
		$ret = clone $this;
		$ret->genericLines = $this->genericLines->withAddedLines( $lines, $taint, $links );
		return $ret;
	}

	public function withGenericLines( CausedByLines $lines ): self {
		$ret = clone $this;
		$ret->genericLines = $lines;
		return $ret;
	}

	/**
	 * @param int $param
	 * @param string[] $lines
	 * @param Taintedness $taint
	 */
	public function withAddedParamSinkLines( int $param, array $lines, Taintedness $taint ): self {
		assert( $param !== $this->variadicParamIndex );
		$ret = clone $this;
		if ( !isset( $ret->paramSinkLines[$param] ) ) {
			$ret->paramSinkLines[$param] = CausedByLines::emptySingleton();
		}
		$ret->paramSinkLines[$param] = $ret->paramSinkLines[$param]->withAddedLines( $lines, $taint );
		return $ret;
	}

	/**
	 * @param int $param
	 * @param string[] $lines
	 * @param Taintedness $taint
	 * @param ?MethodLinks $links
	 */
	public function withAddedParamPreservedLines(
		int $param,
		array $lines,
		Taintedness $taint,
		?MethodLinks $links = null
	): self {
		assert( $param !== $this->variadicParamIndex );
		$ret = clone $this;
		if ( !isset( $ret->paramPreservedLines[$param] ) ) {
			$ret->paramPreservedLines[$param] = CausedByLines::emptySingleton();
		}
		$ret->paramPreservedLines[$param] = $ret->paramPreservedLines[$param]
			->withAddedLines( $lines, $taint, $links );
		return $ret;
	}

	public function withParamSinkLines( int $param, CausedByLines $lines ): self {
		$ret = clone $this;
		$ret->paramSinkLines[$param] = $lines;
		return $ret;
	}

	public function withParamPreservedLines( int $param, CausedByLines $lines ): self {
		$ret = clone $this;
		$ret->paramPreservedLines[$param] = $lines;
		return $ret;
	}

	public function withVariadicParamSinkLines( int $param, CausedByLines $lines ): self {
		$ret = clone $this;
		$ret->variadicParamIndex = $param;
		$ret->variadicParamSinkLines = $lines;
		return $ret;
	}

	public function withVariadicParamPreservedLines( int $param, CausedByLines $lines ): self {
		$ret = clone $this;
		$ret->variadicParamIndex = $param;
		$ret->variadicParamPreservedLines = $lines;
		return $ret;
	}

	/**
	 * @param int $param
	 * @param string[] $lines
	 * @param Taintedness $taint
	 */
	public function withAddedVariadicParamSinkLines(
		int $param,
		array $lines,
		Taintedness $taint
	): self {
		assert( !isset( $this->paramSinkLines[$param] ) && !isset( $this->paramPreservedLines[$param] ) );
		$ret = clone $this;
		$ret->variadicParamIndex = $param;
		if ( !$ret->variadicParamSinkLines ) {
			$ret->variadicParamSinkLines = CausedByLines::emptySingleton();
		}
		$ret->variadicParamSinkLines = $ret->variadicParamSinkLines->withAddedLines( $lines, $taint );
		return $ret;
	}

	/**
	 * @param int $param
	 * @param string[] $lines
	 * @param Taintedness $taint
	 * @param ?MethodLinks $links
	 */
	public function withAddedVariadicParamPreservedLines(
		int $param,
		array $lines,
		Taintedness $taint,
		?MethodLinks $links = null
	): self {
		assert( !isset( $this->paramSinkLines[$param] ) && !isset( $this->paramPreservedLines[$param] ) );
		$ret = clone $this;
		$ret->variadicParamIndex = $param;
		if ( !$ret->variadicParamPreservedLines ) {
			$ret->variadicParamPreservedLines = CausedByLines::emptySingleton();
		}
		$ret->variadicParamPreservedLines = $ret->variadicParamPreservedLines
			->withAddedLines( $lines, $taint, $links );
		return $ret;
	}

	public function getParamSinkLines( int $param ): CausedByLines {
		if ( isset( $this->paramSinkLines[$param] ) ) {
			return $this->paramSinkLines[$param];
		}
		if (
			$this->variadicParamIndex !== null && $param >= $this->variadicParamIndex &&
			$this->variadicParamSinkLines
		) {
			return $this->variadicParamSinkLines;
		}
		return CausedByLines::emptySingleton();
	}

	public function getParamPreservedLines( int $param ): CausedByLines {
		if ( isset( $this->paramPreservedLines[$param] ) ) {
			return $this->paramPreservedLines[$param];
		}
		if (
			$this->variadicParamIndex !== null && $param >= $this->variadicParamIndex &&
			$this->variadicParamPreservedLines
		) {
			return $this->variadicParamPreservedLines;
		}
		return CausedByLines::emptySingleton();
	}

	/**
	 * @param FunctionCausedByLines $other
	 * @param FunctionTaintedness $funcTaint To check NO_OVERRIDE
	 */
	public function asMergedWith( self $other, FunctionTaintedness $funcTaint ): self {
		$ret = clone $this;
		$canOverrideOverall = $funcTaint->canOverrideOverall();
		if ( $canOverrideOverall ) {
			$ret->genericLines = $ret->genericLines->asMergedWith( $other->genericLines );
		}

		foreach ( $other->paramSinkLines as $param => $lines ) {
			if ( $funcTaint->canOverrideNonVariadicParam( $param ) ) {
				if ( isset( $ret->paramSinkLines[$param] ) ) {
					$ret->paramSinkLines[$param] = $ret->paramSinkLines[$param]->asMergedWith( $lines );
				} else {
					$ret->paramSinkLines[$param] = $lines;
				}
			}
		}
		if ( $canOverrideOverall ) {
			foreach ( $other->paramPreservedLines as $param => $lines ) {
				if ( $funcTaint->canOverrideNonVariadicParam( $param ) ) {
					if ( isset( $ret->paramPreservedLines[$param] ) ) {
						$ret->paramPreservedLines[$param] = $ret->paramPreservedLines[$param]->asMergedWith( $lines );
					} else {
						$ret->paramPreservedLines[$param] = $lines;
					}
				}
			}
		}
		$variadicIndex = $other->variadicParamIndex;
		if ( $variadicIndex !== null && $funcTaint->canOverrideVariadicParam() ) {
			$ret->variadicParamIndex = $variadicIndex;
			$sinkVariadic = $other->variadicParamSinkLines;
			if ( $sinkVariadic ) {
				if ( $ret->variadicParamSinkLines ) {
					$ret->variadicParamSinkLines = $ret->variadicParamSinkLines->asMergedWith( $sinkVariadic );
				} else {
					$ret->variadicParamSinkLines = $sinkVariadic;
				}
			}
			if ( $canOverrideOverall ) {
				$preserveVariadic = $other->variadicParamPreservedLines;
				if ( $preserveVariadic ) {
					if ( $ret->variadicParamPreservedLines ) {
						$ret->variadicParamPreservedLines = $this->variadicParamPreservedLines
							->asMergedWith( $preserveVariadic );
					} else {
						$ret->variadicParamPreservedLines = $preserveVariadic;
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function toString(): string {
		$str = "{\nGeneric: " . $this->genericLines->toDebugString() . ",\n";
		foreach ( $this->paramSinkLines as $par => $lines ) {
			$str .= "$par (sink): " . $lines->toDebugString() . ",\n";
		}
		foreach ( $this->paramPreservedLines as $par => $lines ) {
			$str .= "$par (preserved): " . $lines->toDebugString() . ",\n";
		}
		if ( $this->variadicParamSinkLines ) {
			$str .= "...{$this->variadicParamIndex} (sink): " . $this->variadicParamSinkLines->toDebugString() . "\n";
		}
		if ( $this->variadicParamPreservedLines ) {
			$str .= "...{$this->variadicParamIndex} (preserved): " .
				$this->variadicParamPreservedLines->toDebugString() . "\n";
		}
		return "$str}";
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __toString(): string {
		return $this->toString();
	}
}
