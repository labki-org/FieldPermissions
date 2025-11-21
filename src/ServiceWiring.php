<?php

namespace FieldPermissions;

use MediaWiki\MediaWikiServices;

use FieldPermissions\Config\VisibilityLevelStore;
use FieldPermissions\Config\GroupLevelStore;
use FieldPermissions\Visibility\VisibilityResolver;
use FieldPermissions\Visibility\PermissionEvaluator;
use FieldPermissions\Visibility\SmwQueryFilter;
use FieldPermissions\Visibility\ResultPrinterVisibilityFilter;
use FieldPermissions\Protection\VisibilityEditGuard;

/**
 * Service wiring for FieldPermissions.
 *
 * Registers all internal components into MediaWiki's dependency injection
 * container. These services are used across:
 *   - printer filtering (Fp*ResultPrinter)
 *   - Factbox filtering
 *   - edit-time and save-time enforcement
 *   - visibility/property resolution
 *
 * This file establishes the complete dependency graph for the extension.
 */
return [

	/* =====================================================================
	 * 1. Storage (DB-backed)
	 * =================================================================== */

	/**
	 * Stores named visibility levels (Public, LabMembers, PIOnly, ...).
	 * Backed by table: fp_visibility_levels
	 */
	'FieldPermissions.VisibilityLevelStore' => static function ( MediaWikiServices $services ) {
		return new VisibilityLevelStore(
			$services->getDBLoadBalancer()
		);
	},

	/**
	 * Maps user groups → maximum visibility level.
	 * Backed by table: fp_group_levels
	 */
	'FieldPermissions.GroupLevelStore' => static function ( MediaWikiServices $services ) {
		return new GroupLevelStore(
			$services->getDBLoadBalancer()
		);
	},

	/* =====================================================================
	 * 2. Core visibility model
	 * =================================================================== */

	/**
	 * Resolves property metadata:
	 *   - numeric visibility level
	 *   - VisibleTo group list
	 *
	 * Performs SMW store lookups for:
	 *   [[Has visibility level::...]]
	 *   [[Visible to::...]]
	 */
	'FieldPermissions.VisibilityResolver' => static function ( MediaWikiServices $services ) {
		return new VisibilityResolver(
			$services->get( 'FieldPermissions.VisibilityLevelStore' )
		);
	},

	/**
	 * Builds the user’s visibility profile:
	 *   - normalized list of groups
	 *   - maximum allowed visibility level
	 */
	'FieldPermissions.PermissionEvaluator' => static function ( MediaWikiServices $services ) {
		return new PermissionEvaluator(
			$services->get( 'FieldPermissions.GroupLevelStore' ),
			$services->getUserGroupManager()
		);
	},

	/* =====================================================================
	 * 3. Query-time filtering
	 * =================================================================== */

	/**
	 * Tier-1 filtering:
	 * Reduces property lists for:
	 *   - SMW Factbox
	 *   - (optionally) QueryResult post-processing
	 */
	'FieldPermissions.SmwQueryFilter' => static function ( MediaWikiServices $services ) {
		return new SmwQueryFilter(
			$services->get( 'FieldPermissions.VisibilityResolver' ),
			$services->get( 'FieldPermissions.PermissionEvaluator' )
		);
	},

	/**
	 * Tier-2 filtering:
	 * Used by Fp*ResultPrinter classes to filter SMW query columns
	 * (via PrintRequest visibility checks).
	 */
	'FieldPermissions.ResultPrinterVisibilityFilter' => static function ( MediaWikiServices $services ) {
		return new ResultPrinterVisibilityFilter(
			$services->get( 'FieldPermissions.VisibilityResolver' ),
			$services->get( 'FieldPermissions.PermissionEvaluator' )
		);
	},

	/* =====================================================================
	 * 4. Edit-time enforcement
	 * =================================================================== */

	/**
	 * Protects:
	 *   - visibility definition pages (Visibility:*)
	 *   - SMW property pages with visibility annotations
	 *   - content modifications that introduce / change visibility settings
	 */
	'FieldPermissions.VisibilityEditGuard' => static function ( MediaWikiServices $services ) {
		return new VisibilityEditGuard(
			$services->get( 'FieldPermissions.VisibilityResolver' ),
			$services->get( 'FieldPermissions.PermissionEvaluator' )
		);
	},
];
