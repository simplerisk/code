<?php declare( strict_types = 1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\PluginV3\BeforeLoopBodyAnalysisVisitor;

class TaintednessLoopVisitor extends BeforeLoopBodyAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * Visit a foreach loop
	 *
	 * We do that in this visitor so that we can handle the loop condition prior to
	 * determine the taint of the loop variable, prior to evaluating the loop body.
	 * See https://github.com/phan/phan/issues/3936
	 */
	public function visitForeach( Node $node ): void {
		$expr = $node->children['expr'];
		$lhsTaintednessWithError = $this->getTaintedness( $expr );
		$lhsTaintedness = $lhsTaintednessWithError->getTaintedness();
		$lhsLinks = $lhsTaintednessWithError->getMethodLinks();

		$value = $node->children['value'];
		if ( $value->kind === \ast\AST_REF ) {
			// TODO, this doesn't propagate the taint to the outer scope
			// (FWIW, phan doesn't do much better with types, https://github.com/phan/phan/issues/4017)
			$value = $value->children['var'];
		}

		$valueTaint = $lhsTaintedness->asValueFirstLevel();
		$valueError = $lhsTaintednessWithError->getError()->asAllValueFirstLevel();
		$valueLinks = $lhsLinks->asValueFirstLevel();
		// TODO Actually compute this
		$rhsIsArray = false;
		// NOTE: As mentioned in test 'foreach', we won't be able to retroactively attribute
		// the right taint to the value if we discover what the key is for the current iteration
		$valueVisitor = new TaintednessAssignVisitor(
			$this->code_base,
			$this->context,
			$valueTaint,
			$valueError,
			$valueLinks,
			$valueTaint,
			$valueLinks,
			$rhsIsArray
		);
		$valueVisitor( $value );

		$key = $node->children['key'] ?? null;
		if ( $key instanceof Node ) {
			$keyTaint = $lhsTaintedness->asKeyForForeach();
			$keyError = $lhsTaintednessWithError->getError()->asAllKeyForForeach();
			$keyLinks = $lhsLinks->asKeyForForeach();
			$keyVisitor = new TaintednessAssignVisitor(
				$this->code_base,
				$this->context,
				$keyTaint,
				$keyError,
				$keyLinks,
				$keyTaint,
				$keyLinks,
				$rhsIsArray
			);
			$keyVisitor( $key );
		}
	}
}
