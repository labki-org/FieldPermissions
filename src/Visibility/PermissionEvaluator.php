<?php

namespace FieldPermissions\Visibility;

use FieldPermissions\Config\GroupLevelStore;
use FieldPermissions\Model\UserVisibilityProfile;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

class PermissionEvaluator {
	private GroupLevelStore $groupStore;
	private UserGroupManager $userGroupManager;

	// Cache per user to avoid rebuilding profile
	private array $userProfiles = [];

	public function __construct(
		GroupLevelStore $groupStore,
		UserGroupManager $userGroupManager
	) {
		$this->groupStore = $groupStore;
		$this->userGroupManager = $userGroupManager;
	}

	public function getUserProfile( UserIdentity $user ): UserVisibilityProfile {
		$userId = $user->getId();
		// For anon users, we can cache by name or just not cache as effectively?
		// Use name for key if ID is 0.
		$key = $userId > 0 ? "ID:$userId" : "NAME:" . $user->getName();

		if ( isset( $this->userProfiles[$key] ) ) {
			return $this->userProfiles[$key];
		}

		$groups = $this->userGroupManager->getUserEffectiveGroups( $user );
		
		wfDebugLog( 'fieldpermissions', "PermissionEvaluator: User " . $user->getName() . " has groups: " . implode( ', ', $groups ) );
		
		// For anonymous users, explicitly add '*' group if not present
		if ( $user->getId() === 0 && !in_array( '*', $groups, true ) ) {
			$groups[] = '*';
		}
		
		$maxLevel = 0;
		
		foreach ( $groups as $group ) {
			$gl = $this->groupStore->getGroupMaxLevel( $group );
			wfDebugLog( 'fieldpermissions', "PermissionEvaluator: Group '$group' has max level: " . ( $gl !== null ? $gl : 'NULL (not configured)' ) );
			if ( $gl !== null && $gl > $maxLevel ) {
				$maxLevel = $gl;
			}
		}

		wfDebugLog( 'fieldpermissions', "PermissionEvaluator: User " . $user->getName() . " final max level: $maxLevel" );
		
		$profile = new UserVisibilityProfile( $maxLevel, $groups );
		$this->userProfiles[$key] = $profile;
		
		return $profile;
	}

	/**
	 * Check if user can view a property.
	 *
	 * @param UserIdentity $user
	 * @param int $propLevel Numeric level required
	 * @param array $visibleToGroups List of groups explicitly allowed
	 * @return bool
	 */
	public function mayViewProperty( UserIdentity $user, int $propLevel, array $visibleToGroups ): bool {
		$profile = $this->getUserProfile( $user );
		
		// 1. Check explicit group allow-list (Visible to)
		if ( !empty( $visibleToGroups ) ) {
			$userGroups = $profile->getGroups();
			foreach ( $visibleToGroups as $allowedGroup ) {
				if ( in_array( $allowedGroup, $userGroups, true ) ) {
					wfDebugLog( 'fieldpermissions', "Access allowed by group match: $allowedGroup" );
					return true;
				}
			}
		}

		// 2. Check numeric level
		$userMax = $profile->getMaxLevel();
		if ( $userMax >= $propLevel ) {
			return true;
		}

		wfDebugLog( 'fieldpermissions', "Access denied. User max level $userMax < Property level $propLevel" );
		return false;
	}
}

