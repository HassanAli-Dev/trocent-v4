<?php

namespace App\Imports;

use App\Models\RateSheet;
use App\Models\RateSheetMeta;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RateSheetImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected Customer $customer,
        protected string   $type,
        protected bool     $skidByWeight = false,
        protected string   $importBatchId = '',
    ) {}



    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $sheet = RateSheet::create([
                'customer_id'      => $this->customer->id,
                'type'             => $this->type,
                'skid_by_weight'   => $this->skidByWeight,
                'destination_city' => $row['destination_city'] ?? '',
                'rate_code'        => $row['rate_code']       ?? null,
                'province' => $row['province'] ?? null,
                'postal_code' => $row['postal_code'] ?? null,
                'external'         => $row['external'] ?? 'I',
                'priority_sequence' => $row['priority_sequence'] ?? 0,
                'min_rate'         => $row['min']             ?? null,
                'import_batch_id' => $this->importBatchId,
                'ltl'              => $row['ltl'] ?? null,
            ]);

            // Save each column (except the known location/min columns) as meta
            foreach ($row as $key => $value) {
                if (in_array($key, ['from', 'source_city', 'to', 'destination_city', 'rate_code', 'min', 'province','priority_sequence','province','carrier','ltl'], true)) {
                    continue;
                }

                if ($value !== null && $value !== '') {
                    RateSheetMeta::create([
                        'rate_sheet_id' => $sheet->id,
                        'name'          => trim($key),
                        'value'         => trim((string) $value),
                    ]);
                }
            }
        }
    }
}
