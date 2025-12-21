<?php

namespace PropertyPermissions\Model;

/**
 * UserVisibilityProfile
 *
 * Represents the resolved visibility profile for a given user.
 * This model encapsulates:
 *
 *   - The user's **maximum allowed numeric visibility level**
 *   - The list of **user groups** contributing to that visibility level
 *
 * This class is used by PermissionEvaluator to determine whether
 * a given user may view a property with:
 *   - a required numeric level, or
 *   - a required set of permitted user groups.
 *
 * Instances of this class are immutable data containers.
 */
class UserVisibilityProfile {

	/** @var int Numeric visibility level (e.g., 0 = public, higher = more restricted) */
	private int $maxLevel;

	/** @var string[] List of user groups for the user (e.g., [ 'sysop', 'researcher' ]) */
	private array $groups;

	/**
	 * @param int $maxLevel User's maximum allowed visibility level
	 * @param string[] $groups List of user groups (canonical group names)
	 */
	public function __construct( int $maxLevel, array $groups ) {
		$this->maxLevel = $maxLevel;
		$this->groups = $groups;
	}

	/**
	 * Return the user's maximum allowed numeric visibility level.
	 *
	 * @return int
	 */
	public function getMaxLevel(): int {
		return $this->maxLevel;
	}

	/**
	 * Return the list of user groups that apply to this user.
	 *
	 * @return string[]
	 */
	public function getGroups(): array {
		return $this->groups;
	}
}
