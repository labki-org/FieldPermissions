<?php

namespace FieldPermissions\ParserFunctions;

use FieldPermissions\ParserFunctions\FieldPermissionsParserHelper;
use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Utils\StringUtils;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

/**
 * Parser function for group-based field permissions:
 *
 *   {{#field-groups: group1, group2 | content }}
 *
 * Behavior:
 *   - Groups must match user’s effective groups OR group-set names
 *   - Content only shows if user belongs to >=1 allowed group
 *   - Fail-closed on missing args or malformed group directives
 */
class FieldGroupsFunction {

	private PermissionChecker $permissionChecker;

	public function __construct( PermissionChecker $permissionChecker ) {
		$this->permissionChecker = $permissionChecker;
	}

	/**
	 * Execute the #field-groups parser function.
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

		wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction::execute: Processing #field-groups parser function' );

		/* -----------------------------------------------------------
		 * 1. Configure parser output (cache disable)
		 * ----------------------------------------------------------- */
		$output = $parser->getOutput();
		FieldPermissionsParserHelper::setupParserOutput( $output );

		/* -----------------------------------------------------------
		 * 2. Resolve arguments
		 * ----------------------------------------------------------- */
		$resolved = FieldPermissionsParserHelper::resolveContentArguments(
			$frame,
			$args
		);

		if ( $resolved === null ) {
			// Missing args or empty args
			wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction::execute: Invalid arguments → DENY' );
			return '';
		}

		[ $groupsStr, $content ] = $resolved;

		/* -----------------------------------------------------------
		 * 3. Parse the comma-separated group list
		 * ----------------------------------------------------------- */
		$requiredGroups = StringUtils::splitCommaSeparated( $groupsStr );

		// Remove empty entries (e.g., trailing commas)
		$requiredGroups = array_values(
			array_filter( $requiredGroups, static fn ( $x ) => $x !== '' )
		);

		if ( !$requiredGroups ) {
			// No valid groups → fail closed
			wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction::execute: No valid groups specified → DENY' );
			return '';
		}

		/* -----------------------------------------------------------
		 * 4. Get current user
		 * ----------------------------------------------------------- */
		$user = FieldPermissionsParserHelper::getUser( $parser );

		/* -----------------------------------------------------------
		 * 5. Permission check
		 * ----------------------------------------------------------- */
		if ( $this->permissionChecker->hasGroupAccess( $user, $requiredGroups ) ) {
			wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction::execute: User ' . $user->getName() . ' granted access to groups "' . implode( ',', $requiredGroups ) . '" → showing content' );
			return $content;
		}

		/* -----------------------------------------------------------
		 * 6. Fail closed → hide content
		 * ----------------------------------------------------------- */
		wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction::execute: User ' . $user->getName() . ' denied access to groups "' . implode( ',', $requiredGroups ) . '" → hiding content' );
		return '';
	}
}
