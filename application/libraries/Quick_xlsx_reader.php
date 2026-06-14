<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Quick_xlsx_reader
 *
 * Minimal, dependency-free reader for the .xlsx subset produced by Excel /
 * LibreOffice / Google Sheets when "Save as .xlsx" is used. Returns the first
 * worksheet as a 2D array of strings (row 0 = header row).
 *
 * Only PHP's bundled extensions are required:
 *   - ext-zip (ZipArchive)
 *   - ext-simplexml (SimpleXMLElement)
 *
 * Limitations (acceptable for an admin bulk-import UI):
 *   - Reads only the first worksheet.
 *   - Returns every cell as a UTF-8 string. Dates appear as Excel serial
 *     numbers (callers can convert if they need real dates).
 *   - Inline rich text inside <is> is collapsed to plain text.
 */
class Quick_xlsx_reader
{
    /**
     * Parse a .xlsx file and return a 2D array of strings.
     *
     * @param  string $filepath absolute path to the .xlsx
     * @return array|false      array of rows, each row is a numerically-indexed
     *                          array of cell strings. false on failure.
     */
    public function get_array($filepath)
    {
        if (!is_string($filepath) || !is_file($filepath)) {
            return false;
        }
        if (!class_exists('ZipArchive')) {
            log_message('error', 'Quick_xlsx_reader: ZipArchive PHP extension is not loaded.');
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            return false;
        }

        // Shared strings table — most string cells reference this by index.
        $sharedStrings = array();
        $ssIndex = $zip->locateName('xl/sharedStrings.xml');
        if ($ssIndex !== false) {
            $ssXml = $zip->getFromIndex($ssIndex);
            if ($ssXml !== false && $ssXml !== '') {
                $sharedStrings = $this->_parse_shared_strings($ssXml);
            }
        }

        // Always read sheet1.xml — Excel guarantees its presence.
        $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($sheetIndex === false) {
            $zip->close();
            return false;
        }
        $sheetXml = $zip->getFromIndex($sheetIndex);
        $zip->close();
        if ($sheetXml === false || $sheetXml === '') {
            return false;
        }

        return $this->_parse_sheet($sheetXml, $sharedStrings);
    }

    /** @return string[] shared strings, indexed by their position in <sst> */
    private function _parse_shared_strings($xmlString)
    {
        $out = array();
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($xmlString);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            return $out;
        }
        foreach ($xml->si as $si) {
            // A shared string may be either <si><t>Value</t></si> (plain) or
            // <si><r>…</r><r>…</r></si> (rich text — concatenate all <t>).
            if (isset($si->t)) {
                $out[] = (string) $si->t;
            } else {
                $buf = '';
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $buf .= (string) $r->t;
                    }
                }
                $out[] = $buf;
            }
        }
        return $out;
    }

    /** @return string[][] rows of cell strings (0-indexed, dense) */
    private function _parse_sheet($xmlString, array $sharedStrings)
    {
        $rows = array();
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($xmlString);
        libxml_use_internal_errors($prev);
        if ($xml === false || !isset($xml->sheetData)) {
            return $rows;
        }

        foreach ($xml->sheetData->row as $row) {
            $rowOut = array();
            $maxCol = -1;
            foreach ($row->c as $c) {
                // Column letter ("A", "B", "AA"...) is encoded in the r= attr;
                // map it back to a 0-based column index so blank cells stay aligned.
                $ref     = (string) $c['r'];
                $colIdx  = $this->_col_index_from_ref($ref);
                $type    = isset($c['t']) ? (string) $c['t'] : '';
                $value   = '';

                if ($type === 's') {
                    // Shared string — value is an index into $sharedStrings.
                    $idx = (int) ((string) $c->v);
                    if (isset($sharedStrings[$idx])) {
                        $value = $sharedStrings[$idx];
                    }
                } elseif ($type === 'inlineStr') {
                    if (isset($c->is->t)) {
                        $value = (string) $c->is->t;
                    } elseif (isset($c->is->r)) {
                        $buf = '';
                        foreach ($c->is->r as $r) {
                            if (isset($r->t)) {
                                $buf .= (string) $r->t;
                            }
                        }
                        $value = $buf;
                    }
                } elseif ($type === 'b') {
                    // Boolean — 1/0 in the XML.
                    $value = ((string) $c->v) === '1' ? 'TRUE' : 'FALSE';
                } elseif ($type === 'str' || $type === 'e') {
                    // Formula result string / error code — take as-is.
                    $value = (string) $c->v;
                } else {
                    // Numbers (and dates, which are stored as serial numbers).
                    if (isset($c->v)) {
                        $value = (string) $c->v;
                    }
                }

                $rowOut[$colIdx] = trim($value);
                if ($colIdx > $maxCol) {
                    $maxCol = $colIdx;
                }
            }

            // Densify — fill skipped columns with empty strings so all rows
            // line up under the header.
            $dense = array();
            for ($i = 0; $i <= $maxCol; $i++) {
                $dense[] = isset($rowOut[$i]) ? $rowOut[$i] : '';
            }
            $rows[] = $dense;
        }
        return $rows;
    }

    /** "A1" -> 0, "B2" -> 1, "AA3" -> 26, "" -> 0 */
    private function _col_index_from_ref($ref)
    {
        if ($ref === '') {
            return 0;
        }
        $letters = '';
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = $ref[$i];
            if ($ch >= 'A' && $ch <= 'Z') {
                $letters .= $ch;
            } else {
                break;
            }
        }
        if ($letters === '') {
            return 0;
        }
        $col = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $col = $col * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $col - 1;
    }
}
