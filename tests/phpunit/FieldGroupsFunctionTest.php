<?php

namespace FieldPermissions\Tests;

use FieldPermissions\ParserFunctions\FieldGroupsFunction;
use FieldPermissions\ParserFunctions\FieldPermissionsParserHelper;
use FieldPermissions\Permissions\PermissionChecker;
use FieldPermissions\Permissions\PermissionConfig;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FieldGroupsFunction (#field-groups)
 */
class FieldGroupsFunctionTest extends TestCase {

	/* -------------------------------------------------------------------------
	 * Helper: construct PermissionChecker with user groups
	 * ---------------------------------------------------------------------- */
	private function makeChecker(array $userGroups): PermissionChecker {

		$config = new PermissionConfig([
			'FieldPermissionsLevels'         => [],
			'FieldPermissionsGroupMaxLevel' => [],
			'FieldPermissionsGroupSets'     => [],
		]);

		$groupManager = $this->createMock(UserGroupManager::class);
		$groupManager->method('getUserEffectiveGroups')
			->willReturn($userGroups);

		return new PermissionChecker($config, $groupManager);
	}

	/* -------------------------------------------------------------------------
	 * Helper: construct parser with ParserOutput + User + Preprocessor mocks
	 * ---------------------------------------------------------------------- */
	private function makeParser(PPFrame $frame, User $user = null): Parser {

		$parserOutput = $this->createMock(ParserOutput::class);

		// Validate caching disabling calls
		$parserOutput->expects($this->once())
			->method('updateCacheExpiry')
			->with(0);

		$parserOutput->expects($this->once())
			->method('setExtensionData')
			->with('usesFieldPermissions', true);

		$parserOutput->expects($this->any())
			->method('getExtensionData')
			->willReturn(null);

		$parser = $this->getMockBuilder(Parser::class)
			->disableOriginalConstructor()
			->addMethods([ 'getUser' ])
			->onlyMethods([ 'getOutput', 'getPreprocessor' ])
			->getMock();
		$parser->method('getOutput')->willReturn($parserOutput);

		if (!$user) {
			$user = $this->createMock(User::class);
			$user->method('isAnon')->willReturn(false);
			$user->method('getName')->willReturn('TestUser');
		}
		$parser->method('getUser')->willReturn($user);

		// Preprocessor mock to provide ->newFrame()
		$preproc = $this->getMockBuilder(\MediaWiki\Parser\Preprocessor::class)
			->disableOriginalConstructor()
			->onlyMethods([ 'newFrame', 'newCustomFrame', 'newPartNodeArray', 'preprocessToObj' ])
			->getMock();
		$preproc->method('newFrame')->willReturn($frame);
		$preproc->method('newCustomFrame')->willReturn(
			$this->getMockForAbstractClass(PPFrame::class)
		);
		$preproc->method('newPartNodeArray')->willReturn([]);
		$preproc->method('preprocessToObj')->willReturn(
			$this->createMock(\MediaWiki\Parser\PPNode::class)
		);

		$parser->method('getPreprocessor')->willReturn($preproc);

		return $parser;
	}

	/* -------------------------------------------------------------------------
	 * Helper: PPFrame that expands arg1 then arg2
	 * ---------------------------------------------------------------------- */
	private function makeFrame(string $arg1, string $arg2): PPFrame {
		$frame = $this->getMockForAbstractClass(PPFrame::class);
		$call = 0;

		$frame->method('expand')
			->willReturnCallback(function () use ($arg1, $arg2, &$call) {
				$call++;
				return $call === 1 ? $arg1 : $arg2;
			});

		return $frame;
	}

	/* -------------------------------------------------------------------------
	 * Helper: dummy PPNode list
	 * ---------------------------------------------------------------------- */
	private function dummyArgs(): array {
		return [
			$this->createMock(\MediaWiki\Parser\PPNode::class),
			$this->createMock(\MediaWiki\Parser\PPNode::class)
		];
	}

	/* -------------------------------------------------------------------------
	 * TESTS
	 * ---------------------------------------------------------------------- */

	public function testUserInGroupSeesContent(): void {
		$checker = $this->makeChecker(['sysop']);
		$fn = new FieldGroupsFunction($checker);

		$frame = $this->makeFrame('sysop', 'OK');
		$parser = $this->makeParser($frame);

		$result = $fn->execute($parser, $frame, $this->dummyArgs());
		$this->assertSame('OK', $result);
	}

	public function testUserNotInGroupGetsEmpty(): void {
		$checker = $this->makeChecker(['viewer']);
		$fn = new FieldGroupsFunction($checker);

		$frame = $this->makeFrame('sysop', 'SECRET');
		$parser = $this->makeParser($frame);

		$result = $fn->execute($parser, $frame, $this->dummyArgs());
		$this->assertSame('', $result);
	}

	public function testORLogicAcrossGroups(): void {
		$checker = $this->makeChecker(['editor']);
		$fn = new FieldGroupsFunction($checker);

		$frame = $this->makeFrame('sysop, editor, staff', 'VISIBLE');
		$parser = $this->makeParser($frame);

		$this->assertSame(
			'VISIBLE',
			$fn->execute($parser, $frame, $this->dummyArgs())
		);
	}

	public function testWhitespaceInsideGroupList(): void {
		$checker = $this->makeChecker(['staff']);
		$fn = new FieldGroupsFunction($checker);

		$frame = $this->makeFrame('   sysop   ,  staff  ', 'YEP');
		$parser = $this->makeParser($frame);

		$this->assertSame('YEP', $fn->execute($parser, $frame, $this->dummyArgs()));
	}

	public function testEmptyGroupListFailsClosed(): void {
		$checker = $this->makeChecker(['sysop']);
		$fn = new FieldGroupsFunction($checker);

		$frame = $this->makeFrame(' , , ', 'NEVER');
		$parser = $this->makeParser($frame);

		$this->assertSame('', $fn->execute($parser, $frame, $this->dummyArgs()));
	}
}
