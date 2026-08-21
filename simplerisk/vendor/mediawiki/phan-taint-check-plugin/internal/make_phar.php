<?php declare( strict_types=1 );

/**
 * Taken from phan's internal/package.php. See history on github for attribution.
 * File distributed under the MIT license
 */

$dir = dirname( __DIR__ );
chdir( $dir );

if ( !file_exists( 'build' ) ) {
	exit( 'Run this script via make_phar.sh' );
}
$phar = new Phar( 'build/taint-check.phar', 0, 'taint-check.phar' );

$iterators = new AppendIterator();
foreach ( [ 'src', 'scripts', 'vendor' ] as $subdir ) {
	$iterators->append(
		new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$subdir,
				RecursiveDirectoryIterator::FOLLOW_SYMLINKS
			)
		)
	);
}

// Include all files with suffix .php or .phan_php, excluding those found in the tests folder.
$iterator = new CallbackFilterIterator(
	$iterators,
	static function ( SplFileInfo $file_info ): bool {
		$extension = $file_info->getExtension();
		// Include .php files and .phan_php stub files
		if ( $extension !== 'php' && !str_ends_with( $file_info->getFilename(), '.phan_php' ) ) {
			return false;
		}
		if ( preg_match(
			'@^vendor/symfony/(console|debug)/Tests/@i',
			str_replace( '\\', '/', $file_info->getPathname() )
		) ) {
			return false;
		}
		return true;
	}
);

$phar->buildFromIterator( $iterator, $dir );
foreach ( glob( '*SecurityCheckPlugin.php' ) as $plugin ) {
	$phar->addFile( $plugin );
}

foreach ( $phar as $file ) {
	// @phan-suppress-next-line PhanPluginUnknownObjectMethodCall TODO fix https://github.com/phan/phan/issues/3723
	echo $file->getFileName() . "\n";
}

// We don't want to use https://secure.php.net/manual/en/phar.interceptfilefuncs.php , which Phar does by default.
// That causes annoying bugs.
// Also, phan.phar is has no use cases to use as a web server, so don't include that, either.
// See https://github.com/composer/xdebug-handler/issues/46 and
// https://secure.php.net/manual/en/phar.createdefaultstub.php
$stub = <<<'EOT'
#!/usr/bin/env php
<?php
Phar::mapPhar('taint-check.phar');
require 'phar://taint-check.phar/vendor/phan/phan/src/phan.php';
__HALT_COMPILER();
EOT;
$phar->setStub( $stub );

echo "Created phar in build/phan.phar\n";
