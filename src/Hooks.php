<?php

namespace FieldPermissions;

use FieldPermissions\Protection\VisibilityEditGuard;
use FieldPermissions\Visibility\SmwQueryFilter;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;

use SMW\DIWikiPage;

/**
 * FieldPermissions – Central MediaWiki + Semantic MediaWiki hook implementations.
 *
 * Visibility filtering architecture (three-tier):
 *
 *   Tier 1 — Early override
 *       SMW::Settings::BeforeInitializationComplete
 *       Replace smwgResultFormats inside SMW’s Settings object.
 *
 *   Tier 2 — Global fallback
 *       SetupAfterCache
 *       Reinforce global smwgResultFormats[].
 *
 *   Tier 3 — Late final override (critical)
 *       ExtensionFunctions
 *       Executed after SMW completes initialization and after the
 *       ResultPrinterRegistry has already been constructed.
 *       This ensures our printers cannot be overwritten by SMW.
 *
 * Remaining responsibilities:
 *   - filter Factbox properties
 *   - vary parser cache by user visibility profile
 *   - enforce edit restrictions and content validation
 *   - install visibility-related DB tables
 */
class Hooks {

	/* ======================================================================
	 * 0. TIER 1 — Early override of SMW result printers
	 * ====================================================================== */

	/**
	 * Hook: SMW::Settings::BeforeInitializationComplete
	 *
	 * Primary mechanism for overriding SMW result printers inside the SMW
	 * Settings object before SMW builds its printer registry.
	 */
	public static function onSMWSettingsBeforeInitializationComplete( $settings ) {
		wfDebugLog( 'fieldpermissions', 'SMW settings hook: overriding result printers.' );
		self::overrideResultFormats( $settings );
		return true;
	}

	/* ======================================================================
	 * 0a. TIER 2 — Global fallback override
	 * ====================================================================== */

	/**
	 * Hook: SetupAfterCache
	 *
	 * Ensures global smwgResultFormats is updated for SMW versions that
	 * still consult the global array during result printer resolution.
	 */
	public static function onSetupAfterCache() {
		wfDebugLog( 'fieldpermissions', 'SetupAfterCache: reinforcing printer overrides.' );
        self::overrideResultFormats();
		return true;
	}

	/* ======================================================================
	 * 0b. TIER 3 — FINAL late override (critical)
     * ----------------------------------------------------------------------
     * Hook: ExtensionFunctions
     *
     * Executed very late in MediaWiki initialization – after SMW has fully
     * constructed its ResultPrinterRegistry and after all SMW components have
     * had a chance to overwrite global mappings.
     *
     * Re-applying the printer override here ensures our custom printers are
     * final. Without this layer, SMW’s late initialization can silently undo
     * earlier overrides.
	 * ====================================================================== */

	public static function onExtensionFunction() {
		wfDebugLog( 'fieldpermissions', 'ExtensionFunctions: applying final visibility printer override.' );
		self::overrideResultFormats();
		return true;
	}

	/* ======================================================================
	 * Result Printer Override Helper
	 * ====================================================================== */

	/**
	 * Replace SMW result format → class mappings with FieldPermissions printers.
	 *
	 * Updates:
	 *   - the SMW Settings object (when provided)
	 *   - global $smwgResultFormats (always)
	 */
	private static function overrideResultFormats( $settings = null ): void {

		$overrides = [
			'table'      => \FieldPermissions\SMW\Printers\FpTableResultPrinter::class,
			'broadtable' => \FieldPermissions\SMW\Printers\FpTableResultPrinter::class,

			'list'       => \FieldPermissions\SMW\Printers\FpListResultPrinter::class,
			'ul'         => \FieldPermissions\SMW\Printers\FpListResultPrinter::class,
			'ol'         => \FieldPermissions\SMW\Printers\FpListResultPrinter::class,

			'template'   => \FieldPermissions\SMW\Printers\FpTemplateResultPrinter::class,

			'json'       => \FieldPermissions\SMW\Printers\FpJsonResultPrinter::class,
			'csv'        => \FieldPermissions\SMW\Printers\FpCsvResultPrinter::class,
			'dsv'        => \FieldPermissions\SMW\Printers\FpCsvResultPrinter::class,

			'default'    => \FieldPermissions\SMW\Printers\FpTableResultPrinter::class,
		];

		/* 1) Update SMW Settings object (Tier 1) */
		if (
			$settings &&
			is_object( $settings ) &&
			method_exists( $settings, 'get' ) &&
			method_exists( $settings, 'set' )
		) {
			$current = $settings->get( 'smwgResultFormats' );

			if ( is_array( $current ) ) {
				$merged = array_merge( $current, $overrides );
				$settings->set( 'smwgResultFormats', $merged );
			}
		}

		/* 2) Update global fallback (Tier 2 & Tier 3) */
		global $smwgResultFormats;

		if ( !is_array( $smwgResultFormats ?? null ) ) {
			$smwgResultFormats = [];
		}

		$smwgResultFormats = array_merge( $smwgResultFormats, $overrides );
		$GLOBALS['smwgResultFormats'] = $smwgResultFormats;
	}

	/* ======================================================================
	 * 1. Schema Installation
	 * ====================================================================== */

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dir = dirname( __DIR__ );

		if ( $updater->getDB()->getType() === 'sqlite' ) {
			$script = "$dir/sql/sqlite/tables.sql";
			$updater->addExtensionTable( 'fp_visibility_levels', $script );
			$updater->addExtensionTable( 'fp_group_levels', $script );
		}

		return true;
	}

	/* ======================================================================
	 * 2. Parser Cache Variation
	 * ====================================================================== */

	public static function onPageRenderingHash( &$confstr, $user, &$forOptions ) {
		$services = MediaWikiServices::getInstance();

		if ( !$services->hasService( 'FieldPermissions.PermissionEvaluator' ) ) {
			return true;
		}

		$profile = $services
			->get( 'FieldPermissions.PermissionEvaluator' )
			->getUserProfile( $user );

		$confstr .= '!fp-level=' . $profile->getMaxLevel();
		$confstr .= '!fp-groups=' . implode( ',', $profile->getGroups() );

		return true;
	}

	/* ======================================================================
	 * 3. Edit Permission Enforcement
	 * ====================================================================== */

	public static function onGetUserPermissionsErrors(
		$title,
		$user,
		$action,
		&$result
	) {
		if ( $action !== 'edit' && $action !== 'create' ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		if ( !$services->hasService( 'FieldPermissions.VisibilityEditGuard' ) ) {
			return true;
		}

		$status = $services
			->get( 'FieldPermissions.VisibilityEditGuard' )
			->checkEditPermission( $title, $user );

		if ( !$status->isOK() ) {
			$result = $status->getErrorsArray();
			return false;
		}

		return true;
	}

	/* ======================================================================
	 * 4. Save-Time Content Validation
	 * ====================================================================== */

	public static function onMultiContentSave(
		RenderedRevision $renderedRevision,
		UserIdentity $user,
		$performer,
		$slots,
		$editResult = null
	) {
		$services = MediaWikiServices::getInstance();

		if ( !$services->hasService( 'FieldPermissions.VisibilityEditGuard' ) ) {
			return true;
		}

		$guard = $services->get( 'FieldPermissions.VisibilityEditGuard' );

		foreach ( $slots as $slot ) {
			$status = $guard->validateContent( $slot->getContent(), $user );
			if ( !$status->isOK() ) {
				return $status;
			}
		}

		return true;
	}

	/* ======================================================================
	 * 5. SMW Factbox Filtering
	 * ====================================================================== */

	public static function onSMWFactboxBeforeContentGeneration(
		DIWikiPage $subject,
		array &$properties
	) {
		$services = MediaWikiServices::getInstance();

		if ( !$services->hasService( 'FieldPermissions.SmwQueryFilter' ) ) {
			return;
		}

		$services
			->get( 'FieldPermissions.SmwQueryFilter' )
			->filterFactboxProperties( $subject, $properties );
	}
}
