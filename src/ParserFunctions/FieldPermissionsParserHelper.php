<?php

namespace FieldPermissions\ParserFunctions;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MediaWiki\User\UserIdentity;

class FieldPermissionsParserHelper {

	public static function setupParserOutput( ParserOutput $output ): void {
		$output->updateCacheExpiry( 0 );
		$output->setExtensionData( 'usesFieldPermissions', true );
	}

	/**
	 * RAW mode (for SMW property extraction).
	 *
	 * First arg expands, second arg stays unexpanded.
	 */
	public static function resolveContentArgumentsRaw(
		PPFrame $frame,
		array $args
	): ?array {
	
		// Must have exactly two args
		if ( !isset( $args[0], $args[1] ) ) {
			return null;
		}
	
		/** CONTROL ARGUMENT (expand) */
		$control = trim( $frame->expand( $args[0] ) );
		if ( $control === '' ) {
			return null;
		}
	
		/**
		 * CONTENT ARGUMENT
		 *
		 * IMPORTANT:
		 *  - Cast-to-string on PPNode instances produces the XML dump
		 *    (e.g., "<part><name index=\"1\"/>…") instead of the original
		 *    wikitext.
		 *  - We still need the actual wikitext so it can be parsed normally
		 *    by recursiveTagParse()/internalParse().
		 */
		$rawContent = $frame->expand( $args[1] );
	
		if ( $rawContent === '' ) {
			return null;
		}
	
		return [ $control, $rawContent ];
	}
	

	public static function resolveContentArguments(
		PPFrame $frame,
		array $args
	): ?array {

		if ( !isset( $args[0], $args[1] ) ) {
			return null;
		}

		$control = trim( $frame->expand( $args[0] ) );
		$content = $frame->expand( $args[1] );

		if ( $control === '' || $content === '' ) {
			return null;
		}

		return [ $control, $content ];
	}

	public static function getUser( Parser $parser ): UserIdentity {
		return $parser->getOptions()->getUserIdentity();
	}
}
