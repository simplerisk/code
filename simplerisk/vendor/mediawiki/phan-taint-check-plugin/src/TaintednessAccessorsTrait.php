<?php declare( strict_types=1 );

// @phan-file-suppress PhanUndeclaredProperty

namespace SecurityCheckPlugin;

use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\TypedElementInterface;

/**
 * Accessors to read and write taintedness props stored inside phan objects. This trait exists to avoid duplicating
 * dynamic property names, to have better type inference, to enable phan checks for undeclared props on the other
 * files, to keep track of props usage etc.
 */
trait TaintednessAccessorsTrait {
	protected static function getTaintednessRaw( TypedElementInterface $element ): ?Taintedness {
		return $element->taintedness ?? null;
	}

	protected static function setTaintednessRaw( TypedElementInterface $element, Taintedness $taintedness ): void {
		$element->taintedness = $taintedness;
		if ( $element instanceof PassByReferenceVariable ) {
			self::setTaintednessRef( $element->getElement(), $taintedness );
		}
	}

	protected static function getCausedByRaw( TypedElementInterface $element ): ?CausedByLines {
		return $element->taintedOriginalError ?? null;
	}

	protected static function getCausedByRef( TypedElementInterface $element ): ?CausedByLines {
		return $element->taintedOriginalErrorRef ?? null;
	}

	protected static function getFuncCausedByRaw( FunctionInterface $func ): ?FunctionCausedByLines {
		return $func->funcTaintedOriginalError ?? null;
	}

	protected static function setCausedByRaw( TypedElementInterface $element, CausedByLines $lines ): void {
		$element->taintedOriginalError = $lines;
		if ( $element instanceof PassByReferenceVariable ) {
			self::setCausedByRef( $element->getElement(), $lines );
		}
	}

	protected static function setCausedByRef( TypedElementInterface $element, CausedByLines $lines ): void {
		$element->taintedOriginalErrorRef = $lines;
	}

	protected static function setFuncCausedByRaw( FunctionInterface $func, FunctionCausedByLines $lines ): void {
		$func->funcTaintedOriginalError = $lines;
	}

	protected static function getMethodLinks( TypedElementInterface $element ): ?MethodLinks {
		return $element->taintedMethodLinks ?? null;
	}

	protected static function setMethodLinks( TypedElementInterface $element, MethodLinks $links ): void {
		$element->taintedMethodLinks = $links;
		if ( $element instanceof PassByReferenceVariable ) {
			$element->getElement()->taintedMethodLinksRef = $links;
		}
	}

	protected static function getMethodLinksRef( TypedElementInterface $element ): ?MethodLinks {
		return $element->taintedMethodLinksRef ?? null;
	}

	protected static function getVarLinks( FunctionInterface $func, int $index ): ?VarLinksMap {
		return $func->taintedVarLinks[$index] ?? null;
	}

	protected static function ensureVarLinksForArgExist( TypedElementInterface $element, int $arg ): void {
		$element->taintedVarLinks ??= [];
		$element->taintedVarLinks[$arg] ??= new VarLinksMap;
	}

	protected static function getTaintednessRef( TypedElementInterface $element ): ?Taintedness {
		return $element->taintednessRef ?? null;
	}

	protected static function setTaintednessRef( TypedElementInterface $element, Taintedness $taintedness ): void {
		$element->taintednessRef = $taintedness;
	}

	protected static function clearRefData( TypedElementInterface $element ): void {
		unset( $element->taintednessRef, $element->taintedMethodLinksRef, $element->taintedOriginalErrorRef );
	}

	/**
	 * Get $func's taint, or null if not set.
	 */
	protected static function getFuncTaint( FunctionInterface $func ): ?FunctionTaintedness {
		return $func->funcTaint ?? null;
	}

	protected static function doSetFuncTaint( FunctionInterface $func, FunctionTaintedness $funcTaint ): void {
		$func->funcTaint = $funcTaint;
	}

	/**
	 * @return TypedElementInterface[]|null
	 */
	protected static function getRetObjs( FunctionInterface $func ): ?array {
		$funcNode = $func->getNode();
		if ( !$funcNode ) {
			// If it has no node, it won't have any returned object, so don't return null, to avoid
			// potential recursive analysis attempts.
			return [];
		}
		return $funcNode->retObjs ?? null;
	}

	/**
	 * @note These are saved in the function node so that they can be shared by all implementations, without
	 * having to check the defining FQSEN of a method and canonicalize $func for lookup.
	 * @param FunctionInterface $func
	 * @param TypedElementInterface[] $retObjs
	 * @suppress PhanUnreferencedProtectedMethod Used in TaintednessVisitor
	 */
	protected static function addRetObjs( FunctionInterface $func, array $retObjs ): void {
		$funcNode = $func->getNode();
		if ( $funcNode ) {
			$funcNode->retObjs = array_merge( $funcNode->retObjs ?? [], $retObjs );
		}
	}

	protected static function initRetObjs( FunctionInterface $func ): void {
		$funcNode = $func->getNode();
		if ( $funcNode ) {
			$funcNode->retObjs ??= [];
		}
	}

}
