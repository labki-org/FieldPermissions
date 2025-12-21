<?php

namespace PropertyPermissions;

use PropertyPermissions\Config\GroupLevelStore;
use PropertyPermissions\Config\VisibilityLevelStore;
use PropertyPermissions\Protection\VisibilityEditGuard;
use PropertyPermissions\Visibility\PermissionEvaluator;
use PropertyPermissions\Visibility\ResultPrinterVisibilityFilter;
use PropertyPermissions\Visibility\SmwQueryFilter;
use PropertyPermissions\Visibility\VisibilityResolver;
use MediaWiki\MediaWikiServices;

/**
 * Service wiring for PropertyPermissions.
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
	'PropertyPermissions.VisibilityLevelStore' => static function ( MediaWikiServices $services ) {
		return new VisibilityLevelStore(
			$services->getDBLoadBalancer()
		);
	},

	/**
	 * Maps user groups → maximum visibility level.
	 * Backed by table: fp_group_levels
	 */
	'PropertyPermissions.GroupLevelStore' => static function ( MediaWikiServices $services ) {
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
	'PropertyPermissions.VisibilityResolver' => static function ( MediaWikiServices $services ) {
		return new VisibilityResolver(
			$services->get( 'PropertyPermissions.VisibilityLevelStore' )
		);
	},

	/**
	 * Builds the user’s visibility profile:
	 *   - normalized list of groups
	 *   - maximum allowed visibility level
	 */
	'PropertyPermissions.PermissionEvaluator' => static function ( MediaWikiServices $services ) {
		return new PermissionEvaluator(
			$services->get( 'PropertyPermissions.GroupLevelStore' ),
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
	'PropertyPermissions.SmwQueryFilter' => static function ( MediaWikiServices $services ) {
		return new SmwQueryFilter(
			$services->get( 'PropertyPermissions.VisibilityResolver' ),
			$services->get( 'PropertyPermissions.PermissionEvaluator' )
		);
	},

	/**
	 * Tier-2 filtering:
	 * Used by Fp*ResultPrinter classes to filter SMW query columns
	 * (via PrintRequest visibility checks).
	 */
	'PropertyPermissions.ResultPrinterVisibilityFilter' => static function ( MediaWikiServices $services ) {
		return new ResultPrinterVisibilityFilter(
			$services->get( 'PropertyPermissions.VisibilityResolver' ),
			$services->get( 'PropertyPermissions.PermissionEvaluator' )
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
	'PropertyPermissions.VisibilityEditGuard' => static function ( MediaWikiServices $services ) {
		return new VisibilityEditGuard(
			$services->get( 'PropertyPermissions.VisibilityResolver' ),
			$services->get( 'PropertyPermissions.PermissionEvaluator' )
		);
	},
];
