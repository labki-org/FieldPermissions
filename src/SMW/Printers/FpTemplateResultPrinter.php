<?php

namespace FieldPermissions\SMW\Printers;

use SMW\Query\ResultPrinters\TemplateResultPrinter;

/**
 * Template format printer.
 *
 * NOTE: SMW may not have a dedicated TemplateResultPrinter class in all versions.
 * This extends the base ResultPrinter class. If template format requires specific
 * functionality, we may need to check the SMW version and extend the appropriate class.
 */
class FpTemplateResultPrinter extends TemplateResultPrinter {
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
			'fieldpermissions',
			static::class . "::__construct called (format={$format})"
		);
		parent::__construct( $format, (array)$params );
	}
}
