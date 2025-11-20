<?php

/**
 * FieldPermissions extension — hooks implementation.
 *
 * Provides:
 *  - Parser functions (#field, #field-groups)
 *  - Parser output marking for permission-aware content
 *  - Cache variation based on user-level and group membership
 *  - SMW query field filtering (3.x + 4.x)
 *
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

namespace FieldPermissions;

use FieldPermissions\ParserFunctions\FieldFunction;
use FieldPermissions\ParserFunctions\FieldGroupsFunction;
use FieldPermissions\Permissions\LevelPermissionChecker;
use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Permissions\PermissionConfig;
use FieldPermissions\Permissions\PropertyPermissionRegistry;
use FieldPermissions\Utils\PropertyNameNormalizer;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

/**
 * Hook handlers for the FieldPermissions extension.
 */
class Hooks {

	/** @var PermissionConfig */
	private PermissionConfig $config;

	/** @var UserGroupManager */
	private UserGroupManager $groupManager;

	/**
	 * @param PermissionConfig $config
	 * @param UserGroupManager $groupManager
	 */
	public function __construct(
		PermissionConfig $config,
		UserGroupManager $groupManager
	) {
		$this->config       = $config;
		$this->groupManager = $groupManager;
	}

	/* ---------------------------------------------------------------------- */
	/*  PARSER INITIALIZATION                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Register parser functions (#field, #field-groups) and reset per-parse registry.
	 */
	public function onParserFirstCallInit( Parser $parser ): void {

		wfDebugLog( 'fieldpermissions', 'onParserFirstCallInit: Registering parser functions and resetting property registry' );

		// Reset property registry only once per parse cycle.
		PropertyPermissionRegistry::reset();

		$services          = MediaWikiServices::getInstance();
		$permissionChecker = $services->get( PermissionChecker::SERVICE_NAME );

		// #field
		$parser->setFunctionHook(
			'field',
			static function ( Parser $parser, ...$args ) use ( $permissionChecker ) {
				$fn    = new FieldFunction( $permissionChecker );
				$frame = $parser->getPreprocessor()->newFrame();
				return $fn->execute( $parser, $frame, $args );
			},
			Parser::SFH_OBJECT_ARGS
		);

		// #field-groups
		$parser->setFunctionHook(
			'field-groups',
			static function ( Parser $parser, ...$args ) use ( $permissionChecker ) {
				$fn    = new FieldGroupsFunction( $permissionChecker );
				$frame = $parser->getPreprocessor()->newFrame();
				return $fn->execute( $parser, $frame, $args );
			},
			Parser::SFH_OBJECT_ARGS
		);
	}

	/* ---------------------------------------------------------------------- */
	/*  OUTPUT PAGE CACHE CONTROL                                             */
	/* ---------------------------------------------------------------------- */

	/**
	 * Disable client-side caching when FieldPermissions content was used.
	 */
	public function onOutputPageParserOutput(
		OutputPage $out,
		ParserOutput $parserOutput
	): void {

		// Do NOT reset the registry here — this hook fires too late.

		if ( !$parserOutput->getExtensionData( 'usesFieldPermissions' ) ) {
			return;
		}

		wfDebugLog( 'fieldpermissions', 'onOutputPageParserOutput: Disabling client-side caching for FieldPermissions content' );

		// Fully disable caching
		$out->enableClientCache( false );
		$out->setCdnMaxage( 0 );
		$out->setLastModified( false );

		// Prevent edge caches from reusing content across users
		$out->addVaryHeader( 'Cookie' );
	}

	/* ---------------------------------------------------------------------- */
	/*  PARSER CACHE VARIATION                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Vary parser cache by permission level AND exact group membership.
	 *
	 * Ensures:
	 *  - Level-based permissions do not cross users
	 *  - Group-based permissions do not cross users with same level
	 */
	public function onPageRenderingHash(
		string &$confstr,
		UserIdentity $user,
		array &$forOptions
	): void {

		$groups        = $this->groupManager->getUserEffectiveGroups( $user );
		$maxLevelValue = 0;

		foreach ( $groups as $group ) {
			$levelName = $this->config->getGroupMaxLevel( $group );
			if ( $levelName === null ) {
				continue;
			}
			$value = $this->config->getLevelValue( $levelName );
			if ( $value !== null && $value > $maxLevelValue ) {
				$maxLevelValue = $value;
			}
		}

		// Hash the group memberships so distinct group sets don't share caches.
		$groupHash = md5( implode( ',', $groups ) );

		$confstr .= '!FieldPermissions:' . $maxLevelValue . ':' . $groupHash;

		wfDebugLog( 'fieldpermissions', 'onPageRenderingHash: User ' . $user->getName() . ' (ID: ' . $user->getId() . ') maxLevel=' . $maxLevelValue . ' groups=' . implode( ',', $groups ) );
	}

	/* ---------------------------------------------------------------------- */
	/*  SMW 4.x – FILTER PROPERTIES DURING PRINTING                           */
	/* ---------------------------------------------------------------------- */

	public function onSMWResultArrayBeforePrint(
		\SMWResultArray $resultArray,
		string &$output,
		\SMW\Query\ResultPrinters\ResultPrinter $printer
	) {
		// Normalize property name
		$property = PropertyNameNormalizer::normalize(
			$resultArray->getPrintRequest()->getLabel()
		);

		if ( !PropertyPermissionRegistry::isPropertyProtected( $property ) ) {
			return true;
		}

		$requiredLevels = PropertyPermissionRegistry::getPropertyLevels( $property );
		if ( !$requiredLevels ) {
			return true;
		}

		$checker = new LevelPermissionChecker(
			$this->config,
			$this->groupManager
		);

		$user = RequestContext::getMain()->getUser();

		wfDebugLog( 'fieldpermissions', 'onSMWResultArrayBeforePrint: Checking property "' . $property . '" for user ' . $user->getName() . ' (ID: ' . $user->getId() . ') requiredLevels=' . implode( ',', $requiredLevels ) );

		// Most restrictive level
		$maxValue = 0;
		$maxLevel = null;

		foreach ( $requiredLevels as $level ) {
			$value = $this->config->getLevelValue( $level );
			if ( $value !== null && $value > $maxValue ) {
				$maxValue = $value;
				$maxLevel = $level;
			}
		}

		// Allow or block
		if ( $maxLevel !== null && $checker->hasAccess( $user, $maxLevel ) ) {
			wfDebugLog( 'fieldpermissions', 'onSMWResultArrayBeforePrint: Allowing property "' . $property . '" for user ' . $user->getName() );
			return true;
		}

		wfDebugLog( 'fieldpermissions', 'onSMWResultArrayBeforePrint: Blocking property "' . $property . '" for user ' . $user->getName() . ' (required level: ' . $maxLevel . ')' );
		$output = '';
		return false;
	}

	/* ---------------------------------------------------------------------- */
	/*  SMW 3.x – FILTER DURING DATA PROCESSING                                */
	/* ---------------------------------------------------------------------- */

	public function onSMWResultArrayBeforeProcessing(
		\SMWResultArray $resultArray,
		array &$dataItems
	) {
		$property = PropertyNameNormalizer::normalize(
			$resultArray->getPrintRequest()->getLabel()
		);

		if ( !PropertyPermissionRegistry::isPropertyProtected( $property ) ) {
			return true;
		}

		$requiredLevels = PropertyPermissionRegistry::getPropertyLevels( $property );
		if ( !$requiredLevels ) {
			return true;
		}

		$checker = new LevelPermissionChecker(
			$this->config,
			$this->groupManager
		);

		$user = RequestContext::getMain()->getUser();

		wfDebugLog( 'fieldpermissions', 'onSMWResultArrayBeforeProcessing: Checking property "' . $property . '" for user ' . $user->getName() . ' (ID: ' . $user->getId() . ') requiredLevels=' . implode( ',', $requiredLevels ) );

		$maxValue = 0;
		$maxLevel = null;

		foreach ( $requiredLevels as $level ) {
			$value = $this->config->getLevelValue( $level );
			if ( $value !== null && $value > $maxValue ) {
				$maxValue = $value;
				$maxLevel = $level;
			}
		}

		if ( $maxLevel !== null && $checker->hasAccess( $user, $maxLevel ) ) {
			wfDebugLog( 'fieldpermissions', 'onSMWResultArrayBeforeProcessing: Allowing property "' . $property . '" for user ' . $user->getName() );
			return true;
		}

		wfDebugLog( 'fieldpermissions', 'onSMWResultArrayBeforeProcessing: Blocking property "' . $property . '" for user ' . $user->getName() . ' (required level: ' . $maxLevel . ')' );
		$dataItems = [];
		return false;
	}

}
