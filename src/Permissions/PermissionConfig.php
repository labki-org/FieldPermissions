<?php

namespace FieldPermissions\Permissions;

/**
 * Immutable configuration container for FieldPermissions.
 *
 * Loaded via ServiceWiring + FieldPermissionsServiceFactory.
 * All validation is performed by PermissionConfigValidator prior to construction.
 */
class PermissionConfig {

	public const SERVICE_NAME = 'FieldPermissions.PermissionConfig';

	/** @var array<string,int> */
	private array $levels;

	/** @var array<string,string> */
	private array $groupMaxLevel;

	/** @var array<string,string[]> */
	private array $groupSets;

	/**
	 * @param array{
	 *   FieldPermissionsLevels: array<string,int>|null,
	 *   FieldPermissionsGroupMaxLevel: array<string,string>|null,
	 *   FieldPermissionsGroupSets: array<string,string[]>|null
	 * } $config
	 */
	public function __construct( array $config ) {

		// ----- Provide built-in defaults if LocalSettings did not define values -----

		$levels = $config['FieldPermissionsLevels'] ?? null;
		if ( $levels === null ) {
			$levels = [
				'public'    => 0,
				'internal'  => 10,
				'sensitive' => 20,
			];
		}

		$groupMax = $config['FieldPermissionsGroupMaxLevel'] ?? null;
		if ( $groupMax === null ) {
			$groupMax = [
				'*'          => 'public',
				'user'       => 'public',
				'lab_member' => 'internal',
				'pi'         => 'sensitive',
			];
		}

		$groupSets = $config['FieldPermissionsGroupSets'] ?? null;
		if ( $groupSets === null ) {
			$groupSets = [
				'all_admins' => [ 'sysop', 'pi' ],
			];
		}

		$this->levels        = $levels;
		$this->groupMaxLevel = $groupMax;
		$this->groupSets     = $groupSets;
	}

	/* ----------------------------------------------------------------------
	 * LEVELS
	 * ---------------------------------------------------------------------- */

	public function getLevelValue( string $levelName ): ?int {
		return $this->levels[$levelName] ?? null;
	}

	public function hasLevel( string $levelName ): bool {
		return isset( $this->levels[$levelName] );
	}

	/** @return array<string,int> */
	public function getAllLevels(): array {
		return $this->levels;
	}

	/* ----------------------------------------------------------------------
	 * GROUP MAX LEVELS
	 * ---------------------------------------------------------------------- */

	public function getGroupMaxLevel( string $group ): ?string {
		return $this->groupMaxLevel[$group] ?? null;
	}

	/** @return array<string,string> */
	public function getAllGroupMaxLevels(): array {
		return $this->groupMaxLevel;
	}

	/* ----------------------------------------------------------------------
	 * GROUP SETS
	 * ---------------------------------------------------------------------- */

	public function getGroupSet( string $setName ): ?array {
		return $this->groupSets[$setName] ?? null;
	}

	/** @return array<string,string[]> */
	public function getAllGroupSets(): array {
		return $this->groupSets;
	}
}
