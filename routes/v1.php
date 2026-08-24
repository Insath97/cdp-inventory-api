<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\RoleController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\BranchController;
use App\Http\Controllers\V1\ReportingManagerController;
use App\Http\Controllers\V1\MainCategoryController;
use App\Http\Controllers\V1\SubCategoryController;
use App\Http\Controllers\V1\BrandController;
use App\Http\Controllers\V1\SupplierController;
use App\Http\Controllers\V1\SupplierBankAccountController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\ProductVariantController;
use App\Http\Controllers\V1\ExpiryRecordController;
use App\Http\Controllers\V1\StockTakeController;
use App\Http\Controllers\V1\StockTakeItemController;
use App\Http\Controllers\V1\StockTransferController;
use App\Http\Controllers\V1\StockTransferItemController;
use App\Http\Controllers\V1\ReorderLevelController;
use App\Http\Controllers\V1\DamageRecordController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\PaymentController;
use App\Http\Controllers\V1\InventoryDashboardController;
use App\Http\Controllers\V1\ActivityLogController;
use Illuminate\Support\Facades\Route;

/* public routes */

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
});

/* protected routes */
Route::middleware(['auth:api', 'throttle:300,1'])->prefix('v1')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Profile Management
    Route::put('profile',          [UserController::class, 'updateProfile'])->middleware('throttle:30,1');
    Route::put('profile/password', [UserController::class, 'changePassword'])->middleware('throttle:10,1');

    Route::get('permissions/list', [PermissionController::class, 'getPermissionList']);
    Route::apiResource('permissions', PermissionController::class);
    Route::patch('permissions/{id}/activate', [PermissionController::class, 'activate']);

    Route::get('roles/list/', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);
    Route::patch('roles/{id}/activate', [RoleController::class, 'activate']);
    Route::patch('roles/{id}/deactivate', [RoleController::class, 'deactivate']);

    Route::get('users/list', [UserController::class, 'getList']);
    Route::patch('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Activity Logs
    Route::apiResource('activity-logs', ActivityLogController::class)->only(['index', 'show']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

    // Branches
    Route::apiResource('branches', BranchController::class);
    Route::patch('branches/{id}/toggle-status', [BranchController::class, 'toggleStatus']);
    Route::patch('branches/{id}/activate', [BranchController::class, 'activate']);
    Route::patch('branches/{id}/deactivate', [BranchController::class, 'deactivate']);

    // Reporting managers
    Route::apiResource('reporting-managers', ReportingManagerController::class);
    Route::patch('reporting-managers/{id}/toggle-status', [ReportingManagerController::class, 'toggleStatus']);
    Route::patch('reporting-managers/{id}/activate', [ReportingManagerController::class, 'activate']);
    Route::patch('reporting-managers/{id}/deactivate', [ReportingManagerController::class, 'deactivate']);

    // Main categories
    Route::apiResource('main-categories', MainCategoryController::class);
    Route::patch('main-categories/{id}/toggle-status', [\App\Http\Controllers\V1\MainCategoryController::class, 'toggleStatus']);
    Route::patch('main-categories/{id}/activate', [\App\Http\Controllers\V1\MainCategoryController::class, 'activate']);
    Route::patch('main-categories/{id}/deactivate', [\App\Http\Controllers\V1\MainCategoryController::class, 'deactivate']);

    // Sub categories
    Route::apiResource('sub-categories', SubCategoryController::class);
    Route::patch('sub-categories/{id}/toggle-status', [ \App\Http\Controllers\V1\SubCategoryController::class, 'toggleStatus']);
    Route::patch('sub-categories/{id}/activate', [ \App\Http\Controllers\V1\SubCategoryController::class, 'activate']);
    Route::patch('sub-categories/{id}/deactivate', [ \App\Http\Controllers\V1\SubCategoryController::class, 'deactivate']);

    // Brands
   Route::apiResource('brands', BrandController::class);
   Route::patch('brands/{id}/toggle-status', [ \App\Http\Controllers\V1\BrandController::class, 'toggleStatus']);
   Route::patch('brands/{id}/activate', [ \App\Http\Controllers\V1\BrandController::class, 'activate']);
   Route::patch('brands/{id}/deactivate', [ \App\Http\Controllers\V1\BrandController::class, 'deactivate']);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);
    Route::patch('suppliers/{id}/toggle-status', [\App\Http\Controllers\V1\SupplierController::class, 'toggleStatus']);
    Route::patch('suppliers/{id}/activate', [\App\Http\Controllers\V1\SupplierController::class, 'activate']);
    Route::patch('suppliers/{id}/deactivate', [\App\Http\Controllers\V1\SupplierController::class, 'deactivate']);

    // Supplier Bank Accounts
    Route::apiResource('supplier-bank-accounts', SupplierBankAccountController::class);
    Route::patch('supplier-bank-accounts/{id}/toggle-status', [\App\Http\Controllers\V1\SupplierBankAccountController::class, 'toggleStatus']);
    Route::patch('supplier-bank-accounts/{id}/activate', [\App\Http\Controllers\V1\SupplierBankAccountController::class, 'activate']);
    Route::patch('supplier-bank-accounts/{id}/deactivate', [\App\Http\Controllers\V1\SupplierBankAccountController::class,  'deactivate']);

    // Units
    Route::apiResource('units', \App\Http\Controllers\V1\UnitController::class);

    // Measurement Units
    Route::apiResource('measurement-units', \App\Http\Controllers\V1\MeasurementUnitController::class);
    Route::patch('measurement-units/{id}/toggle-status', [\App\Http\Controllers\V1\MeasurementUnitController::class, 'toggleStatus']);
    Route::patch('measurement-units/{id}/activate', [\App\Http\Controllers\V1\MeasurementUnitController::class, 'activate']);
    Route::patch('measurement-units/{id}/deactivate', [\App\Http\Controllers\V1\MeasurementUnitController::class, 'deactivate']);

    // Containers
    Route::apiResource('containers', \App\Http\Controllers\V1\ContainerController::class);
    Route::patch('containers/{id}/toggle-status', [\App\Http\Controllers\V1\ContainerController::class, 'toggleStatus']);
    Route::patch('containers/{id}/activate', [\App\Http\Controllers\V1\ContainerController::class, 'activate']);
    Route::patch('containers/{id}/deactivate', [\App\Http\Controllers\V1\ContainerController::class, 'deactivate']);

    // CheckIns
    Route::apiResource('check-ins', \App\Http\Controllers\V1\CheckInController::class);
    Route::patch('check-ins/{id}/toggle-status', [\App\Http\Controllers\V1\CheckInController::class, 'toggleStatus']);
    Route::patch('check-ins/{id}/activate', [\App\Http\Controllers\V1\CheckInController::class, 'activate']);
    Route::patch('check-ins/{id}/deactivate', [\App\Http\Controllers\V1\CheckInController::class, 'deactivate']);

    // CheckOuts
    Route::apiResource('check-outs', \App\Http\Controllers\V1\CheckOutController::class);
    Route::patch('check-outs/{id}/toggle-status', [\App\Http\Controllers\V1\CheckOutController::class, 'toggleStatus']);
    Route::patch('check-outs/{id}/activate', [\App\Http\Controllers\V1\CheckOutController::class, 'activate']);
    Route::patch('check-outs/{id}/deactivate', [\App\Http\Controllers\V1\CheckOutController::class, 'deactivate']);

    // Products
    Route::get('products/{id}/details', [ProductController::class, 'lookupDetails']);
    Route::get('products/{id}/serials', [ProductController::class, 'serialsWithAssignments']);
    Route::get('products/{id}/transfers', [ProductController::class, 'transfersForProduct']);
    Route::apiResource('products', ProductController::class);
    Route::patch('products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
    Route::patch('products/{id}/activate', [ProductController::class, 'activate']);
    Route::patch('products/{id}/deactivate', [ProductController::class, 'deactivate']);

    // Product Variants
    Route::apiResource('product-variants', ProductVariantController::class);
    Route::patch('product-variants/{id}/toggle-status', [ProductVariantController::class, 'toggleStatus']);
    Route::patch('product-variants/{id}/activate', [ProductVariantController::class, 'activate']);
    Route::patch('product-variants/{id}/deactivate', [ProductVariantController::class, 'deactivate']);

    // Purchase Orders
    Route::apiResource('purchase-orders', \App\Http\Controllers\V1\PurchaseOrderController::class);
    Route::patch('purchase-orders/{id}/activate', [\App\Http\Controllers\V1\PurchaseOrderController::class, 'activate']);
    Route::patch('purchase-orders/{id}/deactivate', [\App\Http\Controllers\V1\PurchaseOrderController::class, 'deactivate']);

    // GRNs
    Route::apiResource('grns', \App\Http\Controllers\V1\GrnController::class);

    // GRN Items
    Route::get('grn-items/next-serial', [\App\Http\Controllers\V1\GrnItemController::class, 'nextSerial']);
    Route::get('grn-item-serials/search', [\App\Http\Controllers\V1\GrnItemController::class, 'searchAvailableSerials']);
    Route::get('grn-item-serials/resolve', [\App\Http\Controllers\V1\GrnItemController::class, 'resolveSerial']);
    Route::apiResource('grn-items', \App\Http\Controllers\V1\GrnItemController::class);
    Route::patch('grn-items/{id}/activate', [\App\Http\Controllers\V1\GrnItemController::class, 'activate']);
    Route::patch('grn-items/{id}/deactivate', [\App\Http\Controllers\V1\GrnItemController::class, 'deactivate']);

    // Purchase Order Items
    Route::apiResource('purchase-order-items', \App\Http\Controllers\V1\PurchaseOrderItemController::class);
    Route::patch('purchase-order-items/{id}/activate', [\App\Http\Controllers\V1\PurchaseOrderItemController::class, 'activate']);
    Route::patch('purchase-order-items/{id}/deactivate', [\App\Http\Controllers\V1\PurchaseOrderItemController::class, 'deactivate']);

    // Supplier Products
    Route::apiResource('supplier-products', \App\Http\Controllers\V1\SupplierProductsController::class);
    Route::patch('supplier-products/{id}/activate', [\App\Http\Controllers\V1\SupplierProductsController::class, 'activate']);
    Route::patch('supplier-products/{id}/deactivate', [\App\Http\Controllers\V1\SupplierProductsController::class, 'deactivate']);

    // Stock Ledger
    // Registered before the apiResource below so "balance" / "branch-stock" isn't swallowed by the {stock_ledger} show route.
    Route::get('stock-ledgers/balance', [\App\Http\Controllers\V1\StockLedgerController::class, 'balance']);
    Route::get('stock-ledgers/branch-stock', [\App\Http\Controllers\V1\StockLedgerController::class, 'branchStock']);
    Route::apiResource('stock-ledgers', \App\Http\Controllers\V1\StockLedgerController::class);
    Route::patch('stock-ledgers/{id}/activate', [\App\Http\Controllers\V1\StockLedgerController::class, 'activate']);
    Route::patch('stock-ledgers/{id}/deactivate', [\App\Http\Controllers\V1\StockLedgerController::class, 'deactivate']);


    // Money activities ledger (read-only aggregation of all financial events)
    Route::get('money-ledger', [\App\Http\Controllers\V1\MoneyLedgerController::class, 'index']);

    // Bulk data import (System Management → Bulk Upload)
    Route::get('bulk-import/tables', [\App\Http\Controllers\V1\BulkImportController::class, 'index']);
    Route::get('bulk-import/{table}/template', [\App\Http\Controllers\V1\BulkImportController::class, 'template']);
    Route::post('bulk-import/{table}', [\App\Http\Controllers\V1\BulkImportController::class, 'import'])->middleware('throttle:20,1');

    // Database management (System Management → Database Management)
    Route::get('database/overview', [\App\Http\Controllers\V1\DatabaseManagementController::class, 'overview']);
    Route::get('database/backup',   [\App\Http\Controllers\V1\DatabaseManagementController::class, 'backup'])->middleware('throttle:5,1');
    Route::post('database/clear-cache', [\App\Http\Controllers\V1\DatabaseManagementController::class, 'clearCache'])->middleware('throttle:5,1');

    // Expiry Records
    Route::apiResource('expiry-records', ExpiryRecordController::class);
    Route::patch('expiry-records/{id}/activate', [ExpiryRecordController::class, 'activate']);
    Route::patch('expiry-records/{id}/deactivate', [ExpiryRecordController::class, 'deactivate']);


    // Damaged Records
    Route::apiResource('damage-records', DamageRecordController::class);
    Route::patch('damage-records/{id}/activate', [DamageRecordController::class, 'activate']);
    Route::patch('damage-records/{id}/deactivate', [DamageRecordController::class, 'deactivate']);

    // Payments
    Route::apiResource('payments', PaymentController::class);

    // Stock Transfers
    Route::apiResource('stock-transfers', StockTransferController::class);

    //Stock Transfer Items
    Route::apiResource('stock-transfer-items', StockTransferItemController::class);

    // Purchase Return Notes
    Route::apiResource('purchase-return-notes', \App\Http\Controllers\V1\PurchaseReturnNoteController::class);
    Route::patch('purchase-return-notes/{id}/activate', [\App\Http\Controllers\V1\PurchaseReturnNoteController::class, 'activate']);
    Route::patch('purchase-return-notes/{id}/deactivate', [\App\Http\Controllers\V1\PurchaseReturnNoteController::class, 'deactivate']);

    // Purchase Return Note Items
    Route::apiResource('purchase-return-note-items', \App\Http\Controllers\V1\PurchaseReturnNoteItemController::class);
    Route::patch('purchase-return-note-items/{id}/activate', [\App\Http\Controllers\V1\PurchaseReturnNoteItemController::class, 'activate']);
    Route::patch('purchase-return-note-items/{id}/deactivate', [\App\Http\Controllers\V1\PurchaseReturnNoteItemController::class, 'deactivate']);

    // Reorder Levels
    Route::apiResource('reorder-levels', ReorderLevelController::class);
    Route::patch('reorder-levels/{id}/toggle-status', [ReorderLevelController::class, 'toggleStatus']);
    Route::patch('reorder-levels/{id}/activate', [ReorderLevelController::class, 'activate']);
    Route::patch('reorder-levels/{id}/deactivate', [ReorderLevelController::class, 'deactivate']);

    // Inventory Dashboards
    Route::get('inventory-dashboards/stats', [InventoryDashboardController::class, 'getStats']);
    Route::apiResource('inventory-dashboards', InventoryDashboardController::class);
    Route::patch('inventory-dashboards/{id}/toggle-status', [InventoryDashboardController::class, 'toggleStatus']);
    Route::patch('inventory-dashboards/{id}/activate', [InventoryDashboardController::class, 'activate']);
    Route::patch('inventory-dashboards/{id}/deactivate', [InventoryDashboardController::class, 'deactivate']);

    //Branch Requests
    Route::apiResource('branch-requests', \App\Http\Controllers\V1\BranchRequestController::class);
    Route::patch('branch-requests/{id}/toggle-status', [\App\Http\Controllers\V1\BranchRequestController::class, 'toggleStatus']);
    Route::patch('branch-requests/{id}/activate', [\App\Http\Controllers\V1\BranchRequestController::class, 'activate']);
    Route::patch('branch-requests/{id}/deactivate', [\App\Http\Controllers\V1\BranchRequestController::class, 'deactivate']);

    //Product Assignments
    // Registered before the apiResource below so "active" isn't swallowed by the {product_assignment} show route.
    Route::get('product-assignments/active', [\App\Http\Controllers\V1\ProductsAssignmentsController::class, 'activeForPerson']);
    Route::apiResource('product-assignments', \App\Http\Controllers\V1\ProductsAssignmentsController::class);
    Route::patch('product-assignments/{id}/toggle-status', [\App\Http\Controllers\V1\ProductsAssignmentsController::class, 'toggleStatus']);
    Route::patch('product-assignments/{id}/activate', [\App\Http\Controllers\V1\ProductsAssignmentsController::class, 'activate']);
    Route::patch('product-assignments/{id}/deactivate', [\App\Http\Controllers\V1\ProductsAssignmentsController::class, 'deactivate']);

    //Product Returns
    Route::apiResource('product-returns', \App\Http\Controllers\V1\ProductReturnController::class);

    // Stock Takes
    Route::apiResource('stock-takes', \App\Http\Controllers\V1\StockTakeController::class);
    Route::patch('stock-takes/{id}/toggle-status', [\App\Http\Controllers\V1\StockTakeController::class, 'toggleStatus']);
    Route::patch('stock-takes/{id}/activate', [\App\Http\Controllers\V1\StockTakeController::class, 'activate']);
    Route::patch('stock-takes/{id}/deactivate', [\App\Http\Controllers\V1\StockTakeController::class, 'deactivate']);

    // Stock Take Items
    Route::apiResource('stock-take-items', \App\Http\Controllers\V1\StockTakeItemController::class);
    Route::patch('stock-take-items/{id}/toggle-status', [\App\Http\Controllers\V1\StockTakeItemController::class, 'toggleStatus']);
    Route::patch('stock-take-items/{id}/activate', [\App\Http\Controllers\V1\StockTakeItemController::class, 'activate']);
    Route::patch('stock-take-items/{id}/deactivate', [\App\Http\Controllers\V1\StockTakeItemController::class, 'deactivate']);
});
