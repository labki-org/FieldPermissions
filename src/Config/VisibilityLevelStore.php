<?php

namespace PropertyPermissions\Config;

use PropertyPermissions\Model\VisibilityLevel;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * VisibilityLevelStore
 *
 * Handles CRUD operations for visibility levels.
 *
 * Storage backend:
 *   Table: fp_visibility_levels
 *   Columns:
 *     - vl_id            (int, primary key)
 *     - vl_name          (string, human-readable label, e.g. "Private", "Internal")
 *     - vl_numeric_level (int, numeric level used for comparisons)
 *     - vl_page_title    (string|null, optional page explaining the level)
 *
 * This table provides the core visibility-level definitions used by the system.
 * It is edited via the Special:ManageVisibility admin interface.
 *
 * All reads use DB_REPLICA.
 * All writes use DB_PRIMARY.
 */
class VisibilityLevelStore {

	/** @var ILoadBalancer */
	private ILoadBalancer $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Convenience: return replica DB connection.
	 */
	private function getReplicaDB(): IDatabase {
		return $this->loadBalancer->getConnection( DB_REPLICA );
	}

	/**
	 * Convenience: return primary DB connection.
	 */
	private function getPrimaryDB(): IDatabase {
		return $this->loadBalancer->getConnection( DB_PRIMARY );
	}

	/**
	 * Return all visibility levels, ordered by numeric level ascending.
	 *
	 * @return VisibilityLevel[]
	 */
	public function getAllLevels(): array {
		$dbr = $this->getReplicaDB();

		$res = $dbr->select(
			'fp_visibility_levels',
			[ 'vl_id', 'vl_name', 'vl_page_title', 'vl_numeric_level' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'vl_numeric_level ASC' ]
		);

		$levels = [];
		foreach ( $res as $row ) {
			$levels[] = new VisibilityLevel(
				(int)$row->vl_id,
				$row->vl_name,
				(int)$row->vl_numeric_level,
				$row->vl_page_title
			);
		}

		return $levels;
	}

	/**
	 * Insert a new visibility level.
	 *
	 * @param string $name
	 * @param int $numericLevel
	 * @param string|null $pageTitle
	 */
	public function addLevel( string $name, int $numericLevel, ?string $pageTitle = null ): void {
		$dbw = $this->getPrimaryDB();

		$dbw->insert(
			'fp_visibility_levels',
			[
				'vl_name'          => $name,
				'vl_numeric_level' => $numericLevel,
				'vl_page_title'    => $pageTitle
			],
			__METHOD__
		);

		wfDebugLog(
			'propertypermissions',
			"[VisibilityLevelStore] Added level: '{$name}' (numeric={$numericLevel})"
		);
	}

	/**
	 * Update an existing visibility level.
	 *
	 * @param int $id
	 * @param string $name
	 * @param int $numericLevel
	 * @param string|null $pageTitle
	 */
	public function updateLevel( int $id, string $name, int $numericLevel, ?string $pageTitle = null ): void {
		$dbw = $this->getPrimaryDB();

		$dbw->update(
			'fp_visibility_levels',
			[
				'vl_name'          => $name,
				'vl_numeric_level' => $numericLevel,
				'vl_page_title'    => $pageTitle
			],
			[ 'vl_id' => $id ],
			__METHOD__
		);

		wfDebugLog(
			'propertypermissions',
			"[VisibilityLevelStore] Updated level ID={$id} to '{$name}' (numeric={$numericLevel})"
		);
	}

	/**
	 * Delete a visibility level by ID.
	 *
	 * @param int $id
	 */
	public function deleteLevel( int $id ): void {
		$dbw = $this->getPrimaryDB();

		$dbw->delete(
			'fp_visibility_levels',
			[ 'vl_id' => $id ],
			__METHOD__
		);

		wfDebugLog(
			'propertypermissions',
			"[VisibilityLevelStore] Deleted level ID={$id}"
		);
	}

	/**
	 * Retrieve a visibility level by its name.
	 *
	 * @param string $name
	 * @return VisibilityLevel|null
	 */
	public function getLevelByName( string $name ): ?VisibilityLevel {
		$dbr = $this->getReplicaDB();

		$row = $dbr->selectRow(
			'fp_visibility_levels',
			[ 'vl_id', 'vl_name', 'vl_page_title', 'vl_numeric_level' ],
			[ 'vl_name' => $name ],
			__METHOD__
		);

		if ( !$row ) {
			return null;
		}

		return new VisibilityLevel(
			(int)$row->vl_id,
			$row->vl_name,
			(int)$row->vl_numeric_level,
			$row->vl_page_title
		);
	}
}
