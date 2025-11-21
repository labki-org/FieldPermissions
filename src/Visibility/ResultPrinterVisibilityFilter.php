<?php

namespace FieldPermissions\Visibility;

use MediaWiki\User\UserIdentity;
use SMW\DIProperty;
use SMW\PrintRequest;

class ResultPrinterVisibilityFilter {

    private VisibilityResolver $resolver;
    private PermissionEvaluator $evaluator;

    public function __construct(
        VisibilityResolver $resolver,
        PermissionEvaluator $evaluator
    ) {
        $this->resolver = $resolver;
        $this->evaluator = $evaluator;
    }

    /**
     * Filter an array of SMWDataValue objects based on visibility permissions.
     *
     * @param mixed $printRequest The print request associated with the data.
     * @param array $dataValues Array of SMWDataValue objects.
     * @param UserIdentity $user The user viewing the data.
     * @return array Filtered array of SMWDataValue objects.
     */
    public function filterDataValues( $printRequest, array $dataValues, UserIdentity $user ): array {
        // If there are no values, nothing to filter
        if ( empty( $dataValues ) ) {
            return [];
        }

        // Ensure we have a valid PrintRequest object
        if ( !$printRequest instanceof PrintRequest && !$printRequest instanceof \SMW\Query\PrintRequest ) {
             return $dataValues;
        }

        // Check if method exists
        if ( method_exists( $printRequest, 'getDataItem' ) ) {
            $dataItem = $printRequest->getDataItem();
        } elseif ( method_exists( $printRequest, 'getData' ) ) {
            $data = $printRequest->getData();
            // getData() returns a DataValue, we need to extract the DataItem
            if ( $data && method_exists( $data, 'getDataItem' ) ) {
                $dataItem = $data->getDataItem();
            } else {
                $dataItem = null;
            }
        } else {
            return $dataValues;
        }

        // If the column is not based on a property (e.g. category, modification date), usually allow it.
        if ( !$dataItem instanceof DIProperty ) {
            return $dataValues;
        }

        // Resolve visibility level and allowed groups for the property
        $level = $this->resolver->getPropertyLevel( $dataItem );
        $visibleTo = $this->resolver->getPropertyVisibleTo( $dataItem );

        // Public property?
        if ( $level === 0 && empty( $visibleTo ) ) {
            return $dataValues;
        }

        // Check permissions
        if ( $this->evaluator->mayViewProperty( $user, $level, $visibleTo ) ) {
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: ALLOW property " . $dataItem->getKey() . " for user " . $user->getName() );
            return $dataValues;
        }

        // Blocked
        wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: BLOCK property " . $dataItem->getKey() . " for user " . $user->getName() );
        return [];
    }

    /**
     * Check if a print request (column) is visible to a user.
     *
     * @param mixed $printRequest
     * @param UserIdentity $user
     * @return bool
     */
    public function isPrintRequestVisible( $printRequest, UserIdentity $user ): bool {
        // Ensure we have a valid PrintRequest object
        if ( !$printRequest instanceof PrintRequest && !$printRequest instanceof \SMW\Query\PrintRequest ) {
             wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: Invalid print request type: " . (is_object($printRequest) ? get_class($printRequest) : gettype($printRequest)) );
             return true; // Safe default or false? SMW uses mixed namespaces sometimes.
        }

        // Check if method exists (SMW 6 vs older versions compatibility)
        if ( method_exists( $printRequest, 'getDataItem' ) ) {
            $dataItem = $printRequest->getDataItem();
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: Got dataItem via getDataItem(): " . ( $dataItem ? get_class($dataItem) : 'null' ) );
        } elseif ( method_exists( $printRequest, 'getData' ) ) {
             $data = $printRequest->getData();
             wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: Got data via getData(): " . ( $data ? get_class($data) : 'null' ) );
             
             // getData() returns a DataValue, we need to extract the DataItem
             if ( $data && method_exists( $data, 'getDataItem' ) ) {
                 $dataItem = $data->getDataItem();
                 wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: Extracted dataItem from DataValue: " . ( $dataItem ? get_class($dataItem) : 'null' ) );
             } else {
                 $dataItem = null;
             }
        } else {
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: PrintRequest class " . get_class($printRequest) . " missing getDataItem(). Methods: " . implode(', ', get_class_methods($printRequest)) );
            return true;
        }

        // Non-property columns are generally visible
        if ( !$dataItem instanceof DIProperty ) {
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: DataItem is not a DIProperty, allowing. Type: " . ( $dataItem ? get_class($dataItem) : 'null' ) );
            return true;
        }

        wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: Processing property: " . $dataItem->getKey() );

        $level = $this->resolver->getPropertyLevel( $dataItem );
        $visibleTo = $this->resolver->getPropertyVisibleTo( $dataItem );

        // Public
        if ( $level === 0 && empty( $visibleTo ) ) {
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: PUBLIC property " . $dataItem->getKey() . " (allowed)" );
            return true;
        }

        $allowed = $this->evaluator->mayViewProperty( $user, $level, $visibleTo );
        
        if ( !$allowed ) {
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: BLOCK property " . $dataItem->getKey() . " for user " . $user->getName() );
        } else {
            wfDebugLog( 'fieldpermissions', "ResultPrinterVisibilityFilter: ALLOW property " . $dataItem->getKey() . " for user " . $user->getName() );
        }

        return $allowed;
    }
}
