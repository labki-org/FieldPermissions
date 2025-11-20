<?php

namespace FieldPermissions\Permissions;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserGroupManager;

/**
 * Group-based access checker for {{#field-groups: ... }}.
 *
 * A field may declare:
 *   - direct group names (e.g., "sysop", "lab-member")
 *   - group-set names defined in $wgFieldPermissionsGroupSets
 *
 * Access is granted if the user belongs to ANY expanded group.
 */
class GroupPermissionChecker {

	private PermissionConfig $config;
	private UserGroupManager $userGroupManager;

	public function __construct(
		PermissionConfig $config,
		UserGroupManager $userGroupManager
	) {
		$this->config = $config;
		$this->userGroupManager = $userGroupManager;
	}

	/**
	 * Check whether the user belongs to any of the required groups.
	 *
	 * @param UserIdentity $user
	 * @param array<string> $requiredGroups  Direct group names OR set names
	 * @return bool
	 */
	public function hasAccess( UserIdentity $user, array $requiredGroups ): bool {

		// Expand set names into actual group names
		$expanded = $this->expandGroupSets( $requiredGroups );
		if ( $expanded === [] ) {
			// No required groups → safest to deny
			wfDebugLog( 'fieldpermissions', 'GroupPermissionChecker::hasAccess: No valid required groups for user ' . $user->getName() . ' → DENY' );
			return false;
		}

		$userGroups = $this->userGroupManager->getUserEffectiveGroups( $user );

		// Typical group-access semantics treat "*" as "everyone including anonymous"
		// If "*" is in required groups, auto-allow
		if ( in_array( '*', $expanded, true ) ) {
			wfDebugLog( 'fieldpermissions', 'GroupPermissionChecker::hasAccess: User ' . $user->getName() . ' requiredGroups include "*" → ALLOW' );
			return true;
		}

		// Intersection test
		$hasAccess = !empty( array_intersect( $userGroups, $expanded ) );
		wfDebugLog( 'fieldpermissions', 'GroupPermissionChecker::hasAccess: User ' . $user->getName() . ' (ID: ' . $user->getId() . ') userGroups=' . implode( ',', $userGroups ) . ' requiredGroups=' . implode( ',', $expanded ) . ' → ' . ( $hasAccess ? 'ALLOW' : 'DENY' ) );
		return $hasAccess;
	}

	/**
	 * Expand group sets into actual group names.
	 *
	 * If a token is:
	 *   - a group-set name → expand to groups in that set
	 *   - a group name → pass through as-is
	 *
	 * @param array<string> $groups
	 * @return array<string>
	 */
	private function expandGroupSets( array $groups ): array {

		$expanded = [];

		foreach ( $groups as $token ) {

			$token = trim( $token );
			if ( $token === '' ) {
				continue;
			}

			// Set expansion
			$set = $this->config->getGroupSet( $token );
			if ( $set !== null ) {
				foreach ( $set as $groupName ) {
					if ( is_string( $groupName ) && $groupName !== '' ) {
						$expanded[] = $groupName;
					}
				}
				continue;
			}

			// Direct group name
			$expanded[] = $token;
		}

		return array_values( array_unique( $expanded ) );
	}
}
