<?php

namespace PropertyPermissions\SMW\Printers;

use SMW\Query\ResultPrinters\ListResultPrinter;

class FpListResultPrinter extends ListResultPrinter {
	use PrinterFilterTrait;

	/**
	 * CRITICAL: Constructor signature must accept $params = false (not array).
	 * See FpTableResultPrinter.php for detailed explanation.
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
