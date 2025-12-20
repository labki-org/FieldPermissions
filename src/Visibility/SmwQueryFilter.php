<?php

namespace FieldPermissions\Visibility;

use MediaWiki\Context\RequestContext;
use SMW\DIProperty;
use SMW\DIWikiPage;

/**
 * SmwQueryFilter
 * ----------------
 * Tier-1 semantic filtering component.
 *
 * This class performs *semantic* (post-query) filtering that does not depend
 * on the ResultPrinter layer. It is currently used for Factbox filtering.
 *
 * Why this exists:
 * - Factbox output is generated *outside* the ResultPrinter pipeline.
 * - It receives raw property/value pairs directly from SMW.
 * - Tier-2 filtering alone is insufficient to secure Factbox output.
 *
 * The filtering logic:
 * - For each DIProperty in the Factbox:
 *   1. Resolve the property's visibility level.
 *   2. Resolve any VisibleTo group restrictions.
 *   3. Run PermissionEvaluator to determine if user may view it.
 * - Remove properties the user should not see.
 */
class SmwQueryFilter {

	private VisibilityResolver $resolver;
	private PermissionEvaluator $evaluator;

	public function __construct(
		VisibilityResolver $resolver,
		PermissionEvaluator $evaluator
	) {
		$this->resolver   = $resolver;
		$this->evaluator  = $evaluator;
	}

	/**
	 * Filter Factbox properties for a page.
	 *
	 * SMW passes:
	 *   - $subject    : DIWikiPage representing the page being shown
	 *   - &$properties: array keyed by DIProperty or property key string
	 *
	 * Example structure:
	 *   $properties = [
	 *      DIProperty("Has email") => [ DataItem, DataItem ],
	 *      DIProperty("Has PI")    => [ DataItem ],
	 *   ];
	 *
	 * This method removes prohibited properties entirely.
	 *
	 * @param DIWikiPage $subject
	 * @param array &$properties (modified in-place)
	 */
	public function filterFactboxProperties( DIWikiPage $subject, array &$properties ): void {
		$user = RequestContext::getMain()->getUser();

		wfDebugLog(
			'fieldpermissions',
			"SmwQueryFilter: Filtering Factbox for " . $subject->getTitle()->getPrefixedText() .
			" (User=" . $user->getName() . ")"
		);

		foreach ( $properties as $key => $values ) {

			// Normalize property key → DIProperty
			$property = $key instanceof DIProperty
				? $key
				: new DIProperty( (string)$key );

			$propertyKey  = $property->getKey();
			$level        = $this->resolver->getPropertyLevel( $property );
			$visibleTo    = $this->resolver->getPropertyVisibleTo( $property );

			// Public properties are always included
			if ( $level === 0 && empty( $visibleTo ) ) {
				continue;
			}

			// Apply permission logic
			if ( !$this->evaluator->mayViewProperty( $user, $level, $visibleTo ) ) {
				unset( $properties[$key] );
				wfDebugLog(
					'fieldpermissions',
					"SmwQueryFilter: HIDDEN Factbox property '{$propertyKey}' for user '" . $user->getName() . "'"
				);
			} else {
				wfDebugLog(
					'fieldpermissions',
					"SmwQueryFilter: ALLOW Factbox property '{$propertyKey}' for user '" . $user->getName() . "'"
				);
			}
		}
	}
}
