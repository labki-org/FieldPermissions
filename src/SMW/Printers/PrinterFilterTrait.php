<?php

namespace FieldPermissions\SMW\Printers;

use FieldPermissions\Visibility\ResultPrinterVisibilityFilter;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use ReflectionClass;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;

/**
 * PrinterFilterTrait
 *
 * Injected into custom SMW ResultPrinter subclasses (FpTableResultPrinter,
 * FpListResultPrinter, etc.) to enforce visibility constraints at render time.
 *
 * Purpose:
 *   - Before SMW prints a table/JSON/list/etc., we examine the PrintRequest
 *     objects that correspond to each column in the result.
 *   - Any PrintRequest that refers to a restricted SMW Property is removed.
 *
 * Why filtering at this stage?
 *   - SMW fetches subject rows lazily.
 *   - Printer stage is the earliest consistent point where SMW hands us both:
 *       (1) the actual PrintRequest list
 *       (2) the correct printer instance
 *   - Tier 2 filtering ensures zero leakage of restricted columns across all
 *     SMW formats (table, list, JSON, CSV, template).
 */
trait PrinterFilterTrait {

	/** @var ResultPrinterVisibilityFilter|null */
	private ?ResultPrinterVisibilityFilter $visibilityFilter = null;

	/**
	 * Get the injected SMW result-printer filter service.
	 *
	 * @return ResultPrinterVisibilityFilter
	 */
	protected function getVisibilityFilter(): ResultPrinterVisibilityFilter {
		if ( $this->visibilityFilter === null ) {
			$this->visibilityFilter =
				MediaWikiServices::getInstance()->get( 'FieldPermissions.ResultPrinterVisibilityFilter' );
		}
		return $this->visibilityFilter;
	}

	/**
	 * Override SMW's printer rendering logic.
	 *
	 * SMW calls getResultText() → Trait intercepts → Filters columns →
	 * calls parent::getResultText().
	 *
	 * @param QueryResult $queryResult
	 * @param int $outputMode One of SMW_OUTPUT_* constants
	 * @return string
	 */
	public function getResultText( QueryResult $queryResult, $outputMode ) {
		$user = RequestContext::getMain()->getUser();

		wfDebugLog( 'fieldpermissions', "----------------------------------------" );
		wfDebugLog( 'fieldpermissions', static::class . "::getResultText called" );
		wfDebugLog( 'fieldpermissions', "User: {$user->getName()} (ID: {$user->getId()})" );

		$this->filterPrintRequests( $queryResult );

		$result = parent::getResultText( $queryResult, $outputMode );

		wfDebugLog( 'fieldpermissions', static::class . "::getResultText completed" );
		wfDebugLog( 'fieldpermissions', "----------------------------------------" );

		return $result;
	}

	/**
	 * Filter the QueryResult's PrintRequests array to remove columns the user
	 * should not see.
	 *
	 * @param QueryResult $queryResult
	 */
	protected function filterPrintRequests( QueryResult $queryResult ): void {
		$user   = RequestContext::getMain()->getUser();
		$filter = $this->getVisibilityFilter();

		wfDebugLog(
			'fieldpermissions',
			"PrinterFilterTrait::filterPrintRequests called for user {$user->getName()}"
		);

		try {
			$propertyName = $this->detectPrintRequestPropertyName( $queryResult );

			if ( $propertyName === null ) {
				wfDebugLog( 'fieldpermissions',
					"PrinterFilterTrait: Unable to locate print request property on QueryResult."
				);
				return;
			}

			$requests = $this->readPrintRequestArray( $queryResult, $propertyName );

			if ( !is_array( $requests ) ) {
				wfDebugLog(
					'fieldpermissions',
					"PrinterFilterTrait: Print requests not stored as array. Cannot filter."
				);
				return;
			}

			wfDebugLog( 'fieldpermissions',
				"PrinterFilterTrait: Inspecting " . count( $requests ) . " print requests."
			);

			$filtered = [];
			$modified = false;

			foreach ( $requests as $pr ) {
				if ( !$pr instanceof PrintRequest ) {
					$filtered[] = $pr;
					continue;
				}

				if ( $filter->isPrintRequestVisible( $pr, $user ) ) {
					$filtered[] = $pr;
				} else {
					$modified = true;
					wfDebugLog(
						'fieldpermissions',
						"PrinterFilterTrait: Removed restricted column '{$pr->getLabel()}'"
					);
				}
			}

			if ( $modified ) {
				$this->writePrintRequestArray( $queryResult, $propertyName, $filtered );
				wfDebugLog( 'fieldpermissions', "PrinterFilterTrait: Filtered requests applied." );
			}

		} catch ( \Throwable $e ) {
			wfDebugLog(
				'fieldpermissions',
				"PrinterFilterTrait: Exception while filtering print requests: {$e->getMessage()}"
			);
		}
	}

	/* -------------------------------------------------------------------------
	 * Internal Reflection Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Detects the internal property name for print requests in QueryResult.
	 *
	 * SMW 3.x – "m_printRequests"
	 * SMW 6.x – "mPrintRequests"
	 *
	 * @param QueryResult $queryResult
	 * @return string|null
	 */
	private function detectPrintRequestPropertyName( QueryResult $queryResult ): ?string {
		$reflection = new ReflectionClass( $queryResult );

		$candidates = [ 'mPrintRequests', 'm_printRequests' ];

		foreach ( $candidates as $prop ) {
			if ( $reflection->hasProperty( $prop ) ) {
				return $prop;
			}
		}

		return null;
	}

	/**
	 * Read print requests via reflection.
	 *
	 * @param QueryResult $queryResult
	 * @param string $property
	 * @return mixed
	 */
	private function readPrintRequestArray( QueryResult $queryResult, string $property ) {
		$ref = new ReflectionClass( $queryResult );
		$prop = $ref->getProperty( $property );
		$prop->setAccessible( true );
		return $prop->getValue( $queryResult );
	}

	/**
	 * Write filtered print requests back to QueryResult via reflection.
	 *
	 * @param QueryResult $queryResult
	 * @param string $property
	 * @param array $value
	 */
	private function writePrintRequestArray( QueryResult $queryResult, string $property, array $value ): void {
		$ref = new ReflectionClass( $queryResult );
		$prop = $ref->getProperty( $property );
		$prop->setAccessible( true );
		$prop->setValue( $queryResult, $value );
	}
}
