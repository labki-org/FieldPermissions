<?php

namespace FieldPermissions\SMW;

use SMW\Query\ResultPrinterFactory;
use SMW\Query\ResultPrinters\ResultPrinter;

class DebugResultPrinterFactory extends ResultPrinterFactory {

	/**
	 * @param string $format
	 * @param array $params
	 * @return ResultPrinter
	 */
	public function newPrinter( $format, array $params = [] ): ResultPrinter {
		wfDebugLog( 'fieldpermissions', "DebugResultPrinterFactory: SMW is requesting format=$format" );
		$printer = parent::newPrinter( $format, $params );
		wfDebugLog( 'fieldpermissions', "DebugResultPrinterFactory: Returned printer class " . get_class( $printer ) );
		return $printer;
	}
}
