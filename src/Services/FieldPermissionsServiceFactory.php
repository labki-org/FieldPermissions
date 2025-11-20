<?php

namespace FieldPermissions\Services;

use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Permissions\PermissionConfig;
use FieldPermissions\Services\PermissionConfigValidator;
use MediaWiki\MediaWikiServices;

/**
 * Central factory for creating FieldPermissions services.
 *
 * Responsibilities:
 *   - Merge LocalSettings overrides with built-in defaults
 *   - Validate the final merged config
 *   - Construct PermissionConfig + PermissionChecker
 */
class FieldPermissionsServiceFactory {

	/** ------------------------------------------------------------------
	 *  DEFAULTS
	 * ------------------------------------------------------------------ */
	private function getDefaultConfig(): array {
		return [
			'FieldPermissionsLevels' => [
				'public'    => 0,
				'internal'  => 10,
				'sensitive' => 20,
			],

			'FieldPermissionsGroupMaxLevel' => [
				'*'          => 'public',
				'user'       => 'public',
				'lab_member' => 'internal',
				'pi'         => 'sensitive',
			],

			'FieldPermissionsGroupSets' => [
				'all_admins' => [ 'sysop', 'pi' ],
			],
		];
	}

	/**
	 * Merge LocalSettings values with defaults.
	 *
	 * LocalSettings always wins when defined.
	 */
	public function mergeWithDefaults( array $raw ): array {
		$defaults = $this->getDefaultConfig();

		return [
			'FieldPermissionsLevels' =>
				!empty( $raw['FieldPermissionsLevels'] )
					? $raw['FieldPermissionsLevels']
					: $defaults['FieldPermissionsLevels'],

			'FieldPermissionsGroupMaxLevel' =>
				!empty( $raw['FieldPermissionsGroupMaxLevel'] )
					? $raw['FieldPermissionsGroupMaxLevel']
					: $defaults['FieldPermissionsGroupMaxLevel'],

			'FieldPermissionsGroupSets' =>
				!empty( $raw['FieldPermissionsGroupSets'] )
					? $raw['FieldPermissionsGroupSets']
					: $defaults['FieldPermissionsGroupSets'],
		];
	}

	/** ------------------------------------------------------------------
	 *  SERVICE: PermissionConfig
	 * ------------------------------------------------------------------ */
	public function newPermissionConfig( MediaWikiServices $services ): PermissionConfig {
		wfDebugLog( 'fieldpermissions', 'FieldPermissionsServiceFactory::newPermissionConfig: Begin' );

		$mainConfig = $services->getMainConfig();

		// Raw config from LocalSettings (may be missing completely)
		$raw = [
			'FieldPermissionsLevels'        => $mainConfig->get( 'FieldPermissionsLevels' ),
			'FieldPermissionsGroupMaxLevel' => $mainConfig->get( 'FieldPermissionsGroupMaxLevel' ),
			'FieldPermissionsGroupSets'     => $mainConfig->get( 'FieldPermissionsGroupSets' ),
		];

		// 1. Merge with built-in defaults
		$merged = $this->mergeWithDefaults( $raw );

		// 2. Validate final configuration
		$validator = new PermissionConfigValidator();
		$validator->validate( $merged );

		// 3. Construct immutable config object
		$config = new PermissionConfig( $merged );

		wfDebugLog(
			'fieldpermissions',
			'newPermissionConfig: Loaded ' .
			count( $merged['FieldPermissionsLevels'] )        . ' levels, ' .
			count( $merged['FieldPermissionsGroupMaxLevel'] ) . ' group max levels, ' .
			count( $merged['FieldPermissionsGroupSets'] )     . ' group sets'
		);

		return $config;
	}

	/** ------------------------------------------------------------------
	 *  SERVICE: PermissionChecker
	 * ------------------------------------------------------------------ */
	public function newPermissionChecker( MediaWikiServices $services ): PermissionChecker {
		$config = $services->get( PermissionConfig::SERVICE_NAME );
		$groupManager = $services->getUserGroupManager();

		return new PermissionChecker( $config, $groupManager );
	}
}
