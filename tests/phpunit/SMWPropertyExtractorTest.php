<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Utils\SMWPropertyExtractor;
use FieldPermissions\Utils\PropertyNameNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SMWPropertyExtractor
 */
class SMWPropertyExtractorTest extends TestCase {

	public function testExtractsSimpleProperty(): void {
		$text = '[[HasAge::42]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['HasAge'], $props);
	}

	public function testExtractsPropertyWithEquals(): void {
		$text = '[[HasName:=John]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['HasName'], $props);
	}

	public function testExtractsPropertiesWithOperators(): void {
		$text = '[[Weight::>20]] and [[Height::<=180]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertEqualsCanonicalizing(['Weight', 'Height'], $props);
	}

	public function testExtractsPropertyWithInternalWhitespace(): void {
		$text = '[[Has Full Name::John]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		// Normalizer collapses whitespace but preserves case
		$this->assertSame(['Has Full Name'], $props);
	}

	public function testIgnoresParserFunctions(): void {
		$text = '[[#if:condition|Something]] [[RealProp::Value]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['RealProp'], $props);
	}

	public function testExtractsFromPipedSyntax(): void {
		$text = '[[Display|HasTitle::The Book]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['HasTitle'], $props);
	}

	public function testExtractsMultiplePropertiesInSameBlock(): void {
		$text = '[[HasAge::42|HasHeight::180]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertEqualsCanonicalizing(['HasAge', 'HasHeight'], $props);
	}

	public function testHandlesNestedLinksInsideValue(): void {
		$text = '[[HasCitation::Some [[Nested]] Value]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['HasCitation'], $props);
	}

	public function testExtractsPropertiesFromAskQueries(): void {
		$text = '{{#ask: [[HasColor::Blue]] [[HasSize::Large]] }}';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertEqualsCanonicalizing(['HasColor', 'HasSize'], $props);
	}

	public function testDeduplicatesProperties(): void {
		$text = '[[HasAge::42]] and again [[HasAge::24]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['HasAge'], $props);
	}

	public function testNormalizationRemovesBadWhitespace(): void {
		$text = '[[   Weird   Property   ::Value]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['Weird Property'], $props);
	}

	public function testPropertyWithPossibleInterwikiPrefixRejected(): void {
		$text = '[[wikidata:Q42::Value]] [[RealProp::X]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		// Only RealProp survives normalization
		$this->assertSame(['RealProp'], $props);
	}

	public function testRejectsHashParserFunctionsInsideAsk(): void {
		$text = '{{#ask: [[#expr:5+5]] [[HasAge::10]] }}';
		$props = SMWPropertyExtractor::extractProperties($text);

		$this->assertSame(['HasAge'], $props);
	}

	public function testHandlesPropertyNamespacePrefix(): void {
		$text = '[[Property:HasAge::42]]';
		$props = SMWPropertyExtractor::extractProperties($text);

		// Normalizer returns "Property:HasAge"
		$this->assertSame(['Property:HasAge'], $props);
	}
}
