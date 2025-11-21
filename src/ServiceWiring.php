<?php

namespace FieldPermissions;

use MediaWiki\MediaWikiServices;

use FieldPermissions\Config\VisibilityLevelStore;
use FieldPermissions\Config\GroupLevelStore;
use FieldPermissions\Visibility\VisibilityResolver;
use FieldPermissions\Visibility\PermissionEvaluator;
use FieldPermissions\Visibility\SmwQueryFilter;
use FieldPermissions\Protection\VisibilityEditGuard;
use FieldPermissions\Visibility\ResultPrinterVisibilityFilter;

return [

    /* ----------------------------------------------------------------------
     * Visibility Level Storage
     * ------------------------------------------------------------------ */
    'FieldPermissions.VisibilityLevelStore' => static function ( MediaWikiServices $services ) {
        return new VisibilityLevelStore(
            // Always pass a LoadBalancer so the store handles replica/master correctly
            $services->getDBLoadBalancer()
        );
    },

    /* ----------------------------------------------------------------------
     * Group-Level Store
     * ------------------------------------------------------------------ */
    'FieldPermissions.GroupLevelStore' => static function ( MediaWikiServices $services ) {
        return new GroupLevelStore(
            $services->getDBLoadBalancer()
        );
    },

    /* ----------------------------------------------------------------------
     * Visibility Resolver
     * ------------------------------------------------------------------ */
    'FieldPermissions.VisibilityResolver' => static function ( MediaWikiServices $services ) {
        return new VisibilityResolver(
            $services->get( 'FieldPermissions.VisibilityLevelStore' )
        );
    },

    /* ----------------------------------------------------------------------
     * Evaluates whether a user/group meets visibility threshold
     * ------------------------------------------------------------------ */
    'FieldPermissions.PermissionEvaluator' => static function ( MediaWikiServices $services ) {
        return new PermissionEvaluator(
            $services->get( 'FieldPermissions.GroupLevelStore' ),
            $services->getUserGroupManager()   // Correct MW 1.44 service
        );
    },

    /* ----------------------------------------------------------------------
     * Semantic MediaWiki Query Filter
     * ------------------------------------------------------------------ */
    'FieldPermissions.SmwQueryFilter' => static function ( MediaWikiServices $services ) {
        return new SmwQueryFilter(
            $services->get( 'FieldPermissions.VisibilityResolver' ),
            $services->get( 'FieldPermissions.PermissionEvaluator' )
        );
    },

    /* ----------------------------------------------------------------------
     * ResultPrinter Visibility Filter (New Tier 2 Service)
     * ------------------------------------------------------------------ */
    'FieldPermissions.ResultPrinterVisibilityFilter' => static function ( MediaWikiServices $services ) {
        return new ResultPrinterVisibilityFilter(
            $services->get( 'FieldPermissions.VisibilityResolver' ),
            $services->get( 'FieldPermissions.PermissionEvaluator' )
        );
    },

    /* ----------------------------------------------------------------------
     * Edit Guard (edit-blocking logic)
     * ------------------------------------------------------------------ */
    'FieldPermissions.VisibilityEditGuard' => static function ( MediaWikiServices $services ) {
        return new VisibilityEditGuard(
            $services->get( 'FieldPermissions.VisibilityResolver' ),
            $services->get( 'FieldPermissions.PermissionEvaluator' )
        );
    },
];
