<?php

namespace PropertyPermissions\Visibility;

use PropertyPermissions\Config\VisibilityLevelStore;
use PropertyPermissions\Model\VisibilityLevel;
use SMW\DIProperty;
use SMW\DIWikiPage;

/**
 * VisibilityResolver
 *
 * Responsible for:
 *   - Determining a property's assigned visibility level
 *   - Determining which user groups are explicitly allowed to see a property
 *
 * This class interacts *directly* with SMW's Store to query semantic
 * annotations attached to Property pages:
 *
 *     [[Has visibility level::Visibility:Internal]]
 *     [[Visible to::pi]]
 *
 * Resolver methods normalize identifiers robustly so user input stored in
 * SMW can vary while still matching configuration-level definitions.
 *
 * Caches results per-request to minimize redundant SMW queries.
 */
class VisibilityResolver {

	/** @var VisibilityLevelStore */
	private VisibilityLevelStore $levelStore;

	/** @var array<string,int> Cache: propertyKey → numeric visibility level */
	private array $propertyLevelCache = [];

	/** @var array<string,string[]> Cache: propertyKey → visible-to groups */
	private array $propertyVisibleToCache = [];

	public function __construct( VisibilityLevelStore $levelStore ) {
		$this->levelStore = $levelStore;
	}

	/* --------------------------------------------------------------------
	 * PUBLIC API
	 * ------------------------------------------------------------------ */

	/**
	 * Get the numeric visibility level for a property.
	 *
	 * Lookup order:
	 *   1. Property annotations for "Has visibility level"
	 *   2. Resolve the page/string identifier to a VisibilityLevel model
	 *   3. Default: 0 (public)
	 *
	 * @param DIProperty $property
	 * @return int
	 */
	public function getPropertyLevel( DIProperty $property ): int {
		$key = $property->getKey();
		if ( isset( $this->propertyLevelCache[$key] ) ) {
			return $this->propertyLevelCache[$key];
		}

		// Default: public
		$levelValue = 0;

		try {
			$store = $this->getSMWStore();
			if ( !$store ) {
				wfDebugLog( 'propertypermissions', "VisibilityResolver: No SMW store available." );
				$this->propertyLevelCache[$key] = $levelValue;
				return $levelValue;
			}

			$subject = $property->getDiWikiPage();
			// SMW normalized form
			$visProp = new DIProperty( 'Has_visibility_level' );

			wfDebugLog(
				'propertypermissions',
				"VisibilityResolver: Checking Has_visibility_level on property {$property->getKey()}"
			);

			$values = $store->getPropertyValues( $subject, $visProp );

			foreach ( $values as $di ) {
				// DIWikiPage case: referencing a dedicated Visibility: page
				if ( $di instanceof DIWikiPage ) {
					$title = $di->getTitle();
					$resolved =
						$this->resolveLevelFromIdentifier( $title->getPrefixedText() ) ??
						$this->resolveLevelFromIdentifier( $title->getText() );
				} elseif ( $this->isStringDataItem( $di ) ) {
					// String/blob case
					$resolved = $this->resolveLevelFromIdentifier( $di->getString() );
				} else {
					wfDebugLog(
						'propertypermissions',
						"VisibilityResolver: Unexpected DI type in visibility level: " . get_class( $di )
					);
					continue;
				}

				if ( $resolved instanceof VisibilityLevel ) {
					$levelValue = $resolved->getNumericLevel();
					break;
				}
			}
		} catch ( \Exception $e ) {
			wfDebugLog(
				'propertypermissions',
				"VisibilityResolver: Exception resolving property level: " . $e->getMessage()
			);
		}

		$this->propertyLevelCache[$key] = $levelValue;

		return $levelValue;
	}

	/**
	 * Resolve which user groups are explicitly allowed to see this property.
	 * Sourced from:
	 *
	 *     [[Visible to::pi]]
	 *     [[Visible to::Group:research_team]]
	 *
	 * Result is case-insensitive and normalized.
	 *
	 * @param DIProperty $property
	 * @return string[] Normalized group names
	 */
	public function getPropertyVisibleTo( DIProperty $property ): array {
		$key = $property->getKey();
		if ( isset( $this->propertyVisibleToCache[$key] ) ) {
			return $this->propertyVisibleToCache[$key];
		}

		$groups = [];

		try {
			$store = $this->getSMWStore();
			if ( !$store ) {
				wfDebugLog( 'propertypermissions', 'VisibilityResolver: No SMW store available.' );
				$this->propertyVisibleToCache[$key] = [];
				return [];
			}

			$subject = $property->getDiWikiPage();
			$visProp = new DIProperty( 'Visible_to' );

			$values = $store->getPropertyValues( $subject, $visProp );

			wfDebugLog(
				'propertypermissions',
				"VisibilityResolver: Found " . count( $values ) .
				" Visible_to values for {$property->getKey()}"
			);

			foreach ( $values as $di ) {

				// String / blob annotation: [[Visible to::pi]]
				if ( $this->isStringDataItem( $di ) ) {
					$normalized = $this->normalizeGroupName( $di->getString() );
				} elseif ( $this->isWikiPageDataItem( $di ) ) {
					// Page link annotation: [[Visible to::Group:PI]]
					$title = $di->getTitle();
					$normalized =
						$this->normalizeGroupName( $title->getPrefixedText() ) ?:
						$this->normalizeGroupName( $title->getText() );
				} else {
					wfDebugLog(
						'propertypermissions',
						"VisibilityResolver: Unexpected DI type in Visible_to: " . get_class( $di )
					);
					continue;
				}

				if ( $normalized !== '' ) {
					$groups[] = $normalized;
				}
			}
		} catch ( \Exception $e ) {
			wfDebugLog(
				'propertypermissions',
				"VisibilityResolver: Exception resolving Visible_to: " . $e->getMessage()
			);
		}

		$groups = array_values( array_unique( $groups ) );

		wfDebugLog(
			'propertypermissions',
			"VisibilityResolver: Final Visible_to groups for {$property->getKey()}: "
			. implode( ', ', $groups )
		);

		$this->propertyVisibleToCache[$key] = $groups;

		return $groups;
	}

	/* --------------------------------------------------------------------
	 * LEVEL RESOLUTION HELPERS
	 * ------------------------------------------------------------------ */

	/**
	 * Attempt to resolve a VisibilityLevel using multiple possible identifiers:
	 *
	 * Accepts:
	 *   - Level name ("internal", "pi_only")
	 *   - Page title ("Visibility:Internal", "Visibility:PI Only")
	 *   - Un-prefixed title ("Internal")
	 *
	 * All are normalized for matching.
	 *
	 * @param string $identifier
	 * @return VisibilityLevel|null
	 */
	private function resolveLevelFromIdentifier( string $identifier ): ?VisibilityLevel {
		$normalized = $this->normalizeIdentifier( $identifier );
		if ( $normalized === '' ) {
			return null;
		}

		foreach ( $this->levelStore->getAllLevels() as $level ) {
			$candidates = array_filter( [
				$level->getName(),
				$level->getPageTitle(),
			] );

			foreach ( $candidates as $candidate ) {
				if ( $this->normalizeIdentifier( $candidate ) === $normalized ) {
					return $level;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize identifiers for comparison:
	 *    "PI Only" → "pi_only"
	 *    "Visibility:PI_only" → "pi_only"
	 *
	 * @param string $value
	 * @return string
	 */
	private function normalizeIdentifier( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		// Strip namespace-like prefixes
		if ( strpos( $value, ':' ) !== false ) {
			$value = substr( $value, strrpos( $value, ':' ) + 1 );
		}

		return strtolower( str_replace( ' ', '_', $value ) );
	}

	/**
	 * Normalize group names for matching Visible_to annotations.
	 *
	 * @param string $group
	 * @return string
	 */
	private function normalizeGroupName( string $group ): string {
		$group = trim( $group );
		if ( $group === '' ) {
			return '';
		}

		if ( strpos( $group, ':' ) !== false ) {
			$group = substr( $group, strrpos( $group, ':' ) + 1 );
		}

		return strtolower( str_replace( ' ', '_', $group ) );
	}

	/* --------------------------------------------------------------------
	 * SMW COMPATIBILITY HELPERS
	 * ------------------------------------------------------------------ */

	/**
	 * Retrieve SMW Store via best available API.
	 *
	 * @return \SMW\Store|null
	 */
	private function getSMWStore() {
		if ( class_exists( '\SMW\ApplicationFactory' ) ) {
			return \SMW\ApplicationFactory::getInstance()->getStore();
		}

		if ( class_exists( '\SMW\StoreFactory' ) ) {
			return \SMW\StoreFactory::getStore();
		}

		return null;
	}

	/**
	 * Identify DIString-like items.
	 *
	 * @param mixed $di
	 * @return bool
	 */
	private function isStringDataItem( $di ): bool {
		return $di instanceof \SMW\DIString
			|| $di instanceof \SMW\DIBlob
			|| ( class_exists( '\SMWDIString', false ) && $di instanceof \SMWDIString )
			|| ( class_exists( '\SMWDIBlob', false ) && $di instanceof \SMWDIBlob );
	}

	/**
	 * Identify DIWikiPage-like items.
	 *
	 * @param mixed $di
	 * @return bool
	 */
	private function isWikiPageDataItem( $di ): bool {
		return $di instanceof DIWikiPage
			|| ( class_exists( '\SMWDIWikiPage', false ) && $di instanceof \SMWDIWikiPage );
	}
}
