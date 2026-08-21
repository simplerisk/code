<?php declare( strict_types = 1 );

/**
 * @license GPL-2.0-or-later
 */
namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\InvalidFQSENException;
use Phan\Exception\IssueException;
use Phan\Language\Element\ClassAliasRecord;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use UnexpectedValueException;
use const ast\AST_METHOD_CALL;

/**
 * MediaWiki specific node visitor
 */
class MWVisitor extends TaintednessVisitor {
	/**
	 * @todo This is a temporary hack. Proper solution is refactoring/avoiding overrideContext
	 * @var bool|null
	 * @suppress PhanWriteOnlyProtectedProperty
	 */
	protected $isHook;

	/**
	 * Try and recognize hook registration
	 * @inheritDoc
	 */
	protected function analyzeCallNode( Node $node, iterable $funcs ): void {
		parent::analyzeCallNode( $node, $funcs );
		if ( !isset( $node->children['method'] ) ) {
			// Called by visitCall
			return;
		}

		assert( is_array( $funcs ) && count( $funcs ) === 1 );
		$method = $funcs[0];
		assert( $method instanceof Method );

		// Should this be getDefiningFQSEN() instead?
		$methodName = (string)$method->getFQSEN();
		// $this->debug( __METHOD__, "Checking to see if we should register $methodName" );
		switch ( $methodName ) {
			case "\\MediaWiki\\Parser\\Parser::setFunctionHook":
			case "\\MediaWiki\\Parser\\Parser::setHook":
				$type = $this->getHookTypeForRegistrationMethod( $methodName );
				if ( $type === null ) {
					break;
				}
				// $this->debug( __METHOD__, "registering $methodName as $type" );
				$this->handleParserHookRegistration( $node, $type );
				break;
			case '\MediaWiki\HookContainer\HookContainer::register':
				$this->handleNormalHookRegistration( $node );
				break;
			case '\MediaWiki\Linker\Linker::makeExternalLink':
				$this->checkExternalLink( $node );
				break;
			default:
				if ( str_starts_with( $method->getName(), 'on' ) ) {
					$this->maybeTriggerHook( $node, $method );
				}
				$this->doSelectWrapperSpecialHandling( $node, $method );
		}
	}

	/**
	 * MediaWiki\Linker\Linker::makeExternalLink escaping depends on third argument
	 */
	private function checkExternalLink( Node $node ): void {
		$escapeArg = $this->resolveValue( $node->children['args']->children[2] ?? true );
		$text = $node->children['args']->children[1] ?? null;
		if ( !$escapeArg && $text instanceof Node ) {
			$this->maybeEmitIssueSimplified(
				new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT ),
				$text,
				"Calling Linker::makeExternalLink with user controlled text " .
				"and third argument set to false"
			);
		}
	}

	/**
	 * Special casing for complex format of IReadableDatabase::select
	 *
	 * This handles the $options, and $join_cond. Other args are
	 * handled through normal means
	 *
	 * @param Node $node Either an AST_METHOD_CALL or AST_STATIC_CALL
	 * @param Method $method
	 */
	private function doSelectWrapperSpecialHandling( Node $node, Method $method ): void {
		static $relevantMethods;
		if ( !$relevantMethods ) {
			$makeFQSEN = [ FullyQualifiedClassName::class, 'fromFullyQualifiedString' ];
			$relevantMethods = [
				'makeList' => $makeFQSEN( '\\Wikimedia\\Rdbms\\Platform\\ISQLPlatform' ),
				'select' => $makeFQSEN( '\\Wikimedia\\Rdbms\\IReadableDatabase' ),
				'selectField' => $makeFQSEN( '\\Wikimedia\\Rdbms\\IReadableDatabase' ),
				'selectFieldValues' => $makeFQSEN( '\\Wikimedia\\Rdbms\\IReadableDatabase' ),
				'selectSQLText' => $makeFQSEN( '\\Wikimedia\\Rdbms\\Platform\\ISQLPlatform' ),
				'selectRowCount' => $makeFQSEN( '\\Wikimedia\\Rdbms\\IReadableDatabase' ),
				'selectRow' => $makeFQSEN( '\\Wikimedia\\Rdbms\\IReadableDatabase' ),
				'option' => $makeFQSEN( '\\Wikimedia\\Rdbms\\SelectQueryBuilder' ),
				'options' => $makeFQSEN( '\\Wikimedia\\Rdbms\\SelectQueryBuilder' ),
				'joinConds' => $makeFQSEN( '\\Wikimedia\\Rdbms\\SelectQueryBuilder' ),
			];
		}

		$name = $method->getName();
		if ( !isset( $relevantMethods[$name] ) ) {
			return;
		}

		if ( !self::isSubclassOf( $method->getClassFQSEN(), $relevantMethods[$name], $this->code_base ) ) {
			return;
		}

		$args = $node->children['args']->children;
		switch ( $name ) {
			case 'select':
			case 'selectField':
			case 'selectFieldValues':
			case 'selectSQLText':
			case 'selectRowCount':
			case 'selectRow':
				if ( isset( $args[4] ) ) {
					$this->checkSQLOptions( $args[4] );
				}
				if ( isset( $args[5] ) ) {
					$this->checkJoinConds( $args[5] );
				}
				return;
			case 'option':
				if ( count( $args ) >= 2 ) {
					$this->checkSQLOption( $args[0], $args[1], $node );
				}
				return;
			case 'options':
				if ( $args ) {
					$this->checkSQLOptions( $args[0] );
				}
				return;
			case 'joinConds':
				if ( $args ) {
					$this->checkJoinConds( $args[0] );
				}
				return;
			case 'makeList':
				$this->checkMakeList( $node );
				return;
			default:
				throw new UnexpectedValueException( "Should be unreachable, got $name" );
		}
	}

	/**
	 * Check if we are running a hook (i.e., calling a hook method on a HookRunner interface).
	 */
	private function maybeTriggerHook( Node $node, FunctionInterface $method ): void {
		if ( $node->kind !== AST_METHOD_CALL || !$method instanceof Method ) {
			return;
		}

		try {
			$implementedInterfaces = $method->getClass( $this->code_base )->getInterfaceFQSENList();
		} catch ( CodeBaseException $e ) {
			$this->debug( __METHOD__, "Class not found for method $method: " . $this->getDebugInfo( $e ) );
			return;
		}

		// We assume that for a hook called Foo, the interface is called FooHook and the handler onFoo, and that the
		// hook runner (but not necessarily the handler) implements the hook interface.
		$hookInterfaceName = preg_replace( '/^on/', '', $method->getName() ) . 'Hook';
		$foundHookInterface = false;
		foreach ( $implementedInterfaces as $implementedInterfaceFQSEN ) {
			if ( $implementedInterfaceFQSEN->getName() === $hookInterfaceName ) {
				$foundHookInterface = true;
				break;
			}
		}

		if ( !$foundHookInterface ) {
			return;
		}

		$args = $node->children['args']->children;

		$hasPassByRef = self::hasPassByReferenceParameter( $method );
		$analyzer = new PostOrderAnalysisVisitor( $this->code_base, $this->context, [] );
		$argumentTypes = array_fill( 0, count( $args ), UnionType::empty() );

		$subscribers = MediaWikiHooksHelper::getInstance()->getHookSubscribers( $method->getName() );
		foreach ( $subscribers as $subscriber ) {
			if ( $subscriber instanceof FullyQualifiedMethodName ) {
				if ( !$this->code_base->hasMethodWithFQSEN( $subscriber ) ) {
					$this->debug( __METHOD__, "Hook subscriber $subscriber not found!" );
					continue;
				}
				$func = $this->code_base->getMethodByFQSEN( $subscriber );
			} else {
				assert( $subscriber instanceof FullyQualifiedFunctionName );
				if ( !$this->code_base->hasFunctionWithFQSEN( $subscriber ) ) {
					$this->debug( __METHOD__, "Hook subscriber $subscriber not found!" );
					continue;
				}
				$func = $this->code_base->getFunctionByFQSEN( $subscriber );
			}

			// $this->debug( __METHOD__, "Dispatching $hookName to $subscriber" );
			// This is hacky, but try to ensure that the associated line
			// number for any issues is in the extension, and not the
			// line where the HookContainer::register() is in MW core.
			// FIXME: In the case of reference parameters, this is
			// still reporting things being in MW core instead of extension.
			$oldContext = $this->overrideContext;
			$fContext = $func->getContext();
			$newContext = clone $this->context;
			$newContext = $newContext->withFile( $fContext->getFile() )
				->withLineNumberStart( $fContext->getLineNumberStart() );
			$this->overrideContext = $newContext;
			$this->isHook = true;

			if ( $hasPassByRef ) {
				// Trigger an analysis of the function call (see e.g. ClosureReturnTypeOverridePlugin's
				// handling of call_user_func_array). Note that it's not enough to use our
				// handleMethodCall, because that doesn't handle references correctly.

				// NOTE: This is only known to be necessary with references, hence the check above
				// (for performance). There might be other edge cases, though...

				// TODO We don't care about types, so we use an empty union type. However this looks
				// very very fragile.
				// TODO 2: Someday we could write a generic-purpose MW plugin, which could (among other
				// things) understand hook. It could share some code with taint-check, and at that
				// point we'd likely want to use the correct types here.
				$analyzer->analyzeCallableWithArgumentTypes( $argumentTypes, $func, $args );
			}
			$this->handleMethodCall( $func, $subscriber, $args, false, true );

			$this->overrideContext = $oldContext;
			$this->isHook = false;
		}
	}

	/**
	 * Check whether a function takes a parameter by reference (copy of
	 * {@link \Phan\Language\Element\FunctionTrait::hasPassByReferenceVariable()})
	 */
	private static function hasPassByReferenceParameter( FunctionInterface $func ): bool {
		foreach ( $func->getParameterList() as $param ) {
			if ( $param->isPassByReference() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $method The method name of the registration function
	 * @return string|null The name of the hook that gets registered
	 */
	private function getHookTypeForRegistrationMethod( string $method ): ?string {
		switch ( $method ) {
			case "\\MediaWiki\\Parser\\Parser::setFunctionHook":
				return '!ParserFunctionHook';
			case "\\MediaWiki\\Parser\\Parser::setHook":
				return '!ParserHook';
			default:
				$this->debug( __METHOD__, "$method not a hook registerer" );
				return null;
		}
	}

	/**
	 * Handle registering a normal hook from HookContainer::register (Not from $wgHooks)
	 *
	 * @param Node $node The node representing the AST_METHOD_CALL
	 */
	private function handleNormalHookRegistration( Node $node ): void {
		assert( $node->kind === \ast\AST_METHOD_CALL );
		$params = $node->children['args']->children;
		if ( count( $params ) < 2 ) {
			$this->debug( __METHOD__, "Could not understand HookContainer::register" );
			return;
		}
		$hookName = $params[0];
		if ( !is_string( $hookName ) ) {
			$this->debug( __METHOD__, "Could not register hook. Name is complex" );
			return;
		}
		$cb = $this->getCallableFromHookRegistration( $params[1], $hookName );
		if ( $cb ) {
			$this->registerHook( $hookName, $cb );
		} else {
			$this->debug( __METHOD__, "Could not register $hookName hook due to complex callback" );
		}
	}

	/**
	 * When someone calls $parser->setFunctionHook() or setTagHook()
	 *
	 * @note Causes phan to error out if given non-existent class
	 * @param Node $node The AST_METHOD_CALL node
	 * @param string $hookType The name of the hook
	 */
	private function handleParserHookRegistration( Node $node, string $hookType ): void {
		$args = $node->children['args']->children;
		if ( count( $args ) < 2 ) {
			return;
		}
		$callback = $this->getCallableFromNode( $args[1] );
		if ( $callback ) {
			$this->registerHook( $hookType, $callback );
		}
	}

	private function registerHook( string $hookType, FunctionInterface $callback ): void {
		$fqsen = $callback->getFQSEN();
		$alreadyRegistered = MediaWikiHooksHelper::getInstance()->registerHook( $hookType, $fqsen );
		if ( !$alreadyRegistered ) {
			// $this->debug( __METHOD__, "registering $fqsen for hook $hookType" );
			// If this is the first time seeing this, make sure we reanalyze the hook function now that
			// we know what it is, in case it's already been analyzed.
			$this->analyzeFunc( $callback );
		}
	}

	/**
	 * For special hooks, check their return value
	 *
	 * e.g. A tag hook's return value is output as html.
	 */
	public function visitReturn( Node $node ): void {
		parent::visitReturn( $node );
		if (
			!$node->children['expr'] instanceof Node ||
			!$this->context->isInFunctionLikeScope()
		) {
			return;
		}
		$funcFQSEN = $this->context->getFunctionLikeFQSEN();
		$funcFQSENStr = $funcFQSEN->__toString();

		if (
			// The one in SelectQueryBuilder is generic, don't bother. The stuff it returns is analyzed separately when
			// we find calls to SelectQueryBuilder methods.
			$funcFQSENStr !== '\\Wikimedia\\Rdbms\\SelectQueryBuilder::getQueryInfo' &&
			str_ends_with( $funcFQSENStr, '::getQueryInfo' )
		) {
			$this->handleGetQueryInfoReturn( $node->children['expr'] );
		}

		$hookType = MediaWikiHooksHelper::getInstance()->isSpecialHookSubscriber( $funcFQSEN );
		switch ( $hookType ) {
			case '!ParserFunctionHook':
				$this->visitReturnOfFunctionHook( $node->children['expr'], $funcFQSEN );
				break;
			case '!ParserHook':
				$ret = $node->children['expr'];
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT ),
					$ret,
					"Outputting user controlled HTML from Parser tag hook {FUNCTIONLIKE}",
					[ $funcFQSEN ]
				);
				break;
		}
	}

	/**
	 * Methods named getQueryInfo() in MediaWiki usually
	 * return an array that is later fed to select
	 *
	 * @note This will only work where the return
	 *  statement is an array literal.
	 * @param Node|mixed $node Node from ast tree
	 */
	private function handleGetQueryInfoReturn( mixed $node ): void {
		if (
			!( $node instanceof Node ) ||
			$node->kind !== \ast\AST_ARRAY
		) {
			return;
		}
		// The argument order is
		// $table, $vars, $conds = '', $fname = __METHOD__,
		// $options = [], $join_conds = []
		$keysToArg = [
			'tables' => 0,
			'fields' => 1,
			'conds' => 2,
			'options' => 4,
			'join_conds' => 5,
		];
		$args = [ '', '', '', '' ];
		foreach ( $node->children as $child ) {
			// Can't have array destructuring in a return statement.
			assert( $child !== null );
			if ( $child->kind === \ast\AST_UNPACK ) {
				// Can't analyze this, skip it.
				continue;
			}
			assert( $child->kind === \ast\AST_ARRAY_ELEM );
			$key = $child->children['key'];
			if ( $key instanceof Node ) {
				// Dynamic name, skip (T268055).
				continue;
			}
			if ( !isset( $keysToArg[$key] ) ) {
				continue;
			}
			$args[$keysToArg[$key]] = $child->children['value'];
		}
		$selectFQSEN = FullyQualifiedMethodName::fromFullyQualifiedString(
			'\Wikimedia\Rdbms\IReadableDatabase::select'
		);
		if ( !$this->code_base->hasMethodWithFQSEN( $selectFQSEN ) ) {
			// Huh. Core wasn't parsed. That's bad, but don't fail hard.
			$this->debug( __METHOD__, 'Database::select does not exist.' );
			return;
		}
		$select = $this->code_base->getMethodByFQSEN( $selectFQSEN );
		// TODO: The message about calling Database::select here is not very clear.
		$this->handleMethodCall( $select, $selectFQSEN, $args, false );
		if ( isset( $args[4] ) ) {
			$this->checkSQLOptions( $args[4] );
		}
		if ( isset( $args[5] ) ) {
			$this->checkJoinConds( $args[5] );
		}
	}

	/**
	 * Check IDatabase::makeList
	 *
	 * Special cased because the second arg totally changes
	 * how this function is interpreted.
	 */
	private function checkMakeList( Node $node ): void {
		$args = $node->children['args'];
		// First determine which IDatabase::LIST_*
		// 0 = IDatabase::LIST_COMMA is default value.
		$typeArg = $args->children[1] ?? 0;
		if ( $typeArg instanceof Node ) {
			$typeArg = $this->getCtxN( $typeArg )->getEquivalentPHPValueForNode(
				$typeArg,
				ContextNode::RESOLVE_SCALAR_DEFAULT & ~ContextNode::RESOLVE_CONSTANTS
			);
		}
		if ( $typeArg instanceof Node ) {
			if ( $typeArg->kind === \ast\AST_CLASS_CONST ) {
				// Probably IDatabase::LIST_*. Note that non-class constants are resolved
				$typeArg = $typeArg->children['const'];
			} elseif ( $typeArg->kind === \ast\AST_CONST ) {
				$typeArg = $typeArg->children['name']->children['name'];
			} else {
				// Something that cannot be resolved statically. Since LIST_NAMES is very rare, and LIST_COMMA is
				// default, assume its LIST_AND or LIST_OR
				$this->debug( __METHOD__, "Could not determine 2nd arg makeList()" );
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ),
					$args->children[0],
					"IDatabase::makeList with unknown type arg is " .
					"given an array with unescaped keynames or " .
					"values for numeric keys (May be false positive)"
				);

				return;
			}
		}

		// Make sure not to mix strings and ints in switch cases, as that will break horribly
		if ( is_int( $typeArg ) ) {
			$typeArg = $this->literalListConstToName( $typeArg );
		}
		switch ( $typeArg ) {
			case 'LIST_COMMA':
				// String keys ignored. Everything escaped. So nothing to worry about.
				break;
			case 'LIST_AND':
			case 'LIST_SET':
			case 'LIST_OR':
				// exec_sql_numkey
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ),
					$args->children[0],
					"IDatabase::makeList with LIST_AND, LIST_OR or "
					. "LIST_SET must sql escape string key names and values of numeric keys"
				);
				break;
			case 'LIST_NAMES':
				// Like comma but with no escaping.
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::SQL_EXEC_TAINT ),
					$args->children[0],
					"IDatabase::makeList with LIST_NAMES needs "
					. "to escape for SQL"
				);
				break;
			default:
				$this->debug( __METHOD__, "Unrecognized 2nd arg " . "to IDatabase::makeList: '$typeArg'" );
		}
	}

	/**
	 * Convert a literal int value for a LIST_* constant to its name. This is a horrible hack for crappy code
	 * that uses the constants literally rather than by name. Such code shouldn't deserve taint analysis.
	 * This method can obviously break very easily if the values are changed.
	 */
	private function literalListConstToName( int $value ): string {
		switch ( $value ) {
			case 0:
				return 'LIST_COMMA';
			case 1:
				return 'LIST_AND';
			case 2:
				return 'LIST_SET';
			case 3:
				return 'LIST_NAMES';
			case 4:
				return 'LIST_OR';
			default:
				// Oh boy, what the heck are you doing? Well, DWIM
				$this->debug(
					__METHOD__,
					'Someone specified a LIST_* constant literally but it is not a valid value. Wow.'
				);
				return 'LIST_AND';
		}
	}

	/**
	 * Check the options parameter to IReadableDatabase::select
	 *
	 * This only works if its specified as an array literal.
	 *
	 * @param Node|mixed $node The node from the AST tree
	 */
	private function checkSQLOptions( mixed $node ): void {
		if ( !( $node instanceof Node ) || $node->kind !== \ast\AST_ARRAY ) {
			return;
		}

		foreach ( $node->children as $arrayElm ) {
			// Can't use array destructuring as an expression
			assert( $arrayElm !== null );
			if ( $arrayElm->kind === \ast\AST_UNPACK ) {
				// Can't analyze this, skip it.
				continue;
			}
			assert( $arrayElm->kind === \ast\AST_ARRAY_ELEM );
			$val = $arrayElm->children['value'];
			$key = $arrayElm->children['key'];
			$this->checkSQLOption( $key, $val, $node );
		}
	}

	/**
	 *  Relevant options:
	 *   GROUP BY is put directly in the query (array gets imploded)
	 *   HAVING is treated like a WHERE clause
	 *   ORDER BY is put directly in the query (array gets imploded)
	 *   USE INDEX is directly put in string (both array and string version)
	 *   IGNORE INDEX ditto
	 *
	 * @param Node|mixed $option
	 * @param Node|mixed $value
	 * @param Node $callNode
	 */
	private function checkSQLOption( mixed $option, mixed $value, Node $callNode ): void {
		$relevant = [
			'GROUP BY' => true,
			'ORDER BY' => true,
			'HAVING' => true,
			'USE INDEX' => true,
			'IGNORE INDEX' => true,
		];

		if ( !is_string( $option ) || !isset( $relevant[$option] ) ) {
			return;
		}
		$taintType = ( $option === 'HAVING' && $this->nodeIsArray( $value ) ) ?
			SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT :
			SecurityCheckPlugin::SQL_EXEC_TAINT;
		$taintType = new Taintedness( $taintType );

		$this->backpropagateArgTaint( $callNode, $taintType );
		if ( $value instanceof Node && $value->lineno !== $this->context->getLineNumberStart() ) {
			$ctx = clone $this->context;
			$this->overrideContext = $ctx->withLineNumberStart( $value->lineno );
		}
		$this->maybeEmitIssueSimplified(
			$taintType,
			$value,
			"{STRING_LITERAL} clause is user controlled",
			[ $option ]
		);
		$this->overrideContext = null;
	}

	/**
	 * Check a join_cond structure.
	 *
	 * Syntax is like
	 *
	 *  [ 'aliasOfTable' => [ 'JOIN TYPE', $onConditions ], ... ]
	 *  join type is usually something safe like INNER JOIN, but it is not
	 *  validated or escaped. $onConditions is the same form as a WHERE clause.
	 *
	 * @param Node|mixed $node
	 */
	private function checkJoinConds( mixed $node ): void {
		if ( !( $node instanceof Node ) || $node->kind !== \ast\AST_ARRAY ) {
			return;
		}

		foreach ( $node->children as $table ) {
			// Can't use array destructuring as an expression
			assert( $table !== null );
			if ( $table->kind === \ast\AST_UNPACK ) {
				// Can't analyze this, skip it.
				continue;
			}
			assert( $table->kind === \ast\AST_ARRAY_ELEM );

			$tableName = is_string( $table->children['key'] ) ?
				$table->children['key'] :
				'[UNKNOWN TABLE]';
			$joinInfo = $table->children['value'];
			if ( $joinInfo instanceof Node && $joinInfo->kind === \ast\AST_ARRAY ) {
				if (
					count( $joinInfo->children ) === 0 ||
					$joinInfo->children[0]->children['key'] !== null
				) {
					$this->debug( __METHOD__, "join info has named key??" );
					continue;
				}
				$joinType = $joinInfo->children[0]->children['value'];
				// join type does not get escaped.
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::SQL_EXEC_TAINT ),
					$joinType,
					"Join type for {STRING_LITERAL} is user controlled",
					[ $tableName ]
				);
				if ( $joinType instanceof Node ) {
					$this->backpropagateArgTaint(
						$joinType,
						new Taintedness( SecurityCheckPlugin::SQL_EXEC_TAINT )
					);
				}
				// On to the join ON conditions.
				if (
					count( $joinInfo->children ) === 1 ||
					$joinInfo->children[1]->children['key'] !== null
				) {
					$this->debug( __METHOD__, "join info has named key??" );
					continue;
				}
				$onCond = $joinInfo->children[1]->children['value'];
				if ( $onCond instanceof Node && $onCond->lineno !== $this->context->getLineNumberStart() ) {
					$ctx = clone $this->context;
					$this->overrideContext = $ctx->withLineNumberStart( $onCond->lineno );
				}
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ),
					$onCond,
					"The ON conditions are not properly escaped for the join to `{STRING_LITERAL}`",
					[ $tableName ]
				);
				if ( $onCond instanceof Node ) {
					$this->backpropagateArgTaint(
						$onCond,
						new Taintedness( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT )
					);
				}
				$this->overrideContext = null;
			}
		}
	}

	/**
	 * Check to see if isHTML => true and is tainted.
	 *
	 * @param Node $node The expr child of the return. NOT the return itself
	 * @param FullyQualifiedFunctionLikeName $funcName
	 */
	private function visitReturnOfFunctionHook( Node $node, FullyQualifiedFunctionLikeName $funcName ): void {
		// XXX: we limit this to literal arrays because otherwise, we wouldn't be able to match the output taintedness
		// and the `isHTML` value from different branches. See `safeHookIndirect1` test.
		if ( $node->kind !== \ast\AST_ARRAY || count( $node->children ) < 2 ) {
			return;
		}
		$arg = $node->children[0];
		// Can't have array destructuring in a return statement.
		assert( $arg instanceof Node );
		if ( $arg->kind === \ast\AST_UNPACK ) {
			// Can't analyze this.
			return;
		}
		assert( $arg->kind === \ast\AST_ARRAY_ELEM );

		$retType = UnionTypeVisitor::unionTypeFromNode( $this->code_base, $this->context, $node );
		$isHTMLElementType = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset(
			$retType,
			'isHTML',
			false,
			$this->code_base
		);
		if ( !$isHTMLElementType instanceof UnionType ) {
			// Can't be resolved statically.
			return;
		}

		// NOTE: Cannot use `containsTrue` here, because it, huh, also returns true for `false`.
		$containsTrue = $isHTMLElementType->hasRealTypeMatchingCallback(
			static fn ( Type $t ): bool => $t instanceof BoolType || $t instanceof TrueType
		);
		if ( !$containsTrue ) {
			return;
		}

		$this->maybeEmitIssueSimplified(
			new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT ),
			$arg->children['value'],
			"Outputting user controlled HTML from Parser function hook {FUNCTIONLIKE}",
			[ $funcName ]
		);
	}

	/**
	 * Given a MediaWiki hook registration, find the callback
	 *
	 * @note This is a different format than Parser hooks use.
	 *
	 * Valid examples of callbacks:
	 *  1) A normal callable (string, array, or first-class)
	 *  2) A class instance with an `on$hook` method
	 *  3) An extension hook handler spec (not handled here as we check extension.json instead)
	 *  4) `HookContainer::NOOP` for no-op handlers
	 *
	 * @param Node|mixed $node
	 * @param string $hookName
	 */
	private function getCallableFromHookRegistration( mixed $node, string $hookName ): ?FunctionInterface {
		$cb = $this->getCallableFromNode( $node );
		if ( $cb ) {
			return $cb;
		}

		$methodName = 'on' . $hookName;

		try {
			// Don't warn if it's the wrong type, for it might be a callable and not a class.
			$classes = $this->getCtxN( $node )->getClassList( true, ContextNode::CLASS_LIST_ACCEPT_ANY, null, false );
		} catch ( CodeBaseException | IssueException ) {
			$classes = [];
		}
		foreach ( $classes as $class ) {
			try {
				return $class->getMethodByName( $this->code_base, $methodName );
			} catch ( CodeBaseException ) {
				continue;
			}
		}

		// @todo Should probably emit a non-security issue
		$this->debug( __METHOD__, "Missing hook handler for node: " . Debug::nodeToString( $node ) );
		return null;
	}

	/**
	 * Check for $wgHooks registration
	 *
	 * @param Node $node
	 * @note This assumes $wgHooks is always the global
	 *   even if there is no globals declaration.
	 */
	public function visitAssign( Node $node ): void {
		parent::visitAssign( $node );

		$var = $node->children['var'];
		if ( !$var instanceof Node ) {
			// Syntax error
			return;
		}
		$hookName = null;
		$expr = $node->children['expr'];
		// The $wgHooks['foo'][] case
		if (
			$var->kind === \ast\AST_DIM &&
			$var->children['dim'] === null &&
			$var->children['expr'] instanceof Node &&
			$var->children['expr']->kind === \ast\AST_DIM &&
			$var->children['expr']->children['expr'] instanceof Node &&
			is_string( $var->children['expr']->children['dim'] ) &&
			/* The $wgHooks['SomeHook'][] case */
			( ( $var->children['expr']->children['expr']->kind === \ast\AST_VAR &&
			$var->children['expr']->children['expr']->children['name'] === 'wgHooks' ) ||
			/* The $GLOBALS['wgHooks']['SomeHook'][] case */
			( $var->children['expr']->children['expr']->kind === \ast\AST_DIM &&
			$var->children['expr']->children['expr']->children['expr'] instanceof Node &&
			$var->children['expr']->children['expr']->children['expr']->kind === \ast\AST_VAR &&
			$var->children['expr']->children['expr']->children['expr']->children['name'] === 'GLOBALS' ) )
		) {
			$hookName = $var->children['expr']->children['dim'];
		}

		if ( $hookName !== null ) {
			$cb = $this->getCallableFromHookRegistration( $expr, $hookName );
			if ( $cb ) {
				$this->registerHook( $hookName, $cb );
			} else {
				$this->debug( __METHOD__, "Could not register hook " .
					"$hookName due to complex callback"
				);
			}
		}
	}

	/**
	 * Special implementation of visitArray to detect HTMLForm specifiers
	 */
	private function detectHTMLForm( Node $node ): void {
		// Try to immediately filter out things that certainly aren't HTMLForms
		$maybeHTMLForm = false;
		foreach ( $node->children as $child ) {
			if ( $child instanceof Node && $child->kind === \ast\AST_ARRAY_ELEM ) {
				$key = $child->children['key'];
				if ( $key instanceof Node || $key === 'class' || $key === 'type' ) {
					$maybeHTMLForm = true;
					break;
				}
			}
		}
		if ( !$maybeHTMLForm ) {
			return;
		}

		$authReqFQSEN = FullyQualifiedClassName::fromFullyQualifiedString(
			'MediaWiki\Auth\AuthenticationRequest'
		);

		if (
			$this->code_base->hasClassWithFQSEN( $authReqFQSEN ) &&
			$this->context->isInClassScope() &&
			self::isSubclassOf( $this->context->getClassFQSEN(), $authReqFQSEN, $this->code_base )
		) {
			// AuthenticationRequest::getFieldInfo() defines a very
			// similar array but with different rules. T202112
			return;
		}

		// This is a rather superficial check. There
		// are many ways to construct htmlform specifiers this
		// won't catch, and it may also have some false positives.

		static $HTMLFormTypesToClasses = null;
		if ( !$HTMLFormTypesToClasses ) {
			$makeFQSEN = FullyQualifiedClassName::fromFullyQualifiedString( ... );
			$HTMLFormTypesToClasses = [
				'api' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLApiField' ),
				'text' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTextField' ),
				'textwithbutton' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTextFieldWithButton' ),
				'textarea' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTextAreaField' ),
				'select' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectField' ),
				'combobox' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLComboboxField' ),
				'radio' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLRadioField' ),
				'multiselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLMultiSelectField' ),
				'limitselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectLimitField' ),
				'check' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLCheckField' ),
				'toggle' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLCheckField' ),
				'int' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLIntField' ),
				'file' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLFileField' ),
				'float' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLFloatField' ),
				'info' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLInfoField' ),
				'selectorother' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectOrOtherField' ),
				'selectandother' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectAndOtherField' ),
				'namespaceselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectNamespace' ),
				'namespaceselectwithbutton' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectNamespaceWithButton' ),
				'tagfilter' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTagFilter' ),
				'sizefilter' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSizeFilterField' ),
				'submit' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSubmitField' ),
				'hidden' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLHiddenField' ),
				'edittools' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLEditTools' ),
				'checkmatrix' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLCheckMatrix' ),
				'cloner' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLFormFieldCloner' ),
				'autocompleteselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLAutoCompleteSelectField' ),
				'language' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLSelectLanguageField' ),
				'date' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLDateTimeField' ),
				'time' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLDateTimeField' ),
				'datetime' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLDateTimeField' ),
				'expiry' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLExpiryField' ),
				'timezone' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTimezoneField' ),
				'email' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTextField' ),
				'password' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTextField' ),
				'url' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTextField' ),
				'title' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTitleTextField' ),
				'user' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLUserTextField' ),
				'tagmultiselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTagMultiselectField' ),
				'orderedmultiselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLOrderedMultiselectField' ),
				'usersmultiselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLUsersMultiselectField' ),
				'titlesmultiselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLTitlesMultiselectField' ),
				'namespacesmultiselect' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLNamespacesMultiselectField' ),
				// NOTE: it isn't actually possible to create an HTMLButtonField using `type => button` for some reason.
				// Here, pretending it to be possible is simpler than special-casing the exception.
				'button' => $makeFQSEN( '\MediaWiki\HTMLForm\Field\HTMLButtonField' ),
			];
		}
		static $rawProps = [
			'label-raw',
			'help-raw',
			'buttonlabel-raw',
		];
		static $propsToResolve = null;
		$propsToResolve ??= [
			'type',
			'class',
			'label',
			'options',
			'default',
			'raw',
			'rawrow',
			// TODO: remove help key case when back compat is no longer needed (T356971)
			'help',
			...$rawProps,
		];

		$fieldProps = [];
		foreach ( $node->children as $child ) {
			if ( $child === null || $child->kind === \ast\AST_UNPACK ) {
				// If we have list( , $x ) = foo(), or an in-place unpack, chances are this is not an HTMLForm.
				return;
			}
			assert( $child->kind === \ast\AST_ARRAY_ELEM );
			if ( $child->children['key'] === null ) {
				// Implicit offset, hence most certainly not an HTMLForm.
				return;
			}
			$key = $this->resolveOffset( $child->children['key'] );
			if ( !is_string( $key ) ) {
				// Either not resolvable (so nothing we can say) or a non-string literal, skip.
				return;
			}
			if ( in_array( $key, $propsToResolve, true ) ) {
				$fieldProps[$key] = $this->resolveValue( $child->children['value'] );
			}
		}
		// Special case
		$raw = $fieldProps['raw'] ?? $fieldProps['rawrow'] ?? null;

		// Also important to reject empty string, not just
		// null, otherwise 9e409c781015 of Wikibase causes
		// this to fatal
		if ( !empty( $fieldProps['type'] ) && is_string( $fieldProps['type'] ) ) {
			$type = $fieldProps['type'];
			if ( !isset( $HTMLFormTypesToClasses[$type] ) ) {
				// Not a valid HTMLForm field (or a new field type we don't recognize)
				return;
			}
		} elseif ( !empty( $fieldProps['class'] ) && is_string( $fieldProps['class'] ) ) {
			try {
				$fqsen = FullyQualifiedClassName::fromStringInContext(
					$fieldProps['class'],
					$this->context
				);
			} catch ( InvalidFQSENException ) {
				// 'class' refers to something which is not a class, and this is probably not
				// an HTMLForm
				return;
			}

			$type = null;
			foreach ( $HTMLFormTypesToClasses as $curType => $fieldFQSEN ) {
				if ( $fqsen === $fieldFQSEN ) {
					$type = $curType;
					break;
				}
				// Note, this assumes that the list above uses the canonical FQSENs
				$fieldAliasFQSENs = array_map(
					static fn ( ClassAliasRecord $car ): FullyQualifiedClassName => $car->alias_fqsen,
					$this->code_base->getClassAliasesByFQSEN( $fieldFQSEN )
				);
				if ( in_array( $fqsen, $fieldAliasFQSENs, true ) ) {
					$type = $curType;
					break;
				}
			}
			if ( !$type ) {
				// Not a valid HTMLForm field (or a new field type we don't recognize)
				return;
			}
		} else {
			// Definitely not an HTMLForm
			return;
		}

		$fieldPropsToCheck = array_diff_key( $fieldProps, [ 'class' => 1, 'type' => 1 ] );
		if ( !$fieldPropsToCheck ) {
			// e.g. [ 'class' => 'someCssClass' ] appears a lot
			// in the code base. If we don't have any of the interesting
			// fields, skip out early.
			return;
		}

		if ( $fieldPropsToCheck['label'] ?? null ) {
			// double escape check for label.
			$this->maybeEmitIssueSimplified(
				new Taintedness( SecurityCheckPlugin::ESCAPED_EXEC_TAINT ),
				$fieldPropsToCheck['label'],
				'HTMLForm label key escapes its input'
			);
		}
		if ( $fieldPropsToCheck['help'] ?? null ) {
			$this->maybeEmitIssueSimplified(
				new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT ),
				$fieldPropsToCheck['help'],
				'HTMLForm help needs to escape input'
			);
		}
		foreach ( $rawProps as $prop ) {
			if ( $fieldPropsToCheck[$prop] ?? null ) {
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT ),
					$fieldPropsToCheck[$prop],
					"HTMLForm $prop needs to escape input"
				);
			}
		}

		if ( $type === 'info' && ( $fieldPropsToCheck['default'] ?? null ) ) {
			if ( $raw === true ) {
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT ),
					$fieldPropsToCheck['default'],
					'HTMLForm info field in raw mode needs to escape default key'
				);
			}
			if ( $raw === false || $raw === null ) {
				$this->maybeEmitIssueSimplified(
					new Taintedness( SecurityCheckPlugin::ESCAPED_EXEC_TAINT ),
					$fieldPropsToCheck['default'],
					'HTMLForm info field (non-raw) escapes default key already'
				);
			}
		}

		// options key is really messed up with escaping.
		$isOptionsSafe = !in_array( $type, [ 'radio', 'multiselect' ], true );
		$options = $fieldProps['options'] ?? null;
		if ( !$isOptionsSafe && $options instanceof Node ) {
			$htmlExecTaint = new Taintedness( SecurityCheckPlugin::HTML_EXEC_TAINT );
			$optTaint = $this->getTaintedness( $options );
			$this->maybeEmitIssue(
				$htmlExecTaint,
				$optTaint->getTaintedness()->asKeyForForeach(),
				'HTMLForm option label needs escaping{DETAILS}',
				[ [ 'lines' => $optTaint->getError(), 'sink' => false ] ]
			);
		}
	}

	/**
	 * Try to detect HTMLForm specifiers
	 */
	public function visitArray( Node $node ): void {
		parent::visitArray( $node );
		// Performance: use isset(), not property_exists
		if ( !isset( $node->skipHTMLFormAnalysis ) ) {
			$this->detectHTMLForm( $node );
		}
	}
}
