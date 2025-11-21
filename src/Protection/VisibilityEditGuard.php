<?php

namespace FieldPermissions\Protection;

use MediaWiki\User\UserIdentity;
use MediaWiki\Title\Title; // Correct namespace for Title in modern MW
use MediaWiki\Status\Status;
use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use FieldPermissions\Visibility\VisibilityResolver;
use FieldPermissions\Visibility\PermissionEvaluator;
use SMW\DIProperty;

class VisibilityEditGuard {
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
	 * Check if user has permission to edit a page (Visibility definition or Property page).
	 *
	 * @param Title $title
	 * @param UserIdentity $user
	 * @return Status
	 */
	public function checkEditPermission( $title, UserIdentity $user ): Status {
		// If it's a Visibility: page (assuming user created this namespace or uses pseudo-namespace)
		// If namespace is "Visibility" (we don't have ID, check name).
		// Or if the title starts with "Visibility:" and we treat it as such.
		
		// Ideally we check if "Visibility" namespace exists.
		// For now, check text prefix or namespace ID if known.
		// Let's assume standard namespace check if it exists, or text.
		
		$nsName = $title->getNamespace() === NS_MAIN ? '' : $title->getNsText();
		
		// Check if title is in "Visibility" namespace (if it exists) or starts with "Visibility:" in main/other
		// The plan mentioned "Create a dedicated Visibility namespace".
		// Users might just use main namespace pages like "Visibility:Public".
		
		if ( $title->getText() === 'Visibility' || strpos( $title->getPrefixedText(), 'Visibility:' ) === 0 ) {
			// It's a visibility definition page
			if ( !$this->canManageVisibility( $user ) ) {
				return Status::newFatal( 'fieldpermissions-edit-denied-visibility' );
			}
		}

		// If it's a Property page
		if ( $title->getNamespace() === SMW_NS_PROPERTY ) {
			// Check if this property is currently restricted.
			// We can't easily know if it IS restricted without parsing it or checking store.
			// But we can check if it HAS restriction properties in store.
			
			try {
				$property = new DIProperty( $title->getText() );
				$level = $this->resolver->getPropertyLevel( $property );
				$visibleTo = $this->resolver->getPropertyVisibleTo( $property );
				
				if ( $level > 0 || !empty( $visibleTo ) ) {
					// It is restricted. Only managers can edit.
					if ( !$this->canManageVisibility( $user ) ) {
						return Status::newFatal( 'fieldpermissions-edit-denied-property' );
					}
				}
			} catch ( \Exception $e ) {
				// If invalid property, ignore
			}
		}

		return Status::newGood();
	}

	/**
	 * Validate content being saved.
	 * Prevent adding "Has visibility level" or "Visible to" if not allowed.
	 *
	 * @param Content $content
	 * @param UserIdentity $user
	 * @return Status
	 */
	public function validateContent( Content $content, UserIdentity $user ): Status {
		if ( $this->canManageVisibility( $user ) ) {
			return Status::newGood();
		}

		if ( !$content instanceof TextContent ) {
			return Status::newGood();
		}

		$text = $content->getText();

		// Check for "Has visibility level" or "Visible to"
		// Regex for SMW syntax: [[Has visibility level::...]]
		// Property names can be localized or strict. Assuming strict English for now as per extension.
		
		$patterns = [
			'/\[\[\s*(Has visibility level|Visible to)\s*::/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return Status::newFatal( 'fieldpermissions-edit-denied-content' );
			}
		}

		return Status::newGood();
	}

	private function canManageVisibility( UserIdentity $user ): bool {
		$services = \MediaWiki\MediaWikiServices::getInstance();
		$permissionManager = $services->getPermissionManager();
		return $permissionManager->userHasRight( $user, 'fp-manage-visibility' );
	}
}

