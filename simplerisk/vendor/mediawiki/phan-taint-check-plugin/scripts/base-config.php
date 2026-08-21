<?php
declare( strict_types = 1 );

// This is the base config, shared with all the others

return [
	'analyzed_file_extensions' => [
		'php',
		'inc'
	],

	// Do not emit false positives
	"minimum_severity" => 1,
	// Include a progress bar in the output
	'progress_bar' => true,
	'whitelist_issue_types' => [
		'SecurityCheck-XSS',
		'SecurityCheck-SQLInjection',
		'SecurityCheck-ShellInjection',
		'SecurityCheck-DoubleEscaped',
		'SecurityCheck-CUSTOM1',
		'SecurityCheck-CUSTOM2',
		'SecurityCheck-RCE',
		'SecurityCheck-PathTraversal',
		'SecurityCheck-ReDoS',
		// Rely on severity setting to prevent false positive.
		'SecurityCheck-LikelyFalsePositive',
		'PhanSyntaxError',
		'SecurityCheckDebugTaintedness',
		'SecurityCheckInvalidAnnotation',
	],

	'plugins' => [
		'UnusedSuppressionPlugin'
	],

	'plugin_config' => [
		// Only report unused suppressions for security issues
		'unused_suppression_whitelisted_only' => true
	],
];
