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
 *
 *     {{#field: level | content }}
 *
 * RAW RULE:
 *   - Extract metadata (SMW properties) from raw wikitext
 *   - But final output must be parsed normally
 */
class FieldFunction {

    private PermissionChecker $permissionChecker;

    public function __construct( PermissionChecker $permissionChecker ) {
        $this->permissionChecker = $permissionChecker;
    }

    public function execute(
        Parser $parser,
        PPFrame $frame,
        array $args
    ) {

        wfDebugLog( 'fieldpermissions', 'FieldFunction::execute: #field invoked' );

        /** 1. Prepare parser output */
        $output = $parser->getOutput();
        FieldPermissionsParserHelper::setupParserOutput( $output );

        /** ----------------------------------------------------------
         * 2. Parse args (raw content preserved)
         *
         * IMPORTANT:
         *   resolveContentArgumentsRaw() takes:
         *      - PPFrame
         *      - array $args
         *
         * (Your previous version incorrectly passed Parser + Frame)
         * ---------------------------------------------------------- */
        $resolved = FieldPermissionsParserHelper::resolveContentArgumentsRaw(
			$frame,
			$args
		);		

        if ( $resolved === null ) {
            return '';
        }

        [ $requiredLevel, $rawContent ] = $resolved;

        /** 3. Current user */
        $user = FieldPermissionsParserHelper::getUser( $parser );

        /** 4. Extract SMW properties (unparsed wikitext) */
        $properties = SMWPropertyExtractor::extractProperties( $rawContent );

        if ( $properties ) {
            $protected = $output->getExtensionData(
                'fieldPermissionsProtectedProperties'
            ) ?? [];

            foreach ( $properties as $property ) {
                $protected[$property] ??= [];

                if ( !in_array( $requiredLevel, $protected[$property], true ) ) {
                    $protected[$property][] = $requiredLevel;
                }

                PropertyPermissionRegistry::registerProperty(
                    $property,
                    $requiredLevel
                );
            }

            $output->setExtensionData(
                'fieldPermissionsProtectedProperties',
                $protected
            );
        }

        /** 5. Permission check */
        if ( !$this->permissionChecker->hasLevelAccess( $user, $requiredLevel ) ) {
            return '';
        }

        /** ------------------------------------------------------
         * 6. ALLOW:
         *     Fully parse the wikitext in the current frame context
         *     so template parameters and parser functions behave
         *     exactly as they would in normal page content.
         *
         *     Use insertStripItem() to wrap the HTML in a marker
         *     so MediaWiki treats it as already-parsed HTML and
         *     doesn't escape it when inserting it into the output.
         * ------------------------------------------------------ */
        $html = $parser->recursiveTagParse( $rawContent, $frame );
        return $parser->insertStripItem( $html );
    }

    public static function factory( Parser $parser, PPFrame $frame, array $args ) {
        $services = \MediaWiki\MediaWikiServices::getInstance();
        $checker = $services->get( \FieldPermissions\Permissions\PermissionChecker::SERVICE_NAME );
        return (new self( $checker ))->execute( $parser, $frame, $args );
    }
}
