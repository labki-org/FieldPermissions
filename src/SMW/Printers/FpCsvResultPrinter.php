<?php

namespace FieldPermissions\SMW\Printers;

use SMW\Query\ResultPrinters\CSVResultPrinter;

class FpCsvResultPrinter extends CSVResultPrinter {
    use PrinterFilterTrait;
    
    public function __construct( $format, $params = [] ) {
        if ( !is_array( $params ) ) {
            $params = [];
        }
        wfDebugLog(
            'fieldpermissions',
            static::class . "::__construct called (format={$format})"
        );
        parent::__construct( $format, $params );
    }
}
