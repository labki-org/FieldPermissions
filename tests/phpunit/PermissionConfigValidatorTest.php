<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Services\PermissionConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PermissionConfigValidator
 */
class PermissionConfigValidatorTest extends TestCase {

	private PermissionConfigValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new PermissionConfigValidator();
	}

	public function testValidatesValidConfig(): void {
		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
				'internal' => 10,
			],
			'FieldPermissionsGroupMaxLevel' => [
				'*' => 'public',
				'user' => 'public',
			],
			'FieldPermissionsGroupSets' => [
				'all_admins' => ['sysop', 'pi'],
			],
		];

		// Should not throw
		$this->validator->validate($config);
		$this->assertTrue(true);
	}

	public function testRejectsEmptyLevels(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: 'FieldPermissionsLevels' must be a non-empty associative array.");

		$config = [
			'FieldPermissionsLevels' => [],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNonArrayLevels(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: 'FieldPermissionsLevels' must be a non-empty associative array.");

		$config = [
			'FieldPermissionsLevels' => 'not-an-array',
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsEmptyLevelName(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Level name must be a non-empty string.");

		$config = [
			'FieldPermissionsLevels' => [
				'' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNonIntegerLevelValue(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Level 'public' must map to a non-negative integer.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 'not-an-int',
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNegativeLevelValue(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Level 'public' must map to a non-negative integer.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => -1,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNonArrayGroupMaxLevel(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: 'FieldPermissionsGroupMaxLevel' must be an array.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => 'not-an-array',
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsEmptyGroupName(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Group names in GroupMaxLevel must be non-empty strings.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [
				'' => 'public',
			],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsUnknownLevelInGroupMaxLevel(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Group 'user' references unknown level 'nonexistent'.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [
				'user' => 'nonexistent',
			],
			'FieldPermissionsGroupSets' => [],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNonArrayGroupSets(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: 'FieldPermissionsGroupSets' must be an array.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => 'not-an-array',
		];

		$this->validator->validate($config);
	}

	public function testRejectsEmptyGroupSetName(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Group set names must be non-empty strings.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'' => ['sysop'],
			],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNonArrayGroupSetValue(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Group set 'all_admins' must contain an array of group names.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'all_admins' => 'not-an-array',
			],
		];

		$this->validator->validate($config);
	}

	public function testRejectsInvalidGroupInSet(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Group set 'all_admins' contains an invalid group name.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'all_admins' => ['sysop', ''],
			],
		];

		$this->validator->validate($config);
	}

	public function testRejectsNonStringGroupInSet(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FieldPermissions: Group set 'all_admins' contains an invalid group name.");

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'all_admins' => ['sysop', 123],
			],
		];

		$this->validator->validate($config);
	}

	public function testAllowsZeroLevelValue(): void {
		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [],
		];

		// Should not throw
		$this->validator->validate($config);
		$this->assertTrue(true);
	}

    public function testIgnoresExtraKeys(): void {
        $config = [
            'FieldPermissionsLevels' => ['public' => 0],
            'FieldPermissionsGroupMaxLevel' => [],
            'FieldPermissionsGroupSets' => [],
            'UnexpectedKey' => 'ignored',
        ];
    
        $this->validator->validate($config);
    
        $this->assertTrue(true);
    }
    
}

