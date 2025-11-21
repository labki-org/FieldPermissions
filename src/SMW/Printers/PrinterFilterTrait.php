<?php

namespace FieldPermissions\SMW\Printers;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use FieldPermissions\Visibility\ResultPrinterVisibilityFilter;

trait PrinterFilterTrait {

    private ?ResultPrinterVisibilityFilter $visibilityFilter = null;

    protected function getVisibilityFilter(): ResultPrinterVisibilityFilter {
        if ( $this->visibilityFilter === null ) {
            $this->visibilityFilter = MediaWikiServices::getInstance()->get( 'FieldPermissions.ResultPrinterVisibilityFilter' );
        }
        return $this->visibilityFilter;
    }

    /**
     * Override getResultText to filter print requests before rendering.
     * 
     * @param \SMW\Query\QueryResult $queryResult
     * @param int $outputMode
     * @return string
     */
    public function getResultText( \SMW\Query\QueryResult $queryResult, $outputMode ) {
        $user = RequestContext::getMain()->getUser();
        
        wfDebugLog( 'fieldpermissions', "========================================" );
        wfDebugLog( 'fieldpermissions', static::class . "::getResultText called" );
        wfDebugLog( 'fieldpermissions', "Current user: " . $user->getName() . " (ID: " . $user->getId() . ")" );
        
        $this->filterPrintRequests( $queryResult );
        $result = parent::getResultText( $queryResult, $outputMode );
        
        wfDebugLog( 'fieldpermissions', static::class . "::getResultText completed" );
        wfDebugLog( 'fieldpermissions', "========================================" );
        
        return $result;
    }

    /**
     * Filter the print requests in the QueryResult object.
     * 
     * @param \SMW\Query\QueryResult $queryResult
     */
    protected function filterPrintRequests( \SMW\Query\QueryResult $queryResult ) {
        $user = RequestContext::getMain()->getUser();
        $filter = $this->getVisibilityFilter();
        
        wfDebugLog( 'fieldpermissions', 'PrinterFilterTrait::filterPrintRequests called for user: ' . $user->getName() );
        
        try {
            $reflection = new \ReflectionClass( $queryResult );
            
            // Determine property name (mPrintRequests for SMW 6.x, m_printRequests for older)
            $propName = 'mPrintRequests';
            if ( !$reflection->hasProperty( $propName ) ) {
                $propName = 'm_printRequests';
            }
            
            if ( $reflection->hasProperty( $propName ) ) {
                $prop = $reflection->getProperty( $propName );
                $prop->setAccessible( true );
                $requests = $prop->getValue( $queryResult );
                
                if ( !is_array( $requests ) ) {
                    wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Requests property is not an array. Skipping." );
                    return;
                }

                $filtered = [];
                $modified = false;

                wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Inspecting " . count($requests) . " print requests." );

                foreach ( $requests as $pr ) {
                    if ( $filter->isPrintRequestVisible( $pr, $user ) ) {
                        $filtered[] = $pr;
                    } else {
                        $modified = true;
                        wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Removed restricted column: " . $pr->getLabel() );
                    }
                }
                
                if ( $modified ) {
                    $prop->setValue( $queryResult, $filtered );
                    wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Applied filtered requests." );
                }
            } else {
                wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Could not find requests property '$propName'." );
            }
        } catch ( \Exception $e ) {
            wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Error filtering requests: " . $e->getMessage() );
        }
    }
}

