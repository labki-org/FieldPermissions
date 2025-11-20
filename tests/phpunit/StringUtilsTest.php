<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Utils\StringUtils;
use PHPUnit\Framework\TestCase;

/**
 * Tests for StringUtils::splitCommaSeparated()
 */
class StringUtilsTest extends TestCase {

    public function testSplitsCommaSeparatedString(): void {
        $result = StringUtils::splitCommaSeparated('admin,staff,editor');
        $this->assertSame(['admin', 'staff', 'editor'], $result);
    }

    public function testTrimsWhitespaceAroundEntries(): void {
        $result = StringUtils::splitCommaSeparated('  admin  ,  staff  ,  editor  ');
        $this->assertSame(['admin', 'staff', 'editor'], $result);
    }

    public function testRemovesEmptyEntries(): void {
        $result = StringUtils::splitCommaSeparated('admin,,staff, ,editor');
        $this->assertSame(['admin', 'staff', 'editor'], $result);
    }

    public function testDeduplicatesByDefault(): void {
        $result = StringUtils::splitCommaSeparated('admin,staff,admin,editor,staff');
        $this->assertSame(['admin', 'staff', 'editor'], $result);
    }

    public function testCanDisableDeduplication(): void {
        $result = StringUtils::splitCommaSeparated('admin,staff,admin', false);
        $this->assertSame(['admin', 'staff', 'admin'], $result);
    }

    public function testHandlesEmptyString(): void {
        $result = StringUtils::splitCommaSeparated('');
        $this->assertSame([], $result);
    }

    public function testHandlesWhitespaceOnlyString(): void {
        $result = StringUtils::splitCommaSeparated('   ');
        $this->assertSame([], $result);
    }

    public function testPreservesInternalWhitespace(): void {
        // The function preserves internal whitespace, only trims around commas
        $result = StringUtils::splitCommaSeparated("New   York,  Los\tAngeles");
        // Tab character is preserved in the output
        $this->assertSame(['New   York', "Los\tAngeles"], $result);
    }

    public function testPreservesUnicodeCharacters(): void {
        $result = StringUtils::splitCommaSeparated('管理员,staff,éditeur');
        $this->assertSame(['管理员', 'staff', 'éditeur'], $result);
    }

    public function testHandlesSingleItem(): void {
        $result = StringUtils::splitCommaSeparated('admin');
        $this->assertSame(['admin'], $result);
    }

    public function testHandlesTrailingComma(): void {
        $result = StringUtils::splitCommaSeparated('admin,staff,');
        $this->assertSame(['admin', 'staff'], $result);
    }

    public function testHandlesLeadingComma(): void {
        $result = StringUtils::splitCommaSeparated(',admin,staff');
        $this->assertSame(['admin', 'staff'], $result);
    }

    public function testHandlesMixedWhitespaceAroundCommas(): void {
        $result = StringUtils::splitCommaSeparated(" admin\t,\n staff  ,editor ");
        $this->assertSame(['admin', 'staff', 'editor'], $result);
    }
}
