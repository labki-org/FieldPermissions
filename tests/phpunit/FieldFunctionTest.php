<?php

namespace FieldPermissions\Tests;

use FieldPermissions\ParserFunctions\FieldFunction;
use FieldPermissions\ParserFunctions\FieldPermissionsParserHelper;
use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Utils\SMWPropertyExtractor;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FieldFunction (#field:level|content)
 */
class FieldFunctionTest extends TestCase {

	/**
	 * Create a mock Parser with a mock ParserOutput and mock User.
	 */
	private function createMockParser() {
		$parserOutput = $this->getMockBuilder(ParserOutput::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'updateCacheExpiry', 'setExtensionData', 'getExtensionData' ])
			->getMock();

		$parserOutput->method('updateCacheExpiry')->willReturn(null);
		$parserOutput->method('setExtensionData')->willReturn(null);
		$parserOutput->method('getExtensionData')->willReturn(null);

		$user = $this->getMockBuilder(\MediaWiki\User\User::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'isAnon' ])
			->getMock();
		$user->method('isAnon')->willReturn(false);

		$parser = $this->getMockBuilder(Parser::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'getOutput', 'getUser', 'getPreprocessor' ])
			->getMock();

		$parser->method('getOutput')->willReturn($parserOutput);
		$parser->method('getUser')->willReturn($user);

		// Preprocessor needs only ->newFrame()
		$preprocessor = $this->getMockBuilder(\MediaWiki\Parser\Preprocessor::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'newFrame' ])
			->getMock();

		$preprocessor->method('newFrame')->willReturn(
			$this->createMock(PPFrame::class)
		);

		$parser->method('getPreprocessor')->willReturn($preprocessor);

		return $parser;
	}

	/**
	 * Create a mock PPFrame expanding arg1 then arg2.
	 */
	private function createFrame(string $level, string $content): PPFrame {
		$frame = $this->getMockBuilder(PPFrame::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'expand' ])
			->getMock();

		$call = 0;
		$frame->method('expand')->willReturnCallback(
			function () use ($level, $content, &$call) {
				$call++;
				return $call === 1 ? $level : $content;
			}
		);

		return $frame;
	}

	/**
	 * Helper to generate dummy PPNode args.
	 */
	private function dummyArgs(): array {
		return [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class),
		];
	}

	/* --------------------------------------------------------------------
	 * TESTS
	 * -------------------------------------------------------------------- */

	public function testReturnsContentWhenPermissionAllowed(): void {
		$parser = $this->createMockParser();
		$frame  = $this->createFrame('internal', 'SECRET');
		$args   = $this->dummyArgs();

		// PermissionChecker → allow
		$checker = $this->getMockBuilder(PermissionChecker::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'hasLevelAccess' ])
			->getMock();

		$checker->method('hasLevelAccess')->willReturn(true);

		// Prevent real SMW extraction from running
		SMWPropertyExtractor::shouldReceive('extractProperties')->andReturn([]);

		$fn = new FieldFunction($checker);
		$result = $fn->execute($parser, $frame, $args);

		$this->assertSame('SECRET', $result);
	}

	public function testReturnsEmptyWhenPermissionDenied(): void {
		$parser = $this->createMockParser();
		$frame  = $this->createFrame('internal', 'SECRET');
		$args   = $this->dummyArgs();

		$checker = $this->getMockBuilder(PermissionChecker::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'hasLevelAccess' ])
			->getMock();
		$checker->method('hasLevelAccess')->willReturn(false);

		SMWPropertyExtractor::shouldReceive('extractProperties')->andReturn([]);

		$fn = new FieldFunction($checker);
		$result = $fn->execute($parser, $frame, $args);

		$this->assertSame('', $result);
	}

	public function testReturnsEmptyWhenArgumentsMissing(): void {
		$parser = $this->createMockParser();

		$frame = $this->getMockBuilder(PPFrame::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'expand' ])
			->getMock();
		$frame->method('expand')->willReturn('');

		$checker = $this->getMockBuilder(PermissionChecker::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'hasLevelAccess' ])
			->getMock();
		$checker->method('hasLevelAccess')->willReturn(true);

		$fn = new FieldFunction($checker);

		// Not enough args → empty
		$result = $fn->execute($parser, $frame, [ $this->createMock(\MediaWiki\Parser\PPNode::class) ]);
		$this->assertSame('', $result);
	}

	public function testReturnsEmptyForEmptyLevel(): void {
		$parser = $this->createMockParser();
		$frame  = $this->createFrame('', 'content');
		$args   = $this->dummyArgs();

		$checker = $this->getMockBuilder(PermissionChecker::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'hasLevelAccess' ])
			->getMock();
		$checker->method('hasLevelAccess')->willReturn(true);

		$fn = new FieldFunction($checker);

		$result = $fn->execute($parser, $frame, $args);

		$this->assertSame('', $result);
	}

	public function testContentNotTrimmed(): void {
		$parser = $this->createMockParser();

		$level   = 'public';
		$content = "   <div>Leading spaces preserved</div>";

		$frame = $this->createFrame($level, $content);
		$args  = $this->dummyArgs();

		$checker = $this->getMockBuilder(PermissionChecker::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'hasLevelAccess' ])
			->getMock();
		$checker->method('hasLevelAccess')->willReturn(true);

		SMWPropertyExtractor::shouldReceive('extractProperties')->andReturn([]);

		$fn = new FieldFunction($checker);

		$result = $fn->execute($parser, $frame, $args);

		$this->assertSame($content, $result);
	}
}
