<?php

namespace Modules\B2B\Http\Controllers;

class DataController
{
    public function user_permissions(): array
    {
        $labels = ['dashboard.view' => 'View B2B dashboard', 'products.manage' => 'Select products for B2B', 'customers.manage' => 'Manage B2B customers and terms', 'stock.transfer' => 'Transfer stock between main and B2B', 'pos.access' => 'Open B2B POS', 'sales.create' => 'Create manually priced B2B sales', 'credit.manage' => 'Create B2B credit sales within customer limits', 'payments.record' => 'Record B2B collections and refunds', 'returns.process' => 'Process B2B returns', 'reports.view' => 'View and export B2B reports'];
        $result = []; foreach ($labels as $key => $label) { $result[] = ['value' => 'b2b.'.$key, 'label' => $label, 'default' => false]; }
        return $result;
    }
}
