<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\Element\FunctionInterface;

/**
 * Value object used to store caused-by lines.
 */
class CausedByLines {
	private const MAX_LINES_PER_ISSUE = 80;
	// XXX Hack: Enforce a hard limit, or things may explode
	private const LINES_HARD_LIMIT = 100;

	/**
	 * Note: the links are nullable for performance.
	 * @var array<array<Taintedness|string|MethodLinks|null>>
	 * @phan-var list<array{0:Taintedness,1:string,2:?MethodLinks}>
	 */
	private $lines = [];

	public static function emptySingleton(): self {
		static $singleton;
		if ( !$singleton ) {
			$singleton = new self();
		}
		return $singleton;
	}

	/**
	 * Adds the given lines to this object. For assignment statements, use {@see self::withAddedAssignmentLines}
	 * @param string[] $lines
	 * @param Taintedness $taintedness
	 * @param MethodLinks|null $links
	 */
	public function withAddedLines( array $lines, Taintedness $taintedness, ?MethodLinks $links = null ): self {
		if ( $links && $links->isEmpty() ) {
			$links = null;
		}
		if ( !$links && $taintedness->isSafe() ) {
			return $this;
		}

		$ret = new self();

		if ( !$this->lines ) {
			foreach ( $lines as $line ) {
				$ret->lines[] = [ $taintedness, $line, $links ];
			}
			return $ret;
		}

		foreach ( $this->lines as $line ) {
			$ret->lines[] = [ $line[0], $line[1], $line[2] ];
		}

		foreach ( $lines as $line ) {
			if ( count( $ret->lines ) >= self::LINES_HARD_LIMIT ) {
				break;
			}
			$idx = array_search( $line, array_column( $ret->lines, 1 ), true );
			if ( $idx !== false ) {
				$ret->lines[ $idx ][0] = $ret->lines[ $idx ][0]->asMergedWith( $taintedness );
				if ( $links && !$ret->lines[$idx][2] ) {
					$ret->lines[$idx][2] = $links;
				} elseif ( $links && $links !== $ret->lines[$idx][2] ) {
					$ret->lines[$idx][2] = $ret->lines[$idx][2]->asMergedWith( $links );
				}
			} else {
				$ret->lines[] = [ $taintedness, $line, $links ];
			}
		}

		return $ret;
	}

	/**
	 * Merge caused-by lines from the RHS, and add the given additional lines to this object, as part of an assignment
	 * statement.
	 *
	 * @param CausedByLines $rightLines For the RHS expression
	 * @param string[] $lines
	 * @param Taintedness $taintedness
	 * @param MethodLinks|null $links
	 */
	public function asMergedForAssignment(
		self $rightLines,
		array $lines,
		Taintedness $taintedness,
		?MethodLinks $links = null
	): self {
		if ( $links && $links->isEmpty() ) {
			$links = null;
		}
		if ( !$links && $taintedness->isSafe() ) {
			return $this->asMergedWith( $rightLines );
		}

		if ( !$rightLines->lines ) {
			return $this->withAddedLines( $lines, $taintedness, $links );
		}

		$ret = $this->withAddedLines( $lines, $taintedness )
			->asMergedWith( $rightLines );

		if ( $links ) {
			$ret = clone $ret;
			foreach ( $lines as $line ) {
				if ( count( $ret->lines ) >= self::LINES_HARD_LIMIT ) {
					break;
				}

				$remainingLinks = $links;
				foreach ( $ret->lines as [ , $lineLine, $lineLinks ] ) {
					if ( $lineLine === $line ) {
						$remainingLinks = $lineLinks ? $remainingLinks->withoutShape( $lineLinks ) : $remainingLinks;
					}
				}
				if ( !$remainingLinks->isEmpty() ) {
					$ret->lines[] = [ Taintedness::safeSingleton(), $line, $remainingLinks ];
				}
			}
		}

		return $ret;
	}

	/**
	 * If this object represents the caused-by lines for a given function parameter, apply the effect of a method call
	 * where the argument for that parameter has the specified taintedness and links.
	 */
	public function asPreservedForParameter(
		Taintedness $argTaint,
		MethodLinks $argLinks,
		FunctionInterface $func,
		int $param
	): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		$argHasLinks = !$argLinks->isEmpty();
		foreach ( $this->lines as [ $eTaint, $eLine, $eLinks ] ) {
			if ( $eLinks ) {
				$preservedTaint = $eLinks->asPreservedTaintednessForFuncParam( $func, $param )
					->asTaintednessForArgument( $argTaint );
				$newTaint = $eTaint->asMergedWith( $preservedTaint );
				if ( $argHasLinks || !$newTaint->isSafe() ) {
					$ret->lines[] = [ $newTaint, $eLine, $argLinks ];
				}
			} else {
				$ret->lines[] = [ $eTaint, $eLine, $argLinks ];
			}
		}
		return $ret;
	}

	/**
	 * If this object represents the caused-by lines for a given function argument, apply the effect of a method call
	 * that preserves the given taintedness.
	 */
	public function asPreservedForArgument(
		PreservedTaintedness $preservedTaint
	): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $eTaint, $eLine, ] ) {
			$newTaint = $preservedTaint->asTaintednessForArgument( $eTaint );
			// TODO: Pass appropriate links through, see I1bd8ae302e91a2b6b951953bc321ea6ae89d5955
			$newLinks = null;
			if ( !$newTaint->isSafe() ) {
				$ret->lines[] = [ $newTaint, $eLine, $newLinks ];
			}
		}
		return $ret;
	}

	/**
	 * @param Taintedness $taintedness
	 * @todo Migrate callers to asPreservedForArgument and drop this.
	 */
	public function asIntersectedWithTaintedness( Taintedness $taintedness ): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		$curTaint = $taintedness->get();
		foreach ( $this->lines as [ $eTaint, $eLine, $links ] ) {
			$newTaint = $curTaint !== SecurityCheckPlugin::NO_TAINT
				? $eTaint->withOnly( $curTaint )
				: Taintedness::safeSingleton();
			$ret->lines[] = [ $newTaint, $eLine, $links ];
		}
		return $ret;
	}

	public function asFilteredForFuncAndParam( FunctionInterface $func, int $param ): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		$safeTaint = Taintedness::safeSingleton();
		foreach ( $this->lines as [ , $lineLine, $lineLinks ] ) {
			if ( $lineLinks && $lineLinks->hasDataForFuncAndParam( $func, $param ) ) {
				$ret->lines[] = [ $safeTaint, $lineLine, $lineLinks ];
			}
		}
		return $ret;
	}

	public function getLinesForGenericReturn(): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, ] ) {
			if ( !$lineTaint->isSafe() ) {
				// For generic lines, links don't matter
				$ret->lines[] = [ $lineTaint, $lineLine, null ];
			}
		}
		return $ret;
	}

	/**
	 * For every line in this object, check if the line has links for $func, and if so, add preserved taintedness from
	 * $taintedness to the line.
	 *
	 * @param Taintedness $taintedness
	 * @param FunctionInterface $func
	 * @param int $i Parameter index
	 * @param bool $isSink True when backpropagating method links for a sink (and $taintedness is the taintedness of the
	 * sink); false when backpropagating variable links (and $taintedness is the new taintedness of the variable).
	 */
	public function withTaintAddedToMethodArgLinks(
		Taintedness $taintedness,
		FunctionInterface $func,
		int $i,
		bool $isSink
	): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			if ( $lineLinks && $lineLinks->hasDataForFuncAndParam( $func, $i ) ) {
				$preservedTaint = $lineLinks->asPreservedTaintednessForFuncParam( $func, $i );
				$newTaint = $isSink
					? $preservedTaint->asTaintednessForBackpropError( $taintedness )
					: $preservedTaint->asTaintednessForVarBackpropError( $taintedness );
				$ret->lines[] = [ $lineTaint->asMergedWith( $newTaint ), $lineLine, $lineLinks ];
			} else {
				$ret->lines[] = [ $lineTaint, $lineLine, $lineLinks ];
			}
		}
		return $ret;
	}

	public function forSinkBackprop( MethodLinks $links, FunctionInterface $func, int $param ): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$newTaint = $lineTaint->asYesToExecTaint()->appliedToLinksForBackprop( $links, $func, $param );
			if ( !$newTaint->isSafe() ) {
				$ret->lines[] = [ $newTaint->asExecToYesTaint(), $lineLine, $lineLinks ];
			}
		}
		return $ret;
	}

	/**
	 * Returns a copy of $this with all taintedness and links moved at the given offset.
	 * @param Node|mixed $offset
	 */
	public function asAllMaybeMovedAtOffset( mixed $offset ): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$ret->lines[] = [
				$lineTaint->asMaybeMovedAtOffset( $offset ),
				$lineLine,
				$lineLinks ? $lineLinks->asMaybeMovedAtOffset( $offset ) : null
			];
		}
		return $ret;
	}

	/**
	 * Returns a copy of $this with all taintedness and links moved inside keys.
	 */
	public function asAllMovedToKeys(): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$ret->lines[] = [
				$lineTaint->asMovedToKeys(),
				$lineLine,
				$lineLinks ? $lineLinks->asMovedToKeys() : null
			];
		}
		return $ret;
	}

	/**
	 * @param Node|mixed $dim
	 * @param bool $pushOffsetsInLinks
	 */
	public function getForDim( mixed $dim, bool $pushOffsetsInLinks = true ): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$newTaint = $lineTaint->getTaintednessForOffsetOrWhole( $dim );
			$newLinks = $lineLinks ? $lineLinks->getForDim( $dim, $pushOffsetsInLinks ) : null;
			if ( $newLinks && $newLinks->isEmpty() ) {
				$newLinks = null;
			}
			if ( $newLinks || !$newTaint->isSafe() ) {
				$ret->lines[] = [
					$newTaint,
					$lineLine,
					$newLinks
				];
			}
		}
		return $ret;
	}

	public function asAllCollapsed(): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$ret->lines[] = [
				$lineTaint->asCollapsed(),
				$lineLine,
				$lineLinks ? $lineLinks->asCollapsed() : null
			];
		}
		return $ret;
	}

	public function asAllValueFirstLevel(): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$ret->lines[] = [
				$lineTaint->asValueFirstLevel(),
				$lineLine,
				$lineLinks ? $lineLinks->asValueFirstLevel() : null
			];
		}
		return $ret;
	}

	public function asAllKeyForForeach(): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$newTaint = $lineTaint->asKeyForForeach();
			$newLinks = $lineLinks ? $lineLinks->asKeyForForeach() : null;
			if ( $newLinks && $newLinks->isEmpty() ) {
				$newLinks = null;
			}
			if ( $newLinks || !$newTaint->isSafe() ) {
				$ret->lines[] = [ $newTaint, $lineLine, $newLinks ];
			}
		}
		return $ret;
	}

	public function withOnlyLinks(): self {
		if ( !$this->lines ) {
			return $this;
		}
		$ret = new self;
		$safeTaint = Taintedness::safeSingleton();
		foreach ( $this->lines as [ , $lineLine, $lineLinks ] ) {
			if ( $lineLinks && !$lineLinks->isEmpty() ) {
				$ret->lines[] = [ $safeTaint, $lineLine, $lineLinks ];
			}
		}
		return $ret;
	}

	/**
	 * @note this isn't a merge operation like array_merge. What this method does is:
	 * 1 - if $other is a subset of $this, leave $this as-is;
	 * 2 - update taintedness values in $this if the *lines* (not taint values) in $other
	 *   are a subset of the lines in $this;
	 * 3 - if an upper set of $this *lines* is also a lower set of $other *lines*, remove that upper
	 *   set from $this and merge the rest with $other;
	 * 4 - array_merge otherwise;
	 *
	 * Step 2 is very important, because otherwise, caused-by lines can grow exponentially if
	 * even a single taintedness value in $this changes.
	 *
	 * @param self $other
	 * @param int $dimDepth Only used for assignments; depth of the array index access on the LHS.
	 */
	public function asMergedWith( self $other, int $dimDepth = 0 ): self {
		$emptySingleton = self::emptySingleton();
		if ( $this === $emptySingleton ) {
			return $other;
		}
		if ( $other === $emptySingleton ) {
			return $this;
		}

		$ret = clone $this;

		if ( !$ret->lines ) {
			$ret->lines = $other->lines;
			return $ret;
		}
		if ( !$other->lines || self::getArraySubsetIdx( $ret->lines, $other->lines ) !== false ) {
			return $ret;
		}

		$baseLines = array_column( $ret->lines, 1 );
		$newLines = array_column( $other->lines, 1 );
		$subsIdx = self::getArraySubsetIdx( $baseLines, $newLines );

		if ( $subsIdx === false ) {
			// Try reversing the order to see if we get a better merge.
			// TODO This whole thing is horrible. We need a better way to merge caused-by lines programmatically
			$reverseSubsetIdx = self::getArraySubsetIdx( $newLines, $baseLines );
			if ( $reverseSubsetIdx !== false ) {
				[ $ret, $other ] = [ clone $other, $ret ];
				[ $baseLines, $newLines ] = [ $newLines, $baseLines ];
				$subsIdx = $reverseSubsetIdx;
			}
		}

		if ( $subsIdx !== false ) {
			foreach ( $other->lines as $i => $otherLine ) {
				/** @var Taintedness $curTaint */
				$curTaint = $ret->lines[ $i + $subsIdx ][0];
				$ret->lines[ $i + $subsIdx ][0] = $dimDepth
					? $curTaint->asMergedForAssignment( $otherLine[0], $dimDepth )
					: $curTaint->asMergedWith( $otherLine[0] );
				/** @var MethodLinks $curLinks */
				$curLinks = $ret->lines[ $i + $subsIdx ][2];
				$otherLinks = $otherLine[2];
				if ( $otherLinks && !$curLinks ) {
					$ret->lines[$i + $subsIdx][2] = $otherLinks;
				} elseif ( $otherLinks && $otherLinks !== $curLinks ) {
					$ret->lines[$i + $subsIdx][2] = $dimDepth
						? $curLinks->asMergedForAssignment( $otherLinks, $dimDepth )
						: $curLinks->asMergedWith( $otherLinks );
				}
			}
			return $ret;
		}

		$resultingLines = null;
		$baseLen = count( $ret->lines );
		$newLen = count( $other->lines );
		// NOTE: array_shift is O(n), and O(n^2) over all iterations, because it reindexes the whole array.
		// So reverse the arrays, that is O(n) twice, and use array_pop which is O(1) (O(n) for all iterations)
		$remaining = array_reverse( $baseLines );
		$newRev = array_reverse( $newLines );
		// Assuming the lines as posets with the "natural" order used by PHP (that is, not the keys):
		// since we're working with reversed arrays, remaining lines should be an upper set of the reversed
		// new lines; which is to say, a lower set of the non-reversed new lines.
		$expectedIndex = $newLen - $baseLen;
		do {
			if ( $expectedIndex >= 0 && self::getArraySubsetIdx( $newRev, $remaining ) === $expectedIndex ) {
				$startIdx = $baseLen - $newLen + $expectedIndex;
				for ( $j = $startIdx; $j < $baseLen; $j++ ) {
					/** @var Taintedness $curTaint */
					$curTaint = $ret->lines[$j][0];
					$otherTaint = $other->lines[$j - $startIdx][0];
					$ret->lines[$j][0] = $dimDepth
						? $curTaint->asMergedForAssignment( $otherTaint, $dimDepth )
						: $curTaint->asMergedWith( $otherTaint );
					$secondLinks = $other->lines[$j - $startIdx][2];
					/** @var MethodLinks $curLinks */
					$curLinks = $ret->lines[$j][2];
					if ( $secondLinks && !$curLinks ) {
						$ret->lines[$j][2] = $secondLinks;
					} elseif ( $secondLinks && $secondLinks !== $curLinks ) {
						$ret->lines[$j][2] = $dimDepth
							? $curLinks->asMergedForAssignment( $secondLinks, $dimDepth )
							: $curLinks->asMergedWith( $secondLinks );
					}
				}
				$resultingLines = array_merge( $ret->lines, array_slice( $other->lines, $newLen - $expectedIndex ) );
				break;
			}
			array_pop( $remaining );
			$expectedIndex++;
		} while ( $remaining );
		$resultingLines ??= array_merge( $ret->lines, $other->lines );

		$ret->lines = array_slice( $resultingLines, 0, self::LINES_HARD_LIMIT );

		return $ret;
	}

	/**
	 * Check whether $needle is subset of $haystack, regardless of the keys, and returns
	 * the starting index of the subset in the $haystack array. If the subset occurs multiple
	 * times, this will just find the first one.
	 *
	 * @param array[] $haystack
	 * @phan-param list<array{0:Taintedness,1:string,2:?MethodLinks}> $haystack
	 * @param array[] $needle
	 * @phan-param list<array{0:Taintedness,1:string,2:?MethodLinks}> $needle
	 * @return false|int False if not a subset, the starting index if it is.
	 * @note Use strict comparisons with the return value!
	 */
	private static function getArraySubsetIdx( array $haystack, array $needle ): bool|int {
		if ( !$needle || !$haystack ) {
			// For our needs, the empty array is not a subset of anything
			return false;
		}

		$needleLength = count( $needle );
		$haystackLength = count( $haystack );
		if ( $haystackLength < $needleLength ) {
			return false;
		}
		$curIdx = 0;
		foreach ( $haystack as $i => $el ) {
			if ( $el === $needle[ $curIdx ] ) {
				$curIdx++;
			} else {
				$curIdx = 0;
			}
			if ( $curIdx === $needleLength ) {
				return $i - ( $needleLength - 1 );
			}
		}
		return false;
	}

	/**
	 * Return a truncated, stringified representation of these lines to be used when reporting issues.
	 *
	 * @todo Perhaps this should include the first and last X lines, not the first 2X. However,
	 *   doing so would make phan emit a new issue for the same line whenever new caused-by
	 *   lines are added to the array.
	 *
	 * @param Taintedness $sinkTaint Must have EXEC flags only.
	 * @param Taintedness $exprTaint Must have normal flags only.
	 * @param bool $isSinkError Whether this object refers to a sink (and not the expr)
	 */
	public function toStringForIssue( Taintedness $sinkTaint, Taintedness $exprTaint, bool $isSinkError ): string {
		$filteredLines = $this->getRelevantLinesForTaintedness( $sinkTaint, $exprTaint, $isSinkError );
		if ( !$filteredLines ) {
			return '';
		}

		if ( count( $filteredLines ) <= self::MAX_LINES_PER_ISSUE ) {
			$linesPart = implode( '; ', $filteredLines );
		} else {
			$linesPart = implode( '; ', array_slice( $filteredLines, 0, self::MAX_LINES_PER_ISSUE ) ) . '; ...';
		}
		return ' (Caused by: ' . $linesPart . ')';
	}

	/**
	 * @param Taintedness $sinkTaint With EXEC flags only.
	 * @param Taintedness $exprTaint With normal flags only.
	 * @param bool $isSinkError
	 * @return string[]
	 */
	private function getRelevantLinesForTaintedness(
		Taintedness $sinkTaint,
		Taintedness $exprTaint,
		bool $isSinkError
	): array {
		$ret = [];
		foreach ( $this->lines as [ $lineTaint, $lineText ] ) {
			$intersection = $isSinkError
				? Taintedness::intersectForSink( $lineTaint->asYesToExecTaint(), $exprTaint )
				: Taintedness::intersectForSink( $sinkTaint, $lineTaint );
			if ( !$intersection->isSafe() ) {
				$ret[] = $lineText;
			}
		}
		return $ret;
	}

	public function isEmpty(): bool {
		return $this->lines === [];
	}

	/**
	 * @return string[]
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function toLinesArray(): array {
		return array_column( $this->lines, 1 );
	}

	/**
	 * @suppress PhanUnreferencedPublicMethod
	 * @codeCoverageIgnore
	 */
	public function toDebugString(): string {
		if ( $this === self::emptySingleton() ) {
			return '(empty)';
		}
		$r = [];
		foreach ( $this->lines as [ $t, $line, $links ] ) {
			$r[] = "\t[\n\t\tT: " . $t->toShortString() . "\n\t\tL: " . $line . "\n\t\tLinks: " .
				( $links ? $links->toString( "\t\t" ) : 'none' ) . "\n\t]";
		}
		return "[\n" . implode( ",\n", $r ) . "\n]";
	}
}
