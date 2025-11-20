<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Permissions\PermissionConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PermissionConfig
 */
class PermissionConfigTest extends TestCase {

	public function testLoadsLevelsFromConfig(): void {
		$config = new PermissionConfig([
			'FieldPermissionsLevels' => [
				'public'   => 0,
				'internal' => 10
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => []
		]);

		$this->assertSame(0, $config->getLevelValue('public'));
		$this->assertSame(10, $config->getLevelValue('internal'));
		$this->assertNull($config->getLevelValue('nonexistent'));
	}

	public function testValidatesLevelMapping(): void {
		$this->expectException(InvalidArgumentException::class);

		new PermissionConfig([
			'FieldPermissionsLevels' => [
				'public' => 'not-an-int'
			],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => []
		]);
	}

	public function testValidatesGroupMaxLevelReferences(): void {
		$this->expectException(InvalidArgumentException::class);

		new PermissionConfig([
			'FieldPermissionsLevels' => [
				'public' => 0
			],
			'FieldPermissionsGroupMaxLevel' => [
				'user' => 'nonexistent_level'
			],
			'FieldPermissionsGroupSets' => []
		]);
	}

	public function testLoadsGroupMaxLevels(): void {
		$config = new PermissionConfig([
			'FieldPermissionsLevels' => [
				'public'   => 0,
				'internal' => 10,
			],
			'FieldPermissionsGroupMaxLevel' => [
				'user'  => 'public',
				'admin' => 'internal'
			],
			'FieldPermissionsGroupSets' => []
		]);

		$this->assertSame('public', $config->getGroupMaxLevel('user'));
		$this->assertSame('internal', $config->getGroupMaxLevel('admin'));
		$this->assertNull($config->getGroupMaxLevel('unknown'));
	}

	public function testLoadsGroupSets(): void {
		$config = new PermissionConfig([
			'FieldPermissionsLevels' => [],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'all_admins' => ['sysop', 'pi']
			]
		]);

		$this->assertSame(['sysop', 'pi'], $config->getGroupSet('all_admins'));
		$this->assertNull($config->getGroupSet('nonexistent'));
	}

	public function testRejectsNonArrayGroupSet(): void {
		$this->expectException(InvalidArgumentException::class);

		new PermissionConfig([
			'FieldPermissionsLevels' => [],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'all_admins' => 'not-an-array'
			]
		]);
	}

	public function testRejectsGroupSetWithNonStringEntries(): void {
		$this->expectException(InvalidArgumentException::class);

		new PermissionConfig([
			'FieldPermissionsLevels' => [],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets' => [
				'all_admins' => ['sysop', 123] // 123 is invalid
			]
		]);
	}
}
