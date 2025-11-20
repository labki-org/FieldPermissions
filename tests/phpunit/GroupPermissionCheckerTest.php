<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Permissions\GroupPermissionChecker;
use FieldPermissions\Permissions\PermissionConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GroupPermissionChecker
 */
class GroupPermissionCheckerTest extends TestCase {

	/**
	 * Build a fully valid PermissionConfig for group-permission testing.
	 *
	 * GroupPermissionChecker does not use levels, but PermissionConfig requires
	 * all three sections to be present and valid.
	 */
	private function makeConfig(): PermissionConfig {

		$config = [
			'FieldPermissionsLevels' => [
				'public' => 0,
				'internal' => 1,
				'admin' => 2,
			],
			'FieldPermissionsGroupMaxLevel' => [
				// not used by group checker, but required structurally
				'*' => 'public'
			],
			'FieldPermissionsGroupSets' => [
				'all_admins' => [ 'sysop', 'pi' ],
				'mixed'      => [ 'staff', 'editor' ]
			],
		];

		return new PermissionConfig( $config );
	}

	/**
	 * Build a mock UserGroupManager that returns a fixed set of groups.
	 */
	private function makeGroupManager( array $groups ): UserGroupManager {
		$mgr = $this->createMock( UserGroupManager::class );
		$mgr->method( 'getUserEffectiveGroups' )
			->willReturn( $groups );
		return $mgr;
	}

	/**
	 * Build a dummy user (not used by checker except for group lookup).
	 */
	private function makeUser(): User {
		return $this->createMock( User::class );
	}

	/* -------------------------------------------------------------------------
	 * TESTS
	 * ---------------------------------------------------------------------- */

	public function testUserInRequiredGroupHasAccess(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'sysop' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		$this->assertTrue( $checker->hasAccess( $user, [ 'sysop' ] ) );
		$this->assertTrue( $checker->hasAccess( $user, [ 'user', 'sysop' ] ) );
	}

	public function testUserNotInRequiredGroupDenied(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'user' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		$this->assertFalse( $checker->hasAccess( $user, [ 'sysop' ] ) );
		$this->assertFalse( $checker->hasAccess( $user, [ 'pi' ] ) );
		$this->assertFalse( $checker->hasAccess( $user, [ 'all_admins' ] ) ); // expanded but still no match
	}

	public function testGroupSetExpandsCorrectly(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'pi' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		$this->assertTrue( $checker->hasAccess( $user, [ 'all_admins' ] ) );
	}

	public function testGroupSetExpansionWithNoMatch(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'visitor' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		$this->assertFalse( $checker->hasAccess( $user, [ 'all_admins' ] ) );
	}

	public function testMultipleGroupsORLogic(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'user' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		// OR logic: match if any group matches
		$this->assertTrue( $checker->hasAccess( $user, [ 'sysop', 'user' ] ) );
	}

	public function testEmptyGroupListFailsClosed(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'sysop' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		$this->assertFalse( $checker->hasAccess( $user, [] ) );
	}

	public function testWhitespacedGroupsAreHandled(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'staff' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		// The checker itself does not trim—group list normalization happens in parser funcs.
		// Here we provide already-trimmed input.
		$this->assertTrue( $checker->hasAccess( $user, [ 'staff', 'editor' ] ) );
	}

	public function testInvalidSetNameDoesNotBreak(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'user' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		// Nonexistent set expands to nothing → fails closed.
		$this->assertFalse( $checker->hasAccess( $user, [ 'nonexistent_set' ] ) );
	}

	public function testUserInMultipleGroupsMatches(): void {
		$config = $this->makeConfig();
		$groupManager = $this->makeGroupManager( [ 'editor', 'staff' ] );
		$checker = new GroupPermissionChecker( $config, $groupManager );
		$user = $this->makeUser();

		// Matches via group-set "mixed"
		$this->assertTrue( $checker->hasAccess( $user, [ 'mixed' ] ) );
	}
}
