<?php

namespace FieldPermissions\Services;

use MediaWiki\MediaWikiServices;
use FieldPermissions\Permissions\PermissionConfig;
use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Services\PermissionConfigValidator;

return [

	// -----------------------------
	// PermissionConfig service
	// -----------------------------
	PermissionConfig::SERVICE_NAME => static function ( MediaWikiServices $services ) {

		// Fetch config from LocalSettings (can be null)
		$raw = [
			'FieldPermissionsLevels'         => $services->getMainConfig()->get( 'FieldPermissionsLevels' ),
			'FieldPermissionsGroupMaxLevel'  => $services->getMainConfig()->get( 'FieldPermissionsGroupMaxLevel' ),
			'FieldPermissionsGroupSets'      => $services->getMainConfig()->get( 'FieldPermissionsGroupSets' ),
		];

		// 1. Merge with defaults (inside factory)
		$factory = new FieldPermissionsServiceFactory();
		$merged = $factory->mergeWithDefaults( $raw );

		// 2. Validate final merged config
		$validator = new PermissionConfigValidator();
		$validator->validate( $merged );

		// 3. Build immutable config object
		return new PermissionConfig( $merged );
	},


	// -----------------------------
	// PermissionChecker service
	// -----------------------------
	PermissionChecker::SERVICE_NAME => static function ( MediaWikiServices $services ) {

		$config = $services->get( PermissionConfig::SERVICE_NAME );
		$groupMgr = $services->getUserGroupManager();

		return new PermissionChecker( $config, $groupMgr );
	},

];
