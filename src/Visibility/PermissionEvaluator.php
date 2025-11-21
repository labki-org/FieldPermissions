<?php

namespace FieldPermissions\Visibility;

use FieldPermissions\Config\GroupLevelStore;
use FieldPermissions\Model\UserVisibilityProfile;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

/**
 * PermissionEvaluator
 *
 * Responsible for computing:
 *   - A user's visibility profile (max permitted visibility level, normalized groups)
 *   - Whether a user is allowed to view a given property
 *
 * A "visibility profile" is derived from:
 *   - The user's effective MediaWiki groups
 *   - Configured group → max visibility-level mappings
 *
 * Logic:
 *   1. If a property specifies VisibleTo groups, those override everything else.
 *   2. Otherwise compare user's max-level vs property-level.
 *
 * Results are cached per-request per-user to avoid repeated lookups.
 */
class PermissionEvaluator {

	/** @var GroupLevelStore */
	private GroupLevelStore $groupStore;

	/** @var UserGroupManager */
	private UserGroupManager $userGroupManager;

	/** @var array<string,UserVisibilityProfile> */
	private array $userProfiles = [];

	public function __construct(
		GroupLevelStore $groupStore,
		UserGroupManager $userGroupManager
	) {
		$this->groupStore        = $groupStore;
		$this->userGroupManager  = $userGroupManager;
	}

	/* --------------------------------------------------------------------
	 * PUBLIC API
	 * ------------------------------------------------------------------ */

	/**
	 * Compute and return a UserVisibilityProfile for the given user.
	 *
	 * User groups are normalized (case-insensitive, stripped prefixes, underscores),
	 * and group-level resolution is derived entirely from our fp_group_levels table.
	 *
	 * Anonymous users:
	 *   - MW normally treats "*" as the anonymous group
	 *   - We ensure "*" is present in the normalized group list
	 *
	 * @param UserIdentity $user
	 * @return UserVisibilityProfile
	 */
	public function getUserProfile( UserIdentity $user ): UserVisibilityProfile {
		$cacheKey = $this->makeCacheKey( $user );

		if ( isset( $this->userProfiles[$cacheKey] ) ) {
			return $this->userProfiles[$cacheKey];
		}

		/* --------------------------------------------------------------
		 * STEP 1: Gather and normalize user groups
		 * ---------------------------------------------------------- */

		$rawGroups = $this->userGroupManager->getUserEffectiveGroups( $user );

		$normalizedGroups = array_values( array_unique(
			array_filter(
				array_map( [ $this, 'normalizeGroupName' ], $rawGroups ),
				static fn ( $g ) => $g !== ''
			)
		) );

		wfDebugLog(
			'fieldpermissions',
			"PermissionEvaluator: User {$user->getName()} effective groups: "
				. implode( ', ', $normalizedGroups )
		);

		// Ensure anonymous users include '*' always
		if ( $user->getId() === 0 && !in_array( '*', $normalizedGroups, true ) ) {
			$normalizedGroups[] = '*';
		}

		$normalizedGroups = array_values( array_unique( $normalizedGroups ) );

		/* --------------------------------------------------------------
		 * STEP 2: Determine user's max numeric visibility level
		 * ---------------------------------------------------------- */

		$maxLevel = 0;

		foreach ( $normalizedGroups as $group ) {
			$groupLevel = $this->groupStore->getGroupMaxLevel( $group );

			wfDebugLog(
				'fieldpermissions',
				"PermissionEvaluator: Group '$group' max level: " .
				( $groupLevel !== null ? $groupLevel : 'NULL (not set)' )
			);

			if ( $groupLevel !== null && $groupLevel > $maxLevel ) {
				$maxLevel = $groupLevel;
			}
		}

		wfDebugLog(
			'fieldpermissions',
			"PermissionEvaluator: User {$user->getName()} final resolved max level = $maxLevel"
		);

		/* --------------------------------------------------------------
		 * STEP 3: Build profile + cache
		 * ---------------------------------------------------------- */

		$profile = new UserVisibilityProfile( $maxLevel, $normalizedGroups );
		$this->userProfiles[$cacheKey] = $profile;

		return $profile;
	}

	/**
	 * Determine whether a user is allowed to view a given property.
	 *
	 * Priority rules:
	 *   1. "Visible to" groups override numeric level checks.
	 *      If ANY group matches, allow.
	 *
	 *   2. Otherwise evaluate numeric visibility level:
	 *      allow iff userMax >= propLevel.
	 *
	 * @param UserIdentity $user
	 * @param int $propLevel
	 * @param string[] $visibleToGroups Normalized group names (lowercase)
	 * @return bool
	 */
	public function mayViewProperty(
		UserIdentity $user,
		int $propLevel,
		array $visibleToGroups
	): bool {

		$profile = $this->getUserProfile( $user );

		/* --------------------------------------------------------------
		 * RULE 1: Explicit group-level access
		 * ---------------------------------------------------------- */
		if ( !empty( $visibleToGroups ) ) {
			$userGroups = $profile->getGroups();

			foreach ( $visibleToGroups as $allowedGroup ) {
				if ( in_array( $allowedGroup, $userGroups, true ) ) {
					wfDebugLog(
						'fieldpermissions',
						"PermissionEvaluator: Access allowed—user group match ($allowedGroup)"
					);
					return true;
				}
			}

			wfDebugLog(
				'fieldpermissions',
				"PermissionEvaluator: Access denied—user {$user->getName()} lacks required VisibleTo group(s) "
					. implode( ', ', $visibleToGroups )
			);

			return false;
		}

		/* --------------------------------------------------------------
		 * RULE 2: Numeric visibility level
		 * ---------------------------------------------------------- */

		$userMax = $profile->getMaxLevel();

		if ( $userMax >= $propLevel ) {
			return true;
		}

		wfDebugLog(
			'fieldpermissions',
			"PermissionEvaluator: Access denied—user max level $userMax < property level $propLevel"
		);

		return false;
	}

	/* --------------------------------------------------------------------
	 * INTERNAL HELPERS
	 * ------------------------------------------------------------------ */

	/**
	 * Create a cache key that works for both registered and anonymous users.
	 */
	private function makeCacheKey( UserIdentity $user ): string {
		return $user->getId() > 0
			? "ID:{$user->getId()}"
			: "NAME:" . $user->getName();
	}

	/**
	 * Normalize MediaWiki group names:
	 *   - Lowercase
	 *   - Replace spaces with underscores
	 *   - Strip any namespace-style prefixes ("Group:PI" → "pi")
	 *
	 * @param string $group
	 * @return string
	 */
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
