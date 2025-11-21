<?php

namespace FieldPermissions\Config;

use Wikimedia\Rdbms\ILoadBalancer;
use FieldPermissions\Model\VisibilityLevel;

class VisibilityLevelStore {
	private ILoadBalancer $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @return VisibilityLevel[]
	 */
	public function getAllLevels(): array {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
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

	public function addLevel( string $name, int $numericLevel, ?string $pageTitle = null ): void {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$dbw->insert(
			'fp_visibility_levels',
			[
				'vl_name' => $name,
				'vl_numeric_level' => $numericLevel,
				'vl_page_title' => $pageTitle
			],
			__METHOD__
		);
		wfDebugLog( 'fieldpermissions', "Added visibility level: $name ($numericLevel)" );
	}

	public function updateLevel( int $id, string $name, int $numericLevel, ?string $pageTitle = null ): void {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$dbw->update(
			'fp_visibility_levels',
			[
				'vl_name' => $name,
				'vl_numeric_level' => $numericLevel,
				'vl_page_title' => $pageTitle
			],
			[ 'vl_id' => $id ],
			__METHOD__
		);
		wfDebugLog( 'fieldpermissions', "Updated visibility level ID $id: $name ($numericLevel)" );
	}

	public function deleteLevel( int $id ): void {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$dbw->delete(
			'fp_visibility_levels',
			[ 'vl_id' => $id ],
			__METHOD__
		);
		wfDebugLog( 'fieldpermissions', "Deleted visibility level ID $id" );
	}

	public function getLevelByName( string $name ): ?VisibilityLevel {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
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

