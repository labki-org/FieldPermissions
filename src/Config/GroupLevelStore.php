<?php

namespace FieldPermissions\Config;

use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IDatabase;

/**
 * GroupLevelStore
 *
 * Handles persistence of "visibility levels" associated with MediaWiki user groups.
 * Each group may be assigned a *maximum allowed visibility level*, which is used
 * by the PermissionEvaluator to determine whether a user may view a property.
 *
 * Storage backend:
 *   Table: fp_group_levels
 *   Columns:
 *     - gl_group_name  (string, primary key)
 *     - gl_max_level   (int)
 *
 * This class provides simple CRUD operations for the table.
 *
 * All reads use DB_REPLICA.
 * All writes use DB_PRIMARY.
 */
class GroupLevelStore {

	/** @var ILoadBalancer */
	private ILoadBalancer $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Returns the replica DB connection.
	 */
	private function getReplicaDB(): IDatabase {
		return $this->loadBalancer->getConnection( DB_REPLICA );
	}

	/**
	 * Returns the primary DB connection.
	 */
	private function getPrimaryDB(): IDatabase {
		return $this->loadBalancer->getConnection( DB_PRIMARY );
	}

	/**
	 * Fetch the maximum visibility level defined for a user group.
	 *
	 * Example:
	 *   getGroupMaxLevel( 'sysop' )  → 5
	 *
	 * @param string $groupName  MediaWiki group name (e.g., "sysop", "user")
	 * @return int|null          Numeric max level, or null if no entry exists
	 */
	public function getGroupMaxLevel( string $groupName ): ?int {
		$dbr = $this->getReplicaDB();

		$level = $dbr->selectField(
			'fp_group_levels',
			'gl_max_level',
			[ 'gl_group_name' => $groupName ],
			__METHOD__
		);

		// selectField returns `false` if no row exists
		if ( $level === false ) {
			return null;
		}

		return (int)$level;
	}

	/**
	 * Fetch all group→level mappings.
	 *
	 * Example return structure:
	 *   [
	 *       'sysop'   => 5,
	 *       'manager' => 3,
	 *       'intern'  => 1
	 *   ]
	 *
	 * @return array<string,int>
	 */
	public function getAllGroupLevels(): array {
		$dbr = $this->getReplicaDB();

		$res = $dbr->select(
			'fp_group_levels',
			[ 'gl_group_name', 'gl_max_level' ],
			[],
			__METHOD__
		);

		$mappings = [];
		foreach ( $res as $row ) {
			$mappings[$row->gl_group_name] = (int)$row->gl_max_level;
		}

		return $mappings;
	}

	/**
	 * Insert or update the maximum visibility level for a group.
	 * Uses REPLACE to overwrite existing entries safely.
	 *
	 * @param string $groupName
	 * @param int $maxLevel
	 */
	public function setGroupMaxLevel( string $groupName, int $maxLevel ): void {
		$dbw = $this->getPrimaryDB();

		$dbw->replace(
			'fp_group_levels',
			[ 'gl_group_name' ], // unique key
			[
				'gl_group_name' => $groupName,
				'gl_max_level'  => $maxLevel
			],
			__METHOD__
		);

		wfDebugLog(
			'fieldpermissions',
			"[GroupLevelStore] Updated group '{$groupName}' → max_level={$maxLevel}"
		);
	}

	/**
	 * Remove a group→level mapping entirely.
	 *
	 * @param string $groupName
	 */
	public function removeGroupMapping( string $groupName ): void {
		$dbw = $this->getPrimaryDB();

		$dbw->delete(
			'fp_group_levels',
			[ 'gl_group_name' => $groupName ],
			__METHOD__
		);

		wfDebugLog(
			'fieldpermissions',
			"[GroupLevelStore] Removed mapping for group '{$groupName}'"
		);
	}
}

