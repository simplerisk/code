<?php
declare( strict_types = 1 );

/**
 * Static analysis tool for MediaWiki extensions.
 *
 * To use, add this file to your phan plugins list.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * @license GPL-2.0-or-later
 */

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\GenericArrayType;
use SecurityCheckPlugin\CausedByLines;
use SecurityCheckPlugin\FunctionTaintedness;
use SecurityCheckPlugin\MWPreVisitor;
use SecurityCheckPlugin\MWVisitor;
use SecurityCheckPlugin\SecurityCheckPlugin;
use SecurityCheckPlugin\Taintedness;
use SecurityCheckPlugin\TaintednessVisitor;

class MediaWikiSecurityCheckPlugin extends SecurityCheckPlugin {
	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return MWVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	public static function getPreAnalyzeNodeVisitorClassName(): string {
		return MWPreVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function getCustomFuncTaints(): array {
		$shellCommandOutput = [
			// This is a bit unclear. Most of the time
			// you should probably be escaping the results
			// of a shell command, but not all the time.
			'overall' => self::YES_TAINT
		];

		$sqlExecTaint = new Taintedness( self::SQL_EXEC_TAINT );
		$insertTaint = FunctionTaintedness::emptySingleton();
		// table name
		$insertTaint = $insertTaint->withParamSinkTaint( 0, $sqlExecTaint, self::NO_OVERRIDE );
		// Insert values. The keys names are unsafe. The argument can be either a single row or an array of rows.
		// Note, here we are assuming the single row case. The multiple rows case is handled in modifyParamSinkTaint.
		$sqlExecKeysTaint = Taintedness::newFromShape( [], null, self::SQL_EXEC_TAINT );
		$insertTaint = $insertTaint->withParamSinkTaint( 1, $sqlExecKeysTaint, self::NO_OVERRIDE );
		// method name
		$insertTaint = $insertTaint->withParamSinkTaint( 2, $sqlExecTaint, self::NO_OVERRIDE );
		// options. They are not escaped
		$insertTaint = $insertTaint->withParamSinkTaint( 3, $sqlExecTaint, self::NO_OVERRIDE );

		$insertQBRowTaint = FunctionTaintedness::emptySingleton();
		$insertQBRowTaint = $insertQBRowTaint->withParamSinkTaint( 0, clone $sqlExecKeysTaint, self::NO_OVERRIDE );

		$insertQBRowsTaint = FunctionTaintedness::emptySingleton();
		$multiRowsTaint = Taintedness::newFromShape( [], clone $sqlExecKeysTaint );
		$insertQBRowsTaint = $insertQBRowsTaint->withParamSinkTaint( 0, $multiRowsTaint, self::NO_OVERRIDE );

		return [
			// Note, at the moment, this checks where the function
			// is implemented, so you can't use IDatabase.
			'\Wikimedia\Rdbms\IDatabase::insert' => $insertTaint,
			'\Wikimedia\Rdbms\IMaintainableDatabase::insert' => $insertTaint,
			'\Wikimedia\Rdbms\Database::insert' => $insertTaint,
			'\Wikimedia\Rdbms\DBConnRef::insert' => $insertTaint,
			'\Wikimedia\Rdbms\InsertQueryBuilder::row' => $insertQBRowTaint,
			'\Wikimedia\Rdbms\InsertQueryBuilder::rows' => $insertQBRowsTaint,
			// FIXME Doesn't handle array args right.
			'\wfShellExec' => [
				self::SHELL_EXEC_TAINT | self::ARRAY_OK,
				'overall' => self::YES_TAINT
			],
			'\wfShellExecWithStderr' => [
				self::SHELL_EXEC_TAINT | self::ARRAY_OK,
				'overall' => self::YES_TAINT
			],
			'\wfEscapeShellArg' => [
				( self::YES_TAINT & ~self::SHELL_TAINT ) | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Shell::escape' => [
				( self::YES_TAINT & ~self::SHELL_TAINT ) | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			// Methods from wikimedia/Shellbox
			'\Shellbox\Shellbox::escape' => [
				( self::YES_TAINT & ~self::SHELL_TAINT ) | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\Shellbox\Command\Command::unsafeParams' => [
				self::SHELL_EXEC_TAINT | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\Shellbox\Command\UnboxedResult::getStdout' => $shellCommandOutput,
			'\Shellbox\Command\UnboxedResult::getStderr' => $shellCommandOutput,
			// The value of a status object can be pretty much anything, with any degree of taintedness
			// and escaping. Since it's a widely used class, it will accumulate a lot of links and taintedness
			// offset, resulting in huge objects (the short string representation of those Taintedness objects
			// can reach lengths in the order of tens of millions).
			// Since the plugin cannot keep track the taintedness of a property per-instance (as it assumes that
			// every property will be used with the same escaping level), we just annotate the methods as safe.
			'\StatusValue::newGood' => [
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Status\Status::newGood' => [
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\StatusValue::getValue' => [
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Status\Status::getValue' => [
				'overall' => self::NO_TAINT
			],
			'\StatusValue::setResult' => [
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Status\Status::setResult' => [
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
		];
	}

	/**
	 * Mark XSS's that happen in a Maintenance subclass as false a positive
	 *
	 * @inheritDoc
	 */
	public function isFalsePositive(
		int $combinedTaint,
		string &$msg,
		Context $context,
		CodeBase $code_base
	): bool {
		if ( $combinedTaint === self::HTML_TAINT ) {
			$path = str_replace( '\\', '/', $context->getFile() );
			if (
				str_starts_with( $path, 'maintenance/' ) ||
				str_contains( $path, '/maintenance/' )
			) {
				// For classes not using Maintenance subclasses
				$msg .= ' [Likely false positive because in maintenance subdirectory, thus probably CLI]';
				return true;
			}
			if ( !$context->isInClassScope() ) {
				return false;
			}
			$maintFQSEN = FullyQualifiedClassName::fromFullyQualifiedString(
				'\\Maintenance'
			);
			if ( !$code_base->hasClassWithFQSEN( $maintFQSEN ) ) {
				return false;
			}
			$classFQSEN = $context->getClassFQSEN();
			$isMaint = TaintednessVisitor::isSubclassOf( $classFQSEN, $maintFQSEN, $code_base );
			if ( $isMaint ) {
				$msg .= ' [Likely false positive because in a subclass of Maintenance, thus probably CLI]';
				return true;
			}
		}
		return false;
	}

	/**
	 * Special-case the $rows argument to Database::insert (T290563)
	 * @inheritDoc
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
		if ( !$func instanceof Method || $argIndex !== 1 || $func->getName() !== 'insert' ) {
			return [ $paramSinkTaint, $paramSinkError ];
		}

		$classFQSEN = $func->getClassFQSEN();
		if ( $classFQSEN->__toString() !== '\\Wikimedia\\Rdbms\\Database' ) {
			$idbFQSEN = FullyQualifiedClassName::fromFullyQualifiedString( '\\Wikimedia\\Rdbms\\IDatabase' );
			$isDBSubclass = $classFQSEN->asType()->asExpandedTypes( $code_base )->hasType( $idbFQSEN->asType() );
			if ( !$isDBSubclass ) {
				return [ $paramSinkTaint, $paramSinkError ];
			}
		}

		$argType = UnionTypeVisitor::unionTypeFromNode( $code_base, $context, $argument );
		$keyType = GenericArrayType::keyUnionTypeFromTypeSetStrict( $argType->getTypeSet() );
		if ( $keyType !== GenericArrayType::KEY_INT ) {
			// Note, it might still be an array of rows, but it's too hard for us to tell.
			return [ $paramSinkTaint, $paramSinkError ];
		}

		// Definitely a list of rows, so remove taintedness from the outer array keys, and instead add it to the
		// keys of inner arrays.
		$sqlExecKeysTaint = Taintedness::newFromShape( [], null, self::SQL_EXEC_TAINT );
		$adjustedTaint = Taintedness::newFromShape( [], $sqlExecKeysTaint );

		$curErrorLines = $paramSinkError->toLinesArray();
		assert( count( $curErrorLines ) === 1 && str_starts_with( $curErrorLines[0], 'Builtin' ) );
		$adjustedError = CausedByLines::emptySingleton()
			->withAddedLines( $curErrorLines, $adjustedTaint->asExecToYesTaint() );

		return [ $adjustedTaint, $adjustedError ];
	}

	/**
	 * Disable double escape checking for messages with polymorphic methods
	 *
	 * A common cause of false positives for double escaping is that some
	 * methods take a string|Message, and this confuses the tool given
	 * the __toString() behaviour of Message. So disable double escape
	 * checking for that.
	 *
	 * This is quite hacky. Ideally the tool would treat methods taking
	 * multiple types as separate for each type, and also be able to
	 * reason out simple conditions of the form if ( $arg instanceof Message ).
	 * However that's much more complicated due to dependence on phan.
	 *
	 * @inheritDoc
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
		if ( $curArgTaintedness->has( self::ESCAPED_TAINT ) ) {
			$argumentIsMaybeAMsg = false;
			try {
				/** @var \Phan\Language\Element\Clazz[] $classes */
				$classes = UnionTypeVisitor::unionTypeFromNode( $code_base, $context, $argument )
					->asClassList( $code_base, $context );
				foreach ( $classes as $cl ) {
					$classFQSEN = $cl->getFQSEN()->__toString();
					// TODO: drop first check when the `\Message` alias is dropped from MW core.
					if ( $classFQSEN === '\Message' || $classFQSEN === '\MediaWiki\Message\Message' ) {
						$argumentIsMaybeAMsg = true;
						break;
					}
				}
			} catch ( CodeBaseException ) {
				// A class that doesn't exist, don't crash.
				return $curArgTaintedness;
			}

			$param = $func->getParameterForCaller( $argIndex );
			if ( !$argumentIsMaybeAMsg || !$param || !$param->getUnionType()->hasStringType() ) {
				return $curArgTaintedness;
			}
			try {
				/** @var \Phan\Language\Element\Clazz[] $classesParam */
				$classesParam = $param->getUnionType()->asClassList( $code_base, $context );
				foreach ( $classesParam as $cl ) {
					$classFQSEN = $cl->getFQSEN()->__toString();
					// TODO: drop first check when the `\Message` alias is dropped from MW core.
					if ( $classFQSEN === '\Message' || $classFQSEN === '\MediaWiki\Message\Message' ) {
						// So we are here. Input is a Message, and func expects either a Message or string
						// (or something else). So disable double escape check.
						return $curArgTaintedness->without( self::ESCAPED_TAINT );
					}
				}
			} catch ( CodeBaseException ) {
				// A class that doesn't exist, don't crash.
				return $curArgTaintedness;
			}
		}
		return $curArgTaintedness;
	}
}

return new MediaWikiSecurityCheckPlugin;
