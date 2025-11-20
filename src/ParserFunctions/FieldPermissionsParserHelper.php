<?php

namespace FieldPermissions\ParserFunctions;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MediaWiki\User\User;

/**
 * Helper utilities shared by FieldFunction and FieldGroupsFunction.
 *
 * Centralizes:
 *  - ParserOutput setup (cache disabling + feature flag)
 *  - Argument extraction + sanitization
 *  - Consistent access to the parser’s current user
 */
class FieldPermissionsParserHelper {

	/**
	 * Apply required parser-output configuration for permissions.
	 *
	 * - Disable caching
	 * - Mark the page output as using field-permission content
	 *
	 * @param ParserOutput $output
	 */
	public static function setupParserOutput( ParserOutput $output ): void {
		// Disable parser output caching
		$output->updateCacheExpiry( 0 );

		// Mark the parse as containing permission-protected fields
		$output->setExtensionData( 'usesFieldPermissions', true );

		// NOTE: do NOT add vary headers; OutputPage handles that via Hooks
	}

	/**
	 * Resolve arguments for parser functions (#field and #field-groups).
	 *
	 * Returns:
	 *   [ $controlArg, $content ]
	 * or null if invalid.
	 *
	 * Behavior:
	 *  - Expand using PPFrame (handles templates, magic words, etc.)
	 *  - Trim control argument (#field:level, #field-groups:group1)
	 *  - Leave content argument untrimmed (content may start with markup)
	 *  - Fail closed (return null) for empty or missing args
	 *
	 * @param PPFrame $frame
	 * @param array $args
	 * @return array{0: string, 1: string}|null
	 */
	public static function resolveContentArguments(
		PPFrame $frame,
		array $args
	): ?array {

		// Require exactly the two arguments required by the parser function
		if ( !isset( $args[0], $args[1] ) ) {
			return null;
		}

		// Expand wikitext
		$control = trim( $frame->expand( $args[0] ) );
		$content = $frame->expand( $args[1] );  // do not trim

		// Fail closed: empty control arg OR empty content
		// Important: field content must not be empty — empty fields should not bypass permission logic
		if ( $control === '' ) {
			return null;
		}

		// Allow content that evaluates to "0" or numeric strings — only reject fully empty
		if ( $content === '' ) {
			return null;
		}

		return [ $control, $content ];
	}

	/**
	 * Convenience wrapper for retrieving the current user from the parser.
	 *
	 * @param Parser $parser
	 * @return User
	 */
	public static function getUser( Parser $parser ): User {
		return $parser->getUser();
	}
}
