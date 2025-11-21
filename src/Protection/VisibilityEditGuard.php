<?php

namespace FieldPermissions\Protection;

use MediaWiki\User\UserIdentity;
use MediaWiki\Title\Title;
use MediaWiki\Status\Status;
use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use FieldPermissions\Visibility\VisibilityResolver;
use FieldPermissions\Visibility\PermissionEvaluator;
use SMW\DIProperty;

/**
 * VisibilityEditGuard
 *
 * Enforces restrictions on:
 *   1. Editing visibility-definition pages
 *   2. Editing restricted SMW Property pages
 *   3. Injecting visibility-related SMW annotations into page content
 *
 * This ensures that visibility rules cannot be modified by non-authorized users.
 */
class VisibilityEditGuard {

	private VisibilityResolver $resolver;
	private PermissionEvaluator $evaluator;

	/** @var string Prefix for visibility definition pages */
	private const VISIBILITY_PREFIX = 'Visibility:';

	public function __construct(
		VisibilityResolver $resolver,
		PermissionEvaluator $evaluator
	) {
		$this->resolver   = $resolver;
		$this->evaluator  = $evaluator;
	}

	/**
	 * Enforces edit permissions for:
	 *   - Visibility: pages (definition pages)
	 *   - Property pages with existing visibility settings
	 *
	 * @param Title        $title
	 * @param UserIdentity $user
	 * @return Status  Fatal if blocked; Good otherwise
	 */
	public function checkEditPermission( $title, UserIdentity $user ): Status {

		// 1. Visibility definition pages (`Visibility:*`)
		if ( $this->isVisibilityDefinitionPage( $title ) ) {
			if ( !$this->canManageVisibility( $user ) ) {
				return Status::newFatal( 'fieldpermissions-edit-denied-visibility' );
			}
		}

		// 2. SMW Property: pages
		if ( $title->getNamespace() === SMW_NS_PROPERTY ) {
			if ( $this->isRestrictedProperty( $title ) ) {
				if ( !$this->canManageVisibility( $user ) ) {
					return Status::newFatal( 'fieldpermissions-edit-denied-property' );
				}
			}
		}

		return Status::newGood();
	}

	/**
	 * Prevent non-authorized users from adding visibility annotations:
	 *   [[Has visibility level::...]]
	 *   [[Visible to::...]]
	 *
	 * Only applies to TextContent.
	 *
	 * @param Content      $content
	 * @param UserIdentity $user
	 * @return Status
	 */
	public function validateContent( Content $content, UserIdentity $user ): Status {

		// Authorized users may always edit
		if ( $this->canManageVisibility( $user ) ) {
			return Status::newGood();
		}

		// Only text content can contain SMW annotations
		if ( !$content instanceof TextContent ) {
			return Status::newGood();
		}

		$text = $content->getText();

		// Match SMW property syntax for visibility annotations
		$patterns = [
			'/\[\[\s*Has\s+visibility\s+level\s*::/i',
			'/\[\[\s*Visible\s+to\s*::/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return Status::newFatal( 'fieldpermissions-edit-denied-content' );
			}
		}

		return Status::newGood();
	}

	/* ---------------------------------------------------------------------
	 * Helper Methods
	 * ------------------------------------------------------------------ */

	/**
	 * Determines if the page is part of the "Visibility" definition system.
	 *
	 * Supports:
	 *   - Literal prefix “Visibility:Something”
	 *   - Possible future custom namespace
	 *
	 * @param Title $title
	 * @return bool
	 */
	private function isVisibilityDefinitionPage( Title $title ): bool {
		$full = $title->getPrefixedText();

		return (
			// Prefix-based detection (most common)
			str_starts_with( $full, self::VISIBILITY_PREFIX ) ||
			// Support a hypothetical dedicated namespace in future
			$title->getNsText() === 'Visibility'
		);
	}

	/**
	 * Determines whether a Property: page already has visibility restrictions.
	 *
	 * @param Title $title
	 * @return bool true if restricted
	 */
	private function isRestrictedProperty( Title $title ): bool {
		try {
			$property = new DIProperty( $title->getText() );

			$level     = $this->resolver->getPropertyLevel( $property );
			$visibleTo = $this->resolver->getPropertyVisibleTo( $property );

			return $level > 0 || !empty( $visibleTo );
		}
		catch ( \Exception $e ) {
			// Invalid property name — treat as unrestricted
			return false;
		}
	}

	/**
	 * Checks whether the user has permission to manage visibility rules.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function canManageVisibility( UserIdentity $user ): bool {
		$services = MediaWikiServices::getInstance();
		$pm = $services->getPermissionManager();
		return $pm->userHasRight( $user, 'fp-manage-visibility' );
	}
}
