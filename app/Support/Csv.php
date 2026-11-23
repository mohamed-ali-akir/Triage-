<?php

namespace App\Support;

class Csv
{
    /**
     * A value that is safe to open in a spreadsheet.
     *
     * A cell that starts with =, +, - or @ is read as a formula by Excel and
     * LibreOffice: a ticket titled "=HYPERLINK(...)" would run on the computer of
     * the administrator who opens the export. A leading apostrophe makes it plain text.
     */
    public static function cell(string|int|float|null $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
