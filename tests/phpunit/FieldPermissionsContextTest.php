<?php

namespace FieldPermissions\Tests;

use FieldPermissions\FieldPermissionsContext;
use MediaWiki\Parser\ParserOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FieldPermissionsContext
 */
class FieldPermissionsContextTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Clear all contexts before each test
		FieldPermissionsContext::clearAll();
	}

	protected function tearDown(): void {
		// Clean up after each test
		FieldPermissionsContext::clearAll();
		parent::tearDown();
	}

	public function testGetReturnsDefaultForMissingKey(): void {
		$parserOutput = $this->createMock(ParserOutput::class);

		$result = FieldPermissionsContext::get($parserOutput, 'nonexistent', 'default');

		$this->assertSame('default', $result);
	}

	public function testGetReturnsNullByDefault(): void {
		$parserOutput = $this->createMock(ParserOutput::class);

		$result = FieldPermissionsContext::get($parserOutput, 'nonexistent');

		$this->assertNull($result);
	}

	public function testSetAndGet(): void {
		$parserOutput = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput, 'testKey', 'testValue');

		$this->assertSame('testValue', FieldPermissionsContext::get($parserOutput, 'testKey'));
	}

	public function testSetOverwritesExistingValue(): void {
		$parserOutput = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput, 'testKey', 'value1');
		FieldPermissionsContext::set($parserOutput, 'testKey', 'value2');

		$this->assertSame('value2', FieldPermissionsContext::get($parserOutput, 'testKey'));
	}

	public function testClearRemovesContextForParserOutput(): void {
		$parserOutput1 = $this->createMock(ParserOutput::class);
		$parserOutput2 = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput1, 'key1', 'value1');
		FieldPermissionsContext::set($parserOutput2, 'key2', 'value2');

		FieldPermissionsContext::clear($parserOutput1);

		$this->assertNull(FieldPermissionsContext::get($parserOutput1, 'key1'));
		$this->assertSame('value2', FieldPermissionsContext::get($parserOutput2, 'key2'));
	}

	public function testClearAllRemovesAllContexts(): void {
		$parserOutput1 = $this->createMock(ParserOutput::class);
		$parserOutput2 = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput1, 'key1', 'value1');
		FieldPermissionsContext::set($parserOutput2, 'key2', 'value2');

		FieldPermissionsContext::clearAll();

		$this->assertNull(FieldPermissionsContext::get($parserOutput1, 'key1'));
		$this->assertNull(FieldPermissionsContext::get($parserOutput2, 'key2'));
	}

	public function testDifferentParserOutputsHaveSeparateContexts(): void {
		$parserOutput1 = $this->createMock(ParserOutput::class);
		$parserOutput2 = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput1, 'key', 'value1');
		FieldPermissionsContext::set($parserOutput2, 'key', 'value2');

		$this->assertSame('value1', FieldPermissionsContext::get($parserOutput1, 'key'));
		$this->assertSame('value2', FieldPermissionsContext::get($parserOutput2, 'key'));
	}

	public function testStoresArrayValues(): void {
		$parserOutput = $this->createMock(ParserOutput::class);
		$arrayValue = ['item1', 'item2', 'item3'];

		FieldPermissionsContext::set($parserOutput, 'arrayKey', $arrayValue);

		$this->assertSame($arrayValue, FieldPermissionsContext::get($parserOutput, 'arrayKey'));
	}

	public function testStoresObjectValues(): void {
		$parserOutput = $this->createMock(ParserOutput::class);
		$objectValue = new \stdClass();
		$objectValue->property = 'value';

		FieldPermissionsContext::set($parserOutput, 'objectKey', $objectValue);

		$result = FieldPermissionsContext::get($parserOutput, 'objectKey');
		$this->assertSame($objectValue, $result);
		$this->assertSame('value', $result->property);
	}

	public function testStoresNullValue(): void {
		$parserOutput = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput, 'nullKey', null);

		$this->assertNull(FieldPermissionsContext::get($parserOutput, 'nullKey'));
	}

	public function testStoresBooleanValues(): void {
		$parserOutput = $this->createMock(ParserOutput::class);

		FieldPermissionsContext::set($parserOutput, 'boolKey', true);

		$this->assertTrue(FieldPermissionsContext::get($parserOutput, 'boolKey'));
	}
}
