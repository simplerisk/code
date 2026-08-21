<?php declare( strict_types = 1 );

namespace SecurityCheckPlugin;

/**
 * @license GPL-2.0-or-later
 */

use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;

class MediaWikiHooksHelper {
	/**
	 * @var bool Whether extension.json/skin.json was already loaded
	 */
	private $extensionJsonLoaded = false;

	/**
	 * @var FullyQualifiedFunctionLikeName[][] A mapping from normalized hook names to FQSEN that implement it
	 * @phan-var array<string,FullyQualifiedFunctionLikeName[]>
	 */
	private $hookSubscribers = [];

	/** @var self|null */
	private static $instance;

	public static function getInstance(): self {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Clear the extension.json cache, for testing purpose
	 *
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function clearCache(): void {
		$this->extensionJsonLoaded = false;
	}

	/**
	 * Add a hook implementation to our list.
	 *
	 * This also handles parser hooks which aren't normal hooks.
	 * Non-normal hooks start their name with a "!"
	 *
	 * @param string $hookName Name of hook
	 * @param FullyQualifiedFunctionLikeName $fqsen The implementing method
	 * @return bool true if already registered, false otherwise
	 */
	public function registerHook( string $hookName, FullyQualifiedFunctionLikeName $fqsen ): bool {
		$normalizedHookName = self::normalizeHookName( $hookName );
		if ( !isset( $this->hookSubscribers[$normalizedHookName] ) ) {
			$this->hookSubscribers[$normalizedHookName] = [];
		}
		if ( in_array( $fqsen, $this->hookSubscribers[$normalizedHookName], true ) ) {
			return true;
		}
		$this->hookSubscribers[$normalizedHookName][] = $fqsen;
		return false;
	}

	/**
	 * Register hooks from extension.json/skin.json
	 *
	 * Assumes extension.json/skin.json is in project root directory
	 * unless SECURITY_CHECK_EXT_PATH is set
	 */
	protected function loadExtensionJson(): void {
		if ( $this->extensionJsonLoaded ) {
			return;
		}
		foreach ( [ 'extension.json', 'skin.json' ] as $filename ) {
			$envPath = getenv( 'SECURITY_CHECK_EXT_PATH' );
			if ( $envPath ) {
				$jsonPath = $envPath . '/' . $filename;
			} else {
				$jsonPath = Config::projectPath( $filename );
			}
			if ( file_exists( $jsonPath ) ) {
				$this->readJsonFile( $jsonPath );
			}
		}
		$this->extensionJsonLoaded = true;
	}

	private function readJsonFile( string $jsonPath ): void {
		$json = json_decode( file_get_contents( $jsonPath ), true );
		if ( !is_array( $json ) || !isset( $json['Hooks'] ) || !is_array( $json['Hooks'] ) ) {
			return;
		}
		$namedHandlers = [];
		foreach ( $json['HookHandlers'] ?? [] as $name => $handler ) {
			// TODO: This key is not unique if more than one extension is being analyzed. Is that wanted, though?
			$namedHandlers[$name] = $handler;
		}

		foreach ( $json['Hooks'] as $hookName => $cbList ) {
			if ( isset( $cbList["handler"] ) ) {
				$cbList = $cbList["handler"];
			}
			if ( is_string( $cbList ) ) {
				$cbList = [ $cbList ];
			}

			foreach ( $cbList as $cb ) {
				if ( isset( $namedHandlers[$cb] ) ) {
					// TODO ObjectFactory not fully handled here. Would deserve some code in a general-purpose
					// MediaWiki plugin, see T275742.
					if ( isset( $namedHandlers[$cb]['class'] ) ) {
						$normalizedHookName = ucfirst( self::normalizeHookName( $hookName ) );
						$callbackString = $namedHandlers[$cb]['class'] . "::on$normalizedHookName";
					} elseif ( isset( $namedHandlers[$cb]['factory'] ) ) {
						// TODO: We'd need a CodeBase to retrieve the factory method and check its return value
						continue;
					} else {
						// @phan-suppress-previous-line PhanPluginDuplicateIfStatements
						continue;
					}
					$callback = FullyQualifiedMethodName::fromFullyQualifiedString( $callbackString );
				} elseif ( !str_contains( $cb, '::' ) ) {
					$callback = FullyQualifiedFunctionName::fromFullyQualifiedString( $cb );
				} else {
					$callback = FullyQualifiedMethodName::fromFullyQualifiedString( $cb );
				}
				$this->registerHook( $hookName, $callback );
			}
		}
	}

	/**
	 * Get a list of subscribers for a hook, given its handler method name.
	 *
	 * @return FullyQualifiedFunctionLikeName[]
	 */
	public function getHookSubscribers( string $handlerMethodName ): array {
		$this->loadExtensionJson();
		$normalizedHookName = self::normalizeHookName( preg_replace( '/^on/', '', $handlerMethodName ) );
		return $this->hookSubscribers[$normalizedHookName] ?? [];
	}

	/**
	 * Is a particular function implementing a special hook.
	 *
	 * @note This assumes that any given func will only implement
	 *   one hook
	 * @param FullyQualifiedFunctionLikeName $fqsen The function to check
	 * @return string|null The hook it is implementing or null if no hook
	 */
	public function isSpecialHookSubscriber( FullyQualifiedFunctionLikeName $fqsen ): ?string {
		$this->loadExtensionJson();
		$specialHooks = [
			'!ParserFunctionHook',
			'!ParserHook'
		];

		// @todo This is probably not the most efficient thing.
		foreach ( $specialHooks as $hook ) {
			if ( !isset( $this->hookSubscribers[$hook] ) ) {
				continue;
			}
			if ( in_array( $fqsen, $this->hookSubscribers[$hook], true ) ) {
				return $hook;
			}
		}
		return null;
	}

	/**
	 * Normalize hook names so that we can match them against handler names. See `HookContainer::getHookMethodName()`.
	 */
	private static function normalizeHookName( string $name ): string {
		return strtr( $name, ':\\-', '___' );
	}
}
