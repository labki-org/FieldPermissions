<?php

namespace FieldPermissions\Tests;

use FieldPermissions\Utils\PropertyNameNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PropertyNameNormalizer
 */
class PropertyNameNormalizerTest extends TestCase {

	/* ----------------------------------------------------------------------
	 * BASIC NORMALIZATION BEHAVIOR (NO SMW)
	 * ---------------------------------------------------------------------- */

	public function testTrimsAndCollapsesWhitespace(): void {
		$raw = "   Weird    Property   Name   ";
		$norm = PropertyNameNormalizer::normalize($raw);

		$this->assertSame("Weird Property Name", $norm);
	}

	public function testPreservesCase(): void {
		$raw = "HasCaMeLcAsE";
		$norm = PropertyNameNormalizer::normalize($raw);

		$this->assertSame("HasCaMeLcAsE", $norm);
	}

	public function testEmptyStringReturnsEmpty(): void {
		$this->assertSame('', PropertyNameNormalizer::normalize(''));
	}

	public function testHandlesPropertyNamespacePrefix(): void {
		$raw = "   property   :   HasAge   ";
		$norm = PropertyNameNormalizer::normalize($raw);

		// Defensive normalizer collapses to canonical "Property:HasAge"
		$this->assertSame("Property:HasAge", $norm);
	}

	public function testDoesNotStripValidPropertyNamespace(): void {
		$raw = "Property:HasHeight";
		$norm = PropertyNameNormalizer::normalize($raw);

		$this->assertSame("Property:HasHeight", $norm);
	}

	/* ----------------------------------------------------------------------
	 * INTERWIKI / INVALID NAME REJECTION
	 * ---------------------------------------------------------------------- */

	public function testRejectsParserFunctionsInIsValid(): void {
		$this->assertFalse(PropertyNameNormalizer::isValid('#expr'));
		$this->assertFalse(PropertyNameNormalizer::isValid('#subobject'));
	}

	public function testRejectsInterwikiPrefixes(): void {
		$this->assertFalse(PropertyNameNormalizer::isValid('wikidata:Q42'));
		$this->assertFalse(PropertyNameNormalizer::isValid('Help:Contents'));
	}

	public function testAllowsPropertyNamespaceEvenWithColon(): void {
		$this->assertTrue(PropertyNameNormalizer::isValid('Property:HasAge'));
		$this->assertTrue(PropertyNameNormalizer::isValid('Property:Has Space'));
	}

	public function testAllowsNormalPropertiesWithoutColon(): void {
		$this->assertTrue(PropertyNameNormalizer::isValid('HasAge'));
	}

	/* ----------------------------------------------------------------------
	 * SMW NORMALIZATION (IF SMW INSTALLED)
	 * ---------------------------------------------------------------------- */

	/**
	 * If SMW is installed, test normalization using SMW\DIProperty.
	 * If not installed, test simply that normalization does not throw and fallback is used.
	 */
	public function testSMWNormalizationBehavior(): void {
		$raw = "HasAge";

		if (defined('SMW_VERSION') && class_exists('\SMW\DIProperty')) {

			// Real SMW normalization (SMW returns canonical keys)
			$norm = PropertyNameNormalizer::normalize($raw);

			// DIProperty->getKey() produces a canonical key like "_HASAGE" or "_HASAGE" depending on SMW version
			// but label fallback may be used—so check that result isn't empty and is string.
			$this->assertIsString($norm);
			$this->assertNotSame('', $norm);

		} else {
			// SMW not installed — normalization falls back to defensive mode
			$norm = PropertyNameNormalizer::normalize($raw);
			$this->assertSame('HasAge', $norm);
		}
	}

	/* ----------------------------------------------------------------------
	 * EDGE CASES
	 * ---------------------------------------------------------------------- */

	public function testNormalizeDoesNotAcceptPropertyWithHash(): void {
		$raw = '#if';
		$norm = PropertyNameNormalizer::normalize($raw);

		// Normalize may return the raw text, but isValid should catch it.
		$this->assertFalse(PropertyNameNormalizer::isValid($norm));
	}

	public function testNormalizeWithLotsOfWhitespace(): void {
		$raw = "   \t  Has    Property   \n ";
		$norm = PropertyNameNormalizer::normalize($raw);

		$this->assertSame('Has Property', $norm);
	}

	public function testNormalizationWithPropertyNamespaceAndWhitespace(): void {
		$raw = "   PROPERTY   :   Has    Something  ";
		$norm = PropertyNameNormalizer::normalize($raw);

		$this->assertSame('Property:Has Something', $norm);
	}

	public function testRejectsInterwikiLikePatternEvenIfHasSpaces(): void {
		$this->assertFalse(PropertyNameNormalizer::isValid('Wikipedia : Something'));
	}

	public function testNonEmptyValidAfterNormalization(): void {
		$raw = "   ValidProperty  ";
		$norm = PropertyNameNormalizer::normalize($raw);

		$this->assertSame('ValidProperty', $norm);
		$this->assertTrue(PropertyNameNormalizer::isValid($norm));
	}
}
