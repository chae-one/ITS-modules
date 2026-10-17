<?php
// =====================================================================
// PDF GENERATOR
//
// A small, dependency-free PDF writer - no Composer, no TCPDF/mPDF/FPDF,
// just raw PDF syntax. Good enough for simple, monospaced text/table
// output such as the monthly activity log export in settings.php.
//
// Currently used by:
//   - settings.php (activity_log_pdf_lines / generate_activity_log_month_pdf)
//     to build the monthly Activity Log PDF.
//
// Anything else in the app that needs a quick text-based PDF can reuse
// build_simple_pdf() the same way - it only needs a title, a header row
// (repeated on every page) and the body lines.
// =====================================================================

/** Escapes a string for a PDF literal string "( ... )" and drops characters the base-14 fonts can't render. */
function pdf_escape_text($text) {
    $text = (string) $text;
    // The built-in PDF fonts only support WinAnsi (~Latin-1). Transliterate
    // anything outside that (curly quotes, emoji, ...) instead of corrupting
    // the file; @ silences iconv's notice on characters it has to drop.
    $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
    if ($converted !== false) $text = $converted;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

/** Truncates (multibyte-safe) to at most $max visible characters, adding an ellipsis if it had to cut. */
function pdf_clip($text, $max) {
    $text = trim((string) $text);
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, max(0, $max - 1)) . "\xE2\x80\xA6"; // "\xE2\x80\xA6" = ...
}

/** Pads (or clips) $text to exactly $width visible characters - for lining up monospaced table columns. */
function pdf_pad($text, $width) {
    $text = pdf_clip($text, $width);
    $len = mb_strlen($text);
    return $len < $width ? $text . str_repeat(' ', $width - $len) : $text;
}

/**
 * Word-wraps $text (multibyte-safe) to at most $width visible characters
 * per line, breaking on spaces where possible. A single word longer than
 * $width is hard-split rather than left to overflow. Used so long fields
 * (like a log entry's Summary) stay fully readable across a couple of
 * lines instead of being cut off with an ellipsis.
 *
 * @return string[] One or more lines; [] only when $text is empty.
 */
function pdf_wrap($text, $width) {
    $text  = trim((string) $text);
    $width = max(1, (int) $width);
    if ($text === '') return [];

    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        while (mb_strlen($word) > $width) {
            if ($current !== '') { $lines[] = $current; $current = ''; }
            $lines[] = mb_substr($word, 0, $width);
            $word = mb_substr($word, $width);
        }
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($candidate) > $width) {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }
    if ($current !== '') $lines[] = $current;
    return $lines;
}

/**
 * Builds a simple multi-page PDF from a title, a one-line column header
 * (repeated at the top of every page, bold, with a rule under it) and the
 * body lines - no external library, just raw PDF syntax. Monospaced
 * (Courier) so the caller's pdf_pad()-aligned columns stay lined up;
 * Letter-landscape pages, sized to fit the page width the caller planned
 * columns for (see PDF_PAGE_USABLE_WIDTH_CHARS below).
 *
 * @param string   $title      Printed at the top of every page.
 * @param string   $headerLine One pdf_pad()-aligned line naming the columns.
 * @param string[] $bodyLines  Body lines, already padded/wrapped into columns by the caller.
 * @return string              Raw PDF file bytes.
 */
function build_simple_pdf($title, $headerLine, array $bodyLines) {
    $fontSize   = 9;
    $lineHeight = 13;
    $pageWidth  = 792; // Letter, landscape (11in) - wide tables fit better
    $pageHeight = 612; // 8.5in
    $marginX    = 40;  // kept clear of printer edge-of-page margins
    $titleY     = 578;
    $subtitleY  = 563;
    $headerY    = 542; // column-header baseline, repeated on every page
    $ruleGap    = 5;   // gap between the header baseline and the rule under it
    $topY       = $headerY - $lineHeight; // first body line on every page
    $bottomY    = 40;

    $linesPerPage = max(1, (int) floor(($topY - $bottomY) / $lineHeight));
    $pages = array_chunk($bodyLines, $linesPerPage);
    if (!$pages) $pages = [[]];
    $pageCount = count($pages);
    $generated = 'Generated ' . date('Y-m-d H:i');

    $ruleY      = $headerY - $ruleGap;
    $bandTop    = $headerY + ($lineHeight * 0.68);
    $bandBottom = $ruleY - 1;
    $bandHeight = $bandTop - $bandBottom;

    // ---- Content stream per page ----
    $contentStreams = [];
    foreach ($pages as $pageIndex => $pageLines) {
        $stream  = '';
        // Light shaded band behind the repeated column header, and a rule under it.
        $stream .= "q 0.90 0.90 0.90 rg $marginX $bandBottom " . ($pageWidth - 2 * $marginX) . " $bandHeight re f Q\n";
        $stream .= "q 0.4 w $marginX $ruleY m " . ($pageWidth - $marginX) . " $ruleY l S Q\n";

        $stream .= "BT\n";
        $stream .= "/F2 12 Tf\n";
        $stream .= "1 0 0 1 $marginX $titleY Tm\n";
        $stream .= '(' . pdf_escape_text($title) . ") Tj\n";
        $stream .= "/F1 8 Tf\n";
        $stream .= "1 0 0 1 $marginX $subtitleY Tm\n";
        $stream .= '(' . pdf_escape_text($generated . '  -  Page ' . ($pageIndex + 1) . ' of ' . $pageCount) . ") Tj\n";

        $stream .= "/F2 $fontSize Tf\n";
        $stream .= "1 0 0 1 $marginX $headerY Tm\n";
        $stream .= '(' . pdf_escape_text($headerLine) . ") Tj\n";

        $stream .= "/F1 $fontSize Tf\n";
        $stream .= "$lineHeight TL\n";
        $stream .= "1 0 0 1 $marginX $topY Tm\n";
        $first = true;
        foreach ($pageLines as $line) {
            if ($first) { $first = false; } else { $stream .= "T*\n"; }
            $stream .= '(' . pdf_escape_text($line) . ") Tj\n";
        }
        $stream .= 'ET';
        $contentStreams[] = $stream;
    }

    // ---- Object numbers: 1 catalog, 2 pages, 3 font (regular), 4 font (bold), then a page+content pair per page ----
    $pageObjNums = [];
    $contentObjNums = [];
    $next = 5;
    for ($i = 0; $i < $pageCount; $i++) {
        $pageObjNums[]    = $next++;
        $contentObjNums[] = $next++;
    }

    $objects = [];
    $kids = implode(' ', array_map(function ($n) { return "$n 0 R"; }, $pageObjNums));
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [$kids] /Count $pageCount /MediaBox [0 0 $pageWidth $pageHeight] >>";
    $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
    $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>";
    for ($i = 0; $i < $pageCount; $i++) {
        $pObj = $pageObjNums[$i];
        $cObj = $contentObjNums[$i];
        $objects[$pObj] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $cObj 0 R >>";
        $stream = $contentStreams[$i];
        $objects[$cObj] = "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream";
    }
    ksort($objects);

    // ---- Serialize with a matching xref table ----
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n$body\nendobj\n";
    }

    $xrefStart = strlen($pdf);
    $maxNum = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxNum + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($n = 1; $n <= $maxNum; $n++) {
        $pdf .= isset($offsets[$n]) ? sprintf("%010d 00000 n \n", $offsets[$n]) : "0000000000 00000 f \n";
    }
    $pdf .= "trailer\n<< /Size " . ($maxNum + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xrefStart\n%%EOF";

    return $pdf;
}

// Courier at 9pt is 0.6 * 9 = 5.4pt per character. With build_simple_pdf()'s
// 792pt landscape page and 40pt side margins, that leaves (792 - 80) / 5.4
// ≈ 131 characters per line - anything a caller lays a row out to must add
// up to no more than this, or it will run past the printable page width.
define('PDF_PAGE_USABLE_WIDTH_CHARS', 131);