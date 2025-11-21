<?php

namespace FieldPermissions;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use MediaWiki\Page\PageIdentity;

use SMW\DIWikiPage;

use FieldPermissions\Protection\VisibilityEditGuard;
use FieldPermissions\Visibility\SmwQueryFilter;

use SMW\Settings as SMWSettings;

class Hooks {

    /**
     * CRITICAL: ExtensionFunctions hook fires VERY LATE in MediaWiki initialization,
     * after SMW has already set up its globals. This is the ONLY reliable way to
     * override $smwgResultFormats before SMW's printer factory reads it.
     * 
     * Without this hook, SMW reverts to default printers and our filtering never runs.
     * This took extensive debugging to identify as the root cause.
     */
    public static function onExtensionFunction() {
        wfDebugLog( 'fieldpermissions', 'onExtensionFunction called.' );
        
        // 1. Try global override again (late binding)
        self::overrideFormats();

        // 2. Try to inject into SMW Factory if available
        if ( class_exists( '\SMW\Query\ResultPrinters\ResultPrinter' ) ) {
            wfDebugLog( 'fieldpermissions', 'CHECK: SMW ResultPrinter class exists.' );
        }

        // 3. Try to find the Registry Service
        // Common service names in SMW 4+
        $possibleServices = [
            'SMW.ResultPrinterRegistry',
            'SMW.Registry.ResultPrinter',
            'SMW.ResultPrinterFactory'
        ];

        if ( class_exists( '\MediaWiki\MediaWikiServices' ) ) {
            $services = MediaWikiServices::getInstance();
            foreach ( $possibleServices as $serviceName ) {
                if ( $services->hasService( $serviceName ) ) {
                    wfDebugLog( 'fieldpermissions', "CHECK: Found service: $serviceName" );
                    // Try to register here if it's a registry
                    try {
                        $service = $services->get( $serviceName );
                        if ( method_exists( $service, 'registerPrinterClass' ) ) {
                             wfDebugLog( 'fieldpermissions', "CHECK: Registering printers on service $serviceName" );
                             // Register manually
                             $service->registerPrinterClass( 'table', \FieldPermissions\SMW\Printers\FpTableResultPrinter::class );
                             // ... others
                        }
                    } catch ( \Exception $e ) {
                        wfDebugLog( 'fieldpermissions', "CHECK: Failed to access service $serviceName: " . $e->getMessage() );
                    }
                }
            }
        }
    }

    /* ----------------------------------------------------------------------
     * 0. Initialize - Override SMW Settings (Tier 2)
     * -------------------------------------------------------------------- */
    public static function onSMWSettingsBeforeInitializationComplete( $settings = null ) {
        wfDebugLog( 'fieldpermissions', 'onSMWSettingsBeforeInitializationComplete called.' );
        self::overrideFormats( $settings );
        return true;
    }

    /* ----------------------------------------------------------------------
     * 0a. SetupAfterCache - Force Global Override
     * -------------------------------------------------------------------- */
    public static function onSetupAfterCache() {
        wfDebugLog( 'fieldpermissions', 'onSetupAfterCache called - forcing global overrides.' );
        self::overrideFormats();
        return true;
    }

    /**
     * CRITICAL: This function forcefully replaces SMW's default result printers
     * with our custom Fp*ResultPrinter classes that include filtering logic.
     * 
     * Must update BOTH the $settings object AND $GLOBALS['smwgResultFormats']
     * because SMW reads from different sources depending on initialization order.
     * The double-write to $GLOBALS is a defensive measure to ensure persistence.
     */
    private static function overrideFormats( $settings = null ) {
        // Define our overrides
        // NOTE: Template format is commented out because TemplateResultPrinter class
        // doesn't exist in this SMW version. Template queries will use SMW's default
        // printer (without filtering) until we identify the correct class to extend.
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

        // 1. Update Settings object if provided
        if ( $settings && is_object( $settings ) && method_exists( $settings, 'get' ) && method_exists( $settings, 'set' ) ) {
            $current = $settings->get( 'smwgResultFormats' );
            if ( is_array( $current ) ) {
                $settings->set( 'smwgResultFormats', array_merge( $current, $overrides ) );
                wfDebugLog( 'fieldpermissions', 'OverrideFormats: Updated SMW Settings object.' );
            }
        }

        // 2. Update Globals (Force)
        // CRITICAL: Must update both $smwgResultFormats and $GLOBALS directly
        // because SMW's factory may read from either depending on timing
        global $smwgResultFormats;
        if ( !isset( $smwgResultFormats ) || !is_array( $smwgResultFormats ) ) {
            $smwgResultFormats = [];
        }
        $smwgResultFormats = array_merge( $smwgResultFormats, $overrides );
        
        // Double check by writing to $GLOBALS directly to be safe
        // This defensive approach ensures the override persists even if SMW
        // reinitializes the global variable later
        foreach ( $overrides as $fmt => $class ) {
            $GLOBALS['smwgResultFormats'][$fmt] = $class;
        }

        wfDebugLog( 'fieldpermissions', 'OverrideFormats: Updated globals.' );
    }

    /* ----------------------------------------------------------------------
     * 0b. Initialize - Register printers after SMW loads
     * -------------------------------------------------------------------- */
    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
        wfDebugLog( 'fieldpermissions', 'onLoadExtensionSchemaUpdates hook called' );
        $dir = dirname( __DIR__ );

        if ( $updater->getDB()->getType() === 'sqlite' ) {
            $updater->addExtensionTable( 'fp_visibility_levels', "$dir/sql/sqlite/tables.sql" );
            $updater->addExtensionTable( 'fp_group_levels', "$dir/sql/sqlite/tables.sql" );
        }

        return true;
    }
    
    /* ----------------------------------------------------------------------
     * 1. BeforePageDisplay — working as is
     * -------------------------------------------------------------------- */
    public static function onBeforePageDisplay( $out, $skin ) {
        wfDebugLog( 'fieldpermissions', 'TEST: onBeforePageDisplay hook called for page: ' . $out->getTitle()->getPrefixedText() );
        
        // CHECK: Does autoloader work?
        if ( class_exists( 'FieldPermissions\SMW\Printers\FpTableResultPrinter' ) ) {
             wfDebugLog( 'fieldpermissions', 'CHECK: FpTableResultPrinter class exists and is autoloadable.' );
        } else {
             wfDebugLog( 'fieldpermissions', 'CHECK: FpTableResultPrinter class NOT found.' );
        }

        // CHECK: Are globals persisted?
        global $smwgResultFormats;
        if ( isset( $smwgResultFormats['table'] ) ) {
             wfDebugLog( 'fieldpermissions', 'CHECK: global $smwgResultFormats[table] is: ' . $smwgResultFormats['table'] );
        } else {
             wfDebugLog( 'fieldpermissions', 'CHECK: global $smwgResultFormats[table] is NOT set.' );
        }

        // ATTEMPT: Force SMW Registry Load
        try {
            // Try to get the ApplicationFactory if available
            if ( class_exists( '\SMW\ApplicationFactory' ) ) {
                $factory = \SMW\ApplicationFactory::getInstance();
                wfDebugLog( 'fieldpermissions', 'CHECK: Found \SMW\ApplicationFactory' );
                
                // Try to get registry - methods vary by version
                if ( method_exists( $factory, 'getResultPrinterRegistry' ) ) {
                    $registry = $factory->getResultPrinterRegistry();
                    wfDebugLog( 'fieldpermissions', 'CHECK: Retrieved ResultPrinterRegistry via ApplicationFactory' );
                    // Just getting it might trigger the init hook
                }
            }
        } catch ( \Exception $e ) {
            wfDebugLog( 'fieldpermissions', 'CHECK: Error trying to access SMW registry: ' . $e->getMessage() );
        }
        
        return true;
    }

    /**
     * CRITICAL: Makes parser cache vary by user's visibility level.
     * 
     * MediaWiki's parser cache is user-agnostic by default - one cached version
     * for everyone. Without this hook, the first user's view (e.g., Admin seeing
     * all columns) gets cached and served to ALL users, breaking permission filtering.
     * 
     * This hook adds the user's max level and groups to the cache key, ensuring
     * each permission level gets its own cached version. This was the final piece
     * needed to make multi-user filtering work correctly.
     */
    public static function onPageRenderingHash( &$confstr, $user, &$forOptions ) {
        $services = MediaWikiServices::getInstance();
        
        if ( !$services->hasService( 'FieldPermissions.PermissionEvaluator' ) ) {
            return true;
        }
        
        $evaluator = $services->get( 'FieldPermissions.PermissionEvaluator' );
        $profile = $evaluator->getUserProfile( $user );
        
        // Add user's max level and groups to cache key
        $confstr .= '!fplevel=' . $profile->getMaxLevel();
        $confstr .= '!fpgroups=' . implode( ',', $profile->getGroups() );
        
        wfDebugLog( 'fieldpermissions', "PageRenderingHash: Added cache key for user " . $user->getName() . " with level " . $profile->getMaxLevel() );
        
        return true;
    }

    /* ----------------------------------------------------------------------
     * 2. Permission denial during edit/create
     * -------------------------------------------------------------------- */
    public static function onGetUserPermissionsErrors(
        $title,
        $user,
        $action,
        &$result
    ) {
        wfDebugLog( 'fieldpermissions', 'onGetUserPermissionsErrors hook called for action: ' . $action . ', page: ' . ( $title ? $title->getPrefixedText() : 'null' ) );
        
        if ( $action !== 'edit' && $action !== 'create' ) {
            return true;
        }

        $services = MediaWikiServices::getInstance();

        if ( !$services->hasService( 'FieldPermissions.VisibilityEditGuard' ) ) {
            wfDebugLog( 'fieldpermissions', 'FieldPermissions.VisibilityEditGuard service not available' );
            return true;
        }

        $guard = $services->get( 'FieldPermissions.VisibilityEditGuard' );
        $status = $guard->checkEditPermission( $title, $user );

        if ( !$status->isOK() ) {
            wfDebugLog( 'fieldpermissions', 'checkEditPermission failed for user ' . $user->getName() . ' on page ' . $title->getPrefixedText() );
            $result = $status->getErrorsArray();
            return false;
        }

        return true;
    }

    /* ----------------------------------------------------------------------
     * 3. MultiContentSave validation
     * -------------------------------------------------------------------- */
    public static function onMultiContentSave(
        RenderedRevision $renderedRevision,
        UserIdentity $user,
        $performer,
        $slots,
        $editResult = null
    ) {
        try {
            $pageTitle = $renderedRevision->getRevision()->getPageAsLinkTarget();
            $titleText = (string)$pageTitle;
        } catch ( \Exception $e ) {
            $titleText = 'unknown';
        }
        wfDebugLog( 'fieldpermissions', 'onMultiContentSave hook called for user: ' . $user->getName() . ', page: ' . $titleText );
        
        $services = MediaWikiServices::getInstance();

        if ( !$services->hasService( 'FieldPermissions.VisibilityEditGuard' ) ) {
            wfDebugLog( 'fieldpermissions', 'FieldPermissions.VisibilityEditGuard service not available' );
            return true;
        }

        $guard = $services->get( 'FieldPermissions.VisibilityEditGuard' );

        if ( is_iterable( $slots ) ) {
            foreach ( $slots as $slot ) {
                $content = $slot->getContent();
                $status = $guard->validateContent( $content, $user );
                if ( !$status->isOK() ) {
                    wfDebugLog( 'fieldpermissions', 'validateContent failed for user ' . $user->getName() . ' on page ' . $titleText );
                    return $status;
                }
            }
        }

        return true;
    }

    /* ----------------------------------------------------------------------
     * 5. SMW Factbox BeforeContentGeneration (VALID)
     * -------------------------------------------------------------------- */
    public static function onSMWFactboxBeforeContentGeneration(
        \SMW\DIWikiPage $subject,
        array &$properties
    ) {
        wfDebugLog( 'fieldpermissions', 'onSMWFactboxBeforeContentGeneration hook called for subject: ' . $subject->getTitle()->getPrefixedText() );
        
        $services = MediaWikiServices::getInstance();

        if ( !$services->hasService( 'FieldPermissions.SmwQueryFilter' ) ) {
            wfDebugLog( 'fieldpermissions', 'FieldPermissions.SmwQueryFilter service not available' );
            return;
        }

        $filter = $services->get( 'FieldPermissions.SmwQueryFilter' );
        $filter->filterFactboxProperties( $subject, $properties );
    }
}
