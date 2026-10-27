<?php

namespace Modules\Manufacturing\Http\Controllers;

class DataController
{
    public function user_permissions(): array
    {
        $labels = [
            'view' => 'View manufacturing', 'materials.manage' => 'Manage raw materials', 'components.manage' => 'Manage components and finished goods',
            'bom.manage' => 'Manage bills of materials', 'orders.create' => 'Create and plan production orders', 'orders.start' => 'Start or cancel production',
            'orders.complete' => 'Receive completed production', 'stock.adjust' => 'Adjust stock, return materials, scrap and reverse production', 'reports.view' => 'View manufacturing reports',
        ];
        $permissions = [];
        foreach ($labels as $ability => $label) { $permissions[] = ['value' => 'manufacturing.'.$ability, 'label' => $label, 'default' => false]; }
        return $permissions;
    }
}
