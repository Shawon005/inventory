<?php

namespace Modules\Manufacturing\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionOrdersExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $orders) {}
    public function query() { return $this->orders; }
    public function headings(): array { return ['Order', 'Status', 'Warehouse ID', 'Output variation ID', 'Planned', 'Accepted', 'Defective', 'Estimated cost', 'Actual cost', 'Created']; }
    public function map($row): array { return [$row->order_number, $row->status, $row->location_id, $row->variation_id, (string) $row->planned_quantity, (string) $row->accepted_quantity, (string) $row->defective_quantity, (string) $row->expected_cost, (string) $row->actual_cost, $row->created_at]; }
}
