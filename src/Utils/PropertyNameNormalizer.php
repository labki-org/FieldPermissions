<?php

namespace FieldPermissions\Utils;

/**
 * Utility for canonical normalization of SMW property names.
 *
 * Always prefers Semantic MediaWiki's DIProperty normalization when available.
 * Falls back to simple defensive string cleanup.
 */
class PropertyNameNormalizer {

	/**
	 * Normalize an SMW property name.
	 *
	 * @param string $rawPropertyName
	 * @return string Normalized property name
	 */
	public static function normalize( string $rawPropertyName ): string {
		$rawPropertyName = trim( $rawPropertyName );
		if ( $rawPropertyName === '' ) {
			return '';
		}

		// Prefer SMW canonical normalization
		if ( defined( 'SMW_VERSION' ) && class_exists( '\SMW\DIProperty' ) ) {
			$normalized = self::normalizeWithSMW( $rawPropertyName );
			if ( $normalized !== null ) {
				return $normalized;
			}
		}

		// Fallback normalization
		return self::normalizeDefensive( $rawPropertyName );
	}

	/**
	 * Normalize using Semantic MediaWiki's DIProperty.
	 *
	 * @param string $raw
	 * @return string|null Canonical property key or null on failure
	 */
	private static function normalizeWithSMW( string $raw ): ?string {
		try {
			$di = \SMW\DIProperty::newFromUserLabel( $raw );

			if ( $di === null ) {
				return null;
			}

			// getKey(): returns canonical SMW property key (e.g. _SKEY, _INST)
			$key = $di->getKey();
			if ( is_string( $key ) && $key !== '' ) {
				return $key;
			}

			// fallback: return label
			$label = $di->getLabel();
			return is_string( $label ) ? $label : null;

		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Defensive fallback: minimal cleanup without changing semantics.
	 *
	 * @param string $raw
	 * @return string
	 */
	private static function normalizeDefensive( string $raw ): string {
		// Collapse internal whitespace
		$raw = preg_replace( '/\s+/u', ' ', $raw );
		return trim( $raw );
	}

	/**
	 * Minimal validation — SMW property labels are extremely permissive.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function isValid( string $name ): bool {
		$name = trim( $name );

		if ( $name === '' ) {
			return false;
		}

		// Prevent parser-function collisions (#foo)
		if ( str_starts_with( $name, '#' ) ) {
			return false;
		}

		// Everything else is allowed. SMW property labels can contain colons,
		// unicode, spaces, punctuation, etc.
		return true;
	}
}
