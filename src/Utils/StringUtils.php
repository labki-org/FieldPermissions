<?php

namespace FieldPermissions\Utils;

/**
 * String utility functions
 */
class StringUtils {

    /**
     * Split a comma-separated string into a clean array:
     *   - trims whitespace around entries
     *   - does NOT alter internal whitespace
     *   - removes empty entries
     *   - preserves Unicode
     *   - optionally deduplicates
     *
     * Examples:
     *   " admin , staff , , editors " → ["admin", "staff", "editors"]
     *   "New York,Los Angeles" → ["New York", "Los Angeles"]
     *
     * @param string $str
     * @param bool $dedupe  Remove duplicate entries (default: true)
     * @return array<string>
     */
    public static function splitCommaSeparated( string $str, bool $dedupe = true ): array {

        // If the whole string is whitespace → empty result
        if ( trim( $str ) === '' ) {
            return [];
        }

        // Split on commas with optional surrounding whitespace
        $parts = preg_split(
            '/\s*,\s*/u',
            $str,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if ( $parts === false ) {
            // Should never happen unless regex fails
            return [];
        }

        // Trim each part once more to ensure no leading/trailing residues
        $clean = array_map( 'trim', $parts );

        return $dedupe ? array_values( array_unique( $clean ) ) : $clean;
    }
}
