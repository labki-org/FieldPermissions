<?php

namespace FieldPermissions\Visibility;

use MediaWiki\User\UserIdentity;
use SMW\DIProperty;
use SMW\PrintRequest;

/**
 * ResultPrinterVisibilityFilter
 *
 * This class enforces per-property visibility rules during SMW result printing.
 *
 * It performs two levels of filtering:
 *
 *   1. Column-level filtering
 *      Determines whether an entire print request (column) should appear.
 *      Used by PrinterFilterTrait before printing results.
 *
 *   2. Value-level filtering
 *      Removes individual data values when a property is visible but a
 *      specific data item should not be shown (used rarely).
 *
 * Both functions must operate across multiple SMW versions, which have
 * *inconsistent* PrintRequest APIs:
 *
 *   - Some versions require getDataItem()
 *   - Some wrap everything in DataValue objects and require getData()->getDataItem()
 *   - Namespaces vary: SMW\PrintRequest vs SMW\Query\PrintRequest
 *
 * This class handles all variations defensively and safely.
 */
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

	/* ----------------------------------------------------------------------
	 * 1. Filter column-level PrintRequests (preferred for Tier 2 approach)
	 * -------------------------------------------------------------------- */

	/**
	 * Determine whether a print request (SMW table column) is visible.
	 *
	 * SMW print requests represent result columns. If a column is tied to a
	 * restricted property, it MUST be removed *before* rendering.
	 *
	 * @param mixed $printRequest SMW PrintRequest (multiple versions supported)
	 * @param UserIdentity $user
	 * @return bool True if the column should be shown.
	 */
	public function isPrintRequestVisible( $printRequest, UserIdentity $user ): bool {
		/* ----------------------------------------------------------
		 * Step 1: Validate PrintRequest type
		 * -------------------------------------------------------- */
		if (
			!$printRequest instanceof PrintRequest &&
			!$printRequest instanceof \SMW\Query\PrintRequest
		) {
			wfDebugLog(
				'fieldpermissions',
				"RPVF::isPrintRequestVisible: Unknown print request type (" .
				( is_object( $printRequest ) ? get_class( $printRequest ) : gettype( $printRequest ) ) .
				"), default ALLOWED"
			);

			// Safe default: allow. (Safer than hiding unknown column types.)
			return true;
		}

		/* ----------------------------------------------------------
		 * Step 2: Obtain DataItem (property) from the PrintRequest
		 * -------------------------------------------------------- */

		$dataItem = $this->extractDataItemFromPrintRequest( $printRequest );

		if ( !$dataItem ) {
			wfDebugLog(
				'fieldpermissions',
				"RPVF::isPrintRequestVisible: No dataItem extracted → ALLOW"
			);
			return true;
		}

		/* Non-property printouts (e.g., category, title, etc.) */
		if ( !$dataItem instanceof DIProperty ) {
			wfDebugLog(
				'fieldpermissions',
				"RPVF::isPrintRequestVisible: DataItem is not DIProperty → ALLOW"
			);
			return true;
		}

		/* ----------------------------------------------------------
		 * Step 3: Resolve property restrictions
		 * -------------------------------------------------------- */
		$propKey = $dataItem->getKey();
		$propLevel = $this->resolver->getPropertyLevel( $dataItem );
		$visibleTo = $this->resolver->getPropertyVisibleTo( $dataItem );

		wfDebugLog(
			'fieldpermissions',
			"RPVF::isPrintRequestVisible: Property $propKey, level=$propLevel, visibleTo=[" .
			implode( ', ', $visibleTo ) . "]"
		);

		/* Public property */
		if ( $propLevel === 0 && empty( $visibleTo ) ) {
			return true;
		}

		/* ----------------------------------------------------------
		 * Step 4: Evaluate user access
		 * -------------------------------------------------------- */
		$allowed = $this->evaluator->mayViewProperty( $user, $propLevel, $visibleTo );

		wfDebugLog(
			'fieldpermissions',
			"RPVF::isPrintRequestVisible: " .
			( $allowed ? "ALLOW" : "BLOCK" ) .
			" user={$user->getName()} property=$propKey"
		);

		return $allowed;
	}

	/* ----------------------------------------------------------------------
	 * 2. Optional: Value-level filtering (rarely needed)
	 * -------------------------------------------------------------------- */

	/**
	 * Filter the actual SMWDataValue array for a single column.
	 * Used if column is visible but specific values should be rejected.
	 *
	 * With Tier 2, full-column filtering is preferred, so this normally
	 * returns either:
	 *   - unchanged values (allowed)
	 *   - empty array     (blocked)
	 *
	 * @param mixed $printRequest
	 * @param array $dataValues Array of SMWDataValue objects
	 * @param UserIdentity $user
	 * @return array Filtered values
	 */
	public function filterDataValues( $printRequest, array $dataValues, UserIdentity $user ): array {
		if ( empty( $dataValues ) ) {
			return [];
		}

		if (
			!$printRequest instanceof PrintRequest &&
			!$printRequest instanceof \SMW\Query\PrintRequest
		) {
			// Unknown → allow
			return $dataValues;
		}

		$dataItem = $this->extractDataItemFromPrintRequest( $printRequest );

		if ( !$dataItem instanceof DIProperty ) {
			// Non-property → allow
			return $dataValues;
		}

		$propLevel = $this->resolver->getPropertyLevel( $dataItem );
		$visibleTo = $this->resolver->getPropertyVisibleTo( $dataItem );

		if ( $propLevel === 0 && empty( $visibleTo ) ) {
			// Public
			return $dataValues;
		}

		if ( $this->evaluator->mayViewProperty( $user, $propLevel, $visibleTo ) ) {
			return $dataValues;
		}

		// If blocked, remove all values
		return [];
	}

	/* ----------------------------------------------------------------------
	 * INTERNAL HELPERS
	 * -------------------------------------------------------------------- */

	/**
	 * Extract a DIProperty (or other DI object) from a PrintRequest.
	 *
	 * SMW versions differ:
	 *   - new: getDataItem()
	 *   - old: getData() → DataValue → getDataItem()
	 *
	 * @param mixed $printRequest
	 * @return mixed|null DataItem or null if unavailable
	 */
	private function extractDataItemFromPrintRequest( $printRequest ) {
		// Newer SMW versions
		if ( method_exists( $printRequest, 'getDataItem' ) ) {
			return $printRequest->getDataItem();
		}

		// Older SMW versions
		if ( method_exists( $printRequest, 'getData' ) ) {
			$data = $printRequest->getData();

			if ( $data && method_exists( $data, 'getDataItem' ) ) {
				return $data->getDataItem();
			}
		}

		wfDebugLog(
			'fieldpermissions',
			"RPVF::extractDataItemFromPrintRequest: Unable to extract dataItem from " .
			get_class( $printRequest )
		);

		return null;
	}
}
