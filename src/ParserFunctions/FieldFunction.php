<?php

namespace FieldPermissions\ParserFunctions;

use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Permissions\PropertyPermissionRegistry;
use FieldPermissions\ParserFunctions\FieldPermissionsParserHelper;
use FieldPermissions\Utils\SMWPropertyExtractor;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

/**
 * Parser function for level-based field permissions:
 *   {{#field: level | content }}
 *
 * Behavior:
 *   - Extracts permission level + content via helper
 *   - Tracks property access expectations (if SMW installed)
 *   - Performs permission check (fail-closed)
 *   - Returns content only if allowed
 */
class FieldFunction {

	/** @var PermissionChecker */
	private PermissionChecker $permissionChecker;

	public function __construct( PermissionChecker $permissionChecker ) {
		$this->permissionChecker = $permissionChecker;
	}

	/**
	 * Execute the #field parser function.
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public function execute(
		Parser $parser,
		PPFrame $frame,
		array $args
	): string {

		wfDebugLog( 'fieldpermissions', 'FieldFunction::execute: Processing #field parser function' );

		/* -----------------------------------------------------------
		 * 1. Configure parser output state (cache disable, flags)
		 * ----------------------------------------------------------- */
		$output = $parser->getOutput();
		FieldPermissionsParserHelper::setupParserOutput( $output );

		/* -----------------------------------------------------------
		 * 2. Parse arguments (fail-closed)
		 * ----------------------------------------------------------- */
		$resolved = FieldPermissionsParserHelper::resolveContentArguments(
			$frame,
			$args
		);

		if ( $resolved === null ) {
			// Invalid arguments → hide field
			wfDebugLog( 'fieldpermissions', 'FieldFunction::execute: Invalid arguments → DENY' );
			return '';
		}

		[ $requiredLevel, $content ] = $resolved;

		/* -----------------------------------------------------------
		 * 3. Determine current user
		 * ----------------------------------------------------------- */
		$user = FieldPermissionsParserHelper::getUser( $parser );

		/* -----------------------------------------------------------
		 * 4. Extract SMW properties used inside the content
		 *    (only if SMW installed — extractor handles fallback)
		 * ----------------------------------------------------------- */
		$properties = SMWPropertyExtractor::extractProperties( $content );

		if ( $properties ) {
			$protected = $output->getExtensionData(
				'fieldPermissionsProtectedProperties'
			) ?? [];

			foreach ( $properties as $property ) {

				// Track per-page mapping
				$protected[$property] ??= [];

				if ( !in_array( $requiredLevel, $protected[$property], true ) ) {
					$protected[$property][] = $requiredLevel;
				}

				// Register in the global static registry
				PropertyPermissionRegistry::registerProperty(
					$property,
					$requiredLevel
				);
			}

			// Store the updated structure back into ParserOutput
			$output->setExtensionData(
				'fieldPermissionsProtectedProperties',
				$protected
			);
		}

		/* -----------------------------------------------------------
		 * 5. Permission check
		 * ----------------------------------------------------------- */
		if ( $this->permissionChecker->hasLevelAccess(
			$user,
			$requiredLevel
		) ) {
			wfDebugLog( 'fieldpermissions', 'FieldFunction::execute: User ' . $user->getName() . ' granted access to level "' . $requiredLevel . '" → showing content' );
			return $content;
		}

		/* -----------------------------------------------------------
		 * 6. Fail-closed: hide content for unauthorized users
		 * ----------------------------------------------------------- */
		wfDebugLog( 'fieldpermissions', 'FieldFunction::execute: User ' . $user->getName() . ' denied access to level "' . $requiredLevel . '" → hiding content' );
		return '';
	}
}
