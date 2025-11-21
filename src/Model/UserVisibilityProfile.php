<?php

namespace FieldPermissions\Model;

class UserVisibilityProfile {
	private int $maxLevel;
	private array $groups;

	public function __construct( int $maxLevel, array $groups ) {
		$this->maxLevel = $maxLevel;
		$this->groups = $groups;
	}

	public function getMaxLevel(): int {
		return $this->maxLevel;
	}

	/**
	 * @return string[] List of user groups
	 */
	public function getGroups(): array {
		return $this->groups;
	}
}

