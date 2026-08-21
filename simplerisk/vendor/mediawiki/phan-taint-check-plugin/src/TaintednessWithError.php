<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

/**
 * Object that encapsulates a Taintedness and a list of lines that caused it
 */
class TaintednessWithError {
	/** @var Taintedness */
	private $taintedness;
	/**
	 * @var CausedByLines
	 */
	private $error;

	/** @var MethodLinks */
	private $methodLinks;

	public function __construct( Taintedness $taintedness, CausedByLines $error, MethodLinks $methodLinks ) {
		$this->taintedness = $taintedness;
		$this->error = $error;
		$this->methodLinks = $methodLinks;
	}

	public static function emptySingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self(
				Taintedness::safeSingleton(),
				CausedByLines::emptySingleton(),
				MethodLinks::emptySingleton()
			);
		}
		return $singleton;
	}

	public static function unknownSingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self(
				Taintedness::unknownSingleton(),
				CausedByLines::emptySingleton(),
				MethodLinks::emptySingleton()
			);
		}
		return $singleton;
	}

	public function getTaintedness(): Taintedness {
		return $this->taintedness;
	}

	public function getError(): CausedByLines {
		return $this->error;
	}

	public function getMethodLinks(): MethodLinks {
		return $this->methodLinks;
	}

	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		$ret->taintedness = $ret->taintedness->asMergedWith( $other->taintedness );
		$ret->error = $ret->error->asMergedWith( $other->error );
		$ret->methodLinks = $ret->methodLinks->asMergedWith( $other->methodLinks );
		return $ret;
	}
}
