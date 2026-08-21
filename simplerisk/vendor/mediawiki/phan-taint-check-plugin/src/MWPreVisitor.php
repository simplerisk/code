<?php declare( strict_types = 1 );

/**
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * @license GPL-2.0-or-later
 */

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * Class for visiting any nodes we want to handle in pre-order.
 */
class MWPreVisitor extends PreTaintednessVisitor {
	/**
	 * Set taint for certain hook types.
	 *
	 * Also handles FuncDecl
	 */
	public function visitMethod( Node $node ): void {
		parent::visitMethod( $node );

		$fqsen = $this->context->getFunctionLikeFQSEN();
		$hookType = MediaWikiHooksHelper::getInstance()->isSpecialHookSubscriber( $fqsen );
		if ( !$hookType ) {
			return;
		}
		$params = $node->children['params']->children;

		switch ( $hookType ) {
			case '!ParserFunctionHook':
				$this->setFuncHookParamTaint( $params );
				break;
			case '!ParserHook':
				$this->setTagHookParamTaint( $params );
				break;
		}
	}

	/**
	 * Set taint for a tag hook.
	 *
	 * The parameters are:
	 *  string contents (Tainted from wikitext)
	 *  array attribs (Tainted from wikitext)
	 *  Parser object
	 *  PPFrame object
	 *
	 * @param array $params formal parameters of tag hook
	 * @phan-param array<Node|int|string|bool|null|float> $params
	 */
	private function setTagHookParamTaint( array $params ): void {
		// Only care about first 2 parameters.
		$scope = $this->context->getScope();
		for ( $i = 0; $i < 2 && $i < count( $params ); $i++ ) {
			$param = $params[$i];
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// @codeCoverageIgnoreStart
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				continue;
				// @codeCoverageIgnoreEnd
			}
			$varObj = $scope->getVariableByName( $param->children['name'] );
			$argTaint = Taintedness::newTainted();
			self::setTaintednessRaw( $varObj, $argTaint );
			$this->addTaintError( $varObj, $argTaint, null, 'tainted argument to tag hook' );
			// $this->debug( __METHOD__, "In $method setting param $varObj as tainted" );
		}
		// If there are no type hints, phan won't know that the parser
		// is a parser as the hook isn't triggered from a real func call.
		$paramFQSENs = [
			2 => FullyQualifiedClassName::fromFullyQualifiedString( '\\MediaWiki\\Parser\\Parser' ),
			3 => FullyQualifiedClassName::fromFullyQualifiedString( '\\MediaWiki\\Parser\\PPFrame' ),
		];
		foreach ( $paramFQSENs as $i => $fqsen ) {
			if ( isset( $params[$i] ) ) {
				$param = $params[$i];
				if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
					// @codeCoverageIgnoreStart
					$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
					// @codeCoverageIgnoreEnd
				} else {
					$varObj = $scope->getVariableByName( $param->children['name'] );
					$varObj->setUnionType( $fqsen->asPHPDocUnionType() );
				}
			}
		}
	}

	/**
	 * Set the appropriate taint for a parser function hook
	 *
	 * Basically all but the first arg comes from wikitext
	 * and is tainted.
	 *
	 * @todo This is handling SFH_OBJECT type func hooks incorrectly.
	 * @param Node[] $params Children of the AST_PARAM_LIST
	 */
	private function setFuncHookParamTaint( array $params ): void {
		// First make sure the first arg is set to be a Parser
		$scope = $this->context->getScope();
		if ( isset( $params[0] ) ) {
			$param = $params[0];
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// @codeCoverageIgnoreStart
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				// @codeCoverageIgnoreEnd
			} else {
				$varObj = $scope->getVariableByName( $param->children['name'] );
				$varObj->setUnionType(
					FullyQualifiedClassName::fromFullyQualifiedString( '\\MediaWiki\\Parser\\Parser' )
						->asPHPDocUnionType()
				);
			}
		}

		foreach ( $params as $i => $param ) {
			if ( $i === 0 ) {
				continue;
			}
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// @codeCoverageIgnoreStart
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				continue;
				// @codeCoverageIgnoreEnd
			}
			$varObj = $scope->getVariableByName( $param->children['name'] );
			$argTaint = Taintedness::newTainted();
			self::setTaintednessRaw( $varObj, $argTaint );
			$this->addTaintError( $varObj, $argTaint, null, 'tainted argument to parser hook' );
		}
	}

	public function visitAssign( Node $node ): void {
		parent::visitAssign( $node );

		$lhs = $node->children['var'];
		if ( $lhs instanceof Node && $lhs->kind === \ast\AST_ARRAY ) {
			// Don't try interpreting the node as an HTMLForm specifier later on, both for performance, and because
			// resolving values might cause phan to emit issues (see test undeclaredvar3)
			// @phan-suppress-next-line PhanUndeclaredProperty
			$lhs->skipHTMLFormAnalysis = true;
		}
	}
}
