<?php declare( strict_types = 1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Exception;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\Variable;
use Phan\PluginV3\PluginAwareBaseAnalysisVisitor;

class TaintednessBackpropVisitor extends PluginAwareBaseAnalysisVisitor {
	use TaintednessBaseVisitor;

	/** @var Taintedness */
	private $taintedness;

	/** @var CausedByLines|null */
	private $additionalError;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		CodeBase $code_base,
		Context $context,
		Taintedness $taintedness,
		?CausedByLines $additionalError = null
	) {
		parent::__construct( $code_base, $context );
		$this->taintedness = $taintedness;
		$this->additionalError = $additionalError;
	}

	/**
	 * @inheritDoc
	 */
	public function visitProp( Node $node ): void {
		$this->doBackpropElements( $this->getPropFromNode( $node ) );
	}

	/**
	 * @inheritDoc
	 */
	public function visitNullsafeProp( Node $node ): void {
		$this->doBackpropElements( $this->getPropFromNode( $node ) );
	}

	/**
	 * @inheritDoc
	 */
	public function visitStaticProp( Node $node ): void {
		$this->doBackpropElements( $this->getPropFromNode( $node ) );
	}

	/**
	 * @inheritDoc
	 */
	public function visitVar( Node $node ): void {
		$cn = $this->getCtxN( $node );
		if ( Variable::isHardcodedGlobalVariableWithName( $cn->getVariableName() ) ) {
			return;
		}
		try {
			$this->doBackpropElements( $cn->getVariable() );
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
				$this->recurse( $child );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitArray( Node $node ): void {
		foreach ( $node->children as $child ) {
			if ( $child instanceof Node ) {
				$this->recurse( $child );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitArrayElem( Node $node ): void {
		$key = $node->children['key'];
		if ( $key instanceof Node ) {
			$this->recurse(
				$key,
				$this->taintedness->asKeyForForeach(),
				$this->additionalError?->asAllKeyForForeach()
			);
		}
		$value = $node->children['value'];
		if ( $value instanceof Node ) {
			$this->recurse(
				$value,
				$this->taintedness->getTaintednessForOffsetOrWhole( $key ),
				$this->additionalError?->getForDim( $key )
			);
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
			$this->recurse( $node->children['expr'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitDim( Node $node ): void {
		if ( $node->children['expr'] instanceof Node ) {
			// For now just consider the outermost array.
			// FIXME. doesn't handle tainted array keys!
			$offs = $node->children['dim'];
			$realOffs = $offs !== null ? $this->resolveOffset( $offs ) : null;
			$this->recurse(
				$node->children['expr'],
				$this->taintedness->asMaybeMovedAtOffset( $realOffs ),
				$this->additionalError?->asAllMaybeMovedAtOffset( $realOffs )
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitUnaryOp( Node $node ): void {
		if ( $node->children['expr'] instanceof Node ) {
			$this->recurse( $node->children['expr'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitBinaryOp( Node $node ): void {
		if ( $node->children['left'] instanceof Node ) {
			$this->recurse( $node->children['left'] );
		}
		if ( $node->children['right'] instanceof Node ) {
			$this->recurse( $node->children['right'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function visitConditional( Node $node ): void {
		if ( $node->children['true'] instanceof Node ) {
			$this->recurse( $node->children['true'] );
		}
		if ( $node->children['false'] instanceof Node ) {
			$this->recurse( $node->children['false'] );
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

	private function handleCall( Node $node ): void {
		$ctxNode = $this->getCtxN( $node );
		// @todo Future todo might be to still return arguments when catching an exception.
		if ( $node->kind === \ast\AST_CALL ) {
			if ( $node->children['expr']->kind !== \ast\AST_NAME ) {
				// TODO Handle this case!
				return;
			}
			try {
				$func = $ctxNode->getFunction( $node->children['expr']->children['name'] );
			} catch ( IssueException | FQSENException $e ) {
				$this->debug( __METHOD__, "FIXME func not found: " . $this->getDebugInfo( $e ) );
				return;
			}
		} else {
			$methodName = $node->children['method'];
			try {
				$func = $ctxNode->getMethod( $methodName, $node->kind === \ast\AST_STATIC_CALL, true );
			} catch ( NodeException | CodeBaseException | IssueException $e ) {
				$this->debug( __METHOD__, "FIXME method not found: " . $this->getDebugInfo( $e ) );
				return;
			}
		}
		// intentionally resetting options to []
		// here to ensure we don't recurse beyond
		// a depth of 1.
		try {
			$retObjs = $this->getReturnObjsOfFunc( $func );
		} catch ( Exception $e ) {
			$this->debug( __METHOD__, "FIXME: " . $this->getDebugInfo( $e ) );
			return;
		}
		$this->doBackpropElements( ...$retObjs );
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
		$this->recurse( reset( $children ) );
	}

	/**
	 * Wrapper for __invoke. Allows changing the taintedness before recursing, and restoring later.
	 */
	private function recurse( Node $node, ?Taintedness $taint = null, ?CausedByLines $error = null ): void {
		if ( !$taint ) {
			$this( $node );
			return;
		}

		[ $oldTaint, $oldErr ] = [ $this->taintedness, $this->additionalError ];
		$this->taintedness = $taint;
		$this->additionalError = $error;
		try {
			$this( $node );
		} finally {
			[ $this->taintedness, $this->additionalError ] = [ $oldTaint, $oldErr ];
		}
	}

	/**
	 * @param TypedElementInterface|null ...$elements
	 */
	private function doBackpropElements( ?TypedElementInterface ...$elements ): void {
		foreach ( array_unique( array_filter( $elements ) ) as $el ) {
			$this->markAllDependentMethodsExec( $el, $this->taintedness, $this->additionalError );
		}
	}
}
