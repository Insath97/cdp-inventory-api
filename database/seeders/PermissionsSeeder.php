<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            /* Access Management */
            ['name' => 'Permission Index', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Delete', 'group_name' => 'Access Management Permissions'],

            ['name' => 'Role Index', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Delete', 'group_name' => 'Access Management Permissions'],

            /* User Management */
            ['name' => 'User Index', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Show', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Create', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Update', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Delete', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Toggle Status', 'group_name' => 'User Management Permissions'],
            ['name' => 'User View All', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Reset Password', 'group_name' => 'User Management Permissions'],

            /* User escalation — deliberately its own group, excluded from the
               Admin bulk-grant below, so only Super Admin holds these unless
               they are granted explicitly. UserController checks these names
               inline for privileged operations. */
            ['name' => 'Assign Super Admin Role', 'group_name' => 'User Escalation Permissions'],
            ['name' => 'Update User Type', 'group_name' => 'User Escalation Permissions'],
            ['name' => 'Delete Any User', 'group_name' => 'User Escalation Permissions'],

            /* Branch Management */
            ['name' => 'Branch Index', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Show', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Create', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Update', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Delete', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Toggle Status', 'group_name' => 'Branch Management Permissions'],

            /* Reporting Manager Management */
            ['name' => 'Reporting Manager Index', 'group_name' => 'Reporting Manager Management Permissions'],
            ['name' => 'Reporting Manager Show', 'group_name' => 'Reporting Manager Management Permissions'],
            ['name' => 'Reporting Manager Create', 'group_name' => 'Reporting Manager Management Permissions'],
            ['name' => 'Reporting Manager Update', 'group_name' => 'Reporting Manager Management Permissions'],
            ['name' => 'Reporting Manager Delete', 'group_name' => 'Reporting Manager Management Permissions'],
            ['name' => 'Reporting Manager Toggle Status', 'group_name' => 'Reporting Manager Management Permissions'],

            /*Main Category Management*/
            ['name' => 'Main Category Index', 'group_name' => 'Main Category Management Permissions'],
            ['name' => 'Main Category Show', 'group_name' => 'Main Category Management Permissions'],
            ['name' => 'Main Category Create', 'group_name' => 'Main Category Management Permissions'],
            ['name' => 'Main Category Update', 'group_name' => 'Main Category Management Permissions'],
            ['name' => 'Main Category Delete', 'group_name' => 'Main Category Management Permissions'],
            ['name' => 'Main Category Toggle Status', 'group_name' => 'Main Category Management Permissions'],

            /*Sub Category Management*/
            ['name' => 'Sub Category Index', 'group_name' => 'Sub Category Management Permissions'],
            ['name' => 'Sub Category Show', 'group_name' => 'Sub Category Management Permissions'],
            ['name' => 'Sub Category Create', 'group_name' => 'Sub Category Management Permissions'],
            ['name' => 'Sub Category Update', 'group_name' => 'Sub Category Management Permissions'],
            ['name' => 'Sub Category Delete', 'group_name' => 'Sub Category Management Permissions'],
            ['name' => 'Sub Category Toggle Status', 'group_name' => 'Sub Category Management Permissions'],

            /*Brand Management*/
            ['name' => 'Brand Index', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Show', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Create', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Update', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Delete', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Toggle Status', 'group_name' => 'Brand Management Permissions'],

            /*Supplier Management*/
            ['name' => 'Supplier Index', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Show', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Create', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Update', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Delete', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Toggle Status', 'group_name' => 'Supplier Management Permissions'],

            /*Supplier Bank Account Management*/
            ['name' => 'Supplier Bank Account Index', 'group_name' => 'Supplier Bank Account Management Permissions'],
            ['name' => 'Supplier Bank Account Show', 'group_name' => 'Supplier Bank Account Management Permissions'],
            ['name' => 'Supplier Bank Account Create', 'group_name' => 'Supplier Bank Account Management Permissions'],
            ['name' => 'Supplier Bank Account Update', 'group_name' => 'Supplier Bank Account Management Permissions'],
            ['name' => 'Supplier Bank Account Delete', 'group_name' => 'Supplier Bank Account Management Permissions'],
            ['name' => 'Supplier Bank Account Toggle Status', 'group_name' => 'Supplier Bank Account Management Permissions'],

            /*Unit Management*/
            ['name' => 'Unit Index', 'group_name' => 'Unit Management Permissions'],
            ['name' => 'Unit Show', 'group_name' => 'Unit Management Permissions'],
            ['name' => 'Unit Create', 'group_name' => 'Unit Management Permissions'],
            ['name' => 'Unit Update', 'group_name' => 'Unit Management Permissions'],
            ['name' => 'Unit Delete', 'group_name' => 'Unit Management Permissions'],
            ['name' => 'Unit Toggle Status', 'group_name' => 'Unit Management Permissions'],

            /*Measurement Management*/
            // ['name' => 'Measurement Index', 'group_name' => 'Measurement Management Permissions'],
            // ['name' => 'Measurement Show', 'group_name' => 'Measurement Management Permissions'],
            // ['name' => 'Measurement Create', 'group_name' => 'Measurement Management Permissions'],
            // ['name' => 'Measurement Update', 'group_name' => 'Measurement Management Permissions'],
            // ['name' => 'Measurement Delete', 'group_name' => 'Measurement Management Permissions'],
            ['name' => 'Measurement Toggle Status', 'group_name' => 'Measurement Management Permissions'],
            ['name' => 'MeasurementUnit Index', 'group_name' => 'Measurement Management Permissions'],
            ['name' => 'MeasurementUnit Show', 'group_name' => 'Measurement Management Permissions'],
            ['name' => 'MeasurementUnit Create', 'group_name' => 'Measurement Management Permissions'],
            ['name' => 'MeasurementUnit Update', 'group_name' => 'Measurement Management Permissions'],
            ['name' => 'MeasurementUnit Delete', 'group_name' => 'Measurement Management Permissions'],

            /*Container Management*/
            ['name' => 'Container Index', 'group_name' => 'Container Management Permissions'],
            ['name' => 'Container Show', 'group_name' => 'Container Management Permissions'],
            ['name' => 'Container Create', 'group_name' => 'Container Management Permissions'],
            ['name' => 'Container Update', 'group_name' => 'Container Management Permissions'],
            ['name' => 'Container Delete', 'group_name' => 'Container Management Permissions'],
            ['name' => 'Container Toggle Status', 'group_name' => 'Container Management Permissions'],

            /*Product Management*/
            ['name' => 'Product Index', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Show', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Create', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Update', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Delete', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Toggle Status', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product View All', 'group_name' => 'Product Management Permissions'],

            /*Product Variant Management*/
            ['name' => 'Product Variant Index', 'group_name' => 'Product Variant Management Permissions'],
            ['name' => 'Product Variant Show', 'group_name' => 'Product Variant Management Permissions'],
            ['name' => 'Product Variant Create', 'group_name' => 'Product Variant Management Permissions'],
            ['name' => 'Product Variant Update', 'group_name' => 'Product Variant Management Permissions'],
            ['name' => 'Product Variant Delete', 'group_name' => 'Product Variant Management Permissions'],
            ['name' => 'Product Variant Toggle Status', 'group_name' => 'Product Variant Management Permissions'],

            /*Purchase Order Management*/
            ['name' => 'PurchaseOrder Index', 'group_name' => 'Purchase Order Management Permissions'],
            ['name' => 'PurchaseOrder Show', 'group_name' => 'Purchase Order Management Permissions'],
            ['name' => 'PurchaseOrder Create', 'group_name' => 'Purchase Order Management Permissions'],
            ['name' => 'PurchaseOrder Update', 'group_name' => 'Purchase Order Management Permissions'],
            ['name' => 'PurchaseOrder Delete', 'group_name' => 'Purchase Order Management Permissions'],
            ['name' => 'PurchaseOrder Activate/Deactivate', 'group_name' => 'Purchase Order Management Permissions'],
            ['name' => 'PurchaseOrder View All', 'group_name' => 'Purchase Order Management Permissions'],

            /*Purchase Order Item Management*/
            ['name' => 'PurchaseOrderItem Index', 'group_name' => 'Purchase Order Item Management Permissions'],
            ['name' => 'PurchaseOrderItem Show', 'group_name' => 'Purchase Order Item Management Permissions'],
            ['name' => 'PurchaseOrderItem Create', 'group_name' => 'Purchase Order Item Management Permissions'],
            ['name' => 'PurchaseOrderItem Update', 'group_name' => 'Purchase Order Item Management Permissions'],
            ['name' => 'PurchaseOrderItem Delete', 'group_name' => 'Purchase Order Item Management Permissions'],
            ['name' => 'PurchaseOrderItem Activate/Deactivate', 'group_name' => 'Purchase Order Item Management Permissions'],

            /*Supplier Product Management*/
            ['name' => 'SupplierProduct Index', 'group_name' => 'Supplier Product Management Permissions'],
            ['name' => 'SupplierProduct Show', 'group_name' => 'Supplier Product Management Permissions'],
            ['name' => 'SupplierProduct Create', 'group_name' => 'Supplier Product Management Permissions'],
            ['name' => 'SupplierProduct Update', 'group_name' => 'Supplier Product Management Permissions'],
            ['name' => 'SupplierProduct Delete', 'group_name' => 'Supplier Product Management Permissions'],
            ['name' => 'SupplierProduct Activate/Deactivate', 'group_name' => 'Supplier Product Management Permissions'],

            /*Purchase Return Note Management*/
            ['name' => 'PurchaseReturnNote Index', 'group_name' => 'Purchase Return Note Management Permissions'],
            ['name' => 'PurchaseReturnNote Show', 'group_name' => 'Purchase Return Note Management Permissions'],
            ['name' => 'PurchaseReturnNote Create', 'group_name' => 'Purchase Return Note Management Permissions'],
            ['name' => 'PurchaseReturnNote Update', 'group_name' => 'Purchase Return Note Management Permissions'],
            ['name' => 'PurchaseReturnNote Delete', 'group_name' => 'Purchase Return Note Management Permissions'],
            ['name' => 'PurchaseReturnNote View All', 'group_name' => 'Purchase Return Note Management Permissions'],

            /*Purchase Return Note Item Management*/
            ['name' => 'PurchaseReturnNoteItem Index', 'group_name' => 'Purchase Return Note Item Management Permissions'],
            ['name' => 'PurchaseReturnNoteItem Show', 'group_name' => 'Purchase Return Note Item Management Permissions'],
            ['name' => 'PurchaseReturnNoteItem Create', 'group_name' => 'Purchase Return Note Item Management Permissions'],
            ['name' => 'PurchaseReturnNoteItem Update', 'group_name' => 'Purchase Return Note Item Management Permissions'],
            ['name' => 'PurchaseReturnNoteItem Delete', 'group_name' => 'Purchase Return Note Item Management Permissions'],

            /*Stock Ledger Management*/
            ['name' => 'StockLedger Index', 'group_name' => 'Stock Ledger Management Permissions'],
            ['name' => 'StockLedger Show', 'group_name' => 'Stock Ledger Management Permissions'],
            ['name' => 'StockLedger Create', 'group_name' => 'Stock Ledger Management Permissions'],
            ['name' => 'StockLedger Update', 'group_name' => 'Stock Ledger Management Permissions'],
            ['name' => 'StockLedger Delete', 'group_name' => 'Stock Ledger Management Permissions'],
            ['name' => 'StockLedger View All', 'group_name' => 'Stock Ledger Management Permissions'],

            /*Expiry Record Management*/
            ['name' => 'Expiry Record Index', 'group_name' => 'Expiry Record Management Permissions'],
            ['name' => 'Expiry Record Show', 'group_name' => 'Expiry Record Management Permissions'],
            ['name' => 'Expiry Record Create', 'group_name' => 'Expiry Record Management Permissions'],
            ['name' => 'Expiry Record Update', 'group_name' => 'Expiry Record Management Permissions'],
            ['name' => 'Expiry Record Delete', 'group_name' => 'Expiry Record Management Permissions'],

            /*Damage Record Management*/
            ['name' => 'Damage Record Index', 'group_name' => 'Damage Record Management Permissions'],
            ['name' => 'Damage Record Show', 'group_name' => 'Damage Record Management Permissions'],
            ['name' => 'Damage Record Create', 'group_name' => 'Damage Record Management Permissions'],
            ['name' => 'Damage Record Update', 'group_name' => 'Damage Record Management Permissions'],
            ['name' => 'Damage Record Delete', 'group_name' => 'Damage Record Management Permissions'],

            /*Damage Record Approval — deliberately its own group, not part of the
              operational bulk-grant, so Manager does not receive it automatically.*/
            ['name' => 'Damage Record Approve', 'group_name' => 'Damage Record Approval Permissions'],

            /*Payment Management*/
            ['name' => 'Payment Index', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment Show', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment Create', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment Update', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment Delete', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment View All', 'group_name' => 'Payment Management Permissions'],

            /*Grn Management*/
            ['name' => 'Grn Index', 'group_name' => 'Grn Management Permissions'],
            ['name' => 'Grn Show', 'group_name' => 'Grn Management Permissions'],
            ['name' => 'Grn Create', 'group_name' => 'Grn Management Permissions'],
            ['name' => 'Grn Update', 'group_name' => 'Grn Management Permissions'],
            ['name' => 'Grn Delete', 'group_name' => 'Grn Management Permissions'],
            ['name' => 'Grn Toggle Status', 'group_name' => 'Grn Management Permissions'],
            ['name' => 'Grn View All', 'group_name' => 'Grn Management Permissions'],

            /*Grn Item Management*/
            ['name' => 'Grn Item Index', 'group_name' => 'Grn Item Management Permissions'],
            ['name' => 'Grn Item Show', 'group_name' => 'Grn Item Management Permissions'],
            ['name' => 'Grn Item Create', 'group_name' => 'Grn Item Management Permissions'],
            ['name' => 'Grn Item Update', 'group_name' => 'Grn Item Management Permissions'],
            ['name' => 'Grn Item Delete', 'group_name' => 'Grn Item Management Permissions'],

            /*Stock Transfer Management*/
            ['name' => 'StockTransfer Index', 'group_name' => 'Stock Transfer Management Permissions'],
            ['name' => 'StockTransfer Show', 'group_name' => 'Stock Transfer Management Permissions'],
            ['name' => 'StockTransfer Create', 'group_name' => 'Stock Transfer Management Permissions'],
            ['name' => 'StockTransfer Update', 'group_name' => 'Stock Transfer Management Permissions'],
            ['name' => 'StockTransfer Delete', 'group_name' => 'Stock Transfer Management Permissions'],
            ['name' => 'StockTransfer View All', 'group_name' => 'Stock Transfer Management Permissions'],

            /*Stock Transfer Item Management*/
            ['name' => 'StockTransferItem Index', 'group_name' => 'Stock Transfer Item Management Permissions'],
            ['name' => 'StockTransferItem Show', 'group_name' => 'Stock Transfer Item Management Permissions'],
            ['name' => 'StockTransferItem Create', 'group_name' => 'Stock Transfer Item Management Permissions'],
            ['name' => 'StockTransferItem Update', 'group_name' => 'Stock Transfer Item Management Permissions'],
            ['name' => 'StockTransferItem Delete', 'group_name' => 'Stock Transfer Item Management Permissions'],

            /*Reorder Level Management*/
            ['name' => 'Reorder Level Index', 'group_name' => 'Reorder Level Management Permissions'],
            ['name' => 'Reorder Level Show', 'group_name' => 'Reorder Level Management Permissions'],
            ['name' => 'Reorder Level Create', 'group_name' => 'Reorder Level Management Permissions'],
            ['name' => 'Reorder Level Update', 'group_name' => 'Reorder Level Management Permissions'],
            ['name' => 'Reorder Level Delete', 'group_name' => 'Reorder Level Management Permissions'],
            ['name' => 'Reorder Level Toggle Status', 'group_name' => 'Reorder Level Management Permissions'],

            /*Stock Take Management*/
            ['name' => 'StockTake Index', 'group_name' => 'Stock Take Management Permissions'],
            ['name' => 'StockTake Show', 'group_name' => 'Stock Take Management Permissions'],
            ['name' => 'StockTake Create', 'group_name' => 'Stock Take Management Permissions'],
            ['name' => 'StockTake Update', 'group_name' => 'Stock Take Management Permissions'],
            ['name' => 'StockTake Delete', 'group_name' => 'Stock Take Management Permissions'],
            ['name' => 'StockTake View All', 'group_name' => 'Stock Take Management Permissions'],

            /*Stock Take Item Management*/
            ['name' => 'StockTakeItem Index', 'group_name' => 'Stock Take Item Management Permissions'],
            ['name' => 'StockTakeItem Show', 'group_name' => 'Stock Take Item Management Permissions'],
            ['name' => 'StockTakeItem Create', 'group_name' => 'Stock Take Item Management Permissions'],
            ['name' => 'StockTakeItem Update', 'group_name' => 'Stock Take Item Management Permissions'],
            ['name' => 'StockTakeItem Delete', 'group_name' => 'Stock Take Item Management Permissions'],

            /*Activity Log*/
            ['name' => 'Activity Log Index', 'group_name' => 'Activity Log Permissions'],
            ['name' => 'Activity Log Show', 'group_name' => 'Activity Log Permissions'],
            ['name' => 'Activity Log View All', 'group_name' => 'Activity Log Permissions'],

            /*Inventory Dashboard*/
            ['name' => 'Inventory Dashboard Index', 'group_name' => 'Inventory Dashboard Permissions'],
            ['name' => 'Inventory Dashboard Show', 'group_name' => 'Inventory Dashboard Permissions'],
            ['name' => 'Inventory Dashboard Create', 'group_name' => 'Inventory Dashboard Permissions'],
            ['name' => 'Inventory Dashboard Update', 'group_name' => 'Inventory Dashboard Permissions'],
            ['name' => 'Inventory Dashboard Delete', 'group_name' => 'Inventory Dashboard Permissions'],
            ['name' => 'Inventory Dashboard Toggle Status', 'group_name' => 'Inventory Dashboard Permissions'],
            ['name' => 'Inventory Dashboard View All', 'group_name' => 'Inventory Dashboard Permissions'],

            /*Branch Request*/
            ['name' => 'Branch Request Index', 'group_name' => 'Branch Request Permissions'],
            ['name' => 'Branch Request Show', 'group_name' => 'Branch Request Permissions'],
            ['name' => 'Branch Request Create', 'group_name' => 'Branch Request Permissions'],
            ['name' => 'Branch Request Update', 'group_name' => 'Branch Request Permissions'],
            ['name' => 'Branch Request Delete', 'group_name' => 'Branch Request Permissions'],
            ['name' => 'Branch Request Toggle Status', 'group_name' => 'Branch Request Permissions'],
            ['name' => 'Branch Request View All', 'group_name' => 'Branch Request Permissions'],

            /*Check In*/
            ['name' => 'CheckIn Index', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn Show', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn Create', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn Update', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn Delete', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn Toggle Status', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn Activate/Deactivate', 'group_name' => 'Check In Permissions'],
            ['name' => 'CheckIn View All', 'group_name' => 'Check In Permissions'],

            /*Check Out*/
            ['name' => 'CheckOut Index', 'group_name' => 'Check Out Permissions'],
            ['name' => 'CheckOut Show', 'group_name' => 'Check Out Permissions'],
            ['name' => 'CheckOut Create', 'group_name' => 'Check Out Permissions'],
            ['name' => 'CheckOut Update', 'group_name' => 'Check Out Permissions'],
            ['name' => 'CheckOut Delete', 'group_name' => 'Check Out Permissions'],
            ['name' => 'CheckOut Toggle Status', 'group_name' => 'Check Out Permissions'], 
            ['name' => 'CheckOut Activate/Deactivate', 'group_name' => 'Check Out Permissions'],
            ['name' => 'CheckOut View All', 'group_name' => 'Check Out Permissions'],

            /*Product Assignment*/
            ['name' => 'ProductAssignment Index', 'group_name' => 'Product Assignment Permissions'],
            ['name' => 'ProductAssignment Show', 'group_name' => 'Product Assignment Permissions'],
            ['name' => 'ProductAssignment Create', 'group_name' => 'Product Assignment Permissions'],
            ['name' => 'ProductAssignment Update', 'group_name' => 'Product Assignment Permissions'],
            ['name' => 'ProductAssignment Delete', 'group_name' => 'Product Assignment Permissions'],
            ['name' => 'ProductAssignment Toggle Status', 'group_name' => 'Product Assignment Permissions'],
            ['name' => 'ProductAssignment View All', 'group_name' => 'Product Assignment Permissions'],

            /*Product Return*/
            ['name' => 'ProductReturn Index', 'group_name' => 'Product Return Permissions'],
            ['name' => 'ProductReturn Show', 'group_name' => 'Product Return Permissions'],
            ['name' => 'ProductReturn Create', 'group_name' => 'Product Return Permissions'],
            ['name' => 'ProductReturn Update', 'group_name' => 'Product Return Permissions'],
            ['name' => 'ProductReturn Delete', 'group_name' => 'Product Return Permissions'],
            ['name' => 'ProductReturn Toggle Status', 'group_name' => 'Product Return Permissions']
        ];

        $seederPermissionNames = collect($permissions)->pluck('name')->toArray();
        Permission::whereNotIn('name', $seederPermissionNames)->delete();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
                ['group_name' => $permission['group_name']]
            );
        }

        $allPermissions = Permission::all();
        
        // 1. Super Admin gets all permissions
        $superAdminRoles = ['SUPER ADMIN', 'Super Admin'];
        foreach ($superAdminRoles as $roleName) {
            $role = Role::firstOrCreate(['guard_name' => 'api', 'name' => $roleName]);
            $role->syncPermissions($allPermissions);
        }

        $adminPermissions = $allPermissions->filter(function ($permission) {
            if ($permission->name === 'Role Index') {
                return true;
            }
            return !in_array($permission->group_name, [
                'Access Management Permissions',
                'User Escalation Permissions'
            ]);
        });
        $adminRoles = ['ADMIN', 'Admin'];
        foreach ($adminRoles as $roleName) {
            $role = Role::firstOrCreate(['guard_name' => 'api', 'name' => $roleName]);
            $role->syncPermissions($adminPermissions);
        }

        // 3. Manager gets Index-only for reference modules, and Full access for operational stock modules
        $managerPermissions = $allPermissions->filter(function ($permission) {
            $name = $permission->name;
            $group = $permission->group_name;

            // Operational groups: manager gets full access
            $operationalGroups = [
                'Check In Permissions',
                'Check Out Permissions',
                'Branch Request Permissions',
                'Stock Take Management Permissions',
                'Stock Take Item Management Permissions',
                'Stock Transfer Management Permissions',
                'Stock Transfer Item Management Permissions',
                'Expiry Record Management Permissions',
                'Damage Record Management Permissions',
                'Inventory Dashboard Permissions',
                'Product Assignment Permissions',
                'Product Return Permissions'
            ];

            if (in_array($group, $operationalGroups)) {
                return true;
            }

            // GRN receiving flow: the manager owns goods receiving end-to-end.
            // The GRN screen also creates pending-setup products inline
            // (POST /products), completes their details afterwards
            // (PUT /products/{id}, PUT /product-variants/{id}) and links them
            // to the supplier (POST /supplier-products), so those specific
            // write permissions are part of the flow — without them the GRN
            // form 403s mid-save. Deletes stay admin-only.
            $grnFlowPermissions = [
                'Grn Index', 'Grn Show', 'Grn Create', 'Grn Update', 'Grn Toggle Status',
                'Grn Item Index', 'Grn Item Show', 'Grn Item Create', 'Grn Item Update',
                'Product Create', 'Product Update',
                'Product Variant Update',
                'SupplierProduct Create',
            ];

            if (in_array($name, $grnFlowPermissions)) {
                return true;
            }

            // Reference groups: manager only gets "Index" permission
            $referenceGroups = [
                'User Management Permissions',
                'Branch Management Permissions',
                'Reporting Manager Management Permissions',
                'Main Category Management Permissions',
                'Sub Category Management Permissions',
                'Brand Management Permissions',
                'Supplier Management Permissions',
                'Supplier Bank Account Management Permissions',
                'Unit Management Permissions',
                'Measurement Management Permissions',
                'Container Management Permissions',
                'Product Management Permissions',
                'Product Variant Management Permissions',
                'Purchase Order Management Permissions',
                'Purchase Order Item Management Permissions',
                'Grn Management Permissions',
                'Grn Item Management Permissions',
                'Purchase Return Note Management Permissions',
                'Purchase Return Note Item Management Permissions',
                'Supplier Product Management Permissions',
                'Payment Management Permissions',
                'Stock Ledger Management Permissions',
                'Reorder Level Management Permissions'
            ];

            if (in_array($group, $referenceGroups)) {
                if ($name === 'Supplier Update') {
                    return true;
                }
                return str_ends_with($name, 'Index') || str_ends_with($name, 'Show');
            }

            return false;
        });

        $managerRoles = ['MANAGER', 'Manager'];
        foreach ($managerRoles as $roleName) {
            $role = Role::firstOrCreate(['guard_name' => 'api', 'name' => $roleName]);
            $role->syncPermissions($managerPermissions);
        }
    }
}
