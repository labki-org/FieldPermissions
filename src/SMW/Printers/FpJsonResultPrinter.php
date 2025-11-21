<?php

namespace FieldPermissions\SMW\Printers;

use SMW\Query\ResultPrinters\JsonResultPrinter;

class FpJsonResultPrinter extends JsonResultPrinter {
    use PrinterFilterTrait;
    
    /**
     * CRITICAL: Constructor signature must accept $params = false (not array).
     * See FpTableResultPrinter.php for detailed explanation.
     */
    public function __construct( $format, $params = false ) {
        wfDebugLog(
            'fieldpermissions',
            static::class . "::__construct called (format={$format})"
        );
        parent::__construct( $format, (array)$params );
    }
}
