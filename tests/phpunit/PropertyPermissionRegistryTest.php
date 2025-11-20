<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Permissions\PropertyPermissionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PropertyPermissionRegistry (current minimal version)
 */
class PropertyPermissionRegistryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        PropertyPermissionRegistry::reset();
    }

    protected function tearDown(): void {
        PropertyPermissionRegistry::reset();
        parent::tearDown();
    }

    public function testRegistersPropertyWithLevel(): void {
        PropertyPermissionRegistry::registerProperty('HasAge', 'internal');

        $this->assertTrue(
            PropertyPermissionRegistry::isPropertyProtected('HasAge')
        );

        $this->assertSame(
            ['internal'],
            PropertyPermissionRegistry::getPropertyLevels('HasAge')
        );
    }

    public function testRegistersMultipleLevelsForSameProperty(): void {
        PropertyPermissionRegistry::registerProperty('HasAge', 'internal');
        PropertyPermissionRegistry::registerProperty('HasAge', 'sensitive');

        $levels = PropertyPermissionRegistry::getPropertyLevels('HasAge');

        $this->assertCount(2, $levels);
        $this->assertContains('internal', $levels);
        $this->assertContains('sensitive', $levels);
    }

    public function testDeduplicatesLevels(): void {
        PropertyPermissionRegistry::registerProperty('HasAge', 'internal');
        PropertyPermissionRegistry::registerProperty('HasAge', 'internal');

        $levels = PropertyPermissionRegistry::getPropertyLevels('HasAge');

        $this->assertSame(['internal'], $levels);
    }

    public function testReturnsEmptyArrayForUnregisteredProperty(): void {
        $this->assertSame([], PropertyPermissionRegistry::getPropertyLevels('NonExistent'));
        $this->assertFalse(PropertyPermissionRegistry::isPropertyProtected('NonExistent'));
    }

    public function testClearResetsAll(): void {
        PropertyPermissionRegistry::registerProperty('HasAge', 'internal');

        PropertyPermissionRegistry::reset();

        $this->assertFalse(PropertyPermissionRegistry::isPropertyProtected('HasAge'));
        $this->assertSame([], PropertyPermissionRegistry::getPropertyLevels('HasAge'));
    }
}
