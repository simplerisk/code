<?php declare( strict_types=1 );

/**
 * Base class for SecurityCheckPlugin. Extend if you want to customize.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * @license GPL-2.0-or-later
 */

namespace SecurityCheckPlugin;

use ast\Node;
use Closure;
use InvalidArgumentException;
use LogicException;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeLiteralStatementCapability;
use Phan\PluginV3\BeforeLoopBodyAnalysisCapability;
use Phan\PluginV3\MergeVariableInfoCapability;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\PreAnalyzeNodeCapability;
use RuntimeException;

/**
 * Base class used by the Generic and MediaWiki flavours of the plugin.
 */
abstract class SecurityCheckPlugin extends PluginV3 implements
	PostAnalyzeNodeCapability,
	PreAnalyzeNodeCapability,
	BeforeLoopBodyAnalysisCapability,
	MergeVariableInfoCapability,
	AnalyzeLiteralStatementCapability
{
	use TaintednessAccessorsTrait;

	// Various taint flags. The _EXEC_ varieties mean
	// that it is unsafe to assign that type of taint
	// to the variable in question.

	public const NO_TAINT = 0;

	// Flag to denote that we don't know
	public const UNKNOWN_TAINT = 1 << 0;

	// Flag for function parameters and the like, where it
	// preserves whatever taint the function is given.
	public const PRESERVE_TAINT = 1 << 1;

	// In future might separate out different types of html quoting.
	// e.g. "<div data-foo='" . htmlspecialchars( $bar ) . "'>";
	// is unsafe.
	public const HTML_TAINT = 1 << 2;
	public const HTML_EXEC_TAINT = 1 << 3;

	public const SQL_TAINT = 1 << 4;
	public const SQL_EXEC_TAINT = 1 << 5;

	public const SHELL_TAINT = 1 << 6;
	public const SHELL_EXEC_TAINT = 1 << 7;

	public const SERIALIZE_TAINT = 1 << 8;
	public const SERIALIZE_EXEC_TAINT = 1 << 9;

	// Tainted paths, as input to include(), require() and some FS functions (path traversal)
	public const PATH_TAINT = 1 << 10;
	public const PATH_EXEC_TAINT = 1 << 11;

	// User-controlled code, for RCE
	public const CODE_TAINT = 1 << 12;
	public const CODE_EXEC_TAINT = 1 << 13;

	// User-controlled regular expressions, for ReDoS
	public const REGEX_TAINT = 1 << 14;
	public const REGEX_EXEC_TAINT = 1 << 15;

	// To allow people to add other application specific taints.
	public const CUSTOM1_TAINT = 1 << 16;
	public const CUSTOM1_EXEC_TAINT = 1 << 17;
	public const CUSTOM2_TAINT = 1 << 18;
	public const CUSTOM2_EXEC_TAINT = 1 << 19;

	// Special purpose for supporting MediaWiki's IDatabase::select
	// and friends. Like SQL_TAINT, but only applies to the numeric
	// keys of an array. Note: These are not included in YES_TAINT/EXEC_TAINT.
	// e.g. given $f = [ $_GET['foo'] ]; $f would have the flag, but
	// $g = $_GET['foo']; or $h = [ 's' => $_GET['foo'] ] would not.
	// The associative keys also have this flag if they are tainted.
	// It is also assumed anything with this flag will also have
	// the SQL_TAINT flag set.
	public const SQL_NUMKEY_TAINT = 1 << 20;
	public const SQL_NUMKEY_EXEC_TAINT = 1 << 21;

	// For double escaped variables
	public const ESCAPED_TAINT = 1 << 22;
	public const ESCAPED_EXEC_TAINT = 1 << 23;

	// Special purpose flags (Starting at 2^28)
	// TODO Renumber these. Requires changing format of the hardcoded arrays
	// Cancel's out all EXEC flags on a function arg if arg is array.
	public const ARRAY_OK = 1 << 28;

	// Do not allow autodetected taint info override given taint.
	// TODO Store this and other special flags somewhere else in the FunctionTaintedness object, not
	// as normal taint flags.
	public const NO_OVERRIDE = 1 << 29;

	public const VARIADIC_PARAM = 1 << 30;

	// *All* function flags
	//TODO Add a structure test for this
	public const FUNCTION_FLAGS = self::ARRAY_OK | self::NO_OVERRIDE;

	// Combination flags.

	// YES_TAINT denotes all taint a user controlled variable would have
	public const YES_TAINT = self::HTML_TAINT | self::SQL_TAINT | self::SHELL_TAINT | self::SERIALIZE_TAINT |
		self::PATH_TAINT | self::CODE_TAINT | self::REGEX_TAINT | self::CUSTOM1_TAINT | self::CUSTOM2_TAINT;
	public const EXEC_TAINT = self::YES_TAINT << 1;
	// @phan-suppress-next-line PhanUnreferencedPublicClassConstant
	public const YES_EXEC_TAINT = self::YES_TAINT | self::EXEC_TAINT;

	// ALL taint is YES + special purpose taints, but not including special flags.
	public const ALL_TAINT = self::YES_TAINT | self::SQL_NUMKEY_TAINT | self::ESCAPED_TAINT;
	public const ALL_EXEC_TAINT =
		self::EXEC_TAINT | self::SQL_NUMKEY_EXEC_TAINT | self::ESCAPED_EXEC_TAINT;
	public const ALL_YES_EXEC_TAINT = self::ALL_TAINT | self::ALL_EXEC_TAINT;

	// Taints that support backpropagation.
	public const BACKPROP_TAINTS = self::ALL_EXEC_TAINT;

	public const ESCAPES_HTML = ( self::YES_TAINT & ~self::HTML_TAINT ) | self::ESCAPED_EXEC_TAINT;

	// As the name would suggest, this must include *ALL* possible taint flags.
	public const ALL_TAINT_FLAGS = self::ALL_YES_EXEC_TAINT | self::FUNCTION_FLAGS |
		self::UNKNOWN_TAINT | self::PRESERVE_TAINT | self::VARIADIC_PARAM;

	/**
	 * Used to print taint debug data, see BlockAnalysisVisitor::PHAN_DEBUG_VAR_REGEX
	 */
	private const DEBUG_TAINTEDNESS_REGEXP =
		'/@phan-debug-var-taintedness\s+\$(' . Builder::WORD_REGEX . '(,\s*\$' . Builder::WORD_REGEX . ')*)/';
	// @phan-suppress-previous-line PhanAccessClassConstantInternal It's just perfect for use here

	public const PARAM_ANNOTATION_REGEX =
		'/@param-taint\s+&?(?P<variadic>\.\.\.)?\$(?P<paramname>\S+)\s+(?P<taint>.*)$/';

	/**
	 * @var self Passed to the visitor for context
	 */
	public static $pluginInstance;

	/**
	 * @var array<array<FunctionTaintedness|MethodLinks>> Cache of parsed docblocks. This is declared here (as opposed
	 *  to the BaseVisitor) so that PHPUnit can snapshot and restore it.
	 * @phan-var array<array{0:FunctionTaintedness,1:MethodLinks}>
	 */
	public static $docblockCache = [];

	/** @var FunctionTaintedness[] Cache of taintedness of builtin functions */
	private static $builtinFuncTaintCache = [];

	/**
	 * Save the subclass instance to make it accessible from the visitor
	 */
	public function __construct() {
		$this->assertRequiredConfig();
		self::$pluginInstance = $this;
	}

	/**
	 * Ensure that the options we need are enabled.
	 */
	private function assertRequiredConfig(): void {
		if ( Config::get_quick_mode() ) {
			throw new RuntimeException( 'Quick mode must be disabled to run taint-check' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getMergeVariableInfoClosure(): Closure {
		/**
		 * For branches that are not guaranteed to be executed, merge taint info for any involved
		 * variable across all branches.
		 *
		 * @note This method is HOT, so keep it optimized
		 *
		 * @param Variable $variable
		 * @param Scope[] $scopeList
		 * @param bool $varExistsInAllScopes @phan-unused-param
		 * @suppress PhanUnreferencedClosure, PhanUndeclaredProperty, UnusedSuppression
		 */
		return static function ( Variable $variable, array $scopeList, bool $varExistsInAllScopes ): void {
			$varName = $variable->getName();

			$vars = [];
			$firstVar = null;
			foreach ( $scopeList as $scope ) {
				$localVar = $scope->getVariableByNameOrNull( $varName );
				if ( $localVar ) {
					if ( !$firstVar ) {
						$firstVar = $localVar;
					} else {
						$vars[] = $localVar;
					}
				}
			}

			if ( !$firstVar ) {
				return;
			}

			/** @var Taintedness $taintedness */
			$taintedness = $prevTaint = $firstVar->taintedness ?? null;
			/** @var MethodLinks $methodLinks */
			$methodLinks = $prevLinks = $firstVar->taintedMethodLinks ?? null;
			/** @var CausedByLines $error */
			$error = $prevErr = $firstVar->taintedOriginalError ?? null;

			foreach ( $vars as $localVar ) {
				// Below we only merge data if it's non-null in the current scope and different from the previous
				// branch. Using arrays to save all previous values and then in_array seems useless on MW core,
				// since >99% cases of duplication are already covered by these simple checks.

				$taintOrNull = $localVar->taintedness ?? null;
				if ( $taintOrNull && $taintOrNull !== $prevTaint ) {
					$prevTaint = $taintOrNull;
					if ( $taintedness ) {
						$taintedness = $taintedness->asMergedWith( $taintOrNull );
					} else {
						$taintedness = $taintOrNull;
					}
				}

				$variableObjLinksOrNull = $localVar->taintedMethodLinks ?? null;
				if ( $variableObjLinksOrNull && $variableObjLinksOrNull !== $prevLinks ) {
					$prevLinks = $variableObjLinksOrNull;
					if ( $methodLinks ) {
						$methodLinks = $methodLinks->asMergedWith( $variableObjLinksOrNull );
					} else {
						$methodLinks = $variableObjLinksOrNull;
					}
				}

				$varErrorOrNull = $localVar->taintedOriginalError ?? null;
				if ( $varErrorOrNull && $varErrorOrNull !== $prevErr ) {
					$prevErr = $varErrorOrNull;
					if ( $error ) {
						$error = $error->asMergedWith( $varErrorOrNull );
					} else {
						$error = $varErrorOrNull;
					}
				}
			}

			if ( $taintedness ) {
				self::setTaintednessRaw( $variable, $taintedness );
			}
			if ( $methodLinks ) {
				self::setMethodLinks( $variable, $methodLinks );
			}
			if ( $error ) {
				self::setCausedByRaw( $variable, $error );
			}
		};
	}

	/**
	 * Print the taintedness of a variable, when requested
	 * @see BlockAnalysisVisitor::analyzeSubstituteVarAssert()
	 * @inheritDoc
	 * @suppress PhanUndeclaredProperty, UnusedSuppression
	 */
	public function analyzeStringLiteralStatement( CodeBase $codeBase, Context $context, string $statement ): bool {
		$found = false;
		if ( preg_match_all( self::DEBUG_TAINTEDNESS_REGEXP, $statement, $matches, PREG_SET_ORDER ) ) {
			$scope = $context->getScope();
			foreach ( $matches as $group ) {
				foreach ( explode( ',', $group[1] ) as $rawVar ) {
					$varName = ltrim( trim( $rawVar ), '$' );
					if ( $scope->hasVariableWithName( $varName ) ) {
						$var = $scope->getVariableByName( $varName );
						$taintOrNull = self::getTaintednessRaw( $var );
						$taint = $taintOrNull ? $taintOrNull->toShortString() : 'unset';
						$msg = "Variable {CODE} has taintedness: {DETAILS}";
						$params = [ "\$$varName", $taint ];
					} else {
						$msg = "Variable {CODE} doesn't exist in scope";
						$params = [ "\$$varName" ];
					}
					self::emitIssue(
						$codeBase,
						$context,
						'SecurityCheckDebugTaintedness',
						$msg,
						$params
					);
					$found = true;
				}
			}
		} elseif ( str_contains( $statement, '@taint-check-debug-method-first-arg' ) ) {
			// FIXME This is a hack. The annotation is INTERNAL, for use only in the backpropoffsets-blowup
			// test. We should either find a better way to test that, or maybe add a public annotation
			// for debugging taintedness of a method (probably unreadable on a single line).
			$funcName = preg_replace( '/@taint-check-debug-method-first-arg ([a-z0-9:]+)\b.*/i', '$1', $statement );
			// Let any exception bubble up here, the annotation is for internal use in testing
			$fqsen = FullyQualifiedMethodName::fromStringInContext( $funcName, $context );
			$method = $codeBase->getMethodByFQSEN( $fqsen );
			/** @var FunctionTaintedness|null $fTaint */
			$fTaint = $method->funcTaint ?? null;
			if ( !$fTaint ) {
				return false;
			}
			self::emitIssue(
				$codeBase,
				$context,
				'SecurityCheckDebugTaintedness',
				"Method {CODE} has first param with taintedness: {DETAILS}",
				[ $funcName, $fTaint->getParamSinkTaint( 0 )->toShortString() ]
			);
			return true;
		}
		return $found;
	}

	/**
	 * Get a string representation of a taint integer
	 *
	 * The prefix ~ means all input taints except the letter given.
	 * The prefix * means the EXEC version of the taint.
	 */
	public static function taintToString( int $taint ): string {
		if ( $taint === self::NO_TAINT ) {
			return 'NONE';
		}

		// Note, order matters here.
		static $mapping = [
			self::UNKNOWN_TAINT => 'UNKNOWN',
			self::PRESERVE_TAINT => 'PRESERVE',
			self::ALL_TAINT => 'ALL',
			self::YES_TAINT => 'YES',
			self::YES_TAINT &
			( ~self::HTML_TAINT ) => '~HTML',
			self::YES_TAINT &
			( ~self::SQL_TAINT ) => '~SQL',
			self::YES_TAINT &
			( ~self::SHELL_TAINT ) => '~SHELL',
			self::YES_TAINT &
			( ~self::SERIALIZE_TAINT ) => '~SERIALIZE',
			self::YES_TAINT &
			( ~self::CUSTOM1_TAINT ) => '~CUSTOM1',
			self::YES_TAINT &
			( ~self::CUSTOM2_TAINT ) => '~CUSTOM2',
			// We skip ~ versions of flags which shouldn't be possible.
			self::HTML_TAINT => 'HTML',
			self::SQL_TAINT => 'SQL',
			self::SHELL_TAINT => 'SHELL',
			self::ESCAPED_TAINT => 'ESCAPED',
			self::SERIALIZE_TAINT => 'SERIALIZE',
			self::CUSTOM1_TAINT => 'CUSTOM1',
			self::CUSTOM2_TAINT => 'CUSTOM2',
			self::CODE_TAINT => 'CODE',
			self::PATH_TAINT => 'PATH',
			self::REGEX_TAINT => 'REGEX',
			self::SQL_NUMKEY_TAINT => 'SQL_NUMKEY',
			self::ARRAY_OK => 'ARRAY_OK',
			self::ALL_EXEC_TAINT => '*ALL',
			self::HTML_EXEC_TAINT => '*HTML',
			self::SQL_EXEC_TAINT => '*SQL',
			self::SHELL_EXEC_TAINT => '*SHELL',
			self::ESCAPED_EXEC_TAINT => '*ESCAPED',
			self::SERIALIZE_EXEC_TAINT => '*SERIALIZE',
			self::CUSTOM1_EXEC_TAINT => '*CUSTOM1',
			self::CUSTOM2_EXEC_TAINT => '*CUSTOM2',
			self::CODE_EXEC_TAINT => '*CODE',
			self::PATH_EXEC_TAINT => '*PATH',
			self::REGEX_EXEC_TAINT => '*REGEX',
			self::SQL_NUMKEY_EXEC_TAINT => '*SQL_NUMKEY',
		];

		$types = [];
		foreach ( $mapping as $bitmap => $val ) {
			if ( ( $bitmap & $taint ) === $bitmap ) {
				$types[] = $val;
				$taint &= ~$bitmap;
			}
		}
		if ( $taint !== 0 ) {
			$types[] = "Unrecognized: $taint";
		}
		return implode( ', ', $types );
	}

	public function builtinFuncHasTaint( FullyQualifiedFunctionLikeName $fqsen ): bool {
		return $this->getBuiltinFuncTaint( $fqsen ) !== null;
	}

	/**
	 * Get the taintedness of a function
	 *
	 * This allows overriding the default taint of a function
	 *
	 * If you want to provide custom taint hints for your application,
	 * override the getCustomFuncTaints()
	 *
	 * @param FullyQualifiedFunctionLikeName $fqsen The function/method in question
	 * @return FunctionTaintedness|null Null to autodetect taintedness
	 */
	public function getBuiltinFuncTaint( FullyQualifiedFunctionLikeName $fqsen ): ?FunctionTaintedness {
		$name = (string)$fqsen;

		if ( isset( self::$builtinFuncTaintCache[$name] ) ) {
			return self::$builtinFuncTaintCache[$name];
		}

		static $funcTaints = null;
		if ( $funcTaints === null ) {
			$funcTaints = $this->getCustomFuncTaints() + $this->getPHPFuncTaints();
		}

		if ( isset( $funcTaints[$name] ) ) {
			$rawFuncTaint = $funcTaints[$name];
			if ( $rawFuncTaint instanceof FunctionTaintedness ) {
				$funcTaint = $rawFuncTaint;
			} else {
				self::assertFunctionTaintArrayWellFormed( $rawFuncTaint );
				// Note: for backcompat, we set NO_OVERRIDE everywhere.
				$overallFlags = ( $rawFuncTaint['overall'] & self::FUNCTION_FLAGS ) | self::NO_OVERRIDE;
				$funcTaint = new FunctionTaintedness(
					new Taintedness( $rawFuncTaint['overall'] & ~$overallFlags ),
					$overallFlags
				);
				unset( $rawFuncTaint['overall'] );
				foreach ( $rawFuncTaint as $i => $val ) {
					assert( ( $val & self::UNKNOWN_TAINT ) === 0, 'Cannot set UNKNOWN' );
					$paramFlags = ( $val & self::FUNCTION_FLAGS ) | self::NO_OVERRIDE;
					// TODO Split sink and preserve in the hardcoded arrays
					if ( $val & self::VARIADIC_PARAM ) {
						$pTaint = new Taintedness( $val & ~( self::VARIADIC_PARAM | $paramFlags ) );
						$funcTaint = $funcTaint
							->withVariadicParamSinkTaint( $i, $pTaint->withOnly( self::ALL_EXEC_TAINT ), $paramFlags )
							->withVariadicParamPreservedTaint(
								$i,
								$pTaint->without( self::ALL_EXEC_TAINT )->asPreservedTaintedness()
							);
					} else {
						$pTaint = new Taintedness( $val & ~$paramFlags );
						$funcTaint = $funcTaint
							->withParamSinkTaint( $i, $pTaint->withOnly( self::ALL_EXEC_TAINT ), $paramFlags )
							->withParamPreservedTaint(
								$i,
								$pTaint->without( self::ALL_EXEC_TAINT )->asPreservedTaintedness()
							);
					}
				}
			}
			self::$builtinFuncTaintCache[$name] = $funcTaint;
			return self::$builtinFuncTaintCache[$name];
		}
		return null;
	}

	/**
	 * Assert that a taintedness array is well-formed, and fail hard if it isn't.
	 *
	 * @param int[] $taint
	 */
	private static function assertFunctionTaintArrayWellFormed( array $taint ): void {
		if ( !isset( $taint['overall'] ) ) {
			throw new LogicException( 'Overall taint must be set' );
		}

		foreach ( $taint as $i => $t ) {
			if ( !is_int( $i ) && $i !== 'overall' ) {
				throw new LogicException( "Taint indexes must be int or 'overall', got '$i'" );
			}
			if ( !is_int( $t ) || ( $t & ~self::ALL_TAINT_FLAGS ) ) {
				throw new LogicException( "Wrong taint index $i, got: " . var_export( $t, true ) );
			}
			if ( $t & ~self::ALL_TAINT_FLAGS ) {
				throw new LogicException( "Taint index $i has unknown flags: " . decbin( $t ) );
			}
		}
	}

	/**
	 * Get an array of function taints custom for the application
	 *
	 * @return array<string,int[]|FunctionTaintedness> Array of function taints. The keys are FQSENs. The values can be
	 *   either FunctionTaintedness objects, or arrays with 'overall' string key and numeric keys for parameters.
	 *
	 *   For example: [ self::YES_TAINT, 'overall' => self::NO_TAINT ]
	 *   means that the taint of the return value is the same as the taint
	 *   of the first arg, and all other args are ignored.
	 *   [ self::HTML_EXEC_TAINT, 'overall' => self::NO_TAINT ]
	 *   Means that the first arg is output in an html context (e.g. like echo)
	 *   [ self::YES_TAINT & ~self::HTML_TAINT, 'overall' => self::NO_TAINT ]
	 *   Means that the function removes html taint (escapes) e.g. htmlspecialchars
	 *   [ 'overall' => self::YES_TAINT ]
	 *   Means that it returns a tainted value (e.g. return $_POST['foo']; )
	 * @see FunctionTaintedness for more details
	 */
	abstract protected function getCustomFuncTaints(): array;

	/**
	 * Can be used to force specific issues to be marked false positives
	 *
	 * For example, a specific application might be able to recognize
	 * that we are in a CLI context, and thus the XSS is really a false positive.
	 *
	 * @param int $combinedTaint Combined and adjusted taint of LHS+RHS
	 * @param string &$msg Issue description (so plugin can modify to state why false)
	 * @param Context $context
	 * @param CodeBase $code_base
	 * @return bool Is this a false positive?
	 * @suppress PhanUnusedPublicMethodParameter No param is used
	 */
	public function isFalsePositive(
		int $combinedTaint,
		string &$msg,
		Context $context,
		CodeBase $code_base
	): bool {
		return false;
	}

	/**
	 * Given a param description line, extract taint
	 *
	 * This is to allow putting taint information in method docblocks.
	 * If a function has a docblock comment like:
	 *  *  @param-taint $foo escapes_html
	 * This converts that line into:
	 *   ( self::YES_TAINT & ~self::SQL_TAINT )
	 * Multiple taint types are separated by commas
	 * (which are interpreted as bitwise OR ( "|" ). Future versions
	 * might support more complex bitwise operators, but for now it
	 * doesn't seem needed.
	 *
	 * The following keywords are supported where {type} can be
	 * html, sql, shell, serialize, custom1, custom2, sql_numkey,
	 * escaped.
	 *  * {type} - just set the flag. 99% you should only use 'none' or 'tainted'
	 *  * exec_{type} - sets the exec flag.
	 *  * escapes_{type} - self::YES_TAINT & ~self::{type}_TAINT.
	 *     Note: escapes_html adds the exec_escaped flag, use
	 *     escapes_htmlnoent if the value is safe to double encode.
	 *  * onlysafefor_{type}
	 *     Same as above, intended for return type declarations.
	 *     Only difference is that onlysafefor_html sets ESCAPED_TAINT instead
	 *     of ESCAPED_EXEC_TAINT
	 *  * none - self::NO_TAINT
	 *  * tainted - self::YES_TAINT
	 *  * array_ok - sets self::ARRAY_OK
	 *  * allow_override - Allow autodetected taints to override annotation
	 *
	 * @todo What about ~ operator?
	 * @note The special casing to have escapes_html always add exec_escaped
	 *   (and having htmlnoent exist) is "experimental" and may change in
	 *   future versions (Maybe all types should set exec_escaped. Maybe it
	 *   should be explicit)
	 * @note Excluding UNKNOWN here on purpose, as if we're setting it, it's not unknown
	 * @param string $line A line from the docblock
	 * @return array|null Array of [taintedness, flags], or null on no info
	 * @phan-return array{0:Taintedness,1:int}|null
	 */
	public static function parseTaintLine( string $line ): ?array {
		$types = '(?P<type>htmlnoent|html|sql|shell|serialize|custom1|'
			. 'custom2|code|path|regex|sql_numkey|escaped|none|tainted)';
		$prefixes = '(?P<prefix>escapes|onlysafefor|exec)';
		$taintExpr = "(?P<taint>(?:{$prefixes}_)?$types|array_ok|allow_override)";

		$filteredLine = preg_replace( "/((?:$taintExpr,? *)+)(?: .*)?$/", '$1', $line );
		$taints = explode( ',', strtolower( $filteredLine ) );

		$overallTaint = Taintedness::safeSingleton();
		$overallFlags = self::NO_OVERRIDE;
		$numberOfTaintsProcessed = 0;
		foreach ( $taints as $taint ) {
			$taintParts = [];
			if ( !preg_match( "/^$taintExpr$/", trim( $taint ), $taintParts ) ) {
				continue;
			}
			$numberOfTaintsProcessed++;
			if ( $taintParts['taint'] === 'array_ok' ) {
				$overallFlags |= self::ARRAY_OK;
				continue;
			}
			if ( $taintParts['taint'] === 'allow_override' ) {
				$overallFlags &= ~self::NO_OVERRIDE;
				continue;
			}
			$taintAsInt = self::convertTaintNameToConstant( $taintParts['type'] );
			switch ( $taintParts['prefix'] ) {
				case '':
					$overallTaint = $overallTaint->with( $taintAsInt );
					break;
				case 'exec':
					$overallTaint = $overallTaint->with( Taintedness::flagsAsYesToExecTaint( $taintAsInt ) );
					break;
				case 'escapes':
				case 'onlysafefor':
					$overallTaint = $overallTaint->with( self::YES_TAINT & ~$taintAsInt );
					if ( $taintParts['type'] === 'html' ) {
						if ( $taintParts['prefix'] === 'escapes' ) {
							$overallTaint = $overallTaint->with( self::ESCAPED_EXEC_TAINT );
						} else {
							$overallTaint = $overallTaint->with( self::ESCAPED_TAINT );
						}
					}
					break;
			}
		}
		if ( $numberOfTaintsProcessed === 0 ) {
			return null;
		}
		return [ $overallTaint, $overallFlags ];
	}

	/**
	 * Hook to override the sink taintedness of a method parameter depending on the current argument.
	 *
	 * @internal This method is unstable and may be removed without prior notice.
	 *
	 * @param Taintedness $paramSinkTaint
	 * @param Taintedness $curArgTaintedness
	 * @param Node $argument Note: This hook is not called on literals
	 * @param int $argIndex Which argument number is this
	 * @param FunctionInterface $func The function/method being called
	 * @param FunctionTaintedness $funcTaint Taint of method formal parameters
	 * @param CausedByLines $paramSinkError
	 * @param Context $context Context object
	 * @param CodeBase $code_base CodeBase object
	 * @return array<Taintedness|CausedByLines> The taint and caused-by lines to use for actual parameter
	 * @phan-return array{0:Taintedness,1:CausedByLines}
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function modifyParamSinkTaint(
		Taintedness $paramSinkTaint,
		Taintedness $curArgTaintedness,
		Node $argument,
		int $argIndex,
		FunctionInterface $func,
		FunctionTaintedness $funcTaint,
		CausedByLines $paramSinkError,
		Context $context,
		CodeBase $code_base
	): array {
		// no-op
		return [ $paramSinkTaint, $paramSinkError ];
	}

	/**
	 * Hook to override how taint of an argument to method call is calculated
	 *
	 * @param Taintedness $curArgTaintedness
	 * @param Node $argument Note: This hook is not called on literals
	 * @param int $argIndex Which argument number is this
	 * @param FunctionInterface $func The function/method being called
	 * @param FunctionTaintedness $funcTaint Taint of method formal parameters
	 * @param Context $context Context object
	 * @param CodeBase $code_base CodeBase object
	 * @return Taintedness The taint to use for actual parameter
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function modifyArgTaint(
		Taintedness $curArgTaintedness,
		Node $argument,
		int $argIndex,
		FunctionInterface $func,
		FunctionTaintedness $funcTaint,
		Context $context,
		CodeBase $code_base
	): Taintedness {
		// no-op
		return $curArgTaintedness;
	}

	/**
	 * Convert a string like 'html' to self::HTML_TAINT.
	 *
	 * @note htmlnoent treated like self::HTML_TAINT.
	 * @param string $name one of:
	 *   html, sql, shell, serialize, custom1, custom2, code, path, regex, sql_numkey,
	 *   escaped, none (= self::NO_TAINT), tainted (= self::YES_TAINT)
	 * @return int One of the TAINT constants
	 */
	public static function convertTaintNameToConstant( string $name ): int {
		return match ( $name ) {
			'html', 'htmlnoent' => self::HTML_TAINT,
			'sql' => self::SQL_TAINT,
			'shell' => self::SHELL_TAINT,
			'serialize' => self::SERIALIZE_TAINT,
			'custom1' => self::CUSTOM1_TAINT,
			'custom2' => self::CUSTOM2_TAINT,
			'code' => self::CODE_TAINT,
			'path' => self::PATH_TAINT,
			'regex' => self::REGEX_TAINT,
			'sql_numkey' => self::SQL_NUMKEY_TAINT,
			'escaped' => self::ESCAPED_TAINT,
			'tainted' => self::YES_TAINT,
			'none' => self::NO_TAINT,
			// @codeCoverageIgnoreStart
			default => throw new InvalidArgumentException( "$name not valid taint" )
			// @codeCoverageIgnoreEnd
		};
	}

	/**
	 * Taints for builtin php functions
	 *
	 * @return int[][] List of func taints (See getBuiltinFuncTaint())
	 * @phan-return array<string,int[]>
	 */
	private function getPHPFuncTaints(): array {
		$pregMatchTaint = [
			self::REGEX_EXEC_TAINT,
			self::YES_TAINT,
			// TODO: Possibly unsafe pass-by-ref
			self::NO_TAINT,
			self::NO_TAINT,
			self::NO_TAINT,
			'overall' => self::NO_TAINT,
		];
		$pregReplaceTaint = [
			self::REGEX_EXEC_TAINT,
			// TODO: This is used for strings (in preg_replace) and callbacks (in preg_replace_callback)
			self::YES_TAINT,
			self::YES_TAINT,
			self::NO_TAINT,
			self::NO_TAINT,
			'overall' => self::NO_TAINT
		];
		return [
			'\htmlentities' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\htmlspecialchars' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\escapeshellarg' => [
				~self::SHELL_TAINT & self::YES_TAINT,
				'overall' => self::NO_TAINT
			],
			// TODO: Perhaps we should distinguish arguments escape vs command escape
			'\escapeshellcmd' => [
				~self::SHELL_TAINT & self::YES_TAINT,
				'overall' => self::NO_TAINT
			],
			'\shell_exec' => [
				self::SHELL_EXEC_TAINT,
				'overall' => self::YES_TAINT
			],
			'\passthru' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\exec' => [
				self::SHELL_EXEC_TAINT,
				// TODO: This is an unsafe passbyref
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::YES_TAINT
			],
			'\system' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::YES_TAINT
			],
			'\proc_open' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				// TODO: Unsafe passbyref
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				// TODO: Perhaps not so safe
				'overall' => self::NO_TAINT
			],
			'\popen' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				// TODO: Perhaps not so safe
				'overall' => self::NO_TAINT
			],
			// Or any time the serialized data comes from a trusted source.
			'\serialize' => [
				'overall' => self::YES_TAINT & ~self::SERIALIZE_TAINT,
			],
			'\unserialize' => [
				self::SERIALIZE_EXEC_TAINT,
				'overall' => self::NO_TAINT,
			],
			'\mysql_query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli::query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_real_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli::real_query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_fetch_all' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_result::fetch' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_fetch_array' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_result::fetch_array' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_fetch_assoc' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_result::fetch_assoc' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_fetch_column' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_result::fetch_column' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_fetch_object' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_result::fetch_object' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_fetch_row' => [
				'overall' => self::YES_TAINT,
			],
			'\mysqli_result::fetch_row' => [
				'overall' => self::YES_TAINT,
			],
			'\sqlite_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\sqlite_single_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			// Note: addslashes, addcslashes etc. intentionally omitted because they're not
			// enough to avoid SQLi.
			'\mysqli_escape_string' => [
				self::NO_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli_real_escape_string' => [
				self::NO_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli::escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli::real_escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\sqlite_escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\PDO::query' => [
				self::SQL_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\PDO::prepare' => [
				self::SQL_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\PDO::exec' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\base64_encode' => [
				self::YES_TAINT & ~self::HTML_TAINT,
				'overall' => self::NO_TAINT
			],
			'\file_put_contents' => [
				self::PATH_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			// TODO: What about file_get_contents() and file() ?
			'\fopen' => [
				self::PATH_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				// TODO: Perhaps not so safe
				'overall' => self::NO_TAINT
			],
			'\opendir' => [
				self::PATH_EXEC_TAINT,
				self::NO_TAINT,
				// TODO: Perhaps not so safe
				'overall' => self::NO_TAINT
			],
			'\rawurlencode' => [
				self::YES_TAINT & ~self::PATH_TAINT,
				'overall' => self::NO_TAINT
			],
			'\urlencode' => [
				self::YES_TAINT & ~self::PATH_TAINT,
				'overall' => self::NO_TAINT
			],
			'\printf' => [
				self::HTML_EXEC_TAINT,
				// TODO We could check if the respective specifiers are safe
				self::HTML_EXEC_TAINT | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\preg_filter' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_grep' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_match_all' => $pregMatchTaint,
			'\preg_match' => $pregMatchTaint,
			'\preg_quote' => [
				self::YES_TAINT & ~self::REGEX_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_replace' => $pregReplaceTaint,
			'\preg_replace_callback' => $pregReplaceTaint,
			'\preg_replace_callback_array' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_split' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			// We assume that hashing functions are safe, see T272492
			'\md5' => [
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\sha1' => [
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\crc32' => [
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\hash' => [
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\hash_hmac' => [
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			// exit() and die() became ordinary functions in PHP 8.4
			'\exit' => [
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\die' => [
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getBeforeLoopBodyAnalysisVisitorClassName(): string {
		return TaintednessLoopVisitor::class;
	}

	/**
	 * Clear caches for testing.
	 * @suppress PhanUnreferencedPublicMethod Used in tests (not analyzed by phan)
	 */
	public static function clearCaches(): void {
		self::$docblockCache = [];
		TaintednessVisitor::$fqsensWithoutToStringCache = [];
	}
}
