<?php

namespace FieldPermissions\Visibility;

use FieldPermissions\Config\GroupLevelStore;
use FieldPermissions\Model\UserVisibilityProfile;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

class PermissionEvaluator {
	private GroupLevelStore $groupStore;
	private UserGroupManager $userGroupManager;
	private array $userProfiles = [];

	public function __construct(
		GroupLevelStore $groupStore,
		UserGroupManager $userGroupManager
	) {
		$this->groupStore  = $groupStore;
		$this->userGroupManager = $userGroupManager;
	}

	public function getUserProfile( UserIdentity $user ): UserVisibilityProfile {
		$userId = $user->getId();
		$key = $userId > 0 ? "ID:$userId" : "NAME:" . $user->getName();

		if ( isset( $this->userProfiles[$key] ) ) {
			return $this->userProfiles[$key];
		}

		$rawGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
		$groups = array_values( array_unique( array_filter(
			array_map( [ $this, 'normalizeGroupName' ], $rawGroups ),
			static fn ( $g ) => $g !== ''
		) ) );

		wfDebugLog(
			'fieldpermissions',
			"PermissionEvaluator: User " . $user->getName() . " has groups: " . implode( ', ', $groups )
		);

		if ( $user->getId() === 0 && !in_array( '*', $groups, true ) ) {
			$groups[] = '*';
		}
		$groups = array_values( array_unique( $groups ) );

		$maxLevel = 0;
		foreach ( $groups as $group ) {
			$gl = $this->groupStore->getGroupMaxLevel( $group );
			wfDebugLog(
				'fieldpermissions',
				"PermissionEvaluator: Group '$group' has max level: " . ( $gl !== null ? $gl : 'NULL (not configured)' )
			);
			if ( $gl !== null && $gl > $maxLevel ) {
				$maxLevel = $gl;
			}
		}

		wfDebugLog(
			'fieldpermissions',
			"PermissionEvaluator: User " . $user->getName() . " final max level: $maxLevel"
		);

		$profile = new UserVisibilityProfile( $maxLevel, $groups );
		$this->userProfiles[$key] = $profile;

		return $profile;
	}

	public function mayViewProperty( UserIdentity $user, int $propLevel, array $visibleToGroups ): bool {
		$profile = $this->getUserProfile( $user );

		if ( !empty( $visibleToGroups ) ) {
			$userGroups = $profile->getGroups();
			foreach ( $visibleToGroups as $allowedGroup ) {
				if ( in_array( $allowedGroup, $userGroups, true ) ) {
					wfDebugLog( 'fieldpermissions', "Access allowed by group match: $allowedGroup" );
					return true;
				}
			}

			wfDebugLog(
				'fieldpermissions',
				"Access denied: user " . $user->getName() . " lacks required VisibleTo group (" . implode( ', ', $visibleToGroups ) . ")"
			);
			return false;
		}

		$userMax = $profile->getMaxLevel();
		if ( $userMax >= $propLevel ) {
			return true;
		}

		wfDebugLog( 'fieldpermissions', "Access denied. User max level $userMax < Property level $propLevel" );
		return false;
	}

	private function normalizeGroupName( string $group ): string {
		$group = trim( $group );
		if ( $group === '' ) {
			return '';
		}

		if ( $group === '*' ) {
			return '*';
		}

		if ( strpos( $group, ':' ) !== false ) {
			$group = substr( $group, strrpos( $group, ':' ) + 1 );
		}

		return strtolower( str_replace( ' ', '_', $group ) );
	}
}

