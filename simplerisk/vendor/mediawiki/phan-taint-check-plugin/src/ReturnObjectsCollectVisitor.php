<?php declare( strict_types = 1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\Variable;
use Phan\PluginV3\PluginAwareBaseAnalysisVisitor;

/**
 * Given a return statements, return a list of phan objects that are returned by it.
 *
 * @todo This should also do what matchTaintToParam currently does
 */
class ReturnObjectsCollectVisitor extends PluginAwareBaseAnalysisVisitor {
	use TaintednessBaseVisitor;

	/** @var TypedElementInterface[] */
	private $buffer = [];

	/**
	 * @return TypedElementInterface[]
	 */
	public function collectFromNode( Node $node ): array {
		assert( $node->kind === \ast\AST_RETURN );
		$this->buffer = [];
		$this( $node->children['expr'] );
		return $this->buffer;
	}

	/**
	 * @inheritDoc
	 */
	public function visitProp( Node $node ): void {
		$this->handleReturnedObject( $this->getPropFromNode( $node ) );
	}

	/**
	 * @inheritDoc
	 */
	public function visitNullsafeProp( Node $node ): void {
		$this->handleReturnedObject( $this->getPropFromNode( $node ) );
	}

	/**
	 * @inheritDoc
	 */
	public function visitStaticProp( Node $node ): void {
		$this->handleReturnedObject( $this->getPropFromNode( $node ) );
	}

	/**
	 * @inheritDoc
	 */
	public function visitVar( Node $node ): void {
		$this->handleVarNode( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitClosureVar( Node $node ): void {
		// FIXME Is this needed?
		$this->handleVarNode( $node );
	}

	private function handleVarNode( Node $node ): void {
		$cn = $this->getCtxN( $node );
		if ( Variable::isHardcodedGlobalVariableWithName( $cn->getVariableName() ) ) {
			return;
		}
		try {
			$this->handleReturnedObject( $cn->getVariable() );
		} catch ( NodeException | IssueException $e ) {
			$this->debug( __METHOD__, "variable not in scope?? " . $this->getDebugInfo( $e ) );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitEncapsList( Node $node ): void {
		foreach ( $node->children as $child ) {
			if ( $child instanceof Node ) {
				$this( $child );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitArray( Node $node ): void {
		foreach ( $node->children as $child ) {
			if ( $child instanceof Node ) {
				$this( $child );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitArrayElem( Node $node ): void {
		if ( $node->children['key'] instanceof Node ) {
			$this( $node->children['key'] );
		}
		if ( $node->children['value'] instanceof Node ) {
			$this( $node->children['value'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitCast( Node $node ): void {
		// Future todo might be to ignore casts to ints, since
		// such things should be safe. Unclear if that makes
		// sense in all circumstances.
		if ( $node->children['expr'] instanceof Node ) {
			$this( $node->children['expr'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitDim( Node $node ): void {
		if ( $node->children['expr'] instanceof Node ) {
			// For now just consider the outermost array.
			// FIXME. doesn't handle tainted array keys!
			$this( $node->children['expr'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitUnaryOp( Node $node ): void {
		if ( $node->children['expr'] instanceof Node ) {
			$this( $node->children['expr'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitBinaryOp( Node $node ): void {
		if ( $node->children['left'] instanceof Node ) {
			$this( $node->children['left'] );
		}
		if ( $node->children['right'] instanceof Node ) {
			$this( $node->children['right'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitConditional( Node $node ): void {
		if ( $node->children['true'] instanceof Node ) {
			$this( $node->children['true'] );
		}
		if ( $node->children['false'] instanceof Node ) {
			$this( $node->children['false'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitCall( Node $node ): void {
		$this->handleCall( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitMethodCall( Node $node ): void {
		$this->handleCall( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitStaticCall( Node $node ): void {
		$this->handleCall( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitNullsafeMethodCall( Node $node ): void {
		$this->handleCall( $node );
	}

	/**
	 * @param Node $node @phan-unused-param
	 */
	private function handleCall( Node $node ): void {
		// TODO If the func being called already has retObjs, we might add them.
	}

	/**
	 * @inheritDoc
	 */
	public function visitPreDec( Node $node ): void {
		$this->handleIncOrDec( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitPreInc( Node $node ): void {
		$this->handleIncOrDec( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitPostDec( Node $node ): void {
		$this->handleIncOrDec( $node );
	}

	/**
	 * @inheritDoc
	 */
	public function visitPostInc( Node $node ): void {
		$this->handleIncOrDec( $node );
	}

	private function handleIncOrDec( Node $node ): void {
		$children = $node->children;
		assert( count( $children ) === 1 );
		$this( reset( $children ) );
	}

	private function handleReturnedObject( ?TypedElementInterface $el ): void {
		if ( $el ) {
			$this->buffer[] = $el;
		}
	}
}
