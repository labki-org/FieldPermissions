<?php

namespace FieldPermissions\ParserFunctions;

use FieldPermissions\ParserFunctions\FieldPermissionsParserHelper;
use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Utils\StringUtils;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

class FieldGroupsFunction {

    private PermissionChecker $permissionChecker;

    public function __construct( PermissionChecker $permissionChecker ) {
        $this->permissionChecker = $permissionChecker;
    }

    public function execute(
        Parser $parser,
        PPFrame $frame,
        array $args
    ): string {

        wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction::execute invoked' );

        /* -------------------------------------------------------------
         * 1. Setup parser output (disable caching)
         * ------------------------------------------------------------- */
        $output = $parser->getOutput();
        FieldPermissionsParserHelper::setupParserOutput( $output );

        /* -------------------------------------------------------------
         * 2. Extract arguments (RAW content + expanded control)
         * ------------------------------------------------------------- */
        $resolved = FieldPermissionsParserHelper::resolveContentArgumentsRaw(
            $frame,
            $args
        );

        if ( $resolved === null ) {
            wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction: invalid args → DENY' );
            return '';
        }

        [ $groupsStr, $rawContent ] = $resolved;

        /* -------------------------------------------------------------
         * 3. Parse required groups
         * ------------------------------------------------------------- */
        $requiredGroups = StringUtils::splitCommaSeparated( $groupsStr );

        $requiredGroups = array_values( array_filter(
            $requiredGroups,
            static fn ( $x ) => trim( $x ) !== ''
        ) );

        if ( !$requiredGroups ) {
            wfDebugLog( 'fieldpermissions', 'FieldGroupsFunction: no valid groups → DENY' );
            return '';
        }

        /* -------------------------------------------------------------
         * 4. Current user
         * ------------------------------------------------------------- */
        $user = FieldPermissionsParserHelper::getUser( $parser );

        /* -------------------------------------------------------------
         * 5. Access check
         * ------------------------------------------------------------- */
        if ( !$this->permissionChecker->hasGroupAccess( $user, $requiredGroups ) ) {
            wfDebugLog(
                'fieldpermissions',
                'FieldGroupsFunction: DENY user ' . $user->getName() .
                ' required groups: ' . implode( ',', $requiredGroups )
            );
            return '';
        }

        /* -------------------------------------------------------------
         * 6. ALLOW — parse wikitext normally in the current frame and
         *    wrap it in a strip marker so the HTML isn't escaped.
         * ------------------------------------------------------------- */
        wfDebugLog(
            'fieldpermissions',
            'FieldGroupsFunction: ALLOW for user ' . $user->getName()
        );

        $html = $parser->recursiveTagParse( $rawContent, $frame );
        return $parser->insertStripItem( $html );
    }

    public static function factory( Parser $parser, PPFrame $frame, array $args ) {
        $services = \MediaWiki\MediaWikiServices::getInstance();
        $checker = $services->get( \FieldPermissions\Permissions\PermissionChecker::SERVICE_NAME );
        return ( new self( $checker ) )->execute( $parser, $frame, $args );
    }
}
