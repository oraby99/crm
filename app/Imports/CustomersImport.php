<?php

namespace App\Imports;

use App\Enums\ImportStatus;
use App\Models\Customer;
use App\Models\CustomerNeed;
use App\Models\CustomerStatus;
use App\Models\Import;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class CustomersImport implements ToCollection
{
    public function __construct(
        public Import $importRecord
    ) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->importRecord->update([
                'status' => ImportStatus::Failed->value,
                'total_rows' => 0,
                'error_log' => 'ملف الإكسيل فارغ ولا يحتوي على أي بيانات.',
            ]);

            return;
        }

        $this->importRecord->update([
            'status' => ImportStatus::Processing->value,
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        // Cache lookups (normalized string -> ID)
        $platforms = Platform::all()->mapWithKeys(fn ($p) => [mb_strtolower(trim($p->name)) => $p->id, (string) $p->id => $p->id])->toArray();
        $statuses = CustomerStatus::all()->mapWithKeys(fn ($s) => [mb_strtolower(trim($s->name)) => $s->id, (string) $s->id => $s->id])->toArray();
        $needs = CustomerNeed::all()->mapWithKeys(fn ($n) => [mb_strtolower(trim($n->name)) => $n->id, (string) $n->id => $n->id])->toArray();
        $users = User::all()->mapWithKeys(fn ($u) => [mb_strtolower(trim($u->name)) => $u, (string) $u->id => $u])->toArray();

        $uploader = User::find($this->importRecord->uploaded_by);

        // Detect headers from first row
        $firstRow = $rows->first();
        $headerMap = []; // field_name => column_index
        $hasHeaderRow = false;

        if ($firstRow instanceof Collection || is_array($firstRow)) {
            foreach ($firstRow as $colIndex => $cellValue) {
                $cleanHeader = $this->cleanString((string) $cellValue);
                $field = $this->matchHeaderToField($cleanHeader);
                if ($field) {
                    $headerMap[$field] = $colIndex;
                    $hasHeaderRow = true;
                }
            }
        }

        // If first row was headers, skip it for data processing
        $dataRows = $hasHeaderRow ? $rows->slice(1) : $rows;

        $this->importRecord->update([
            'total_rows' => $dataRows->count(),
        ]);

        $rowCounter = 1;
        foreach ($dataRows as $row) {
            $rowCounter++; // 1-based row index in file

            try {
                // Extract field values
                $name = $this->getFieldValue($row, $headerMap, 'name');
                $rawPhone = $this->getFieldValue($row, $headerMap, 'phone');
                $phone = $this->normalizePhone($rawPhone);

                if (empty($name) || empty($phone)) {
                    throw new \Exception('اسم العميل ورقم الهاتف مطلوبان (تأكد من وجود بيانات الاسم والهاتف في هذا الصف)');
                }

                $whatsappPhone = $this->normalizePhone($this->getFieldValue($row, $headerMap, 'whatsapp'));
                $platformInput = $this->getFieldValue($row, $headerMap, 'platform');
                $needInput = $this->getFieldValue($row, $headerMap, 'need');
                $statusInput = $this->getFieldValue($row, $headerMap, 'status');
                $salesInput = $this->getFieldValue($row, $headerMap, 'sales');
                $details = $this->getFieldValue($row, $headerMap, 'details');

                $platformId = $platformInput ? ($platforms[mb_strtolower(trim($platformInput))] ?? null) : null;
                $statusId = $statusInput ? ($statuses[mb_strtolower(trim($statusInput))] ?? null) : null;
                $needId = $needInput ? ($needs[mb_strtolower(trim($needInput))] ?? null) : null;

                $data = [
                    'name' => $name,
                    'phone' => $phone,
                ];

                if ($whatsappPhone !== null) {
                    $data['whatsapp_phone'] = $whatsappPhone;
                }
                if ($details !== null) {
                    $data['details'] = $details;
                }
                if ($platformId) {
                    $data['platform_id'] = $platformId;
                }
                if ($statusId) {
                    $data['status_id'] = $statusId;
                }
                if ($needId) {
                    $data['customer_need_id'] = $needId;
                }

                // Handle sales assignment
                $assignedSales = $salesInput ? ($users[mb_strtolower(trim($salesInput))] ?? null) : null;
                if ($assignedSales) {
                    $data['sales_id'] = $assignedSales->id;
                    $data['team_leader_id'] = $assignedSales->team_leader_id;
                } elseif ($uploader && $uploader->isSales()) {
                    $data['sales_id'] = $uploader->id;
                    $data['team_leader_id'] = $uploader->team_leader_id;
                } elseif ($uploader && $uploader->isTeamLeader()) {
                    $data['team_leader_id'] = $uploader->id;
                }

                // Check if customer already exists by phone
                $customer = Customer::where('phone', $phone)->first();

                if ($customer) {
                    // Update existing customer
                    $customer->update($data);
                } else {
                    // Create new customer
                    $data['created_by'] = $this->importRecord->uploaded_by;
                    Customer::create($data);
                }

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "الصف {$rowCounter}: ".$e->getMessage();
            }
        }

        $this->importRecord->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'status' => $failed > 0 && $successful === 0 ? ImportStatus::Failed->value : ImportStatus::Completed->value,
            'error_log' => count($errors) > 0 ? implode("\n", $errors) : null,
        ]);
    }

    private function cleanString(string $val): string
    {
        // Remove UTF-8 BOM \xEF\xBB\xBF and hidden control characters
        $clean = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $val);

        return trim($clean);
    }

    private function matchHeaderToField(string $header): ?string
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $header));

        if ($normalized === '') {
            return null;
        }

        $fieldMap = [
            'name' => ['name', 'customername', 'fullname', 'اسمالعميل', 'الاسم', 'اسموالعميل', 'اسمعميل', 'asmlaamyl', 'asmalamyl', 'alasm', 'asm'],
            'phone' => ['phone', 'mobile', 'phonenumber', 'رقمالهاتف', 'الهاتف', 'جوال', 'رقمالجوال', 'rqmalhatf', 'rqmlhatf', 'alhatf', 'alhtf'],
            'whatsapp' => ['whatsapp', 'whatsappphone', 'waphone', 'رقمالواتساب', 'الواتساب', 'واتساب', 'rqmalvatsab', 'alvatsab', 'vatsab'],
            'platform' => ['platform', 'source', 'platformid', 'المصدر', 'المنصة', 'المصدرالمنصة', 'المصدرالمنصة', 'almasdr', 'almnst'],
            'need' => ['need', 'customerneed', 'productneed', 'الاحتياج', 'احتياجالعميل', 'احتياج', 'alahtyaj', 'ahtyaj'],
            'status' => ['status', 'customerstatus', 'الحالة', 'حالةالعميل', 'حالة', 'alhalat', 'halat'],
            'sales' => ['sales', 'salesid', 'salesrep', 'المندوب', 'مندوبالمبيعات', 'مندوب', 'almndvb', 'mndvb'],
            'details' => ['details', 'notes', 'description', 'التفاصيلواللاحظات', 'التفاصيل والملاحظات', 'التفاصيلوالملاحظات', 'التفاصيل', 'الملاحظات', 'altfasayl', 'almlahzat'],
        ];

        foreach ($fieldMap as $field => $keywords) {
            foreach ($keywords as $keyword) {
                if ($normalized === mb_strtolower($keyword)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function getFieldValue(Collection|array $row, array $headerMap, string $field): ?string
    {
        if (isset($headerMap[$field])) {
            $colIdx = $headerMap[$field];
            $val = is_array($row) ? ($row[$colIdx] ?? null) : $row->get($colIdx);
            if (filled($val)) {
                return $this->cleanString((string) $val);
            }
        }

        return null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Handle scientific notation like 1E+09 or 1.01E+9
        if (stripos($phone, 'e+') !== false || stripos($phone, 'e-') !== false) {
            $phone = sprintf('%.0f', (float) $phone);
        }

        // Strip trailing .0 if float conversion introduced it
        $phone = preg_replace('/\.0+$/', '', $phone);

        return trim($phone);
    }
}
