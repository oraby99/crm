<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Headers
$headers = ['name', 'phone', 'whatsapp', 'status', 'platform', 'need', 'details'];
foreach ($headers as $index => $header) {
    // A, B, C, D...
    $column = chr(65 + $index);
    $sheet->setCellValue($column . '1', $header);
}

// Add sample data rows
$data = [
    ['أحمد محمود', '01012345678', '01012345678', 'Under Follow-up', 'Facebook', 'Doors', 'مهتم جداً ومحتاج معاينة'],
    ['سارة علي', '01198765432', '', 'Completed', 'Instagram', 'HDF', 'تم التركيب بنجاح'],
    ['محمد خالد', '01234567890', '01234567890', '', '', 'Cladding', 'يحتاج تفاصيل الأسعار'], // empty status and platform
    ['عميل خطأ', '', '', 'New', 'TikTok', 'HDF', 'لا يوجد رقم هاتف (سيفشل)'], // No phone
];

$rowNumber = 2;
foreach ($data as $rowData) {
    foreach ($rowData as $colIndex => $value) {
        $column = chr(65 + $colIndex);
        $sheet->setCellValue($column . $rowNumber, $value);
    }
    $rowNumber++;
}

$writer = new Xlsx($spreadsheet);
$writer->save('sample_customers.xlsx');

echo "Excel file generated successfully!\n";
