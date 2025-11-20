<?php

namespace FieldPermissions\Tests;

use FieldPermissions\ParserFunctions\FieldPermissionsParserHelper;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MediaWiki\User\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FieldPermissionsParserHelper
 */
class FieldPermissionsParserHelperTest extends TestCase {

	private function makeFrame(string $arg1, string $arg2): PPFrame {
		$frame = $this->createMock(PPFrame::class);
		$call = 0;

		$frame->method('expand')
			->willReturnCallback(function () use ($arg1, $arg2, &$call) {
				$call++;
				return $call === 1 ? $arg1 : $arg2;
			});

		return $frame;
	}

	private function makeParser(User $user = null): Parser {
		$parserOutput = $this->createMock(ParserOutput::class);
		$parserOutput->method('updateCacheExpiry')->willReturn(null);
		$parserOutput->method('setExtensionData')->willReturn(null);
		$parserOutput->method('getExtensionData')->willReturn(null);

		if (!$user) {
			$user = $this->createMock(User::class);
			$user->method('isAnon')->willReturn(false);
		}

		$parser = $this->createMock(Parser::class);
		$parser->method('getOutput')->willReturn($parserOutput);
		$parser->method('getUser')->willReturn($user);

		return $parser;
	}

	public function testSetupParserOutputDisablesCache(): void {
		$parserOutput = $this->createMock(ParserOutput::class);
		$parserOutput->expects($this->once())
			->method('updateCacheExpiry')
			->with(0);

		$parserOutput->expects($this->once())
			->method('setExtensionData')
			->with('usesFieldPermissions', true);

		FieldPermissionsParserHelper::setupParserOutput($parserOutput);
	}

	public function testResolveContentArgumentsReturnsValidArgs(): void {
		$frame = $this->makeFrame('internal', 'Secret content');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNotNull($result);
		$this->assertSame('internal', $result[0]);
		$this->assertSame('Secret content', $result[1]);
	}

	public function testResolveContentArgumentsTrimsControlArg(): void {
		$frame = $this->makeFrame('  internal  ', 'Content');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNotNull($result);
		$this->assertSame('internal', $result[0]);
	}

	public function testResolveContentArgumentsDoesNotTrimContent(): void {
		$frame = $this->makeFrame('internal', '  Content with spaces  ');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNotNull($result);
		$this->assertSame('  Content with spaces  ', $result[1]);
	}

	public function testResolveContentArgumentsReturnsNullForMissingArgs(): void {
		$frame = $this->makeFrame('internal', 'Content');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNull($result);
	}

	public function testResolveContentArgumentsReturnsNullForEmptyControl(): void {
		$frame = $this->makeFrame('', 'Content');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNull($result);
	}

	public function testResolveContentArgumentsReturnsNullForEmptyContent(): void {
		$frame = $this->makeFrame('internal', '');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNull($result);
	}

	public function testResolveContentArgumentsAllowsNumericZeroContent(): void {
		$frame = $this->makeFrame('internal', '0');
		$args = [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];

		$result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);

		$this->assertNotNull($result);
		$this->assertSame('0', $result[1]);
	}

	public function testGetUserReturnsParserUser(): void {
		$user = $this->createMock(User::class);
		$parser = $this->makeParser($user);

		$result = FieldPermissionsParserHelper::getUser($parser);

		$this->assertSame($user, $result);
	}

    public function testSetupParserOutputDoesNotModifyOtherProperties(): void {
        $parserOutput = $this->createMock(ParserOutput::class);
    
        $parserOutput->expects($this->once())
            ->method('updateCacheExpiry')
            ->with(0);
    
        $parserOutput->expects($this->once())
            ->method('setExtensionData')
            ->with('usesFieldPermissions', true);
    
        // Ensure helper does NOT call unrelated output mutations
        $parserOutput->expects($this->never())->method('addModules');
        $parserOutput->expects($this->never())->method('setPageProperty');
        $parserOutput->expects($this->never())->method('addJsConfigVars');
    
        FieldPermissionsParserHelper::setupParserOutput($parserOutput);
    }
    
    public function testResolveContentArgumentsIgnoresAdditionalArgs(): void {
        $frame = $this->makeFrame('internal', 'Visible content');
    
        $args = [
            $this->createMock(\MediaWiki\Parser\PPNode::class),
            $this->createMock(\MediaWiki\Parser\PPNode::class),
            $this->createMock(\MediaWiki\Parser\PPNode::class),   // extra arg
            $this->createMock(\MediaWiki\Parser\PPNode::class),
        ];
    
        $result = FieldPermissionsParserHelper::resolveContentArguments($frame, $args);
    
        $this->assertNotNull($result);
        $this->assertSame('internal', $result[0]);
        $this->assertSame('Visible content', $result[1]);
    }
    
}

