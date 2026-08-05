<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$modules = ['StockLedger', 'StockTake', 'StockTransfer', 'Branch Request', 'PurchaseOrder', 'PurchaseReturnNote', 'Product', 'Payment', 'User', 'ProductAssignment', 'Grn', 'CheckOut', 'CheckIn', 'Activity Log', 'Inventory Dashboard'];
foreach ($modules as $mod) {
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => $mod . ' View All', 'guard_name' => 'api', 'group_name' => $mod . ' Permissions']);
}
$admin = Spatie\Permission\Models\Role::findByName('Admin', 'api');
if ($admin) {
    $perms = Spatie\Permission\Models\Permission::where('name', 'like', '%View All%')->get();
    $admin->givePermissionTo($perms);
}
echo 'Done';
