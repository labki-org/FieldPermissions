<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Permissions\LevelPermissionChecker;
use FieldPermissions\Permissions\PermissionConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LevelPermissionChecker
 */
class LevelPermissionCheckerTest extends TestCase {

	/**
	 * Build a valid PermissionConfig for testing level logic.
	 */
	private function makeConfig(): PermissionConfig {

		$config = [
			'FieldPermissionsLevels' => [
				'public'    => 0,
				'internal'  => 10,
				'sensitive' => 20,
				'pi_only'   => 30,
			],
			'FieldPermissionsGroupMaxLevel' => [
				'*'         => 'public',
				'user'      => 'public',
				'lab_member'=> 'internal',
				'analyst'   => 'sensitive',
				'pi'        => 'pi_only'
			],
			'FieldPermissionsGroupSets' => []  // not used in LevelPermissionChecker
		];

		return new PermissionConfig( $config );
	}

	private function makeGroupManager( array $groups ): UserGroupManager {
		$mgr = $this->createMock( UserGroupManager::class );
		$mgr->method( 'getUserEffectiveGroups' )->willReturn( $groups );
		return $mgr;
	}

	private function makeUser( bool $isAnon ): User {
		$user = $this->createMock( User::class );
		$user->method( 'isAnon' )->willReturn( $isAnon );
		return $user;
	}

	/* -------------------------------------------------------------------------
	 * TESTS
	 * ---------------------------------------------------------------------- */

	public function testUserWithHigherLevelCanAccessLowerLevel(): void {
		$config = $this->makeConfig();
		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [ 'pi' ] )
		);

		$user = $this->makeUser( false );

		$this->assertTrue( $checker->hasAccess( $user, 'public' ) );
		$this->assertTrue( $checker->hasAccess( $user, 'internal' ) );
		$this->assertTrue( $checker->hasAccess( $user, 'sensitive' ) );
		$this->assertTrue( $checker->hasAccess( $user, 'pi_only' ) );
	}

	public function testUserWithLowerLevelCannotAccessHigherLevel(): void {
		$config = $this->makeConfig();
		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [ 'user' ] )
		);

		$user = $this->makeUser( false );

		$this->assertTrue( $checker->hasAccess( $user, 'public' ) );
		$this->assertFalse( $checker->hasAccess( $user, 'internal' ) );
		$this->assertFalse( $checker->hasAccess( $user, 'sensitive' ) );
		$this->assertFalse( $checker->hasAccess( $user, 'pi_only' ) );
	}

	public function testAnonymousUserUsesDefaultLevel(): void {
		$config = $this->makeConfig();
		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [] )
		);

		$user = $this->makeUser( true );

		$this->assertTrue( $checker->hasAccess( $user, 'public' ) );
		$this->assertFalse( $checker->hasAccess( $user, 'internal' ) );
	}

	public function testUnknownRequiredLevelDeniesAccess(): void {
		$config = $this->makeConfig();
		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [ 'pi' ] )
		);

		$user = $this->makeUser( false );

		$this->assertFalse( $checker->hasAccess( $user, 'NO_SUCH_LEVEL' ) );
	}

	public function testUserWithMultipleGroupsGetsHighestLevel(): void {
		$config = $this->makeConfig();

		// user is both lab_member (internal) and analyst (sensitive)
		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [ 'user', 'lab_member', 'analyst' ] )
		);

		$user = $this->makeUser( false );

		$this->assertTrue( $checker->hasAccess( $user, 'internal' ) );
		$this->assertTrue( $checker->hasAccess( $user, 'sensitive' ) );
		$this->assertFalse( $checker->hasAccess( $user, 'pi_only' ) );
	}

	public function testGroupWithNoValidLevelIsIgnored(): void {
		$config = $this->makeConfig();

		// "weird_group" is not in FieldPermissionsGroupMaxLevel → ignored
		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [ 'weird_group' ] )
		);

		$user = $this->makeUser( false );

		// Should fall back to: user has no max level (not anon) → deny everything
		$this->assertFalse( $checker->hasAccess( $user, 'public' ) );
	}

	public function testNonAnonUserWithNoGroupsGetsNoAccess(): void {
		$config = $this->makeConfig();

		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [] )
		);

		$user = $this->makeUser( false ); // not anon → no '*' fallback

		$this->assertFalse( $checker->hasAccess( $user, 'public' ) );
	}

	public function testAnonUserWhenAsteriskNotDefined(): void {
		// Same config as before, except we remove '*' to test fallback behavior.
		$configArr = [
			'FieldPermissionsLevels' => [
				'public'    => 0,
				'internal'  => 10,
			],
			'FieldPermissionsGroupMaxLevel' => [
				'user' => 'public'
				// '*' missing
			],
			'FieldPermissionsGroupSets' => []
		];

		$config = new PermissionConfig( $configArr );

		$checker = new LevelPermissionChecker(
			$config,
			$this->makeGroupManager( [] )
		);

		$user = $this->makeUser( true );

		// Without '*' mapping, anon has NO level → cannot even access public
		$this->assertFalse( $checker->hasAccess( $user, 'public' ) );
	}
}
