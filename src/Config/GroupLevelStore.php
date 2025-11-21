<?php

namespace FieldPermissions\Config;

use Wikimedia\Rdbms\ILoadBalancer;

class GroupLevelStore {
	private ILoadBalancer $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Get max level for a specific group.
	 * @return int|null Numeric level or null if not set
	 */
	public function getGroupMaxLevel( string $groupName ): ?int {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$val = $dbr->selectField(
			'fp_group_levels',
			'gl_max_level',
			[ 'gl_group_name' => $groupName ],
			__METHOD__
		);

		return $val !== false ? (int)$val : null;
	}

	/**
	 * Get all group mappings.
	 * @return array<string, int> [ groupName => maxLevel ]
	 */
	public function getAllGroupLevels(): array {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
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

	public function setGroupMaxLevel( string $groupName, int $maxLevel ): void {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$dbw->replace(
			'fp_group_levels',
			[ 'gl_group_name' ],
			[
				'gl_group_name' => $groupName,
				'gl_max_level' => $maxLevel
			],
			__METHOD__
		);
		wfDebugLog( 'fieldpermissions', "Set max level for group $groupName to $maxLevel" );
	}

	public function removeGroupMapping( string $groupName ): void {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$dbw->delete(
			'fp_group_levels',
			[ 'gl_group_name' => $groupName ],
			__METHOD__
		);
		wfDebugLog( 'fieldpermissions', "Removed mapping for group $groupName" );
	}
}

