<?php

namespace FieldPermissions\Permissions;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserGroupManager;

/**
 * Computes whether a user satisfies a required field level.
 *
 * Level permissions are numeric, and users inherit the highest level
 * allowed by any group they belong to.
 */
class LevelPermissionChecker {

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
	 * Determine whether the user is allowed to see a field requiring $requiredLevel.
	 *
	 * @param UserIdentity $user
	 * @param string       $requiredLevel  Level name (e.g., "public", "internal")
	 * @return bool
	 */
	public function hasAccess( UserIdentity $user, string $requiredLevel ): bool {

		$requiredValue = $this->config->getLevelValue( $requiredLevel );
		if ( $requiredValue === null ) {
			// Unknown level → safest to deny
			wfDebugLog( 'fieldpermissions', 'LevelPermissionChecker::hasAccess: Unknown required level "' . $requiredLevel . '" for user ' . $user->getName() . ' → DENY' );
			return false;
		}

		$userMaxLevel = $this->getUserMaxLevelValue( $user );
		if ( $userMaxLevel === null ) {
			wfDebugLog( 'fieldpermissions', 'LevelPermissionChecker::hasAccess: User ' . $user->getName() . ' has no max level, requiredLevel=' . $requiredLevel . ' (value=' . $requiredValue . ') → DENY' );
			return false;
		}

		$hasAccess = $userMaxLevel >= $requiredValue;
		wfDebugLog( 'fieldpermissions', 'LevelPermissionChecker::hasAccess: User ' . $user->getName() . ' maxLevel=' . $userMaxLevel . ' requiredLevel=' . $requiredLevel . ' (value=' . $requiredValue . ') → ' . ( $hasAccess ? 'ALLOW' : 'DENY' ) );
		return $hasAccess;
	}

	/**
	 * Compute user's maximum allowed numeric level.
	 *
	 * @param UserIdentity $user
	 * @return int|null
	 */
	private function getUserMaxLevelValue( UserIdentity $user ): ?int {

		$userGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
		$maxValue = null;

		foreach ( $userGroups as $group ) {
			$groupLevelName = $this->config->getGroupMaxLevel( $group );
			if ( $groupLevelName === null ) {
				continue;
			}

			$levelValue = $this->config->getLevelValue( $groupLevelName );
			if ( $levelValue === null ) {
				continue;
			}

			if ( $maxValue === null || $levelValue > $maxValue ) {
				$maxValue = $levelValue;
			}
		}

		// Anonymous user fallback "*"
		if ( $user->getId() === 0 ) {
			$anonLevelName = $this->config->getGroupMaxLevel( '*' );
			if ( $anonLevelName !== null ) {
				$anonLevelValue = $this->config->getLevelValue( $anonLevelName );
				if (
					$anonLevelValue !== null &&
					( $maxValue === null || $anonLevelValue > $maxValue )
				) {
					$maxValue = $anonLevelValue;
				}
			}
		}

		return $maxValue;
	}
}
