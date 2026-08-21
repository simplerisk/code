<?php
declare( strict_types = 1 );

/**
 * This is phan plugin to provide security static analysis checks for php
 *
 * If your project has functions/methods whose output you
 * specifically need to mark tainted, then you probably
 * want to make your own subclass of SecurityCheckPlugin
 * and override getCustomFuncTaint().
 *
 * See MediaWikiSecurityCheckPlugin for an example of that.
 *
 * To use, add this file to the list of your phan plugins.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * @license GPL-2.0-or-later
 */

use SecurityCheckPlugin\PreTaintednessVisitor;
use SecurityCheckPlugin\SecurityCheckPlugin;
use SecurityCheckPlugin\TaintednessVisitor;

class GenericSecurityCheckPlugin extends SecurityCheckPlugin {
	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return TaintednessVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	public static function getPreAnalyzeNodeVisitorClassName(): string {
		return PreTaintednessVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function getCustomFuncTaints(): array {
		return [];
	}
}

return new GenericSecurityCheckPlugin;
