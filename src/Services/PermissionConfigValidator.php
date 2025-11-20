<?php

namespace FieldPermissions\Services;

use InvalidArgumentException;

/**
 * Validates the FieldPermissions configuration values from LocalSettings.php.
 *
 * Throws InvalidArgumentException on invalid config.
 */
class PermissionConfigValidator {

	/**
	 * Validate configuration array.
	 *
	 * @param array $cfg
	 * @throws InvalidArgumentException
	 */
	public function validate( array $cfg ): void {

		wfDebugLog( 'fieldpermissions', 'PermissionConfigValidator::validate: Starting configuration validation' );

		$levels = $cfg['FieldPermissionsLevels'] ?? [];
		$groupMax = $cfg['FieldPermissionsGroupMaxLevel'] ?? [];
		$groupSets = $cfg['FieldPermissionsGroupSets'] ?? [];

		$this->validateLevels( $levels );
		$this->validateGroupMaxLevels( $groupMax, $levels );
		$this->validateGroupSets( $groupSets );

		wfDebugLog( 'fieldpermissions', 'PermissionConfigValidator::validate: Configuration validation completed successfully' );
	}


	/**
	 * Validate the Levels definition.
	 *
	 * @param mixed $levels
	 * @throws InvalidArgumentException
	 */
	private function validateLevels( $levels ): void {
		if ( !is_array( $levels ) || empty( $levels ) ) {
			throw new InvalidArgumentException(
				"FieldPermissions: 'FieldPermissionsLevels' must be a non-empty associative array."
			);
		}

		foreach ( $levels as $name => $value ) {
			if ( !is_string( $name ) || trim( $name ) === '' ) {
				throw new InvalidArgumentException(
					"FieldPermissions: Level name must be a non-empty string."
				);
			}

			if ( !is_int( $value ) || $value < 0 ) {
				throw new InvalidArgumentException(
					"FieldPermissions: Level '$name' must map to a non-negative integer."
				);
			}
		}
	}

	/**
	 * Validate GroupMaxLevel mapping.
	 *
	 * @param mixed $groupMax
	 * @param array $levels
	 * @throws InvalidArgumentException
	 */
	private function validateGroupMaxLevels( $groupMax, array $levels ): void {
		if ( !is_array( $groupMax ) ) {
			throw new InvalidArgumentException(
				"FieldPermissions: 'FieldPermissionsGroupMaxLevel' must be an array."
			);
		}

		foreach ( $groupMax as $group => $levelName ) {

			if ( !is_string( $group ) || trim( $group ) === '' ) {
				throw new InvalidArgumentException(
					"FieldPermissions: Group names in GroupMaxLevel must be non-empty strings."
				);
			}

			if ( !isset( $levels[$levelName] ) ) {
				throw new InvalidArgumentException(
					"FieldPermissions: Group '$group' references unknown level '$levelName'."
				);
			}
		}
	}

	/**
	 * Validate group sets (for group-based access control).
	 *
	 * @param mixed $groupSets
	 * @throws InvalidArgumentException
	 */
	private function validateGroupSets( $groupSets ): void {
		if ( !is_array( $groupSets ) ) {
			throw new InvalidArgumentException(
				"FieldPermissions: 'FieldPermissionsGroupSets' must be an array."
			);
		}

		foreach ( $groupSets as $setName => $groups ) {

			if ( !is_string( $setName ) || trim( $setName ) === '' ) {
				throw new InvalidArgumentException(
					"FieldPermissions: Group set names must be non-empty strings."
				);
			}

			if ( !is_array( $groups ) ) {
				throw new InvalidArgumentException(
					"FieldPermissions: Group set '$setName' must contain an array of group names."
				);
			}

			foreach ( $groups as $group ) {
				if ( !is_string( $group ) || trim( $group ) === '' ) {
					throw new InvalidArgumentException(
						"FieldPermissions: Group set '$setName' contains an invalid group name."
					);
				}
			}
		}
	}
}

