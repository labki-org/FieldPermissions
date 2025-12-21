<?php

namespace PropertyPermissions\SMW\Printers;

use SMW\Query\ResultPrinters\TableResultPrinter;

class FpTableResultPrinter extends TableResultPrinter {
	use PrinterFilterTrait;

	/**
	 * CRITICAL: Constructor signature must accept $params = false (not array).
	 *
	 * SMW's SMW_QueryProcessor.php line 402 calls newPrinter($format, false)
	 * when no parameters are provided. A strict array type hint causes:
	 * "TypeError: Argument #2 ($params) must be of type array, false given"
	 *
	 * Solution: Remove type hint, accept false, cast to array internally.
	 * This compatibility issue took significant debugging to identify.
	 *
	 * @param string $format
	 * @param mixed $params
	 */
	public function __construct( $format, $params = false ) {
		wfDebugLog(
			'propertypermissions',
			static::class . "::__construct called (format={$format})"
		);
		parent::__construct( $format, (array)$params );
	}
}
