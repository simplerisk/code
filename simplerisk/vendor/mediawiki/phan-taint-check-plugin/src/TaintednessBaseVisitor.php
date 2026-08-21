<?php declare( strict_types=1 );

/**
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * @license GPL-2.0-or-later
 */

namespace SecurityCheckPlugin;

use ast\Node;
use Closure;
use Exception;
use Generator;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalVariable;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\LiteralTypeInterface;
use Phan\Language\UnionType;
use RuntimeException;
use SplObjectStorage;

/**
 * Trait for the Tainedness visitor subclasses. Mostly contains
 * utility methods.
 *
 * @property-read Context $context
 * @property-read \Phan\CodeBase $code_base
 */
trait TaintednessBaseVisitor {
	use TaintednessAccessorsTrait;

	/** @var null|string|bool|resource filehandle to output debug messages */
	private $debugOutput;

	/** @var Context|null Override the file/line number to emit issues */
	protected $overrideContext;

	/**
	 * @var bool[] FQSENs of classes without __toString, map of [ (string)FQSEN => true ]
	 */
	public static $fqsensWithoutToStringCache = [];

	/**
	 * @var array<string,bool> FQSENs of functions currently being analyzed by us, map of [ (string)FQSEN => true ]
	 */
	private static $funcAnalysisStack = [];

	/**
	 * Merge taintedness of a function/method
	 */
	protected function addFuncTaint( FunctionInterface $func, FunctionTaintedness $taint ): void {
		$curTaint = self::getFuncTaint( $func );
		if ( $curTaint ) {
			$newTaint = $curTaint->asMergedWith( $taint );
		} else {
			$newTaint = $taint;
		}
		self::doSetFuncTaint( $func, $newTaint );
	}

	/**
	 * Ensure a function-like has its taintedness set and not unknown
	 */
	protected function ensureFuncTaintIsSet( FunctionInterface $func ): void {
		if ( !self::getFuncTaint( $func ) ) {
			self::doSetFuncTaint( $func, FunctionTaintedness::emptySingleton() );
		}
	}

	/**
	 * @param FunctionInterface $func
	 * @param Context|string|null $reason To override the caused-by line
	 * @param FunctionTaintedness $addedTaint
	 * @param FunctionTaintedness $allNewTaint
	 * @param MethodLinks|null $returnLinks NOTE: These are only used for preserved params, since for sink params
	 * we're already adding a Taintedness with the expected EXEC bits.
	 */
	private function maybeAddFuncError(
		FunctionInterface $func,
		Context|string|null $reason,
		FunctionTaintedness $addedTaint,
		FunctionTaintedness $allNewTaint,
		?MethodLinks $returnLinks = null
	): void {
		if ( !is_string( $reason ) ) {
			$newErrors = [ $this->dbgInfo( $reason ?? $this->context ) ];
		} else {
			$newErrors = [ $reason ];
		}
		if ( $this->overrideContext && !( $this->isHook ?? false ) ) {
			// @phan-suppress-previous-line PhanUndeclaredProperty
			$newErrors[] = $this->dbgInfo( $this->overrideContext );
		}

		$hasReturnLinks = $returnLinks && !$returnLinks->isEmpty();

		// Future TODO: we might consider using PreservedTaintedness from the funcs instead of MethodLinks, but using
		// links is more consistent with what we do for non-function causedby lines.

		$newErr = self::getFuncCausedByRaw( $func ) ?? FunctionCausedByLines::emptySingleton();

		foreach ( $addedTaint->getSinkParamKeysNoVariadic() as $key ) {
			if ( $reason || $allNewTaint->canOverrideNonVariadicParam( $key ) ) {
				$curTaint = $addedTaint->getParamSinkTaint( $key );
				if ( $curTaint->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) ) {
					$newErr = $newErr->withAddedParamSinkLines( $key, $newErrors, $curTaint->asExecToYesTaint() );
				}
			}
		}
		foreach ( $addedTaint->getPreserveParamKeysNoVariadic() as $key ) {
			if ( $hasReturnLinks && ( $reason || $allNewTaint->canOverrideNonVariadicParam( $key ) ) ) {
				$newErr = $newErr->withAddedParamPreservedLines(
					$key,
					$newErrors,
					Taintedness::safeSingleton(),
					$returnLinks->asFilteredForFuncAndParam( $func, $key )
				);
			}
		}
		$variadicIndex = $addedTaint->getVariadicParamIndex();
		if ( $variadicIndex !== null && ( $reason || $allNewTaint->canOverrideVariadicParam() ) ) {
			$sinkVariadic = $addedTaint->getVariadicParamSinkTaint();
			if ( $sinkVariadic && $sinkVariadic->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) ) {
				$newErr = $newErr->withAddedVariadicParamSinkLines(
					$variadicIndex,
					$newErrors,
					$sinkVariadic->asExecToYesTaint()
				);
			}
			if ( $hasReturnLinks ) {
				$newErr = $newErr->withAddedVariadicParamPreservedLines(
					$variadicIndex,
					$newErrors,
					Taintedness::safeSingleton(),
					$returnLinks->asFilteredForFuncAndParam( $func, $variadicIndex )
				);
			}
		}

		$curTaint = $addedTaint->getOverall();
		if ( ( $reason || $allNewTaint->canOverrideOverall() ) && $curTaint->has( SecurityCheckPlugin::ALL_TAINT ) ) {
			// Note, the generic error shouldn't have any link
			$newErr = $newErr->withAddedGenericLines( $newErrors, $curTaint );
		}

		self::setFuncCausedByRaw( $func, $newErr );
	}

	/**
	 * @param FunctionInterface $func
	 * @param FunctionCausedByLines $newError
	 * @param FunctionTaintedness $allFuncTaint Used to check NO_OVERRIDE
	 */
	protected function mergeFuncError(
		FunctionInterface $func,
		FunctionCausedByLines $newError,
		FunctionTaintedness $allFuncTaint
	): void {
		$curError = self::getFuncCausedByRaw( $func ) ?? FunctionCausedByLines::emptySingleton();
		self::setFuncCausedByRaw( $func, $curError->asMergedWith( $newError, $allFuncTaint ) );
	}

	/**
	 * Shortcut to add all possible caused-by lines for the current context to the given variable-like element.
	 *
	 * @param TypedElementInterface $elem Where to put it
	 * @param Taintedness $taintedness
	 * @param MethodLinks|null $links
	 * @param string|null $reason To override the caused by line
	 */
	protected function addTaintError(
		TypedElementInterface $elem,
		Taintedness $taintedness,
		?MethodLinks $links,
		?string $reason = null
	): void {
		assert( !$elem instanceof FunctionInterface, 'Should use addFuncTaintError' );
		$newErrors = $this->getCausedByLinesToAdd( $taintedness, $links, $reason );
		$curErr = self::getCausedByRaw( $elem ) ?? CausedByLines::emptySingleton();
		self::setCausedByRaw( $elem, $curErr->withAddedLines( $newErrors, $taintedness, $links ) );
	}

	/**
	 * @param Taintedness $taintedness
	 * @param MethodLinks|null $links
	 * @param string|null $reason To override the caused by line
	 * @return string[]
	 */
	private function getCausedByLinesToAdd(
		Taintedness $taintedness,
		?MethodLinks $links,
		?string $reason = null
	): array {
		if ( !$taintedness->has( SecurityCheckPlugin::ALL_TAINT ) && ( !$links || $links->isEmpty() ) ) {
			// Don't add book-keeping if no actual taint was added.
			return [];
		}

		$newErrors = $reason !== null ? [ $reason ] : [ $this->dbgInfo() ];
		if ( $this->overrideContext && !( $this->isHook ?? false ) ) {
			// @phan-suppress-previous-line PhanUndeclaredProperty
			$newErrors[] = $this->dbgInfo( $this->overrideContext );
		}
		return $newErrors;
	}

	/**
	 * Ensures that the given variable obj has some taintedness set, initializing to safe if it doesn't.
	 */
	protected function ensureTaintednessIsSet( TypedElementInterface $varObj ): void {
		if ( !self::getTaintednessRaw( $varObj ) ) {
			self::setTaintednessRaw( $varObj, Taintedness::safeSingleton() );
		}
		if ( $varObj instanceof GlobalVariable ) {
			$gVarObj = $varObj->getElement();
			if ( !self::getTaintednessRaw( $gVarObj ) ) {
				self::setTaintednessRaw( $gVarObj, Taintedness::safeSingleton() );
			}
		}
	}

	/**
	 * Change the taintedness of $variableObj.
	 */
	private function setTaintedness(
		TypedElementInterface $variableObj,
		Taintedness $taintedness,
		bool $override
	): void {
		assert( !$variableObj instanceof FunctionInterface, 'Must use setFuncTaint for functions' );

		if (
			$variableObj instanceof Property &&
			$variableObj->getClassFQSEN() === FullyQualifiedClassName::getStdClassFQSEN()
		) {
			// Phan conflates all stdClass props, see https://github.com/phan/phan/issues/3869
			// Avoid doing the same with taintedness, as that would cause weird issues (see
			// 'stdclassconflation' test).
			// TODO Is it possible to store prop taintedness in the Variable object?
			// that would be similar to a fine-grained handling of arrays.
			return;
		}

		if ( $override ) {
			$newTaint = $taintedness;
		} else {
			$curTaint = self::getTaintednessRaw( $variableObj );
			if ( !$curTaint ) {
				$newTaint = $taintedness;
			} else {
				// NOTE: Do NOT merge in place here, as that would change the taintedness for all variable
				// objects of which $variableObj is a clone!
				$newTaint = $curTaint->asMergedWith( $taintedness );
			}
		}
		self::setTaintednessRaw( $variableObj, $newTaint );
	}

	/**
	 * Given a func, if it has a defining func different from itself, return that defining func. Returns null otherwise.
	 */
	private function getDefiningFuncIfDifferent( FunctionInterface $func ): ?FunctionInterface {
		if ( $func instanceof Method && $func->hasDefiningFQSEN() ) {
			$definingFQSEN = $func->getDefiningFQSEN();
			if ( $definingFQSEN !== $func->getFQSEN() ) {
				return $this->code_base->getMethodByFQSEN( $definingFQSEN );
			}
		}
		return null;
	}

	/**
	 * Get a list of places to look for function taint info
	 *
	 * @todo How to handle multiple function definitions (phan "alternates")
	 * @return Generator<FunctionInterface>
	 */
	private function getPossibleFuncDefinitions( FunctionInterface $func ): Generator {
		yield $func;

		// If we don't have a defining func, stay with the same func.
		// definingFunc is used later on during fallback processing.
		$definingFunc = $this->getDefiningFuncIfDifferent( $func );
		if ( $definingFunc ) {
			yield $definingFunc;
		}
		if ( $func instanceof Method ) {
			try {
				$class = $func->getClass( $this->code_base );
			} catch ( CodeBaseException $e ) {
				$this->debug( __METHOD__, "Class not found for func $func: " . $this->getDebugInfo( $e ) );
				return;
			}

			// Iterate through the whole hierarchy to see if the method was defined in an interface or trait. A few
			// notes on this:
			// - getNonParentAncestorFQSENList (and similar methods in Class and Method) only go one level up, and
			//   would not give us e.g. the interfaces implemented by the parent class.
			// - asExpandedTypes would work, but it has a non-zero overhead, and most importantly, we would cause phan
			//   to emit issues like RedefinedClass in places where phan wouldn't normally emit them.
			// - It's unclear whether this code should also look for method definitions in classes (and not just
			//   interfaces/traits). And more generally, what would the expectations for *-taint annotations be.
			$curClass = $class;
			// Use a safeguard in case this goes out of control (e.g., broken code with circular inheritance).
			$depth = 0;
			do {
				$depth++;
				$nonParents = $curClass->getNonParentAncestorFQSENList();

				foreach ( $nonParents as $nonParentFQSEN ) {
					if ( $this->code_base->hasClassWithFQSEN( $nonParentFQSEN ) ) {
						$nonParent = $this->code_base->getClassByFQSEN( $nonParentFQSEN );
						// TODO Assuming this is a direct invocation, but it doesn't always make sense
						$directInvocation = true;
						if ( $nonParent->hasMethodWithName( $this->code_base, $func->getName(), $directInvocation ) ) {
							try {
								yield $nonParent->getMethodByName( $this->code_base, $func->getName() );
							} catch ( CodeBaseException $e ) {
								$this->debug(
									__METHOD__,
									"Can't find func definition for $func in $nonParent: " . $this->getDebugInfo( $e )
								);
							}
						}
					}
				}
				if (
					!$curClass->hasParentType() ||
					!$this->code_base->hasClassWithFQSEN( $curClass->getParentClassFQSEN() )
				) {
					break;
				}
				$curClass = $curClass->getParentClass( $this->code_base );
			} while ( $depth < 20 );
		}
	}

	/**
	 * This is also for methods and other function like things
	 * @note This is not guaranteed to return a clone
	 *
	 * @param FunctionInterface $func What function/method to look up
	 * @return FunctionTaintedness Always a clone
	 */
	protected function getTaintOfFunction( FunctionInterface $func ): FunctionTaintedness {
		$funcTaint = self::getFuncTaint( $func );
		if ( $funcTaint !== null ) {
			return $funcTaint;
		}

		$annotatedTaint = $this->getSetKnownTaintOfFunctionWithoutAnalysis( $func );
		if ( $annotatedTaint ) {
			return $annotatedTaint;
		}

		$fqsen = $func->getFQSEN();
		if (
			isset( self::$funcAnalysisStack[$fqsen->__toString()] ) ||
			( $this->context->isInFunctionLikeScope() && $fqsen === $this->context->getFunctionLikeFQSEN() )
		) {
			// Recursive function(s). Analyzing it again isn't useful. Provisionally mark the function as safe, the idea
			// being that anything dangerous will be added as it's found. Failing to do this would mark the function as
			// inconditionally preserving all taintedness, as we'd look at its return type only. `--analyze-twice` gives
			// more accurate results here, since it will analyze the function again once its taintedness (except that
			// coming from the recursive call) is known.
			$taint = FunctionTaintedness::emptySingleton();
			self::doSetFuncTaint( $func, $taint );
			return $taint;
		}

		$isPHPInternalFunc = $func->isPHPInternal();
		if ( !$isPHPInternalFunc ) {
			// PHP internal functions cannot be analyzed because they don't have a body.
			$funcToAnalyze = $this->getDefiningFuncIfDifferent( $func ) ?: $func;
			$this->analyzeFunc( $funcToAnalyze );
			$analyzedFuncTaint = self::getFuncTaint( $funcToAnalyze );
			if ( $analyzedFuncTaint !== null ) {
				return $analyzedFuncTaint;
			}
		}

		$taintFromReturnType = $this->getTaintByType( $func->getUnionType() );
		if ( !$isPHPInternalFunc ) {
			// If we haven't seen this function before, first of all check the return type. If it
			// returns a safe type (like int), it's safe.
			// TODO Should we remove UNKNOWN_TAINT here? Strictly speaking it *is* unknown, but that's treated as if
			// it were unsafe. This can be seen, for example, when the maximum analysis depth is reached.
			$taint = new FunctionTaintedness( $taintFromReturnType );
			self::doSetFuncTaint( $func, $taint );
			$this->maybeAddFuncError( $func, null, $taint, $taint );
		} else {
			// Assume that anything really dangerous we've already hardcoded. So just preserve taint.
			$overall = $taintFromReturnType->isSafe()
				? $taintFromReturnType
				: new Taintedness( SecurityCheckPlugin::PRESERVE_TAINT );
			$taint = new FunctionTaintedness( $overall );
			// We're not adding any error here, since it's presumably unnecessary for PHP internal stuff.
			self::doSetFuncTaint( $func, $taint );
		}
		return $taint;
	}

	/**
	 * Given a function, find out if it has any hardcoded/annotated taint, or whether it should inherit its taint
	 * from an alternate definition. If anything was found, set that taintedness in the func object and return it.
	 * In particular, this does NOT cause $func to be analyzed.
	 */
	private function getSetKnownTaintOfFunctionWithoutAnalysis( FunctionInterface $func ): ?FunctionTaintedness {
		$funcsToTry = $this->getPossibleFuncDefinitions( $func );
		foreach ( $funcsToTry as $trialFunc ) {
			/** @var FunctionInterface $trialFunc */
			if ( !$trialFunc->isPHPInternal() ) {
				// PHP internal functions can't have a docblock.
				$taintData = $this->getDocBlockTaintOfFunc( $trialFunc );
				if ( $taintData !== null ) {
					[ $taint, $methodLinks ] = $taintData;
					self::doSetFuncTaint( $func, $taint );
					// TODO Make this more granular if possible
					$errorDesc = 'annotations in ' . $trialFunc->getFQSEN()->__toString();
					$this->maybeAddFuncError( $func, $errorDesc, $taint, $taint, $methodLinks );
					return $taint;
				}
			}

			$trialFuncName = $trialFunc->getFQSEN();
			$taint = SecurityCheckPlugin::$pluginInstance->getBuiltinFuncTaint( $trialFuncName );
			if ( $taint !== null ) {
				self::doSetFuncTaint( $func, $taint );
				if ( !$func->isPHPInternal() ) {
					// Caused-by lines are presumably unnecessary for PHP internal stuff.
					$this->maybeAddFuncError( $func, "Builtin-$trialFuncName", $taint, $taint );
				}
				return $taint;
			}
		}

		$definingFunc = $this->getDefiningFuncIfDifferent( $func );
		if ( $definingFunc ) {
			$definingFuncTaint = self::getFuncTaint( $definingFunc );
			if ( $definingFuncTaint !== null ) {
				return $definingFuncTaint;
			}
		}

		return null;
	}

	/**
	 * Analyze a function. This is very similar to Analyzable::analyze, but avoids several checks
	 * used by phan for performance. Phan doesn't know about taintedness, so it may decide to skip
	 * a re-analysis which we need.
	 * @todo This is a bit hacky.
	 * @todo We should implement our own perf checks, e.g. if the method as already called with
	 * the same taintedness, taint links, etc. for all params.
	 * @see \Phan\Analysis\Analyzable::analyze()
	 */
	public function analyzeFunc( FunctionInterface $func ): void {
		$fqsenStr = $func->getFQSEN()->__toString();
		if ( isset( self::$funcAnalysisStack[$fqsenStr] ) ) {
			// Bail out immediately if this function is already being analyzed.
			return;
		}

		$node = $func->getNode();
		if ( !$node ) {
			return;
		}

		if ( $this->context->isInFunctionLikeScope() && $func->getFQSEN() === $this->context->getFunctionLikeFQSEN() ) {
			// Avoid pointless recursion
			return;
		}

		// @todo Tune the max depth. Raw benchmarking shows very little difference between e.g.
		// 5 and 10. However, while with higher values we can detect more issues and avoid more
		// false positives, it becomes harder to tell where an issue is coming from.
		// Thus, this value should be increased only when we'll have better error reporting.
		$maxDepth = 5;
		if ( count( self::$funcAnalysisStack ) > $maxDepth ) {
			// $this->debug( __METHOD__, 'WARNING: aborting analysis earlier due to max depth' );
			return;
		}
		if ( $node->kind === \ast\AST_CLOSURE && isset( $node->children['uses'] ) ) {
			return;
		}

		self::$funcAnalysisStack[$fqsenStr] = true;
		// Like Analyzable::analyze, clone the context to avoid overriding anything
		$context = clone $func->getContext();
		// @phan-suppress-next-line PhanUndeclaredMethod All implementations have it
		if ( $func->getRecursionDepth() !== 0 ) {
			// Add the arguments types to the internal scope of the function, see
			// https://github.com/phan/phan/issues/3848
			foreach ( $func->getParameterList() as $parameter ) {
				$context->addScopeVariable( $parameter->cloneAsNonVariadic() );
			}
		}
		try {
			( new BlockAnalysisVisitor( $this->code_base, $context ) )(
				$node
			);
		} finally {
			unset( self::$funcAnalysisStack[$fqsenStr] );
		}
	}

	/**
	 * Obtain taint information from a docblock comment.
	 *
	 * @param FunctionInterface $func The function to check
	 * @return array<FunctionTaintedness|MethodLinks>|null null for no info
	 * @phan-return array{0:FunctionTaintedness,1:MethodLinks}|null
	 */
	protected function getDocBlockTaintOfFunc( FunctionInterface $func ): ?array {
		// Note that we're not using the hashed docblock for caching, because the same docblock
		// may have different meanings in different contexts. E.g. @return self
		$fqsen = (string)$func->getFQSEN();
		if ( isset( SecurityCheckPlugin::$docblockCache[ $fqsen ] ) ) {
			return SecurityCheckPlugin::$docblockCache[ $fqsen ];
		}

		$docBlock = $func->getDocComment();
		if ( $docBlock === null ) {
			return null;
		}
		if ( !str_contains( $docBlock, '-taint' ) ) {
			// Lightweight check for methods that certainly aren't annotated
			return null;
		}
		$lines = explode( "\n", $docBlock );
		/** @param string[] $args */
		$invalidLineIssueEmitter = function ( string $msg, array $args ) use ( $func ): void {
			SecurityCheckPlugin::emitIssue(
				$this->code_base,
				// Emit issues at the line of the signature
				$func->getContext(),
				'SecurityCheckInvalidAnnotation',
				$msg,
				$args
			);
		};
		// Note, not forCaller, as that doesn't see variadic parameters
		$calleeParamList = $func->getParameterList();
		$validTaintEncountered = false;
		// Assume that if some of the taint is specified, then
		// the person would specify all the dangerous taints, so
		// don't set the unknown flag if not taint annotation on
		// @return.
		$funcTaint = FunctionTaintedness::emptySingleton();
		// TODO $fakeMethodLinks here is a bit hacky...
		$fakeMethodLinks = MethodLinks::emptySingleton();
		foreach ( $lines as $line ) {
			$m = [];
			$trimmedLine = ltrim( rtrim( $line ), "* \t/" );
			if ( str_starts_with( $trimmedLine, '@param-taint' ) ) {
				$matched = preg_match( SecurityCheckPlugin::PARAM_ANNOTATION_REGEX, $trimmedLine, $m );
				if ( !$matched ) {
					$invalidLineIssueEmitter( "Cannot parse taint line '{COMMENT}'", [ $trimmedLine ] );
					continue;
				}

				$paramNumber = null;
				$isVariadic = null;
				foreach ( $calleeParamList as $i => $param ) {
					if ( $m['paramname'] === $param->getName() ) {
						$paramNumber = $i;
						$isVariadic = $param->isVariadic();
						break;
					}
				}
				if ( $paramNumber === null || $isVariadic === null ) {
					$invalidLineIssueEmitter(
						'Annotated parameter ${PARAMETER} not found in the signature',
						[ $m['paramname'] ]
					);
					continue;
				}

				$annotatedAsVariadic = $m['variadic'] !== '';
				if ( $isVariadic !== $annotatedAsVariadic ) {
					$msg = $isVariadic
						? 'Variadic parameter ${PARAMETER} should be annotated as `...${PARAMETER}`'
						: 'Non-variadic parameter ${PARAMETER} should be annotated as `${PARAMETER}`';
					$invalidLineIssueEmitter( $msg, [ $m['paramname'], $m['paramname'] ] );
				}
				$taintData = SecurityCheckPlugin::parseTaintLine( $m['taint'] );
				if ( $taintData === null ) {
					$invalidLineIssueEmitter( "Invalid param taintedness '{COMMENT}'", [ $m['taint'] ] );
					continue;
				}
				/** @var Taintedness $taint */
				[ $taint, $flags ] = $taintData;
				$sinkTaint = $taint->withOnly( SecurityCheckPlugin::ALL_EXEC_TAINT );
				$allTaint = $taint->without( SecurityCheckPlugin::ALL_EXEC_TAINT );
				$preserveTaint = $allTaint->asPreservedTaintedness();
				if ( $isVariadic ) {
					$funcTaint = $funcTaint->withVariadicParamSinkTaint( $paramNumber, $sinkTaint )
						->withVariadicParamPreservedTaint( $paramNumber, $preserveTaint, $flags );
				} else {
					$funcTaint = $funcTaint->withParamSinkTaint( $paramNumber, $sinkTaint )
						->withParamPreservedTaint( $paramNumber, $preserveTaint, $flags );
				}
				$preservedFlags = $allTaint->get();
				if ( $preservedFlags !== SecurityCheckPlugin::NO_TAINT ) {
					$fakeMethodLinks = $fakeMethodLinks->withFuncAndParam(
						$func,
						$paramNumber,
						$isVariadic,
						$preservedFlags
					);
				}
				$validTaintEncountered = true;
				if ( ( $taint->get() & SecurityCheckPlugin::ESCAPES_HTML ) === SecurityCheckPlugin::ESCAPES_HTML ) {
					// Special case to auto-set anything that escapes html to detect double escaping.
					$funcTaint = $funcTaint->withOverall(
						$funcTaint->getOverall()->with( SecurityCheckPlugin::ESCAPED_TAINT )
					);
				}
			} elseif ( str_starts_with( $trimmedLine, '@return-taint' ) ) {
				$taintLine = substr( $trimmedLine, strlen( '@return-taint' ) + 1 );
				$taintData = SecurityCheckPlugin::parseTaintLine( $taintLine );
				if ( $taintData === null ) {
					$invalidLineIssueEmitter( "Invalid return taintedness '{COMMENT}'", [ $taintLine ] );
					continue;
				}
				/** @var Taintedness $taint */
				[ $taint, $flags ] = $taintData;
				if ( $taint->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) ) {
					$invalidLineIssueEmitter( "Return taintedness cannot be exec", [] );
					continue;
				}
				$funcTaint = $funcTaint->withOverall( $taint, $flags );
				$validTaintEncountered = true;
			}
		}

		if ( !$validTaintEncountered ) {
			$this->debug( __METHOD__, 'Possibly wrong taint annotation in docblock: ' . json_encode( $docBlock ) );
		}

		SecurityCheckPlugin::$docblockCache[ $fqsen ] = $validTaintEncountered
			? [ $funcTaint, $fakeMethodLinks ]
			: null;
		return SecurityCheckPlugin::$docblockCache[ $fqsen ];
	}

	/**
	 * Given a type, determine what type of taint
	 *
	 * e.g. Integers are probably untainted since its hard to do evil
	 * with them, but mark strings as unknown since we don't know.
	 *
	 * Only use as a fallback
	 * @param UnionType $types The types
	 */
	protected function getTaintByType( UnionType $types ): Taintedness {
		// NOTE: This flattens intersection types
		$typelist = $types->getUniqueFlattenedTypeSet();
		if ( !$typelist ) {
			// $this->debug( __METHOD__, "Setting type unknown due to no type info." );
			return Taintedness::unknownSingleton();
		}

		$taint = Taintedness::safeSingleton();
		$isPossiblyUnknown = false;
		foreach ( $typelist as $type ) {
			if ( $type instanceof LiteralTypeInterface ) {
				// We're going to assume that literals aren't tainted...
				continue;
			}
			switch ( $type->getName() ) {
				case 'int':
				case 'non-zero-int':
				case 'float':
				case 'bool':
				case 'false':
				case 'true':
				case 'null':
				case 'void':
				case 'class-string':
				case 'callable-string':
				case 'callable-object':
				case 'callable-array':
					break;
				case 'string':
				case 'non-empty-string':
				case 'Closure':
				case 'callable':
				case 'array':
				case 'iterable':
				case 'object':
				case 'resource':
				case 'mixed':
				case 'non-empty-mixed':
				case 'non-null-mixed':
					// $this->debug( __METHOD__, "Taint set unknown due to type '$type'." );
					$isPossiblyUnknown = true;
					break;
				default:
					if ( $type->hasTemplateTypeRecursive() ) {
						// TODO Can we do better for template types?
						$isPossiblyUnknown = true;
						break;
					}

					if ( !$type->isObjectWithKnownFQSEN() ) {
						// Likely some phan-specific types not included above
						$this->debug( __METHOD__, " $type (" . get_class( $type ) . ') not a class?' );
						$isPossiblyUnknown = true;
						break;
					}

					$fqsenStr = $type->asFQSEN()->__toString();
					if ( isset( self::$fqsensWithoutToStringCache[$fqsenStr] ) ) {
						$isPossiblyUnknown = true;
						break;
					}

					// This means specific class, so look up __toString()
					$toStringFQSEN = FullyQualifiedMethodName::fromStringInContext(
						$fqsenStr . '::__toString',
						$this->context
					);
					if ( !$this->code_base->hasMethodWithFQSEN( $toStringFQSEN ) ) {
						// This is common in a void context.
						// e.g. code like $this->foo() will reach this
						// check.
						self::$fqsensWithoutToStringCache[$fqsenStr] = true;
						$isPossiblyUnknown = true;
						break;
					}
					// TODO: This should add caused-by lines also.
					$toString = $this->code_base->getMethodByFQSEN( $toStringFQSEN );
					$toStringTaint = $this->getTaintOfFunction( $toString );
					$taint = $taint->asMergedWith( $toStringTaint->getOverall()->without(
						SecurityCheckPlugin::PRESERVE_TAINT | SecurityCheckPlugin::ALL_EXEC_TAINT
					) );
			}
		}
		if ( $isPossiblyUnknown ) {
			$taint = $taint->with( SecurityCheckPlugin::UNKNOWN_TAINT );
		}
		return $taint;
	}

	/**
	 * Get what taint types are allowed on a typed element (i.e. use its type to rule out
	 * impossible taint types).
	 *
	 * @return Taintedness|null Null means all taints, checking for null is faster than ORing
	 */
	protected function getTaintMaskForTypedElement( TypedElementInterface $var ): ?Taintedness {
		if ( $var instanceof GlobalVariable ) {
			// TODO We wouldn't need to do this if phan didn't infer real types for global variables.
			// See https://github.com/phan/phan/issues/4518
			$var = $var->getElement();
		}
		// Note, we must use the real union type because:
		// 1 - The non-real type might be wrong
		// 2 - The non-real type might be incomplete (e.g. when analysing a func without docblock
		// we still don't know all the possible types of the params).
		return $this->getTaintMaskForType( $var->getUnionType()->getRealUnionType() );
	}

	/**
	 * Get what taint types are allowed on an element with the given type.
	 *
	 * @return Taintedness|null Null for all flags
	 */
	protected function getTaintMaskForType( UnionType $type ): ?Taintedness {
		$typeTaint = $this->getTaintByType( $type );

		if ( $typeTaint->has( SecurityCheckPlugin::UNKNOWN_TAINT ) ) {
			return null;
		}
		return $typeTaint;
	}

	/**
	 * Get what taint the element could have in the future. For instance, a func parameter may initially
	 * have no taint, but it may become tainted depending on the argument.
	 * @todo Ensure this won't miss any case (aside from when phan infers a wrong real type)
	 *
	 * @return Taintedness|null Null for all taints
	 */
	protected function getPossibleFutureTaintOfElement( TypedElementInterface $el ): ?Taintedness {
		return $this->getTaintMaskForTypedElement( $el );
	}

	/**
	 * Get name of current method (for debugging purposes)
	 *
	 * @return string Name of method or "[no method]"
	 */
	protected function getCurrentMethod(): string {
		return $this->context->isInFunctionLikeScope() ?
			(string)$this->context->getFunctionLikeFQSEN() : '[no method]';
	}

	/**
	 * Get the taintedness of something from the AST tree.
	 *
	 * @param mixed $expr An expression from the AST tree.
	 */
	protected function getTaintedness( mixed $expr ): TaintednessWithError {
		if ( $expr instanceof Node ) {
			return $this->getTaintednessNode( $expr );
		}

		assert( is_scalar( $expr ) || $expr === null );
		return TaintednessWithError::emptySingleton();
	}

	/**
	 * Give an AST node, find its taint. This always returns a copy.
	 *
	 * @param Node $node
	 * @suppress PhanUndeclaredProperty
	 */
	protected function getTaintednessNode( Node $node ): TaintednessWithError {
		// Performance: use isset(), not property_exists()
		if ( isset( $node->taint ) ) {
			// Return cached result. Cache hit ratio should ideally be 100%, because we should never have to retrieve
			// the taintedness of a node without having analyzed it first. For now the ratio is lower because
			// we don't cache the result of cheap nodes.
			return $node->taint;
		}
		// TODO This might just a return a default if no cached data.

		// Debug::printNode( $node );
		// Make sure to update the line number, or the same issue may be reported
		// more than once on different lines (see test 'multilineissue').
		$oldLine = $this->context->getLineNumberStart();
		$this->context->setLineNumberStart( $node->lineno );

		$visitor = new TaintednessVisitor( $this->code_base, $this->context );
		try {
			return $visitor->analyzeNodeAndGetTaintedness( $node );
		} finally {
			$this->context->setLineNumberStart( $oldLine );
		}
	}

	/**
	 * Given a phan object (not method/function) find its taint. This always returns a copy
	 * for existing objects.
	 */
	protected function getTaintednessPhanObj( TypedElementInterface $variableObj ): Taintedness {
		assert( !$variableObj instanceof FunctionInterface, "This method cannot be used with methods" );
		$taintOrNull = self::getTaintednessRaw( $variableObj );
		if ( $taintOrNull !== null ) {
			$mask = $this->getTaintMaskForTypedElement( $variableObj );
			$taintedness = $mask !== null ? $taintOrNull->withOnly( $mask->get() ) : $taintOrNull;
			// echo "$varName has taintedness $taintedness due to last time\n";
		} else {
			$type = $variableObj->getUnionType();
			$taintedness = $this->getTaintByType( $type );
			// $this->debug( " \$" . $variableObj->getName() . " first sight."
			// . " taintedness set to $taintedness due to type $type\n";
		}
		return $taintedness;
	}

	/**
	 * Shortcut to resolve array offsets, which includes:
	 *  - Ensuring that the value is not null: null is used for implicit dims like in `$a[] = $b`; we can't say
	 *    for sure what the offset will be, and this method would return null (interpreted as offset 0), which is
	 *    most likely wrong.
	 *  - Casting floats to integers, since using a float as array key raises a warning (and crashes taint-check)
	 *    (T307504)
	 *  - Letting nodes that represent resources (e.g. `STDIN`) pass through, since they're not scalar and certainly
	 *    not valid offsets (see https://github.com/phan/phan/issues/4659).
	 *
	 * @param Node|mixed $rawOffset
	 * @return Node|mixed
	 */
	protected function resolveOffset( mixed $rawOffset ): mixed {
		assert( $rawOffset !== null );
		$resolved = $this->resolveValue( $rawOffset );
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.is_resource
		if ( is_resource( $resolved ) ) {
			return $rawOffset;
		}
		return is_float( $resolved ) ? (int)$resolved : $resolved;
	}

	/**
	 * Shortcut to try and turn an AST element (Node or already literal) into an equivalent PHP
	 * scalar value.
	 *
	 * @param Node|mixed $value A Node or a scalar value from the AST
	 * @return Node|mixed An equivalent scalar PHP value, or $value if it cannot be resolved
	 */
	protected function resolveValue( mixed $value ): mixed {
		if ( !$value instanceof Node ) {
			return $value;
		}

		try {
			// Do not emit any issues here, see also upstream's 4629_define_global.php test.
			return $this->getCtxN( $value )->getEquivalentPHPScalarValue( false );
		} catch ( IssueException ) {
			return $value;
		}
	}

	/**
	 * Get a property by name in the current scope, failing hard if it cannot be found.
	 */
	private function getPropInCurrentScopeByName( string $propName ): Property {
		assert( $this->context->isInClassScope() );
		try {
			$clazz = $this->context->getClassInScope( $this->code_base );
		} catch ( CodeBaseException $e ) {
			throw new RuntimeException( 'Cannot find class in scope: ' . $this->getDebugInfo( $e ) );
		}

		assert( $clazz->hasPropertyWithName( $this->code_base, $propName ) );
		return $clazz->getPropertyByName( $this->code_base, $propName );
	}

	/**
	 * Quick wrapper to get the ContextNode for a node
	 *
	 * @param Node|mixed $node
	 */
	protected function getCtxN( mixed $node ): ContextNode {
		return new ContextNode(
			$this->code_base,
			$this->context,
			$node
		);
	}

	/**
	 * Given a node, return the Phan variable objects that
	 * correspond to that node to which we can backpropagate a NUMKEY taintedness.
	 *
	 * @todo This should be handled together with the non-numkey case
	 *
	 * @param Node $node AST node in question
	 * @return TypedElementInterface[] Array of various phan objects corresponding to $node
	 */
	protected function getObjsForNodeForNumkeyBackprop( Node $node ): array {
		// TODO For now we only backprop in the simple case, to avoid tons of false positives, unless
		// the env flag is set (chiefly for tests)
		$definitelyNumkey = !getenv( 'SECCHECK_NUMKEY_SPERIMENTAL' );

		switch ( $node->kind ) {
			case \ast\AST_PROP:
			case \ast\AST_NULLSAFE_PROP:
			case \ast\AST_STATIC_PROP:
				$prop = $this->getPropFromNode( $node );
				return $prop && $this->elementCanBeNumkey( $prop, $definitelyNumkey ) ? [ $prop ] : [];
			case \ast\AST_VAR:
			case \ast\AST_CLOSURE_VAR:
				$cn = $this->getCtxN( $node );
				if ( Variable::isHardcodedGlobalVariableWithName( $cn->getVariableName() ) ) {
					return [];
				}
				try {
					$var = $cn->getVariable();
					return $this->elementCanBeNumkey( $var, $definitelyNumkey ) ? [ $var ] : [];
				} catch ( NodeException | IssueException $e ) {
					$this->debug( __METHOD__, "variable not in scope?? " . $this->getDebugInfo( $e ) );
					return [];
				}
			case \ast\AST_ENCAPS_LIST:
			case \ast\AST_ARRAY:
				$results = [];
				foreach ( $node->children as $child ) {
					if ( !$child instanceof Node ) {
						continue;
					}

					if (
						$child->kind === \ast\AST_ARRAY_ELEM &&
						$child->children['key'] !== null && !$this->nodeCanBeIntKey( $child->children['key'] )
					) {
						continue;
					}
					$results = array_merge( $this->getObjsForNodeForNumkeyBackprop( $child ), $results );
				}
				return $results;
			case \ast\AST_ARRAY_ELEM:
				$results = [];
				if ( $node->children['key'] instanceof Node ) {
					$results = array_merge(
						$this->getObjsForNodeForNumkeyBackprop( $node->children['key'] ),
						$results
					);
				}
				if ( $node->children['value'] instanceof Node ) {
					$results = array_merge(
						$this->getObjsForNodeForNumkeyBackprop( $node->children['value'] ),
						$results
					);
				}
				return $results;
			case \ast\AST_CAST:
				// Future todo might be to ignore casts to ints, since
				// such things should be safe. Unclear if that makes
				// sense in all circumstances.
				if ( $node->children['expr'] instanceof Node ) {
					return $this->getObjsForNodeForNumkeyBackprop( $node->children['expr'] );
				}
				return [];
			case \ast\AST_DIM:
				if ( $node->children['expr'] instanceof Node ) {
					// For now just consider the outermost array.
					// FIXME. doesn't handle tainted array keys!
					return $this->getObjsForNodeForNumkeyBackprop( $node->children['expr'] );
				}
				return [];
			case \ast\AST_UNARY_OP:
				$var = $node->children['expr'];
				return $var instanceof Node ? $this->getObjsForNodeForNumkeyBackprop( $var ) : [];
			case \ast\AST_BINARY_OP:
				$left = $node->children['left'];
				$right = $node->children['right'];
				$leftObj = $left instanceof Node ? $this->getObjsForNodeForNumkeyBackprop( $left ) : [];
				$rightObj = $right instanceof Node ? $this->getObjsForNodeForNumkeyBackprop( $right ) : [];
				return array_merge( $leftObj, $rightObj );
			case \ast\AST_CONDITIONAL:
				$t = $node->children['true'];
				$f = $node->children['false'];
				$tObj = $t instanceof Node ? $this->getObjsForNodeForNumkeyBackprop( $t ) : [];
				$fObj = $f instanceof Node ? $this->getObjsForNodeForNumkeyBackprop( $f ) : [];
				return array_merge( $tObj, $fObj );
			case \ast\AST_CONST:
			case \ast\AST_CLASS_CONST:
			case \ast\AST_CLASS_NAME:
			case \ast\AST_MAGIC_CONST:
			case \ast\AST_ISSET:
			case \ast\AST_NEW:
			// For now we don't do methods, only variables
			// Also don't do args to function calls.
			// Unclear if this makes sense.
				return [];
			case \ast\AST_CALL:
			case \ast\AST_STATIC_CALL:
			case \ast\AST_METHOD_CALL:
			case \ast\AST_NULLSAFE_METHOD_CALL:
				if ( $definitelyNumkey ) {
					// This case is too hard for now.
					return [];
				}
				$ctxNode = $this->getCtxN( $node );
				// @todo Future todo might be to still return arguments when catching an exception.
				if ( $node->kind === \ast\AST_CALL ) {
					if ( $node->children['expr']->kind !== \ast\AST_NAME ) {
						// TODO Handle this case!
						return [];
					}
					try {
						$func = $ctxNode->getFunction( $node->children['expr']->children['name'] );
					} catch ( IssueException | FQSENException $e ) {
						$this->debug( __METHOD__, "FIXME func not found: " . $this->getDebugInfo( $e ) );
						return [];
					}
				} else {
					$methodName = $node->children['method'];
					try {
						$func = $ctxNode->getMethod( $methodName, $node->kind === \ast\AST_STATIC_CALL, true );
					} catch ( NodeException | CodeBaseException | IssueException $e ) {
						$this->debug( __METHOD__, "FIXME method not found: " . $this->getDebugInfo( $e ) );
						return [];
					}
				}
				try {
					return $this->getReturnObjsOfFunc( $func );
				} catch ( Exception $e ) {
					$this->debug( __METHOD__, "FIXME: " . $this->getDebugInfo( $e ) );
					return [];
				}
			case \ast\AST_PRE_INC:
			case \ast\AST_PRE_DEC:
			case \ast\AST_POST_INC:
			case \ast\AST_POST_DEC:
				$children = $node->children;
				assert( count( $children ) === 1 );
				return $this->getObjsForNodeForNumkeyBackprop( reset( $children ) );
			default:
				// TODO Should probably handle AST_MATCH & friends
				// Debug::printNode( $node );
				// This should really be a visitor that recurses into
				// things.
				$this->debug( __METHOD__, "FIXME unhandled case"
					. Debug::nodeName( $node ) . "\n"
				);
				return [];
		}
	}

	protected function getPropFromNode( Node $node ): ?Property {
		try {
			return $this->getCtxN( $node )->getProperty( $node->kind === \ast\AST_STATIC_PROP );
		} catch ( NodeException | IssueException | UnanalyzableException $e ) {
			$this->debug( __METHOD__, "Cannot determine " .
				"property (Maybe don't know what class) - " .
				$this->getDebugInfo( $e )
			);
			return null;
		}
	}

	/**
	 * Extract some useful debug data from an exception
	 */
	protected function getDebugInfo( Exception $e ): string {
		return $e instanceof IssueException
			? $e->getIssueInstance()->__toString()
			: ( get_class( $e ) . " {$e->getMessage()}" );
	}

	/**
	 * Get the current filename and line.
	 *
	 * @param Context|null $context Override the context to make debug info for
	 * @return string path/to/file +linenumber
	 */
	protected function dbgInfo( ?Context $context = null ): string {
		$ctx = $context ?: $this->context;
		// Using a + instead of : so that I can just copy and paste
		// into a vim command line.
		return $ctx->getFile() . ' +' . $ctx->getLineNumberStart();
	}

	/**
	 * Link together a Method and its parameters,the idea being if the method gets called with something evil
	 * later, we can traceback anything it might affect.
	 * Note that we don't do this for functions with hardcoded taint, in which case we assume that any dangerous
	 * association was already hardcoded. This is also good for performance, because hardcoded function tend to be
	 * used a lot (for MW, think of methods in Database or in Html).
	 *
	 * @param Variable $param The variable object for the parameter. This can also be
	 *  instance of Parameter (subclass of Variable).
	 * @param FunctionInterface $func The function/method in question
	 * @param int $i Which argument number is $param
	 */
	protected function linkParamAndFunc( Variable $param, FunctionInterface $func, int $i ): void {
		// $this->debug( __METHOD__, "Linking '$param' to '$func' arg $i" );

		// TODO Use $func's builtin/annotated taintedness (available in PreTaintednessVisitor) to check this per
		// parameter (looking at NO_OVERRIDE)
		$canLinkParam = !SecurityCheckPlugin::$pluginInstance->builtinFuncHasTaint( $func->getFQSEN() );
		if ( !$canLinkParam ) {
			return;
		}

		self::ensureVarLinksForArgExist( $func, $i );

		$curLinks = self::getMethodLinks( $param ) ?? MethodLinks::emptySingleton();
		self::setMethodLinks( $param, $curLinks->withFuncAndParam( $func, $i, $param->isVariadic() ) );
	}

	/**
	 * Given a LHS and RHS make all the methods that can set RHS also for LHS
	 *
	 * Given 2 variables (e.g. $lhs = $rhs ), see to it that any function/method
	 * which we marked as being able to set the value of rhs, is also marked
	 * as being able to set the value of lhs. We use this information to figure
	 * out what method parameter is causing the return statement to be tainted.
	 *
	 * @warning Be careful calling this function if lhs already has taint
	 *  or rhs side is a compound statement. This could result in misattribution
	 *  of where the taint is coming from.
	 *
	 * This also merges the information on what line caused the taint.
	 *
	 * @param TypedElementInterface $lhs Source of method list
	 * @param MethodLinks $rhsLinks New links
	 * @param bool $override
	 */
	protected function mergeTaintDependencies(
		TypedElementInterface $lhs,
		MethodLinks $rhsLinks,
		bool $override
	): void {
		// So if we have $a = $b;
		// First we find out all the methods that can set $b
		// Then we add $a to the list of variables that those methods can set.
		// Last we add these methods to $a's list of all methods that can set it.

		$curLinks = self::getMethodLinks( $lhs );
		if ( $override || !$curLinks ) {
			$newLinks = $rhsLinks;
		} else {
			$newLinks = $curLinks->asMergedWith( $rhsLinks );
		}

		// Don't attach things like Variable and Parameter. These are local elements, and setting taint
		// on them in markAllDependentVarsYes would have no effect. Additionally, since phan creates a new
		// Parameter object for each analysis, we will end up with duplicated links that do nothing but
		// eating memory.
		// Also unnecessary to attach PassByReferenceVariable, as that receives special handling in handlePassByRef.
		if ( $lhs instanceof Property || $lhs instanceof GlobalVariable ) {
			foreach ( $newLinks->getMethodAndParamTuples() as [ $method, $index ] ) {
				$varLinks = self::getVarLinks( $method, $index );
				assert( $varLinks instanceof VarLinksMap );
				// $this->debug( __METHOD__, "During assignment, we link $lhs to $method($index)" );
				$varLinks->offsetSet( $lhs, $newLinks->asPreservedTaintednessForFuncParam( $method, $index ) );
			}
		}

		self::setMethodLinks( $lhs, $newLinks );
	}

	/**
	 * Mark any function setting a specific variable as EXEC taint
	 *
	 * If you do something like echo $this->foo;
	 * This method is called to make all things that set $this->foo
	 * as TAINT_EXEC.
	 *
	 * @note This might have annoying false positives with widely used properties
	 * that are used with different levels of escaping, which is not a good idea anyway.
	 *
	 * @param TypedElementInterface $var The variable in question
	 * @param Taintedness $sinkTaint What taint to mark them as.
	 * @param CausedByLines|null $sinkError Any extra caused-by lines from the sink to add
	 */
	protected function markAllDependentMethodsExec(
		TypedElementInterface $var,
		Taintedness $sinkTaint,
		?CausedByLines $sinkError = null
	): void {
		$futureTaint = $this->getPossibleFutureTaintOfElement( $var );
		if ( $futureTaint !== null && !$futureTaint->has( $sinkTaint->get() ) ) {
			return;
		}
		// Ensure we only set exec bits, not normal taint bits.
		$taint = $sinkTaint->withOnly( SecurityCheckPlugin::BACKPROP_TAINTS );
		if ( $taint->isSafe() || $this->isIssueSuppressedOrFalsePositive( $taint ) ) {
			return;
		}

		$varLinks = self::getMethodLinks( $var );
		if ( $varLinks === null || $varLinks->isEmpty() ) {
			return;
		}
		$varError = self::getCausedByRaw( $var );
		$varError = $varError ? $varError->withOnlyLinks() : CausedByLines::emptySingleton();
		$sinkError ??= CausedByLines::emptySingleton();

		$methodParamTuples = $varLinks->getMethodAndParamTuples();
		$linkedParameters = new SplObjectStorage();
		foreach ( $methodParamTuples as [ $method, $param ] ) {
			$curData = $linkedParameters[$method] ?? [];
			$curData[] = $param;
			$linkedParameters[$method] = $curData;
		}
		$emptyFunctionTaint = FunctionTaintedness::emptySingleton();
		$emptyFunctionCausedByLines = FunctionCausedByLines::emptySingleton();
		foreach ( $linkedParameters as $method ) {
			$parameters = $linkedParameters[$method];
			$calleeParamList = $method->getParameterList();
			$funcTaint = $emptyFunctionTaint;
			$funcArgError = $emptyFunctionCausedByLines;
			$funcSinkError = $emptyFunctionCausedByLines;
			foreach ( $parameters as $param ) {
				$filteredLinks = $varLinks->asFilteredForFuncAndParam( $method, $param );
				$newParamTaint = $sinkTaint->appliedToLinksForBackprop( $filteredLinks, $method, $param );
				$paramVarError = $varError
					->withTaintAddedToMethodArgLinks( $newParamTaint->asExecToYesTaint(), $method, $param, true );
				$paramSinkError = $sinkError->forSinkBackprop( $filteredLinks, $method, $param );

				if ( isset( $calleeParamList[$param] ) && $calleeParamList[$param]->isVariadic() ) {
					$funcTaint = $funcTaint->withVariadicParamSinkTaint( $param, $newParamTaint );
					$funcArgError = $funcArgError->withVariadicParamSinkLines( $param, $paramVarError );
					$funcSinkError = $funcSinkError->withVariadicParamSinkLines( $param, $paramSinkError );
				} else {
					$funcTaint = $funcTaint->withParamSinkTaint( $param, $newParamTaint );
					$funcArgError = $funcArgError->withParamSinkLines( $param, $paramVarError );
					$funcSinkError = $funcSinkError->withParamSinkLines( $param, $paramSinkError );
				}
			}

			$this->addFuncTaint( $method, $funcTaint );
			$newFuncTaint = self::getFuncTaint( $method );
			assert( $newFuncTaint !== null );

			$this->mergeFuncError( $method, $funcArgError, $newFuncTaint );
			$this->maybeAddFuncError( $method, null, $funcTaint, $newFuncTaint );
			$this->mergeFuncError( $method, $funcSinkError, $newFuncTaint );
		}
	}

	/**
	 * Mark any function setting a specific variable as EXEC taint
	 *
	 * If you do something like echo $this->foo;
	 * This method is called to make all things that set $this->foo
	 * as TAINT_EXEC.
	 *
	 * @note This might have annoying false positives with widely used properties
	 * that are used with different levels of escaping, which is not a good idea anyway.
	 *
	 * @param Node $node
	 * @param Taintedness $taint What taint to mark them as.
	 * @param CausedByLines|null $additionalError Additional caused-by lines to propagate
	 * @param bool $tempNumkey Temporary param
	 */
	protected function markAllDependentMethodsExecForNode(
		Node $node,
		Taintedness $taint,
		?CausedByLines $additionalError = null,
		bool $tempNumkey = false
	): void {
		if ( !$tempNumkey ) {
			$backpropVisitor = new TaintednessBackpropVisitor(
				$this->code_base,
				$this->context,
				$taint,
				$additionalError
			);
			$backpropVisitor( $node );
			return;
		}
		$phanObjs = $this->getObjsForNodeForNumkeyBackprop( $node );
		foreach ( array_unique( $phanObjs ) as $phanObj ) {
			$this->markAllDependentMethodsExec( $phanObj, $taint, $additionalError );
		}
	}

	/**
	 * This happens when someone calls foo( $evilTaintedVar );
	 *
	 * It makes sure that any variable that the function foo() sets takes on
	 * the taint of the supplied argument.
	 *
	 * @param FunctionInterface $method The function or method in question
	 * @param int $i The number of the argument in question.
	 * @param Taintedness $taint The taint to apply.
	 * @param CausedByLines $error Caused-by lines to propagate
	 */
	protected function markAllDependentVarsYes(
		FunctionInterface $method,
		int $i,
		Taintedness $taint,
		CausedByLines $error
	): void {
		if ( $method->isPHPInternal() ) {
			return;
		}
		$varLinks = self::getVarLinks( $method, $i );
		if ( $varLinks === null ) {
			return;
		}

		$taintAdjusted = $taint->withOnly( SecurityCheckPlugin::ALL_TAINT );

		foreach ( $varLinks as $var ) {
			$presTaint = $varLinks[$var];
			$taintToPropagate = $presTaint->asTaintednessForArgument( $taintAdjusted );
			$this->setTaintedness( $var, $taintToPropagate, false );

			$newErrors = $this->getCausedByLinesToAdd( $taintToPropagate, null );
			$adjustedCausedBy = ( self::getCausedByRaw( $var ) ?? CausedByLines::emptySingleton() )
				->withTaintAddedToMethodArgLinks( $taintToPropagate, $method, $i, false );
			$newCausedBy = $error->withAddedLines( $newErrors, $taintToPropagate )->asMergedWith( $adjustedCausedBy );
			self::setCausedByRaw( $var, $newCausedBy );

			if ( $var instanceof GlobalVariable ) {
				$globalVar = $var->getElement();
				$this->setTaintedness( $globalVar, $taintToPropagate, false );

				$adjustedGlobalCausedBy = ( self::getCausedByRaw( $globalVar ) ?? CausedByLines::emptySingleton() )
					->withTaintAddedToMethodArgLinks( $taintToPropagate, $method, $i, false );
				$newGlobalCausedBy = $error->withAddedLines( $newErrors, $taintToPropagate )
					->asMergedWith( $adjustedGlobalCausedBy );
				self::setCausedByRaw( $globalVar, $newGlobalCausedBy );
			}
		}
	}

	/**
	 * Get the original cause of taint for the given func
	 */
	private function getCausedByLinesForFunc( FunctionInterface $element ): FunctionCausedByLines {
		$element = $this->getActualFuncWithCausedBy( $element );
		return self::getFuncCausedByRaw( $element ) ?? FunctionCausedByLines::emptySingleton();
	}

	/**
	 * Given a phan element, get the actual element where caused-by data is stored. For instance, for methods, this
	 * returns the defining methods.
	 */
	private function getActualFuncWithCausedBy( FunctionInterface $element ): FunctionInterface {
		if ( SecurityCheckPlugin::$pluginInstance->builtinFuncHasTaint( $element->getFQSEN() ) ) {
			return $element;
		}
		$definingFunc = $this->getDefiningFuncIfDifferent( $element );
		return $definingFunc ?? $element;
	}

	/**
	 * Output a debug message to stdout.
	 *
	 * @param string $method __METHOD__ in question
	 * @param string $msg debug message
	 * @codeCoverageIgnore
	 */
	public function debug( string $method, string $msg ): void {
		if ( $this->debugOutput === null ) {
			$errorOutput = getenv( "SECCHECK_DEBUG" );
			if ( $errorOutput && $errorOutput !== '-' ) {
				$this->debugOutput = fopen( $errorOutput, "w" );
			} elseif ( $errorOutput === '-' ) {
				$this->debugOutput = '-';
			} else {
				$this->debugOutput = false;
			}
		}
		$line = $method . "\33[1m " . $this->dbgInfo() . " \33[0m" . $msg . "\n";
		if ( $this->debugOutput && $this->debugOutput !== '-' ) {
			fwrite(
				$this->debugOutput,
				$line
			);
		} elseif ( $this->debugOutput === '-' ) {
			// @phan-suppress-next-line PhanPluginRemoveDebugEcho This is the only wanted debug echo
			echo $line;
		}
	}

	/**
	 * Given an AST node that might be a callable, try and determine what function it is.
	 * This is intended for functions that register callbacks.
	 *
	 * @param Node|mixed $node The thingy from AST expected to be a Callable
	 */
	protected function getCallableFromNode( mixed $node ): ?FunctionInterface {
		$funcs = UnionTypeVisitor::functionLikeListFromNodeAndContext(
			$this->code_base,
			$this->context,
			$node,
			false
		);
		return $funcs[0] ?? null;
	}

	/**
	 * Get the issue names and severities given a taint, as well as the relevant taint type for each issue.
	 *
	 * @param Taintedness $combinedTaint The taint to warn for, i.e. the exec flags from LHS, intersected with the RHS
	 * taint. Must have EXEC flags only.
	 * @param int $combinedTaintInt All taintedness flags present in $combinedTaint
	 * @return array<array<string|int|Taintedness>> List of issue type, severity, and taintedness
	 * @phan-return non-empty-list<array{0:string,1:int,2:Taintedness}>
	 */
	public function taintToIssuesAndSeverities( Taintedness $combinedTaint, int $combinedTaintInt ): array {
		$issues = [];
		if ( $combinedTaintInt & SecurityCheckPlugin::HTML_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-XSS',
				Issue::SEVERITY_NORMAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::HTML_EXEC_TAINT )
			];
		}
		$allSQLTaints = SecurityCheckPlugin::SQL_EXEC_TAINT | SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT;
		if ( $combinedTaintInt & $allSQLTaints ) {
			$issues[] = [
				'SecurityCheck-SQLInjection',
				Issue::SEVERITY_CRITICAL,
				$combinedTaint->withOnly( $allSQLTaints )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::SHELL_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-ShellInjection',
				Issue::SEVERITY_CRITICAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::SHELL_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::SERIALIZE_EXEC_TAINT ) {
			// For now this is low because it seems to have a lot of false positives.
			$issues[] = [
				'SecurityCheck-PHPSerializeInjection',
				Issue::SEVERITY_NORMAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::SERIALIZE_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::ESCAPED_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-DoubleEscaped',
				Issue::SEVERITY_NORMAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::ESCAPED_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::PATH_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-PathTraversal',
				Issue::SEVERITY_CRITICAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::PATH_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::CODE_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-RCE',
				Issue::SEVERITY_CRITICAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::CODE_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::REGEX_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-ReDoS',
				Issue::SEVERITY_NORMAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::REGEX_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::CUSTOM1_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-CUSTOM1',
				Issue::SEVERITY_NORMAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::CUSTOM1_EXEC_TAINT )
			];
		}
		if ( $combinedTaintInt & SecurityCheckPlugin::CUSTOM2_EXEC_TAINT ) {
			$issues[] = [
				'SecurityCheck-CUSTOM2',
				Issue::SEVERITY_NORMAL,
				$combinedTaint->withOnly( SecurityCheckPlugin::CUSTOM2_EXEC_TAINT )
			];
		}

		return $issues;
	}

	/**
	 * Simplified version of maybeEmitIssue which makes the following assumptions:
	 *  - The caller would compute the RHS taint only to feed it to maybeEmitIssue
	 *  - The message should be followed by caused-by lines
	 *  - These caused-by lines should be taken from the same object passed as RHS
	 *  - Only caused-by lines having the LHS taint should be included
	 * If these conditions hold true, then this method should be preferred.
	 *
	 * @warning DO NOT use this method if the caller already needs to compute the RHS
	 * taintedness! The taint would be computed twice!
	 *
	 * @param Taintedness $lhsTaint
	 * @param mixed $rhsElement
	 * @param string $msg
	 * @param array $params Additional parameters for the message template
	 * @phan-param list<string|FullyQualifiedFunctionLikeName> $params
	 */
	public function maybeEmitIssueSimplified(
		Taintedness $lhsTaint,
		mixed $rhsElement,
		string $msg,
		array $params = []
	): void {
		$rhsTaint = $this->getTaintedness( $rhsElement );
		$this->maybeEmitIssue(
			$lhsTaint,
			$rhsTaint->getTaintedness(),
			$msg . '{DETAILS}',
			array_merge( $params, [ [ 'lines' => $rhsTaint->getError(), 'sink' => false ] ] )
		);
	}

	/**
	 * Emit an issue using the appropriate issue type
	 *
	 * If $this->overrideContext is set, it will use that for the
	 * file/line number to report. This is meant as a hack, so that
	 * in MW we can force hook related issues to be in the extension
	 * instead of where the hook is called from in MW core.
	 *
	 * @param Taintedness $lhsTaint Taint of left hand side (or equivalent)
	 * @param Taintedness $rhsTaint Taint of right hand side (or equivalent)
	 * @param string $msg Issue description
	 * @param array|Closure $msgParamsOrGetter Message parameters passed to emitIssue. Can also be a closure
	 * that returns said parameters, for performance.
	 * @phan-param list|Closure():list $msgParamsOrGetter
	 */
	public function maybeEmitIssue(
		Taintedness $lhsTaint,
		Taintedness $rhsTaint,
		string $msg,
		array|Closure $msgParamsOrGetter
	): void {
		$rhsIsUnknown = $rhsTaint->has( SecurityCheckPlugin::UNKNOWN_TAINT );
		if ( $rhsIsUnknown && $lhsTaint->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) ) {
			$combinedTaint = Taintedness::safeSingleton();
			$combinedTaintInt = SecurityCheckPlugin::NO_TAINT;
		} else {
			$combinedTaint = Taintedness::intersectForSink( $lhsTaint, $rhsTaint );
			if ( $combinedTaint->isSafe() ) {
				return;
			}
			$combinedTaintInt = $combinedTaint->get();
		}

		if (
			( $combinedTaintInt === SecurityCheckPlugin::NO_TAINT && $rhsIsUnknown ) ||
			SecurityCheckPlugin::$pluginInstance->isFalsePositive(
				Taintedness::flagsAsExecToYesTaint( $combinedTaintInt ),
				$msg,
				// FIXME should this be $this->overrideContext ?
				$this->context,
				$this->code_base
			)
		) {
			$issues = [
				[ 'SecurityCheck-LikelyFalsePositive', Issue::SEVERITY_LOW, $combinedTaint ]
			];
		} else {
			$issues = $this->taintToIssuesAndSeverities( $combinedTaint, $combinedTaintInt );
		}

		if ( !$issues ) {
			return;
		}

		$context = $this->context;
		if ( $this->overrideContext ) {
			// If we are overriding the file/line number,
			// report the original line number as well.
			$msg .= " (Originally at: $this->context)";
			$context = $this->overrideContext;
		}

		$msgParams = $msgParamsOrGetter instanceof Closure ? $msgParamsOrGetter() : $msgParamsOrGetter;
		// Phan doesn't analyze the ternary correctly and thinks this might also be a closure.
		'@phan-var list $msgParams';

		/** @var Taintedness $relevantSinkTaint */
		foreach ( $issues as [ $issueType, $severity, $relevantSinkTaint ] ) {
			$relevantRHSTaint = $rhsTaint->withNumkeyAddedToSQL()
				->withOnly( $relevantSinkTaint->asExecToYesTaint()->get() );
			$curMsgParams = [];
			foreach ( $msgParams as $i => $par ) {
				if ( is_array( $par ) ) {
					assert( isset( $par['lines'] ) && $par['lines'] instanceof CausedByLines );
					$curMsgParams[$i] = $par['lines']->toStringForIssue(
						$relevantSinkTaint,
						$relevantRHSTaint,
						$par['sink']
					);
				} else {
					$curMsgParams[$i] = $par;
				}
			}
			SecurityCheckPlugin::emitIssue(
				$this->code_base,
				$context,
				$issueType,
				$msg,
				$curMsgParams,
				$severity
			);
		}
	}

	/**
	 * Method to determine if a potential error isn't really real
	 *
	 * This is useful when a specific warning would have a side effect
	 * and we want to know whether we should suppress the side effect in
	 * addition to the warning.
	 *
	 * @param Taintedness $lhsTaint Must have at least one EXEC flag set
	 */
	public function isIssueSuppressedOrFalsePositive( Taintedness $lhsTaint ): bool {
		assert( $lhsTaint->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) );
		$combinedTaintInt = $lhsTaint->get();

		$issues = $this->taintToIssuesAndSeverities( $lhsTaint, $combinedTaintInt );
		$context = $this->overrideContext ?: $this->context;
		foreach ( $issues as [ $issueType ] ) {
			if ( $context->hasSuppressIssue( $this->code_base, $issueType ) ) {
				return true;
			}
		}

		$msg = "[dummy msg for false positive check]";
		return SecurityCheckPlugin::$pluginInstance->isFalsePositive(
			Taintedness::flagsAsExecToYesTaint( $combinedTaintInt ),
			$msg,
			// not using $this->overrideContext to be consistent with maybeEmitIssue()
			$this->context,
			$this->code_base
		);
	}

	/**
	 * Somebody invokes a method or function (or something similar)
	 *
	 * This has to figure out:
	 *  Is the return value of the call tainted
	 *  Are any of the arguments tainted
	 *  Does the function do anything scary with its arguments
	 * It also has to maintain quite a bit of book-keeping.
	 *
	 * @param FunctionInterface $func
	 * @param FullyQualifiedFunctionLikeName $funcName
	 * @param array $args Arguments to function/method
	 * @phan-param array<Node|mixed> $args
	 * @param bool $computePreserve Whether the caller wants to know which taintedness is preserved by this call
	 * @param bool $isHookHandler Whether we're analyzing a hook handler for a hook handler call.
	 *   FIXME This is MW-specific
	 * @return TaintednessWithError|null Taint The resulting taint of the expression, or null if
	 *   $computePreserve is false
	 */
	public function handleMethodCall(
		FunctionInterface $func,
		FullyQualifiedFunctionLikeName $funcName,
		array $args,
		bool $computePreserve = true,
		bool $isHookHandler = false
	): ?TaintednessWithError {
		$specialCallResult = null;
		if ( $this->maybeHandleSpecialCall( $func, $args, $computePreserve, $specialCallResult ) ) {
			return $specialCallResult;
		}

		$taint = $this->getTaintOfFunction( $func );
		$funcError = $this->getCausedByLinesForFunc( $func );

		$preserveArgumentsData = [];
		foreach ( $args as $i => $argument ) {
			if ( !( $argument instanceof Node ) ) {
				// Literal value
				continue;
			}
			$curParFlags = $taint->getParamFlags( $i );
			if ( ( $curParFlags & SecurityCheckPlugin::ARRAY_OK ) && $this->nodeIsArray( $argument ) ) {
				// This function specifies that arrays are always ok, so skip.
				continue;
			}

			if ( $argument->kind === \ast\AST_NAMED_ARG ) {
				[ $i, $argument, $argName ] = $this->translateNamedArg( $argument, $func );
				if ( $i === null || !$argument instanceof Node ) {
					// Cannot find argument or it's literal
					continue;
				}
				$argName = "`$argName`";
			} else {
				$argName = '#' . ( $i + 1 );
			}

			$paramSinkTaint = $taint->getParamSinkTaint( $i );
			$paramSinkError = $funcError->getParamSinkLines( $i );

			$argTaintWithError = $this->getTaintednessNode( $argument );
			$curArgTaintedness = $argTaintWithError->getTaintedness();
			$baseArgError = $argTaintWithError->getError();
			if (
				$paramSinkTaint->has( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT )
				&& $curArgTaintedness->has( SecurityCheckPlugin::SQL_TAINT )
				&& $this->nodeCanBeString( $argument )
			) {
				// Special case to make NUMKEY work right for non-array values.
				// TODO Should consider if this is really best approach.
				$curArgTaintedness = $curArgTaintedness->with( SecurityCheckPlugin::SQL_NUMKEY_TAINT );
			}

			[ $paramSinkTaint, $paramSinkError ] = SecurityCheckPlugin::$pluginInstance->modifyParamSinkTaint(
				$paramSinkTaint,
				$curArgTaintedness,
				$argument,
				$i,
				$func,
				$taint,
				$paramSinkError,
				$this->context,
				$this->code_base
			);

			// Add a hook in order to special case for codebases. This is primarily used as a hack so that in mediawiki
			// the Message class doesn't have double escape taint if method takes Message|string.
			// TODO This is quite hacky.
			$curArgTaintedness = SecurityCheckPlugin::$pluginInstance->modifyArgTaint(
				$curArgTaintedness,
				$argument,
				$i,
				$func,
				$taint,
				$this->context,
				$this->code_base
			);

			// TODO: We also need to handle the case where someFunc( $execArg ) for pass by reference where
			// the parameter is later executed outside the func.
			if ( $curArgTaintedness->has( SecurityCheckPlugin::ALL_TAINT ) ) {
				$this->markAllDependentVarsYes( $func, $i, $curArgTaintedness, $baseArgError );
			}

			// We are doing something like evilMethod( $arg ); where $arg is a parameter to the current function.
			// So backpropagate that assigning to $arg can cause evilness.
			if ( !$paramSinkTaint->isSafe() ) {
				$this->backpropagateArgTaint( $argument, $paramSinkTaint, $paramSinkError );
			}

			$param = $func->getParameterForCaller( $i );
			// @todo Internal funcs that pass by reference. Should we assume that their variables are tainted? Most
			// common example is probably preg_match, which may very well be tainted much of the time.
			if ( $param && $param->isPassByReference() && !$func->isPHPInternal() ) {
				$this->handlePassByRef( $func, $argument, $args, $i, $isHookHandler );
			}

			/** @phan-return list */
			$issueArgsGetter = function () use (
				$funcName, $argName, $argument, $paramSinkError, $baseArgError
			): array {
				// Always include the ordinal (it helps for repeated arguments)
				$taintedArg = $argName;
				$argStr = ASTReverter::toShortString( $argument );
				if ( strlen( $argStr ) < 25 ) {
					// If we have a short representation of the arg, include it as well.
					$taintedArg .= " (`$argStr`)";
				}

				return [
					$funcName,
					$this->getCurrentMethod(),
					$taintedArg,
					[ 'lines' => $paramSinkError, 'sink' => true ],
					[ 'lines' => $baseArgError, 'sink' => false ],
				];
			};

			$this->maybeEmitIssue(
				$paramSinkTaint,
				$curArgTaintedness,
				"Calling method {FUNCTIONLIKE}() in {FUNCTIONLIKE}" .
				" that outputs using tainted argument {CODE}.{DETAILS}{DETAILS}",
				$issueArgsGetter
			);

			$preserveArgumentsData[$i] = [ $curArgTaintedness, MethodLinks::emptySingleton(), $baseArgError ];
		}

		if ( !$computePreserve ) {
			return null;
		}

		$hardcodedPreservedTaint = $this->getHardcodedPreservedTaintForFunc( $func, $preserveArgumentsData );
		if ( $hardcodedPreservedTaint ) {
			$callLinks = $hardcodedPreservedTaint->getMethodLinks();
			$callLineCausedBy = $this->getCausedByLinesToAdd( Taintedness::safeSingleton(), $callLinks );
			$callError = $hardcodedPreservedTaint->getError()
				->withAddedLines( $callLineCausedBy, Taintedness::safeSingleton(), $callLinks );
			return new TaintednessWithError(
				$hardcodedPreservedTaint->getTaintedness(),
				$callError,
				$callLinks
			);
		}
		$overallTaint = $taint->getOverall();
		$combinedArgTaint = Taintedness::safeSingleton();
		$combinedArgLinks = MethodLinks::emptySingleton();
		$combinedArgErrors = CausedByLines::emptySingleton();
		/**
		 * @var Taintedness $curArgTaintedness
		 * @var CausedByLines $baseArgError
		 */
		foreach ( $preserveArgumentsData as $i => [ $curArgTaintedness, , $baseArgError ] ) {
			if ( $taint->hasParamPreserve( $i ) ) {
				$parTaint = $taint->getParamPreservedTaint( $i );
				if ( $parTaint->isEmpty() ) {
					continue;
				}
				$preservedArgTaint = $parTaint->asTaintednessForArgument( $curArgTaintedness );
				$curArgLinks = MethodLinks::emptySingleton();
				$relevantParamError = $funcError->getParamPreservedLines( $i )
					->asPreservedForParameter( $curArgTaintedness, $curArgLinks, $func, $i );
				$callLineCausedBy = $this->getCausedByLinesToAdd( Taintedness::safeSingleton(), $curArgLinks );
				$curArgError = $baseArgError->asPreservedForArgument( $parTaint )
					->withAddedLines( $callLineCausedBy, Taintedness::safeSingleton(), $curArgLinks )
					->asMergedWith( $relevantParamError );
			} elseif (
				$overallTaint->has( SecurityCheckPlugin::PRESERVE_TAINT | SecurityCheckPlugin::UNKNOWN_TAINT )
			) {
				// No info for this specific parameter, but the overall function either preserves taint
				// when unspecified or is unknown. So just pass the taint through, destroying the shape.
				$preservedArgTaint = $curArgTaintedness->asCollapsed();
				$curArgLinks = MethodLinks::emptySingleton();
				$relevantParamError = $funcError->getParamPreservedLines( $i )
					->asPreservedForParameter( $curArgTaintedness, $curArgLinks, $func, $i );
				$callLineCausedBy = $this->getCausedByLinesToAdd( Taintedness::safeSingleton(), $curArgLinks );
				$curArgError = $baseArgError->asAllCollapsed()
					->withAddedLines( $callLineCausedBy, Taintedness::safeSingleton(), $curArgLinks )
					->asMergedWith( $relevantParamError );
			} else {
				// This parameter has no taint info. And overall this function doesn't depend on param
				// for taint and isn't unknown. So we consider this argument untainted.
				continue;
			}

			$combinedArgTaint = $combinedArgTaint->asMergedWith( $preservedArgTaint );
			$combinedArgLinks = $combinedArgLinks->asMergedWith( $curArgLinks );
			// NOTE: If any line inside the callee's body is responsible for preserving the taintedness of more
			// than one argument, it will appear once per preserved argument in the overall caused-by of the
			// call expression. This is probably a good thing, but can increase the length of caused-by lines.
			// TODO Something like T291379 might help here.
			$combinedArgErrors = $combinedArgErrors->asMergedWith( $curArgError );
		}

		$callTaintedness = $overallTaint->without(
			SecurityCheckPlugin::PRESERVE_TAINT | SecurityCheckPlugin::ALL_EXEC_TAINT
		);
		$combinedArgTaint = $combinedArgTaint->without( SecurityCheckPlugin::ALL_EXEC_TAINT );
		$callTaintedness = $callTaintedness->asMergedWith( $combinedArgTaint );
		$callError = $funcError->getGenericLines()->asMergedWith( $combinedArgErrors );
		return new TaintednessWithError( $callTaintedness, $callError, $combinedArgLinks );
	}

	/**
	 * Handle calls to special built-in functions.
	 *
	 * @param FunctionInterface $func
	 * @param array $args Arguments to function/method
	 * @phan-param array<Node|mixed> $args
	 * @param bool $computePreserve
	 * @param TaintednessWithError|null &$result
	 */
	private function maybeHandleSpecialCall(
		FunctionInterface $func,
		array $args,
		bool $computePreserve,
		?TaintednessWithError &$result
	): bool {
		switch ( ltrim( $func->getName(), '\\' ) ) {
			case 'call_user_func':
				if ( !$args ) {
					// Syntax error, whatever.
					return false;
				}
				$callbackNode = array_shift( $args );
				// Use the same method as ClosureReturnTypeOverridePlugin to avoid emitting new issues.
				$callbacks = UnionTypeVisitor::functionLikeListFromNodeAndContext(
					$this->code_base,
					$this->context,
					$callbackNode,
					false
				);

				$result = $computePreserve ? TaintednessWithError::emptySingleton() : null;
				foreach ( $callbacks as $callback ) {
					// No point in analyzing abstract function declarations
					if ( !$callback instanceof FunctionLikeDeclarationType ) {
						$callTaint = $this->handleMethodCall(
							$callback, $callback->getFQSEN(), $args, $computePreserve
						);
						if ( $result && $callTaint ) {
							$result = $result->asMergedWith( $callTaint );
						}
					}
				}
				return true;
			case 'call_user_func_array':
				if ( count( $args ) < 2 ) {
					// Syntax error, whatever.
					return false;
				}
				// Use the same method as ClosureReturnTypeOverridePlugin to avoid emitting new issues.
				$callbacks = UnionTypeVisitor::functionLikeListFromNodeAndContext(
					$this->code_base,
					$this->context,
					$args[0],
					false
				);
				$callbackArguments = self::extractArrayArgs( $args[1] );
				if ( $callbackArguments === null ) {
					return false;
				}

				$result = $computePreserve ? TaintednessWithError::emptySingleton() : null;
				foreach ( $callbacks as $callback ) {
					// No point in analyzing abstract function declarations
					if ( !$callback instanceof FunctionLikeDeclarationType ) {
						$callTaint = $this->handleMethodCall(
							$callback, $callback->getFQSEN(), $callbackArguments, $computePreserve
						);
						if ( $result && $callTaint ) {
							$result = $result->asMergedWith( $callTaint );
						}
					}
				}
				return true;
			default:
				return false;
		}
	}

	/**
	 * Given the node for an array that contains arguments to a function, extract those arguments.
	 * Shamelessly stolen from phan's ClosureReturnTypeOverridePlugin.
	 *
	 * @param Node|mixed $argArrayNode
	 * @return array<Node|mixed>|null
	 */
	private static function extractArrayArgs( mixed $argArrayNode ): ?array {
		if ( !( $argArrayNode instanceof Node ) || $argArrayNode->kind !== \ast\AST_ARRAY ) {
			return null;
		}
		$arguments = [];
		foreach ( $argArrayNode->children as $child ) {
			if ( !( $child instanceof Node ) ) {
				continue;
			}
			$arguments[] = $child->children['value'];
		}
		return $arguments;
	}

	/**
	 * @todo This should possibly be part of the public interface upstream
	 * @see \Phan\Analysis\ArgumentType::analyzeParameterListForCallback
	 * @return array
	 * @phan-return array{0:int|null,1:Node|mixed,2:?string}
	 */
	private function translateNamedArg( Node $argument, FunctionInterface $func ): array {
		[ 'name' => $argName, 'expr' => $argExpr ] = $argument->children;
		assert( $argExpr !== null );

		foreach ( $func->getRealParameterList() as $i => $parameter ) {
			if ( $parameter->getName() === $argName ) {
				return [ $i, $argExpr, $argName ];
			}
		}
		return [ null, null, null ];
	}

	/**
	 * @param Node $argument
	 * @param Taintedness $taint
	 * @param CausedByLines|null $funcError
	 *
	 * @todo This has false negatives, because we don't collect function arguments in
	 * getPhanObjsForNode (we'd have to pass option 'all'), so we can't handle e.g. array_merge
	 * right now. However, collecting all args would create false positives with functions where
	 * the arg taint isn't propagated to the return value. Ideally, we'd want to include an argument
	 * iff the corresponding parameter passes $taint through.
	 *
	 * @note It's important that we don't backpropagate taintedness to every returned object in case
	 * of function calls, but just props and the like (so excluding vars). See test 'toomanydeps'.
	 */
	protected function backpropagateArgTaint(
		Node $argument,
		Taintedness $taint,
		?CausedByLines $funcError = null
	): void {
		if ( $taint->has( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ) ) {
			// Special case for numkey, we need to "filter" the argument.
			// TODO This doesn't return arrays with mixed keys. Currently, doing so would result
			// in arrays being considered as a unit, and the taint would be backpropagated to all
			// values, even ones with string keys. See TODO in elementCanBeNumkey

			// TODO This should be limited to the outer array, see TODO in backpropnumkey test
			// Note that this is true in general for NUMKEY taint, not just when backpropagating it
			$numkeyTaint = $taint->withOnly( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT );
			$this->markAllDependentMethodsExecForNode( $argument, $numkeyTaint, $funcError, true );
			$taint = $taint->without( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT );
		}

		$this->markAllDependentMethodsExecForNode( $argument, $taint, $funcError );
	}

	/**
	 * Handle pass-by-ref params when examining a function call. Phan handles passbyref by reanalyzing
	 * the method with PassByReferenceVariable objects instead of Parameters. These objects contain
	 * the info about the param, but proxy all calls to the underlying argument object.
	 * We cannot 100% copy that behaviour: inside the function body, the local variable for the pbr param
	 * would have the same taintedness as the argument, and things like `echo $pbr` would emit an issue
	 * inside the function, which is unwanted for now. Additionally, it's unclear how we'd add a caused-by
	 * entry for the line of the function call.
	 * Hence, instead of adding taintedness to the underlying argument, we put it in a separate prop, which is only
	 * written but never read inside the function body. Then after the call was analyzed, this method moves
	 * the taintedness from the "special" prop onto the normal taintedness prop. We do the same thing for links
	 * (so as to infer which taintedness from the argument is preserved by the function) and caused-by lines.
	 * TODO In the future we might want to really copy phan's approach, as that would allow us to delete some hacks,
	 *   and handle conditionals inside the function body more accurately.
	 *
	 * @param FunctionInterface $func
	 * @param Node $argument The argument being passed by-ref
	 * @param array<Node|mixed> $arguments The full list of arguments for this function call
	 * @param int $refArgIndex @phan-unused-param
	 * @param bool $isHookHandler Whether we're analyzing a hook handler for a hook handler call.
	 *   FIXME This is MW-specific
	 */
	private function handlePassByRef(
		FunctionInterface $func,
		Node $argument,
		array $arguments,
		int $refArgIndex,
		bool $isHookHandler
	): void {
		$argObj = $this->getPassByRefObjFromNode( $argument );
		if ( !$argObj ) {
			return;
		}
		$refTaint = self::getTaintednessRef( $argObj );
		if ( !$refTaint ) {
			// If no ref taint was set, it's likely due to a recursive call or another instance where phan is not
			// reanalyzing the callee with PassByReferenceVariable objects.
			return;
		}

		$globalVarObj = $argObj instanceof GlobalVariable ? $argObj->getElement() : null;
		// Move the ref taintedness to the "actual" taintedness of the object
		// Note: We assume that the order in which hook handlers are called is nondeterministic, thus
		// we never override arg taint for reference params in this case.
		$overrideTaint = !( $argObj instanceof Property || $globalVarObj || $isHookHandler );
		$newTaint = $refTaint;
		$refLinks = self::getMethodLinksRef( $argObj );
		$refError = self::getCausedByRef( $argObj ) ?? CausedByLines::emptySingleton();
		$addedError = CausedByLines::emptySingleton();

		// Merge any taintedness from links, indicating that (part of) the original argument "survived" the call.
		if ( $refLinks ) {
			$linkedParameters = array_column(
				array_filter(
					$refLinks->getMethodAndParamTuples(),
					/** @phan-param array{0:FunctionInterface,1:int} $tuple */
					static fn ( array $tuple ): bool => $tuple[0] === $func
				),
				1
			);
		} else {
			$linkedParameters = [];
		}

		if ( in_array( $refArgIndex, $linkedParameters, true ) ) {
			// Special handling for the original by-ref variable: process it first for proper caused-by ordering.
			$linkedParameters = array_diff( $linkedParameters, [ $refArgIndex ] );
			array_unshift( $linkedParameters, $refArgIndex );
		} else {
			$addedError = $addedError->withAddedLines( $this->getCausedByLinesToAdd( $refTaint, null ), $refTaint );
		}

		foreach ( $linkedParameters as $paramIdx ) {
			if ( !isset( $arguments[$paramIdx] ) || !$arguments[$paramIdx] instanceof Node ) {
				// Optional parameter not passed here, or untainted literal.
				continue;
			}
			assert( $refLinks !== null );
			$argTaintFull = $this->getTaintedness( $arguments[$paramIdx] );
			$argTaint = $argTaintFull->getTaintedness();
			$argError = $argTaintFull->getError();

			$paramPreservedTaint = $refLinks->asPreservedTaintednessForFuncParam( $func, $paramIdx );
			$preservedTaint = $paramPreservedTaint->asTaintednessForArgument( $argTaint );
			$newTaint = $preservedTaint->asMergedWith( $newTaint );
			$curLineErrorTaint = $paramIdx === $refArgIndex ? $refTaint : $preservedTaint;
			$curCallLine = $this->getCausedByLinesToAdd( $curLineErrorTaint, null );
			$curAddedError = $argError->asPreservedForArgument( $paramPreservedTaint )
				->withAddedLines( $curCallLine, $curLineErrorTaint )
				->asMergedWith( $refError->asPreservedForParameter( $argTaint, $refLinks, $func, $paramIdx ) );
			$addedError = $addedError->asMergedWith( $curAddedError );
		}

		$newAddedError = $addedError->asMergedWith( $refError );
		if ( $overrideTaint ) {
			$newError = $newAddedError;
		} else {
			$prevError = self::getCausedByRaw( $argObj ) ?? CausedByLines::emptySingleton();
			$newError = $prevError->asMergedWith( $newAddedError );
		}

		$this->setTaintedness( $argObj, $newTaint, $overrideTaint );
		self::setCausedByRaw( $argObj, $newError );
		if ( $globalVarObj ) {
			$this->setTaintedness( $globalVarObj, $newTaint, false );
			$curGlobalError = self::getCausedByRaw( $globalVarObj ) ?? CausedByLines::emptySingleton();
			self::setCausedByRaw( $globalVarObj, $curGlobalError->asMergedWith( $newError ) );
		}
		// We clear method links since the by-ref call might have modified them, and precise tracking is not
		// trivial to implement, and most probably not worth the effort.
		self::setMethodLinks( $argObj, MethodLinks::emptySingleton() );
		self::clearRefData( $argObj );
	}

	/**
	 * Given the node of an argument that is passed by reference, return a list of phan objects
	 * corresponding to that node.
	 */
	private function getPassByRefObjFromNode( Node $node ): ?TypedElementInterface {
		switch ( $node->kind ) {
			case \ast\AST_PROP:
			case \ast\AST_STATIC_PROP:
				return $this->getPropFromNode( $node );
			case \ast\AST_VAR:
				$cn = $this->getCtxN( $node );
				if ( Variable::isHardcodedGlobalVariableWithName( $cn->getVariableName() ) ) {
					return null;
				}
				try {
					return $cn->getVariable();
				} catch ( NodeException | IssueException ) {
					return null;
				}
			case \ast\AST_DIM:
				// Phan doesn't handle this case with PassByReferenceVariable objects, so nothing we can do anyway.
				return null;
			default:
				$this->debug( __METHOD__, 'Unhandled pass-by-ref case: ' . Debug::nodeName( $node ) );
				return null;
		}
	}

	/**
	 * Get the taintedness of the return value of $func (a special-cased internal PHP function) given the taintedness
	 * of its arguments. Note that this doesn't handle passbyref parameters. If the function is not special-cased,
	 * returns null.
	 *
	 * @param FunctionInterface $func
	 * @param array<array<Taintedness|MethodLinks|CausedByLines>> $preserveArgumentsData Actual taintedness, links and
	 * caused-by lines of each argument. Literal arguments aren't included here.
	 * @phan-param array<int,array{0:Taintedness,1:MethodLinks,2:CausedByLines}> $preserveArgumentsData
	 * @suppress PhanUnusedVariable
	 */
	private function getHardcodedPreservedTaintForFunc(
		FunctionInterface $func,
		array $preserveArgumentsData
	): ?TaintednessWithError {
		switch ( ltrim( $func->getName(), '\\' ) ) {
			// Functions that return one element of the array (first and only parameter)
			case 'array_pop':
			case 'array_shift':
			case 'current':
			case 'end':
			case 'next':
			case 'pos':
			case 'prev':
			case 'reset':
			case 'array_first':
			case 'array_last':
			// array_find has an additional $callback parameter that we don't use
			case 'array_find':
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$taint = $preserveArgumentsData[0][0]->asValueFirstLevel();
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asAllValueFirstLevel()->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_values':
				// Same taintedness as the original array (first and only param), but with safe keys and numkey.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$taint = $preserveArgumentsData[0][0]->withoutKeys();
				if ( $taint->has( SecurityCheckPlugin::SQL_TAINT ) ) {
					$taint = $taint->with( SecurityCheckPlugin::SQL_NUMKEY_TAINT );
				}
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asAllCollapsed()->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			// Functions that return a key from the array (first and only parameter)
			case 'key':
			case 'array_key_first':
			case 'array_key_last':
			// array_find_key has an additional $callback parameter that we don't use
			case 'array_find_key':
			// array_keys returns all keys from the array (first param), and can also take two more parameters
			// that don't contribute to the resulting taintedness.
			case 'array_keys':
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$taint = $preserveArgumentsData[0][0]->asKeyForForeach();
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asAllKeyForForeach()->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_change_key_case':
				// The overall shape remains the same, but the keys of the outermost array (first param) have different
				// case. Second param (lower vs upper) is safe.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				// TODO: actually handle case changes!
				$taint = $preserveArgumentsData[0][0];
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_flip':
				// Swaps keys and values of the array (first and only param)
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$taint = $preserveArgumentsData[0][0]->asKeyForForeach()
					->withAddedKeysTaintedness( $preserveArgumentsData[0][0]->asValueFirstLevel()->get() );
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asAllCollapsed()->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			case 'implode':
			case 'join':
				// This function can be called in three different ways:
				// - implode( $string, $array ) -> joins elements in $array using $string
				// - implode( $array ) -> joins elements in $array using the empty string
				// - implode( $array, $string ) -> same as the first one but inverted params, removed in PHP 8,
				// not supported here
				if ( isset( $preserveArgumentsData[0] ) ) {
					$joinerTaint = $preserveArgumentsData[0][0]->asCollapsed();
					$joinerLinks = MethodLinks::emptySingleton();
					$joinerError = $preserveArgumentsData[0][2]->asAllCollapsed()
						->asIntersectedWithTaintedness( $joinerTaint );
				}
				$combinedTaint = $joinerTaint ?? Taintedness::safeSingleton();
				$combinedLinks = $joinerLinks ?? MethodLinks::emptySingleton();
				$combinedError = $joinerError ?? CausedByLines::emptySingleton();
				if ( isset( $preserveArgumentsData[1] ) ) {
					$arrayTaint = $preserveArgumentsData[1][0]->withoutKeys()->asCollapsed();
					$arrayLinks = MethodLinks::emptySingleton();
					$arrayError = $preserveArgumentsData[1][2]->asAllCollapsed();

					$combinedTaint = $combinedTaint->asMergedWith( $arrayTaint );
					$combinedLinks = $combinedLinks->asMergedWith( $arrayLinks );
					$combinedError = $combinedError->asMergedWith(
						$arrayError->asIntersectedWithTaintedness( $arrayTaint )
					);
				}
				return new TaintednessWithError( $combinedTaint, $combinedError, $combinedLinks );
			case 'array_fill':
				// array_fill( $start, $count, $value ) creates an array with $count copies of $value, starting
				// at key $start. The first two params are integers, and thus safe.
				if ( !isset( $preserveArgumentsData[2] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$preservedArgTaint = $preserveArgumentsData[2][0];
				// TODO: We may actually be able to infer the actual keys, instead of setting as unknown
				$taint = Taintedness::newFromShape( [], $preservedArgTaint );
				$links = MethodLinks::emptySingleton();
				// TODO: We should also add numkey if the argument has sql.
				$error = $preserveArgumentsData[2][2]->asAllCollapsed()
					->asIntersectedWithTaintedness( $preservedArgTaint );
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_fill_keys':
				// array_fill_keys( $keys, $value ) creates an array whose keys are the element in $keys, and whose
				// values are all equal to $value.
				$taint = Taintedness::safeSingleton();
				$links = MethodLinks::emptySingleton();
				$error = CausedByLines::emptySingleton();
				if ( isset( $preserveArgumentsData[0] ) ) {
					$keysTaintedness = $preserveArgumentsData[0][0]->asValueFirstLevel();
					$keysLinks = MethodLinks::emptySingleton();
					$keysError = $preserveArgumentsData[0][2]->asAllCollapsed();

					$taint = $taint->withAddedKeysTaintedness( $keysTaintedness->get() );
					$links = $links->withKeysLinks( $keysLinks->getLinksCollapsing() );
					$error = $error->asMergedWith(
						$keysError->asIntersectedWithTaintedness( $taint )
					);
				}
				if ( isset( $preserveArgumentsData[1] ) ) {
					$valueTaint = $preserveArgumentsData[1][0];
					$valueLinks = MethodLinks::emptySingleton();
					$valueError = $preserveArgumentsData[1][2]->asAllCollapsed();

					$taint = $taint->withAddedOffsetTaintedness( null, $valueTaint );
					$links = $links->withLinksAtDim( null, $valueLinks );
					$error = $error->asMergedWith(
						$valueError->asIntersectedWithTaintedness( $valueTaint )
					);
				}
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_combine':
				// array_fill_keys( $keys, $values ) creates an array whose keys are the element in $keys, and whose
				// values the elements in $values.
				$taint = Taintedness::safeSingleton();
				$links = MethodLinks::emptySingleton();
				$error = CausedByLines::emptySingleton();
				if ( isset( $preserveArgumentsData[0] ) ) {
					$keysTaintedness = $preserveArgumentsData[0][0]->asValueFirstLevel();
					$keysLinks = MethodLinks::emptySingleton();
					$keysError = $preserveArgumentsData[0][2]->asAllCollapsed();

					$taint = $taint->withAddedKeysTaintedness( $keysTaintedness->get() );
					$links = $links->withKeysLinks( $keysLinks->getLinksCollapsing() );
					$error = $error->asMergedWith(
						$keysError->asIntersectedWithTaintedness( $taint )
					);
				}
				if ( isset( $preserveArgumentsData[1] ) ) {
					$valueTaint = $preserveArgumentsData[1][0]->withoutKeys();
					$valueLinks = MethodLinks::emptySingleton();
					$valueError = $preserveArgumentsData[1][2]->asAllCollapsed();

					$taint = $taint->asMergedWith( $valueTaint );
					$links = $links->asMergedWith( $valueLinks );
					$error = $error->asMergedWith(
						$valueError->asIntersectedWithTaintedness( $valueTaint )
					);
				}
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_unique':
				// Removes duplicate from an array (first param). We can't tell what gets removed, and what's the effect
				// of this function on array keys. Second param is safe.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$taint = $preserveArgumentsData[0][0]->asKnownKeysMadeUnknown();
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asAllCollapsed()->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_diff':
			case 'array_diff_assoc':
				// - array_diff( $arr, $x_1, ..., $x_n ) returns elements in $arr that are NOT in any of the $x_i.
				//   The equality of two elements is determined by looking at their values.
				//   Only the first argument contributes to the preserved taintedness.
				// - array_diff_assoc does the same, but two elements are considered equal if they have the same value
				//   AND the same key.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				// We can't infer shape mutations because Taintedness doesn't keep track of the values, so just
				// return the taintedness of the first argument.
				$preservedArgTaint = $preserveArgumentsData[0][0];
				$preservedArgLinks = MethodLinks::emptySingleton();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preserveArgumentsData[0][2]->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			case 'array_diff_key':
				// array_diff_key( $arr, $x_1, ..., $x_n ) is similar to array_diff, but here two elements are
				// considered equal if they have the same key (regardless of the value).
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				/** @var Taintedness $taint */
				[ $taint, $links, $error ] = array_shift( $preserveArgumentsData );
				foreach ( $preserveArgumentsData as $argData ) {
					$taint = $taint->withoutKnownKeysFrom( $argData[0] );
					// No argument besides the first one can contribute to caused-by lines, although
					// ideally we would remove the current error from $error.
				}
				// The shape is destroyed to avoid pretending that we know anything about the final shape of the array.
				return new TaintednessWithError(
					$taint->asKnownKeysMadeUnknown(),
					$error->asAllCollapsed(),
					$links
				);
			case 'array_intersect':
			case 'array_intersect_assoc':
				// - array_intersect( $arr_1, ..., $arr_n ) returns an array of elements that are in ALL of the $x_i.
				//   The equality of two elements is determined by looking at their values.
				//   Only values from the first array are used for the return value.
				// - array_intersect_assoc does the same, but two elements are considered equal if they have the same
				//   value AND the same key.
				if ( !$preserveArgumentsData ) {
					return TaintednessWithError::emptySingleton();
				}
				// Note: we can't do an actual intersect on the values because Taintedness does not store them, but
				// intersecting the taintedness flags, although not perfect, is correct and approximates that.
				// The shape is destroyed to avoid pretending that we know anything about the final shape of the array.
				/** @var Taintedness $taint */
				[ $taint, $links, $error ] = array_shift( $preserveArgumentsData );
				$taint = $taint->asKnownKeysMadeUnknown();
				foreach ( $preserveArgumentsData as $argData ) {
					$taint = $taint->withOnly( $argData[0]->get() );
					// No argument besides the first one can contribute to caused-by lines, although
					// ideally we would intersect $error with the current error.
				}
				return new TaintednessWithError( $taint, $error->asAllCollapsed(), $links );
			case 'array_intersect_key':
				// array_intersect_key( $arr, $x_1, ..., $x_n ) is similar to array_intersect, but here two elements are
				// considered equal if they have the same key (irregardless of the value).
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				// We can't infer shape mutations because there might be unknown keys in either argument, so just
				// return the taintedness of the first argument.
				$preservedArgTaint = $preserveArgumentsData[0][0];
				$preservedArgLinks = MethodLinks::emptySingleton();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preserveArgumentsData[0][2]->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			// TODO The last parameter of these functions is a callback, so probably hard to handle. They're also
			// variadic, so we'd need to know the arg type to analyze the callback.
			case 'array_diff_uassoc':
			case 'array_diff_ukey':
			case 'array_intersect_uassoc':
			case 'array_intersect_ukey':
			case 'array_udiff':
			case 'array_udiff_assoc':
			case 'array_uintersect':
			case 'array_uintersect_assoc':
			// The last two params of these are callbacks, so twice as hard
			case 'array_udiff_uassoc':
			case 'array_uintersect_uassoc':
				// Only the taintedness from first argument is preserved.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$preservedArgTaint = $preserveArgumentsData[0][0];
				$preservedArgLinks = MethodLinks::emptySingleton();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preserveArgumentsData[0][2]->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			case 'array_map':
				// array_map( $cb, $arr, $arr_1, ..., $arr_n ) returns the result of applying $cb to all the array
				// arguments, element by element.
				// TODO: Analyze the callback. For now we only preserve taintedness of array arguments.
				unset( $preserveArgumentsData[0] );
				$taint = Taintedness::safeSingleton();
				$links = MethodLinks::emptySingleton();
				$error = CausedByLines::emptySingleton();
				foreach ( $preserveArgumentsData as [ $argTaint, $argLinks, $argError ] ) {
					$preservedArgTaint = $argTaint->asCollapsed();
					$preservedArgLinks = MethodLinks::emptySingleton();
					$preservedArgError = $argError->asAllCollapsed();

					$taint = $taint->asMergedWith( $preservedArgTaint );
					$links = $links->asMergedWith( $preservedArgLinks );
					$error = $error->asMergedWith(
						$preservedArgError->asIntersectedWithTaintedness( $preservedArgTaint )
					);
				}
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_filter':
				// array_filter( $arr, $cb, $mode ) filters the $arr by using $cb.
				// TODO: Analyze the callback. For now we preserve the whole taintedness of the array.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$preservedArgTaint = $preserveArgumentsData[0][0]->asKnownKeysMadeUnknown();
				$preservedArgLinks = MethodLinks::emptySingleton();
				$preservedArgError = $preserveArgumentsData[0][2]->asAllCollapsed();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preservedArgError->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			case 'array_reduce':
				// array_reduce( $arr, $cb, $initial ) applies $cb to $arr to obtain a single value.
				// TODO: Analyze the callback. For now we preserve the whole taintedness of the array.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$preservedArgTaint = $preserveArgumentsData[0][0]->asCollapsed();
				$preservedArgLinks = MethodLinks::emptySingleton();
				$preservedArgError = $preserveArgumentsData[0][2]->asAllCollapsed();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preservedArgError->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			case 'array_reverse':
				// array_reverse( $arr, $preserveKeys ) reverses the order of an array. String keys are always
				// preserved, the second param controls whether int keys are also preserved.
				// TODO: By knowing the value of the second arg, we could improve this by:
				// - Removing only int keys if false
				// - Preserving the whole shape if true
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$preservedArgTaint = $preserveArgumentsData[0][0]->asKnownKeysMadeUnknown();
				$preservedArgLinks = MethodLinks::emptySingleton();
				$preservedArgError = $preserveArgumentsData[0][2]->asAllCollapsed();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preservedArgError->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			case 'array_pad':
				// array_pad( $arr, $length, $val ) returns a copy of $arr padded to the size specified by $length
				// by adding copies of $val.
				if ( isset( $preserveArgumentsData[0] ) ) {
					$taint = $preserveArgumentsData[0][0];
					$links = MethodLinks::emptySingleton();
					$error = $preserveArgumentsData[0][2]->asIntersectedWithTaintedness( $taint );
				} else {
					$taint = Taintedness::safeSingleton();
					$links = MethodLinks::emptySingleton();
					$error = CausedByLines::emptySingleton();
				}
				if ( isset( $preserveArgumentsData[2] ) ) {
					$valArgTaint = $preserveArgumentsData[2][0];
					$valArgLinks = MethodLinks::emptySingleton();
					$valArgError = $preserveArgumentsData[2][2]->asAllCollapsed();

					$taint = $taint->withAddedOffsetTaintedness( null, $valArgTaint );
					$links = $links->withLinksAtDim( null, $valArgLinks );
					$error = $error->asMergedWith(
						$valArgError->asIntersectedWithTaintedness( $valArgTaint )
					);
				}
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_slice':
				// array_slice( $arr, $offset, $len, $preserveKeys ) returns the segment of $arr starting at $offset
				// and of size $len. String keys are always preserved, $preserveKeys controls whether int keys
				// are also preserved.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$preservedArgTaint = $preserveArgumentsData[0][0]->asKnownKeysMadeUnknown();
				$preservedArgLinks = MethodLinks::emptySingleton();
				$preservedArgError = $preserveArgumentsData[0][2]->asAllCollapsed();
				return new TaintednessWithError(
					$preservedArgTaint,
					$preservedArgError->asIntersectedWithTaintedness( $preservedArgTaint ),
					$preservedArgLinks
				);
			case 'array_replace':
				// array_replace( $arr, $rep_1, ..., $rep_n ) returns a copy of $arr where each element is replaced
				// with the element having the same key in the rightmost argument.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				$firstArgData = array_shift( $preserveArgumentsData );
				/** @var Taintedness $taint */
				$taint = $firstArgData[0];
				$links = MethodLinks::emptySingleton();
				$error = $firstArgData[2]->asIntersectedWithTaintedness( $taint );
				foreach ( $preserveArgumentsData as [ $argTaint, $argLinks, $argError ] ) {
					$taint = $taint->asArrayReplaceWith( $argTaint );
					// Note: we may be adding too many caused-by lines here
					$error = $error->asMergedWith(
						$argError->asAllCollapsed()->asIntersectedWithTaintedness( $argTaint )
					);
				}
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_merge':
				// array_merge( $arr_1, ... $arr_n ) merges the given array arguments. If any two (or more) input arrays
				// have the same string key, the value from the rightmost argument with that key will be used. Integer
				// keys are always appended, and never replaced. Additionally, integer keys in the resulting array
				// will be renumbered incrementally starting from 0.
				if ( !$preserveArgumentsData ) {
					return TaintednessWithError::emptySingleton();
				}
				/** @var Taintedness $taint */
				/** @var CausedByLines $error */
				[ $taint, $links, $error ] = array_shift( $preserveArgumentsData );
				foreach ( $preserveArgumentsData as [ $argTaint, $argLinks, $argError ] ) {
					$taint = $taint->asArrayMergeWith( $argTaint );
					$error = $error->asMergedWith( $argError->asIntersectedWithTaintedness( $argTaint ) );
				}
				return new TaintednessWithError( $taint, $error, $links );
			// TODO Handle these with recursion.
			case 'array_merge_recursive':
			case 'array_replace_recursive':
				$taint = Taintedness::safeSingleton();
				$links = MethodLinks::emptySingleton();
				$error = CausedByLines::emptySingleton();
				foreach ( $preserveArgumentsData as [ $curArgTaintedness, $baseArgLinks, $baseArgError ] ) {
					$preservedArgTaint = $curArgTaintedness->asKnownKeysMadeUnknown();
					$preservedArgLinks = MethodLinks::emptySingleton();
					$preservedArgError = $baseArgError->asAllCollapsed();

					$taint = $taint->asMergedWith( $preservedArgTaint );
					$links = $links->asMergedWith( $preservedArgLinks );
					$error = $error->asMergedWith(
						$preservedArgError->asIntersectedWithTaintedness( $preservedArgTaint )
					);
				}
				return new TaintednessWithError( $taint, $error, $links );
			case 'array_chunk':
				// array_chunk( $array, $length, $preserve_keys = false ) returns a list of chunks of $array. The keys
				// in each chunk are the same of $array if $preserve_keys is true. Else, they're just numbers.
				if ( !isset( $preserveArgumentsData[0] ) ) {
					return TaintednessWithError::emptySingleton();
				}
				// TODO: Check value of $preserve_keys to determine the key taintedness more accurately.
				// For now, we just assume that keys are preserved.
				$taint = Taintedness::newFromShape( [], $preserveArgumentsData[0][0]->asKnownKeysMadeUnknown() );
				$links = MethodLinks::emptySingleton();
				$error = $preserveArgumentsData[0][2]->asAllCollapsed()->asIntersectedWithTaintedness( $taint );
				return new TaintednessWithError( $taint, $error, $links );
			default:
				return null;
		}
	}

	/**
	 * Given a binary operator, compute which taint will be preserved. Safe ops don't preserve
	 * any taint, whereas unsafe ops will preserve all taints. The taint of a binop is basically
	 * ( lhs_taint | rhs_taint ) & taint_mask
	 *
	 * @warning This method should avoid computing the taint of $lhs and $rhs, because it might be
	 * called in preorder, but it would trigger a postorder visit.
	 *
	 * @param Node $opNode
	 * @param Node|mixed $lhs Either a Node or a scalar
	 * @param Node|mixed $rhs Either a Node or a scalar
	 */
	protected function getBinOpTaintMask( Node $opNode, mixed $lhs, mixed $rhs ): int {
		static $safeBinOps = [
			\ast\flags\BINARY_BOOL_XOR,
			\ast\flags\BINARY_DIV,
			\ast\flags\BINARY_IS_EQUAL,
			\ast\flags\BINARY_IS_IDENTICAL,
			\ast\flags\BINARY_IS_NOT_EQUAL,
			\ast\flags\BINARY_IS_NOT_IDENTICAL,
			\ast\flags\BINARY_IS_SMALLER,
			\ast\flags\BINARY_IS_SMALLER_OR_EQUAL,
			\ast\flags\BINARY_MOD,
			\ast\flags\BINARY_MUL,
			\ast\flags\BINARY_POW,
			// BINARY_ADD handled below due to array addition.
			\ast\flags\BINARY_SUB,
			\ast\flags\BINARY_BOOL_AND,
			\ast\flags\BINARY_BOOL_OR,
			\ast\flags\BINARY_IS_GREATER,
			\ast\flags\BINARY_IS_GREATER_OR_EQUAL,
			\ast\flags\BINARY_SHIFT_LEFT,
			\ast\flags\BINARY_SHIFT_RIGHT,
			\ast\flags\BINARY_SPACESHIP,
		];

		// This list is mostly used for debugging purposes
		static $knownUnsafeOps = [
			\ast\flags\BINARY_ADD,
			\ast\flags\BINARY_CONCAT,
			\ast\flags\BINARY_COALESCE,
			// The result of bitwise ops can be a string, so we err on the side of caution.
			\ast\flags\BINARY_BITWISE_AND,
			\ast\flags\BINARY_BITWISE_OR,
			\ast\flags\BINARY_BITWISE_XOR,
			// Note, the pipe operator is also special-cased in TaintednessVisitor
			\ast\flags\BINARY_PIPE,
		];

		if ( in_array( $opNode->flags, $safeBinOps, true ) ) {
			return SecurityCheckPlugin::NO_TAINT;
		}
		if (
			$opNode->flags === \ast\flags\BINARY_ADD &&
			( !$this->nodeCanBeArray( $lhs ) || !$this->nodeCanBeArray( $rhs ) )
		) {
			// Array addition is the only way `+` can preserve taintedness; if at least one operand
			// is definitely NOT an array, then the result will be an integer, or a fatal error will
			// occurr (depending on the other operand). Note that if we cannot be 100% sure that the
			// node cannot be an array (e.g. if it has mixed type), we err on the side of caution and
			// consider it potentially tainted.
			return SecurityCheckPlugin::NO_TAINT;
		}

		if ( !in_array( $opNode->flags, $knownUnsafeOps, true ) ) {
			$this->debug(
				__METHOD__,
				'Unhandled binop ' . Debug::astFlagDescription( $opNode->flags, $opNode->kind )
			);
		}

		return SecurityCheckPlugin::ALL_TAINT_FLAGS;
	}

	/**
	 * Get the possible UnionType of a node, without emitting issues.
	 */
	protected function getNodeType( Node $node ): ?UnionType {
		// Don't emit issues, as this method might be called e.g. on a LHS (see T249647).
		// FIXME Improve this. Is it still necessary now that we cache taintedness?
		$catchIssueException = false;
		// And since we don't emit issues, use a cloned context so phan won't cache any union type. In particular,
		// in the event of possibly-undefined union types, the issue about a variable being possibly undeclared would
		// get lost, because we don't emit it, and phan will cache the union type without the undefined bit.
		$ctx = clone $this->context;
		try {
			return UnionTypeVisitor::unionTypeFromNode(
				$this->code_base,
				$ctx,
				$node,
				$catchIssueException
			);
		} catch ( IssueException $e ) {
			$this->debug( __METHOD__, "Got error " . $this->getDebugInfo( $e ) );
			return null;
		}
	}

	/**
	 * Given a Node, is it an array? (And definitely not a string)
	 *
	 * @param Node|mixed $node A node object or simple value from AST tree
	 * @return bool Is it an array?
	 */
	protected function nodeIsArray( mixed $node ): bool {
		if ( !( $node instanceof Node ) ) {
			// simple literal
			return false;
		}
		if ( $node->kind === \ast\AST_ARRAY ) {
			// Exit early in the simple case.
			return true;
		}
		$type = $this->getNodeType( $node );
		return $type && $type->hasArrayLike( $this->code_base ) &&
			!$type->hasMixedOrNonEmptyMixedType() && !$type->hasStringType();
	}

	/**
	 * Can $node potentially be an array?
	 *
	 * @param Node|mixed $node
	 */
	protected function nodeCanBeArray( mixed $node ): bool {
		if ( !( $node instanceof Node ) ) {
			return is_array( $node );
		}
		$type = $this->getNodeType( $node );
		if ( !$type ) {
			return true;
		}
		$type = $type->getRealUnionType();
		return $type->hasArrayLike( $this->code_base ) || $type->hasMixedOrNonEmptyMixedType() || $type->isEmpty();
	}

	/**
	 * Given a Node, is it a string?
	 *
	 * @todo Unclear if this should return true for things that can
	 *   autocast to a string (e.g. ints)
	 * @param Node|mixed $node A node object or simple value from AST tree
	 * @return bool Is it a string?
	 */
	protected function nodeCanBeString( mixed $node ): bool {
		if ( !( $node instanceof Node ) ) {
			// simple literal
			return is_string( $node );
		}
		$type = $this->getNodeType( $node );
		// @todo Should having mixed type result in returning false here?
		return $type && $type->hasStringType();
	}

	/**
	 * @param TypedElementInterface $el
	 * @param bool $definitely Whether $el is *definitely* numkey, not just possibly
	 */
	protected function elementCanBeNumkey( TypedElementInterface $el, bool $definitely ): bool {
		$type = $el->getUnionType()->getRealUnionType();
		if ( $type->hasMixedOrNonEmptyMixedType() || $type->isEmpty() ) {
			return !$definitely;
		}
		if ( !$type->hasArray() ) {
			return false;
		}

		$keyTypes = GenericArrayType::keyUnionTypeFromTypeSetStrict( $el->getUnionType()->getRealTypeSet() );
		// NOTE: This might lead to false positives if the array has mixed keys, but since we're talking about
		// SQLi, we prefer false positives. Also, the mixed keys case isn't fully handled, see backpropagateArgTaint
		return $definitely
			? $keyTypes === GenericArrayType::KEY_INT
			: ( $keyTypes & GenericArrayType::KEY_INT ) !== 0;
	}

	/**
	 * Given a Node that is used as array key, can the key be integer?
	 * Floats are not considered ints here.
	 * Note: this method cannot be 100% accurate. First, we don't use the real type, so we may have a false positive
	 * if e.g. a parameter is annotated as string but the argument is an int. Second, even if something has a real type
	 * and is not an integer, it could be a string that gets autocast to an integer.
	 *
	 * @param Node|mixed $node A node object or simple value from AST tree
	 * @return bool Is it an int?
	 * @fixme A lot of duplication with other similar methods...
	 */
	protected function nodeCanBeIntKey( mixed $node ): bool {
		if ( !( $node instanceof Node ) ) {
			// simple number; floats are autocast to integers (with a warning)
			if ( is_int( $node ) || is_float( $node ) ) {
				return true;
			}
			// Strings that are canonical representation of numbers are coerced to int keys.
			$testArr = [ $node => 'foo' ];
			$key = key( $testArr );
			return is_int( $key );
		}
		$type = $this->getNodeType( $node );
		if ( !$type ) {
			return true;
		}
		return $type->hasIntType() || $type->hasMixedOrNonEmptyMixedType() || $type->isEmpty();
	}

	/**
	 * Get the phan objects from the return line of a Func/Method
	 *
	 * This is primarily used to handle the case where a method
	 * returns a member (e.g. return $this->foo), and then something
	 * else does something evil with it - e.g. echo $someObj->getFoo().
	 * This allows keeping track that $this->foo is outputted, so if
	 * somewhere else in the code someone calls $someObj->setFoo( $unsafe )
	 * we can trigger a warning.
	 *
	 * This of course will only work in simple cases. It may also potentially
	 * have false positives if one instance is used solely for escaped stuff
	 * and a different instance is used for unsafe values that are later
	 * escaped, as all the different instances are treated the same.
	 *
	 * It needs the return statement to be trivial (e.g. return $this->foo;). It
	 * will not work even with something as simple as $a = $this->foo; return $a;
	 * However, this code path will only happen if the plugin encounters the
	 * code to output the value prior to reading the code that sets the value to
	 * something evil. The other code path where the set happens first is much
	 * more robust and hopefully the more common code path.
	 *
	 * @param FunctionInterface $func The function/method. Must use Analyzable trait
	 * @return TypedElementInterface[] An array of phan objects
	 */
	public function getReturnObjsOfFunc( FunctionInterface $func ): array {
		$retObjs = self::getRetObjs( $func );
		if ( $retObjs === null ) {
			// We still have to see the function. Analyze it now.
			$this->analyzeFunc( $func );
			$retObjs = self::getRetObjs( $func );
			if ( $retObjs === null ) {
				// If it still doesn't exist, perhaps we reached the recursion limit, or it may be a recursive
				// function, or a kind of function that we can't handle.
				return [];
			}
		}

		// Note that if a function is recursively calling itself, this list might be incomplete.
		// This could be remediated with another dynamic property (e.g. retObjsCollected), initialized
		// inside visitMethod in preorder, and set to true inside visitMethod in postorder.
		// It would be pointless, though, as returning a partial list is better than returning no list.
		return array_filter(
			$retObjs,
			static function ( TypedElementInterface $el ): bool {
				return !( $el instanceof Variable );
			}
		);
	}

	/**
	 * Shorthand to check if $child is subclass of $parent.
	 */
	public static function isSubclassOf(
		FullyQualifiedClassName $child,
		FullyQualifiedClassName $parent,
		CodeBase $codeBase
	): bool {
		return $child->asType()->asExpandedTypes( $codeBase )->hasType( $parent->asType() );
	}
}
