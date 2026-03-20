<?php

declare(strict_types=1);

/**
 * Example: generating a PDF from HTML with laravel-dompdf (barryvdh/laravel-dompdf).
 *
 * This example uses the Dompdf library directly since laravel-dompdf is a thin
 * Laravel wrapper around it.
 *
 * Run from the laravel-dompdf project root:
 *   php examples/generate_pdf.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// --- Configure Dompdf options ---
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false); // keep PHP disabled for security

$dompdf = new Dompdf($options);

// --- Load HTML content ---
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, sans-serif; margin: 40px; }
        h1   { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Invoice #INV-2026-001</h1>
    <p><strong>Date:</strong> 2026-03-20</p>
    <p><strong>Bill To:</strong> Alice Smith, alice@example.com</p>
    <table>
        <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        <tr><td>Widget A</td><td>3</td><td>$10.00</td><td>$30.00</td></tr>
        <tr><td>Gadget B</td><td>1</td><td>$49.99</td><td>$49.99</td></tr>
        <tr><td colspan="3"><strong>Total</strong></td><td><strong>$79.99</strong></td></tr>
    </table>
</body>
</html>
HTML;

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// --- Save to a temp file ---
$pdf = $dompdf->output();
$path = sys_get_temp_dir() . '/demo-invoice.pdf';
file_put_contents($path, $pdf);

echo "PDF written to: $path\n";
echo "PDF size: " . strlen($pdf) . " bytes\n";
echo "PDF header: " . substr($pdf, 0, 4) . "\n"; // should be "%PDF"

// Cleanup
@unlink($path);
