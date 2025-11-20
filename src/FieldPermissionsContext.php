<?php

namespace FieldPermissions;

use MediaWiki\Parser\ParserOutput;

/**
 * Context object for per-parse state.
 *
 * This context is intentionally lightweight: a simple key/value store
 * tied to a ParserOutput instance. This avoids global state leaking
 * across parses while matching MediaWiki's natural parse lifecycle.
 */
class FieldPermissionsContext {

	/** @var array<string,array<string,mixed>> ParserOutput ID → context data */
	private static array $contexts = [];

	/**
	 * Return an identifier for a ParserOutput instance.
	 */
	private static function getOutputId( ParserOutput $po ): string {
		// ParserOutput is not reused across parses, so this is safe.
		return spl_object_hash( $po );
	}

	/**
	 * Get a value from context for this parse.
	 *
	 * @param ParserOutput $po
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get( ParserOutput $po, string $key, $default = null ) {
		$id = self::getOutputId( $po );
		return self::$contexts[$id][$key] ?? $default;
	}

	/**
	 * Set a value into this parse's context.
	 *
	 * @param ParserOutput $po
	 * @param string $key
	 * @param mixed $value
	 */
	public static function set( ParserOutput $po, string $key, $value ): void {
		$id = self::getOutputId( $po );
		self::$contexts[$id][$key] = $value;
	}

	/**
	 * Clear context for a specific parse.
	 *
	 * Called automatically at the start of parsing.
	 */
	public static function clear( ParserOutput $po ): void {
		$id = self::getOutputId( $po );
		unset( self::$contexts[$id] );
	}

	/**
	 * Clear all contexts — testing only.
	 */
	public static function clearAll(): void {
		self::$contexts = [];
	}
}
