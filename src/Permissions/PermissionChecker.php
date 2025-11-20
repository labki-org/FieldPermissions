<?php

namespace FieldPermissions\Permissions;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserGroupManager;

/**
 * Facade for permission checking that delegates to level- and group-based checkers.
 *
 * This wrapper allows parser functions and hooks to consume a single service
 * without knowing the underlying permission model.
 */
class PermissionChecker {

	public const SERVICE_NAME = 'FieldPermissions.PermissionChecker';

	private LevelPermissionChecker $levelChecker;
	private GroupPermissionChecker $groupChecker;

	/**
	 * @param PermissionConfig   $config
	 * @param UserGroupManager   $userGroupManager
	 */
	public function __construct(
		PermissionConfig $config,
		UserGroupManager $userGroupManager
	) {
		$this->levelChecker = new LevelPermissionChecker( $config, $userGroupManager );
		$this->groupChecker = new GroupPermissionChecker( $config, $userGroupManager );
	}

	/* ----------------------------------------------------------------------
	 * LEVEL-BASED ACCESS
	 * ---------------------------------------------------------------------- */

	/**
	 * Check if the user can access a required level.
	 *
	 * @param UserIdentity $user
	 * @param string       $requiredLevel
	 * @return bool
	 */
	public function hasLevelAccess( UserIdentity $user, string $requiredLevel ): bool {
		$result = $this->levelChecker->hasAccess( $user, $requiredLevel );
		wfDebugLog( 'fieldpermissions', 'PermissionChecker::hasLevelAccess: User ' . $user->getName() . ' (ID: ' . $user->getId() . ') requiredLevel=' . $requiredLevel . ' result=' . ( $result ? 'ALLOW' : 'DENY' ) );
		return $result;
	}

	/* ----------------------------------------------------------------------
	 * GROUP-BASED ACCESS
	 * ---------------------------------------------------------------------- */

	/**
	 * Check if the user belongs to any of the required groups or group sets.
	 *
	 * @param UserIdentity $user
	 * @param string[]     $requiredGroups
	 * @return bool
	 */
	public function hasGroupAccess( UserIdentity $user, array $requiredGroups ): bool {
		$result = $this->groupChecker->hasAccess( $user, $requiredGroups );
		wfDebugLog( 'fieldpermissions', 'PermissionChecker::hasGroupAccess: User ' . $user->getName() . ' (ID: ' . $user->getId() . ') requiredGroups=' . implode( ',', $requiredGroups ) . ' result=' . ( $result ? 'ALLOW' : 'DENY' ) );
		return $result;
	}
}
