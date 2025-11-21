<?php

namespace FieldPermissions\Visibility;

use FieldPermissions\Config\VisibilityLevelStore;
use FieldPermissions\Model\VisibilityLevel;
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
                $resolved = null;
                
                if ( $di instanceof DIWikiPage ) {
                    // Prefer the prefixed page title (e.g. "Visibility:PI_Only")
                    $resolved = $this->resolveLevelFromIdentifier(
                        $di->getTitle()->getPrefixedText()
                    );
                    
                    if ( !$resolved ) {
                        // Fallback to the plain title text (e.g. "PI Only")
                        $resolved = $this->resolveLevelFromIdentifier(
                            $di->getTitle()->getText()
                        );
                    }
                } elseif ( $di instanceof \SMW\DIString || $di instanceof \SMW\DIBlob ) {
                    $resolved = $this->resolveLevelFromIdentifier( $di->getString() );
                }

                if ( $resolved ) {
                    $level = $resolved->getNumericLevel();
                    break;
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
			$store = null;
			if ( class_exists( '\SMW\ApplicationFactory' ) ) {
				$store = \SMW\ApplicationFactory::getInstance()->getStore();
			} elseif ( class_exists( '\SMW\StoreFactory' ) ) {
				$store = \SMW\StoreFactory::getStore();
			}

			if ( !$store ) {
				wfDebugLog( 'fieldpermissions', 'VisibilityResolver: Could not get SMW store.' );
				$this->propertyVisibleToCache[$key] = $groups;
				return $groups;
			}

			$subject = $property->getDiWikiPage();
			$visibleToProp = new DIProperty( 'Visible_to' );
			$res = $store->getPropertyValues( $subject, $visibleToProp );

			wfDebugLog(
				'fieldpermissions',
				"VisibilityResolver: Found " . count( $res ) . " 'Visible to' values for property {$property->getKey()}"
			);

			foreach ( $res as $di ) {
				if ( $this->isStringDataItem( $di ) ) {
					$raw = method_exists( $di, 'getString' ) ? $di->getString() : '';
					$normalized = $this->normalizeGroupName( $raw );
					wfDebugLog(
						'fieldpermissions',
						"VisibilityResolver: VisibleTo raw string '{$raw}' normalized to '{$normalized}'"
					);
					if ( $normalized !== '' ) {
						$groups[] = $normalized;
					}
				} elseif ( $this->isWikiPageDataItem( $di ) ) {
					$title = $di->getTitle();
					$raw = $title->getPrefixedText();
					$normalized = $this->normalizeGroupName( $raw );
					if ( $normalized === '' ) {
						$raw = $title->getText();
						$normalized = $this->normalizeGroupName( $raw );
					}
					wfDebugLog(
						'fieldpermissions',
						"VisibilityResolver: VisibleTo page '{$raw}' normalized to '{$normalized}'"
					);
					if ( $normalized !== '' ) {
						$groups[] = $normalized;
					}
				} else {
					wfDebugLog(
						'fieldpermissions',
						"VisibilityResolver: VisibleTo value of unexpected type " . get_class( $di )
					);
				}
			}
		} catch ( \Exception $e ) {
			wfDebugLog( 'fieldpermissions', 'Error resolving visible to: ' . $e->getMessage() );
		}

		$groups = array_values( array_unique( $groups ) );
		wfDebugLog(
			'fieldpermissions',
			"VisibilityResolver: Final VisibleTo groups for {$property->getKey()}: " . implode( ', ', $groups )
		);

		$this->propertyVisibleToCache[$key] = $groups;
		return $groups;
	}
    /**
     * Resolve a visibility level by any identifier the user might provide:
     * - Level name ("pi_only", "PI Only")
     * - Visibility page title ("Visibility:PI_Only", "Visibility:PI Only")
     *
     * @param string $identifier
     * @return \FieldPermissions\Model\VisibilityLevel|null
     */
    private function resolveLevelFromIdentifier( string $identifier ): ?VisibilityLevel {
        $normalized = $this->normalizeIdentifier( $identifier );
        if ( $normalized === '' ) {
            return null;
        }

        foreach ( $this->levelStore->getAllLevels() as $level ) {
            $candidates = array_filter( [
                $level->getName(),
                $level->getPageTitle()
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
     * Normalize identifiers so that, e.g., "PI Only", "pi_only", and "PI_ONLY"
     * all map to the same key.
     *
     * @param string $value
     * @return string
     */
    private function normalizeIdentifier( string $value ): string {
        $value = trim( $value );
        if ( $value === '' ) {
            return '';
        }

        // MediaWiki titles use underscores internally. Convert spaces to underscores,
        // then lowercase so comparisons are case-insensitive.
        return strtolower( str_replace( ' ', '_', $value ) );
    }

    /**
     * Normalize group names so comparisons are case-insensitive and namespace-agnostic.
     *
     * Examples:
     *  - "Sysop"        -> "sysop"
     *  - "Group:Pi"     -> "pi"
     *  - "Research Team" -> "research_team"
     *
     * @param string $group
     * @return string
     */
    private function normalizeGroupName( string $group ): string {
        $group = trim( $group );
        if ( $group === '' ) {
            return '';
        }

        // Strip namespace-like prefixes (e.g., "Group:PI")
        if ( strpos( $group, ':' ) !== false ) {
            $group = substr( $group, strrpos( $group, ':' ) + 1 );
        }

        // Convert spaces to underscores and lowercase for case-insensitive comparison
        $group = str_replace( ' ', '_', $group );

        return strtolower( $group );
    }

    /**
     * Some SMW versions expose visible-to values as legacy global classes (e.g. SMWDIBlob).
     * Treat any of those blob/string types the same as DIString/DIBlob.
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
     * Similar compatibility helper for wiki-page DI classes.
     *
     * @param mixed $di
     * @return bool
     */
    private function isWikiPageDataItem( $di ): bool {
        return $di instanceof DIWikiPage
            || ( class_exists( '\SMWDIWikiPage', false ) && $di instanceof \SMWDIWikiPage );
    }
}

