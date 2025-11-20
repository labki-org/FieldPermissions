<?php

namespace FieldPermissions\Permissions;

use FieldPermissions\Utils\PropertyNameNormalizer;

/**
 * Global registry for SMW property-level access restrictions.
 *
 * This registry is populated dynamically during parsing when {{#field:}}
 * or {{#field-groups:}} wraps an SMW property assertion.
 *
 * Structure:
 *   self::$propertyLevels = [
 *       'PropertyName' => ['internal', 'confidential'],
 *       'Email'        => ['internal'],
 *   ];
 *
 * This allows SMW result hooks to look up which fields require which
 * permission levels.
 *
 * This class intentionally has no constructor and is purely static. It does
 * not persist data across parse cycles – only for the duration of a single
 * page render.
 *
 * All property names are normalized using PropertyNameNormalizer for
 * canonical, consistent matching across different property formats.
 */
class PropertyPermissionRegistry {

	/**
	 * @var array<string,array<string>>  Property → list of required level names
	 */
	private static array $propertyLevels = [];

	/* ----------------------------------------------------------------------
	 * Normalization helper
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalize property names consistently using the shared normalizer.
	 *
	 * This ensures canonical matching across different property formats
	 * (e.g., "Property::value", "Property:=value", "Property::<value").
	 *
	 * @param string $raw
	 * @return string Normalized property name (canonical form)
	 */
	private static function normalizePropertyName( string $raw ): string {
		return PropertyNameNormalizer::normalize( $raw );
	}

	/* ----------------------------------------------------------------------
	 * Registry Operations
	 * ---------------------------------------------------------------------- */

	/**
	 * Register a property requiring a specific permission level.
	 *
	 * @param string $propertyName  SMW property name
	 * @param string $requiredLevel Symbolic level name (e.g. "public", "internal")
	 */
	public static function registerProperty( string $propertyName, string $requiredLevel ): void {
		$propertyName  = self::normalizePropertyName( $propertyName );
		$requiredLevel = trim( $requiredLevel );

		if ( $propertyName === '' || $requiredLevel === '' ) {
			return; // refuse invalid input silently
		}

		if ( !isset( self::$propertyLevels[$propertyName] ) ) {
			self::$propertyLevels[$propertyName] = [];
		}

		if ( !in_array( $requiredLevel, self::$propertyLevels[$propertyName], true ) ) {
			self::$propertyLevels[$propertyName][] = $requiredLevel;
		}
	}

	/**
	 * Retrieve all required levels for a property.
	 *
	 * @param string $propertyName
	 * @return array<string>
	 */
	public static function getPropertyLevels( string $propertyName ): array {
		$propertyName = self::normalizePropertyName( $propertyName );
		return self::$propertyLevels[$propertyName] ?? [];
	}

	/**
	 * Check if a property has any protection.
	 *
	 * @param string $propertyName
	 * @return bool
	 */
	public static function isPropertyProtected( string $propertyName ): bool {
		$propertyName = self::normalizePropertyName( $propertyName );
		return !empty( self::$propertyLevels[$propertyName] );
	}

	/**
	 * Remove a specific level requirement from a property.
	 *
	 * @param string $propertyName
	 * @param string $levelName
	 */
	public static function removePropertyLevel( string $propertyName, string $levelName ): void {
		$propertyName = self::normalizePropertyName( $propertyName );
		if ( !isset( self::$propertyLevels[$propertyName] ) ) {
			return;
		}

		self::$propertyLevels[$propertyName] = array_values(
			array_filter(
				self::$propertyLevels[$propertyName],
				static fn( string $lvl ) => $lvl !== $levelName
			)
		);

		// Clean empty entries
		if ( self::$propertyLevels[$propertyName] === [] ) {
			unset( self::$propertyLevels[$propertyName] );
		}
	}

	/**
	 * Remove a property entirely.
	 *
	 * @param string $propertyName
	 */
	public static function removeProperty( string $propertyName ): void {
		$propertyName = self::normalizePropertyName( $propertyName );
		unset( self::$propertyLevels[$propertyName] );
	}

	/**
	 * Return a list of all properties that have permission restrictions.
	 *
	 * @return array<string>
	 */
	public static function getAllProtectedProperties(): array {
		return array_keys( self::$propertyLevels );
	}

	/**
	 * Clear the registry — used for unit tests or page-level resets.
	 */
	public static function reset(): void {
		self::$propertyLevels = [];
	}
}
