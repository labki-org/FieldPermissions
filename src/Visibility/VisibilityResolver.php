<?php

namespace FieldPermissions\Visibility;

use FieldPermissions\Config\VisibilityLevelStore;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Store;
use Title;

class VisibilityResolver {
	private VisibilityLevelStore $levelStore;

	// Cache for property levels to avoid repeated DB lookups in one request
	private array $propertyLevelCache = [];
	private array $propertyVisibleToCache = [];

	public function __construct( VisibilityLevelStore $levelStore ) {
		$this->levelStore = $levelStore;
	}

	/**
	 * Get the numeric visibility level for a property.
	 *
	 * 1. Check if property page has "Has visibility level"
	 * 2. If so, resolve that level name/page to numeric value.
	 * 3. Default to 0 (public).
	 *
	 * @param DIProperty $property
	 * @return int
	 */
	public function getPropertyLevel( DIProperty $property ): int {
		$key = $property->getKey();
		if ( isset( $this->propertyLevelCache[$key] ) ) {
			return $this->propertyLevelCache[$key];
		}

		// Default public
		$level = 0;

		try {
			// We need to look up "Has visibility level" (property) on the property page.
			// Let's assume the property for "Has visibility level" is defined as "Has visibility level"
			// or we can use a fixed key if we defined it in the setup.
			// For now, we assume user created property "Has visibility level".
			
			// Get SMW store - try multiple methods for compatibility
			$store = null;
			if ( class_exists( '\SMW\ApplicationFactory' ) ) {
				$store = \SMW\ApplicationFactory::getInstance()->getStore();
			} elseif ( class_exists( '\SMW\StoreFactory' ) ) {
				$store = \SMW\StoreFactory::getStore();
			}
			
			if ( !$store ) {
				wfDebugLog( 'fieldpermissions', "VisibilityResolver: Could not get SMW store." );
				$this->propertyLevelCache[$key] = $level;
				return $level;
			}
			
			// The subject is the property page itself
			$subject = $property->getDiWikiPage();
			
			// The property we are looking for on that page is "Has visibility level"
			// SMW normalizes property names with spaces to underscores
			$hasVisLevelProp = new DIProperty( 'Has_visibility_level' );
			
			wfDebugLog( 'fieldpermissions', "Looking up Has_visibility_level for property: " . $property->getKey() );
			
			$res = $store->getPropertyValues( $subject, $hasVisLevelProp );
			
			foreach ( $res as $di ) {
				// Expecting a Page (DIWikiPage) or Text (DIString)
				// If Page: "Visibility:Private" -> resolve via store
				// If Text: "private" -> resolve via store
				
				$levelName = '';
				if ( $di instanceof DIWikiPage ) {
					$levelName = $di->getTitle()->getText(); // e.g. "Private" from "Visibility:Private"
					// Or if it points to "Visibility:Private", the DB might store "Visibility:Private" as page title
					// or just "private" as name.
					// Let's try to match by page title first, then name.
					$fullTitle = $di->getTitle()->getPrefixedText();
					
					// Try to find level by page title
					$levels = $this->levelStore->getAllLevels();
					foreach ( $levels as $l ) {
						if ( $l->getPageTitle() === $fullTitle ) {
							$level = $l->getNumericLevel();
							break 2;
						}
					}
					$levelName = $di->getTitle()->getText();
				} elseif ( $di instanceof \SMW\DIString || $di instanceof \SMW\DIBlob ) {
					$levelName = $di->getString();
				}

				if ( $levelName ) {
					$l = $this->levelStore->getLevelByName( $levelName );
					if ( $l ) {
						$level = $l->getNumericLevel();
						break;
					}
				}
			}
			
		} catch ( \Exception $e ) {
			wfDebugLog( 'fieldpermissions', "Error resolving property level: " . $e->getMessage() );
		}

		$this->propertyLevelCache[$key] = $level;
		return $level;
	}

	/**
	 * Get list of groups explicitly allowed to see this property.
	 * Mapped from "Visible to" property.
	 *
	 * @param DIProperty $property
	 * @return string[] Group names
	 */
	public function getPropertyVisibleTo( DIProperty $property ): array {
		$key = $property->getKey();
		if ( isset( $this->propertyVisibleToCache[$key] ) ) {
			return $this->propertyVisibleToCache[$key];
		}

		$groups = [];
		try {
			// Get SMW store - try multiple methods for compatibility
			$store = null;
			if ( class_exists( '\SMW\ApplicationFactory' ) ) {
				$store = \SMW\ApplicationFactory::getInstance()->getStore();
			} elseif ( class_exists( '\SMW\StoreFactory' ) ) {
				$store = \SMW\StoreFactory::getStore();
			}
			
			if ( !$store ) {
				wfDebugLog( 'fieldpermissions', "VisibilityResolver: Could not get SMW store." );
				$this->propertyVisibleToCache[$key] = $groups;
				return $groups;
			}
			$subject = $property->getDiWikiPage();
			$visibleToProp = new DIProperty( 'Visible_to' );
			
			$res = $store->getPropertyValues( $subject, $visibleToProp );
			foreach ( $res as $di ) {
				if ( $di instanceof \SMW\DIString || $di instanceof \SMW\DIBlob ) {
					$groups[] = trim( $di->getString() );
				} elseif ( $di instanceof DIWikiPage ) {
					$groups[] = trim( $di->getTitle()->getText() );
				}
			}
		} catch ( \Exception $e ) {
			wfDebugLog( 'fieldpermissions', "Error resolving visible to: " . $e->getMessage() );
		}

		$this->propertyVisibleToCache[$key] = $groups;
		return $groups;
	}
}

