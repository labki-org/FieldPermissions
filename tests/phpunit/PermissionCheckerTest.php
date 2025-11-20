<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Permissions\PermissionConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PermissionChecker (facade)
 */
class PermissionCheckerTest extends TestCase {

    private function makeConfig(): PermissionConfig {
        return new PermissionConfig([
            'FieldPermissionsLevels' => [
                'public' => 0,
                'internal' => 10,
                'sensitive' => 20,
            ],
            'FieldPermissionsGroupMaxLevel' => [
                '*' => 'public',
                'user' => 'public',
                'lab_member' => 'internal',
                'pi' => 'sensitive',
            ],
            'FieldPermissionsGroupSets' => [
                'all_admins' => ['sysop', 'pi'],
            ],
        ]);
    }

    private function makeGroupManager(array $groups): UserGroupManager {
        $mgr = $this->createMock(UserGroupManager::class);
        $mgr->method('getUserEffectiveGroups')->willReturn($groups);
        return $mgr;
    }

    private function makeUser(bool $isAnon = false): User {
        $user = $this->createMock(User::class);
        $user->method('isAnon')->willReturn($isAnon);
        return $user;
    }

    /* ----------------------------------------------------------------------
     * Level Access Tests
     * ------------------------------------------------------------------- */

    public function testHasLevelAccessDelegatesToLevelChecker(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['lab_member']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertTrue($checker->hasLevelAccess($user, 'public'));
        $this->assertTrue($checker->hasLevelAccess($user, 'internal'));
        $this->assertFalse($checker->hasLevelAccess($user, 'sensitive'));
    }

    public function testAnonymousUserLevelAccess(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager([]);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser(true);

        $this->assertTrue($checker->hasLevelAccess($user, 'public'));
        $this->assertFalse($checker->hasLevelAccess($user, 'internal'));
    }

    public function testUnknownLevelDeniesAccess(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['pi']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertFalse($checker->hasLevelAccess($user, 'nonexistent_level'));
    }

    /* ----------------------------------------------------------------------
     * Group Access Tests
     * ------------------------------------------------------------------- */

    public function testHasGroupAccessDelegatesToGroupChecker(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['sysop']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertTrue($checker->hasGroupAccess($user, ['sysop']));
        $this->assertFalse($checker->hasGroupAccess($user, ['pi']));
    }

    public function testHasGroupAccessExpandsGroupSets(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['pi']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertTrue($checker->hasGroupAccess($user, ['all_admins']));
    }

    public function testHasGroupAccessWithMultipleGroups(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['user']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertTrue($checker->hasGroupAccess($user, ['sysop', 'user']));
        $this->assertFalse($checker->hasGroupAccess($user, ['sysop', 'pi']));
    }

    public function testEmptyGroupListDeniesAccess(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['sysop']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertFalse($checker->hasGroupAccess($user, []));
    }

    public function testHasGroupAccessMixedDirectAndSet(): void {
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['sysop']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        $this->assertTrue($checker->hasGroupAccess($user, ['all_admins', 'editor']));
    }

    public function testWhitespaceInGroupNamesIsIgnoredByStringUtils(): void {
        // This test does not mock StringUtils directly—but ensures that
        // PermissionChecker behaves correctly with whitespace included.
        $config = $this->makeConfig();
        $groupManager = $this->makeGroupManager(['staff']);
        $checker = new PermissionChecker($config, $groupManager);
        $user = $this->makeUser();

        // Equivalent to "#field-groups:   staff  | content"
        $this->assertTrue($checker->hasGroupAccess($user, ['  staff  ']));
    }
}
