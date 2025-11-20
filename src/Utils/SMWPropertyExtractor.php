<?php

namespace FieldPermissions\Utils;

/**
 * Robust SMW property extractor.
 *
 * Extracts all SMW property labels from any content containing
 * [[Property::Value]] assertions. This includes nested links, multi-line,
 * multi-property, operators, etc.
 *
 * We intentionally do NOT attempt full SMW grammar parsing. We rely on the
 * definition that any SMW property assertion uses the syntax:
 *
 *      [[PropertyLabel::Value]]
 *
 * PropertyLabel = everything before the FIRST "::"
 */
class SMWPropertyExtractor {

	/**
	 * Extract all SMW property labels from content.
	 *
	 * @param string $content
	 * @return array<string> Unique, normalized property names
	 */
	public static function extractProperties( string $content ): array {

		$properties = [];

		// Match every [[...]] block (lazy, nested-safe)
		if ( preg_match_all( '/\[\[(.*?)\]\]/us', $content, $blocks ) ) {

			foreach ( $blocks[1] as $block ) {

				// Find the first "::" which identifies a property
				$pos = mb_strpos( $block, '::' );

				if ( $pos === false ) {
					continue;
				}

				// Extract property label BEFORE the ::
				$property = trim( mb_substr( $block, 0, $pos ) );

				if ( $property === '' ) {
					continue;
				}

				// Skip parser functions: [[#foo::...]]
				if ( str_starts_with( $property, '#' ) ) {
					continue;
				}

				// Normalize and validate
				$normalized = PropertyNameNormalizer::normalize( $property );

				if ( $normalized !== '' && PropertyNameNormalizer::isValid( $normalized ) ) {
					$properties[$normalized] = true;
				}
			}
		}

		return array_keys( $properties );
	}
}
