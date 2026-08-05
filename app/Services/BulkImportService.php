<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class BulkImportService
{
    /**
     * Curated map of importable entities.
     */
    public function tables(): array
    {
        return [
            'branches' => [
                'label' => 'Branches',
                'model' => \App\Models\Branch::class,
                'unique' => 'branch_code',
                'required' => ['name', 'address', 'branch_code'],
                'columns' => ['name', 'address', 'branch_code', 'email', 'contact_number', 'is_active', 'is_main_branch'],
                'bool' => ['is_active', 'is_main_branch'],
            ],
            'brands' => [
                'label' => 'Brands',
                'model' => \App\Models\Brand::class,
                'unique' => 'slug',
                'required' => ['name'],
                'columns' => ['name', 'slug', 'description', 'is_active'],
                'bool' => ['is_active'],
                'slug_from' => ['slug' => 'name'],
            ],
            'main_categories' => [
                'label' => 'Main Categories',
                'model' => \App\Models\MainCategory::class,
                'unique' => 'slug',
                'required' => ['name'],
                'columns' => ['name', 'slug', 'description', 'is_active'],
                'bool' => ['is_active'],
                'slug_from' => ['slug' => 'name'],
            ],
            'sub_categories' => [
                'label' => 'Sub Categories',
                'model' => \App\Models\SubCategory::class,
                'unique' => 'slug',
                'required' => ['main_category_id', 'name'],
                'columns' => ['main_category_id', 'name', 'slug', 'description', 'is_active'],
                'int' => ['main_category_id'],
                'bool' => ['is_active'],
                'slug_from' => ['slug' => 'name'],
            ],
            'units' => [
                'label' => 'Units',
                'model' => \App\Models\Unit::class,
                'unique' => 'short_code',
                'required' => ['unit_name', 'short_code'],
                'columns' => ['unit_name', 'slug', 'short_code', 'is_base_unit', 'is_active', 'description'],
                'bool' => ['is_base_unit', 'is_active'],
                'slug_from' => ['slug' => 'unit_name'],
            ],
            'measurement_units' => [
                'label' => 'Measurement Units',
                'model' => \App\Models\MeasurementUnit::class,
                'unique' => 'short_code',
                'required' => ['name', 'short_code', 'type'],
                'columns' => ['name', 'slug', 'short_code', 'type', 'is_active', 'description'],
                'bool' => ['is_active'],
                'slug_from' => ['slug' => 'name'],
            ],
            'containers' => [
                'label' => 'Containers',
                'model' => \App\Models\Container::class,
                'unique' => 'slug',
                'required' => ['base_unit_id', 'measurement_unit_id', 'name', 'capacity'],
                'columns' => ['base_unit_id', 'measurement_unit_id', 'name', 'slug', 'capacity', 'is_active', 'description'],
                'int' => ['base_unit_id', 'measurement_unit_id'],
                'decimal' => ['capacity'],
                'bool' => ['is_active'],
                'slug_from' => ['slug' => 'name'],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'model' => \App\Models\Supplier::class,
                'unique' => 'supplier_code',
                'required' => ['supplier_name', 'supplier_code', 'company_name', 'contact_person_name', 'contact_person_phone'],
                'columns' => [
                    'supplier_name', 'supplier_code', 'company_name', 'contact_person_name', 'contact_person_phone',
                    'alternate_phone', 'address', 'city', 'state', 'country', 'phone', 'whatsapp', 'email', 'website',
                    'is_active', 'description',
                ],
                'bool' => ['is_active'],
            ],
            'reporting_managers' => [
                'label'      => 'Reporting Managers',
                'model'      => \App\Models\ReportingManager::class,
                'unique'     => 'username',
                'required'   => ['name', 'username', 'email', 'role'],
                'columns'    => ['name', 'username', 'email', 'password', 'role', 'phone', 'is_active', 'can_login'],
                'bool'       => ['is_active', 'can_login'],
                'hash'       => ['password'],
                'default'    => ['password' => 'password'],
                'admin_only' => true,
            ],
            'products' => [
                'label' => 'Products',
                'model' => \App\Models\Product::class,
                'unique' => 'product_code',
                'required' => ['product_code', 'product_name'],
                'columns' => [
                    'product_code', 'product_name', 'slug', 'description', 'product_type',
                    'brand_id', 'main_category_id', 'sub_category_id', 'measurement_id', 'unit_id',
                    'container_id', 'supplier_id', 'is_variant', 'is_active', 'is_default',
                ],
                'int' => ['brand_id', 'main_category_id', 'sub_category_id', 'measurement_id', 'unit_id', 'container_id', 'supplier_id'],
                'bool' => ['is_variant', 'is_active', 'is_default'],
                'slug_from' => ['slug' => 'product_name'],
            ],
            'users' => [
                'label'      => 'Users',
                'model'      => \App\Models\User::class,
                'unique'     => 'email',
                'required'   => ['name', 'username', 'email', 'password', 'user_type'],
                'columns'    => ['name', 'username', 'email', 'password', 'phone', 'date_of_birth', 'alt_phone', 'user_type', 'is_active', 'can_login', 'branch_id', 'reporting_manager_id', 'is_reporting_manager'],
                'bool'       => ['is_active', 'can_login', 'is_reporting_manager'],
                'int'        => ['branch_id', 'reporting_manager_id'],
                'hash'       => ['password'],
                'default'    => ['password' => 'password'],
                'admin_only' => true,
            ],
            'supplier_bank_accounts' => [
                'label' => 'Supplier Bank Accounts',
                'model' => \App\Models\SupplierBankAccount::class,
                'unique' => 'account_number',
                'required' => ['supplier_id', 'bank_name', 'account_name', 'account_number'],
                'columns' => ['supplier_id', 'bank_name', 'account_name', 'account_number', 'branch_name', 'swift_code', 'is_active'],
                'int' => ['supplier_id'],
                'bool' => ['is_active'],
            ],
            'supplier_products' => [
                'label' => 'Supplier Products',
                'model' => \App\Models\SupplierProduct::class,
                'required' => ['supplier_id', 'product_id', 'supplier_product_code', 'supplier_price'],
                'columns' => ['supplier_id', 'product_id', 'product_variant_id', 'supplier_product_code', 'supplier_price', 'lead_time_days', 'is_active'],
                'int' => ['supplier_id', 'product_id', 'product_variant_id', 'lead_time_days'],
                'decimal' => ['supplier_price'],
                'bool' => ['is_active'],
            ],
            'reorder_levels' => [
                'label' => 'Reorder Levels',
                'model' => \App\Models\ReorderLevel::class,
                'required' => ['product_id', 'branch_id', 'min_quantity', 'reorder_quantity'],
                'columns' => ['product_id', 'product_variant_id', 'branch_id', 'min_quantity', 'reorder_quantity', 'is_active'],
                'int' => ['product_id', 'product_variant_id', 'branch_id'],
                'decimal' => ['min_quantity', 'reorder_quantity'],
                'bool' => ['is_active'],
            ],
            'expiry_records' => [
                'label' => 'Expiry Records',
                'model' => \App\Models\ExpiryRecord::class,
                'required' => ['product_id', 'branch_id', 'batch_number', 'expiry_date', 'quantity'],
                'columns' => ['grn_item_id', 'product_id', 'product_variant_id', 'branch_id', 'batch_number', 'expiry_date', 'quantity', 'status', 'is_active'],
                'int' => ['grn_item_id', 'product_id', 'product_variant_id', 'branch_id'],
                'decimal' => ['quantity'],
                'bool' => ['is_active'],
            ],
            'damaged_records' => [
                'label' => 'Damaged Records',
                'model' => \App\Models\DamagedRecord::class,
                'required' => ['product_id', 'branch_id', 'reported_by', 'quantity', 'damage_date'],
                'columns' => ['product_id', 'product_variant_id', 'branch_id', 'reported_by', 'quantity', 'damage_date', 'notes', 'status'],
                'int' => ['product_id', 'product_variant_id', 'branch_id', 'reported_by'],
                'decimal' => ['quantity'],
            ],
            'payments' => [
                'label' => 'Payments',
                'model' => \App\Models\Payment::class,
                'unique' => 'payment_number',
                'required' => ['supplier_id', 'payment_number', 'payment_date', 'amount', 'payment_method'],
                'columns' => ['purchase_order_id', 'supplier_id', 'payment_number', 'payment_date', 'amount', 'payment_method', 'reference_number', 'status', 'notes'],
                'int' => ['purchase_order_id', 'supplier_id'],
                'decimal' => ['amount'],
            ],
            'check_ins' => [
                'label' => 'Check Ins',
                'model' => \App\Models\CheckIn::class,
                'required' => ['product_id', 'branch_id', 'checked_in_by', 'quantity', 'check_in_date'],
                'columns' => ['product_id', 'product_variant_id', 'branch_id', 'checked_in_by', 'quantity', 'check_in_date', 'status', 'notes'],
                'int' => ['product_id', 'product_variant_id', 'branch_id', 'checked_in_by'],
                'decimal' => ['quantity'],
            ],
            'check_outs' => [
                'label' => 'Check Outs',
                'model' => \App\Models\CheckOut::class,
                'required' => ['product_id', 'branch_id', 'checked_out_by', 'quantity', 'check_out_date'],
                'columns' => ['product_id', 'product_variant_id', 'branch_id', 'checked_out_by', 'quantity', 'check_out_date', 'notes'],
                'int' => ['product_id', 'product_variant_id', 'branch_id', 'checked_out_by'],
                'decimal' => ['quantity'],
            ],
            'product_assignments' => [
                'label' => 'Product Assignments',
                'model' => \App\Models\ProductAssignment::class,
                'required' => ['product_id', 'assigned_to', 'assigned_by', 'quantity', 'assignment_date'],
                'columns' => ['product_id', 'product_variant_id', 'assigned_to', 'assigned_by', 'quantity', 'assignment_date', 'status', 'notes'],
                'int' => ['product_id', 'product_variant_id', 'assigned_to', 'assigned_by'],
                'decimal' => ['quantity'],
            ],
            'product_returns' => [
                'label' => 'Product Returns',
                'model' => \App\Models\ProductReturn::class,
                'required' => ['product_assignment_id', 'returned_by', 'quantity', 'return_date'],
                'columns' => ['product_assignment_id', 'returned_by', 'received_by', 'quantity', 'return_date', 'status', 'notes'],
                'int' => ['product_assignment_id', 'returned_by', 'received_by'],
                'decimal' => ['quantity'],
            ],
            'branch_requests' => [
                'label' => 'Branch Requests',
                'model' => \App\Models\BranchRequest::class,
                'unique' => 'request_no',
                'required' => ['branch_id', 'requested_by', 'request_no', 'request_date'],
                'columns' => ['branch_id', 'requested_by', 'request_no', 'request_date', 'status', 'notes'],
                'int' => ['branch_id', 'requested_by'],
            ],
            'purchase_orders' => [
                'label' => 'Purchase Orders',
                'model' => \App\Models\PurchaseOrder::class,
                'grouped' => true,
                'note' => 'Use one row per line item. Rows that share the same po_number are combined into a single purchase order. Items are matched by variant_sku, the supplier by supplier_code and the branch by branch_code. Totals are calculated automatically.',
                'required' => ['po_number', 'supplier_code', 'branch_code', 'order_date', 'variant_sku', 'quantity_ordered', 'unit_cost'],
                'columns' => [
                    'po_number', 'supplier_code', 'branch_code', 'order_date', 'expected_delivery_date',
                    'status', 'payment_method', 'delivery_address', 'notes',
                    'variant_sku', 'quantity_ordered', 'unit_cost', 'tax_rate', 'discount_percentage',
                ],
            ],
            'grns' => [
                'label' => 'GRNs (Goods Received Notes)',
                'model' => \App\Models\Grn::class,
                'grouped' => true,
                'note' => 'Use one row per line item. Rows sharing the same grn_number are combined into a single GRN. Items are matched by variant_sku, the supplier by supplier_code, the branch by branch_code and the receiver by username.',
                'required' => ['grn_number', 'supplier_code', 'branch_code', 'received_date', 'variant_sku', 'quantity_received', 'unit_price'],
                'columns' => [
                    'grn_number', 'po_number', 'supplier_code', 'branch_code', 'received_date', 'status', 'notes',
                    'variant_sku', 'quantity_ordered', 'quantity_received', 'unit_price', 'expiry_date', 'batch_number'
                ],
            ],
            'purchase_return_notes' => [
                'label' => 'Purchase Return Notes',
                'model' => \App\Models\PurchaseReturnNote::class,
                'grouped' => true,
                'note' => 'Use one row per line item. Rows sharing the same prn_number are combined into a single PRN. Items are matched by variant_sku, the supplier by supplier_code, the branch by branch_code and the creator by username.',
                'required' => ['prn_number', 'grn_number', 'supplier_code', 'branch_code', 'return_date', 'variant_sku', 'quantity_returned', 'unit_price'],
                'columns' => [
                    'prn_number', 'grn_number', 'supplier_code', 'branch_code', 'return_date', 'status', 'notes',
                    'variant_sku', 'quantity_returned', 'unit_price', 'reason'
                ],
            ],
            'stock_transfers' => [
                'label' => 'Stock Transfers',
                'model' => \App\Models\StockTransfer::class,
                'grouped' => true,
                'note' => 'Use one row per line item. Rows sharing the same transfer_number are combined into a single transfer. Items are matched by variant_sku, branches by branch_code and employees/users by username.',
                'required' => ['transfer_number', 'transfer_type', 'transfer_date', 'variant_sku', 'quantity_requested'],
                'columns' => [
                    'transfer_number', 'transfer_type', 'from_branch_code', 'to_branch_code', 'from_employee_username', 'to_employee_username',
                    'requested_username', 'approved_username', 'transfer_date', 'status', 'notes',
                    'variant_sku', 'quantity_requested', 'quantity_sent', 'quantity_received'
                ],
            ],
            'stock_takes' => [
                'label' => 'Stock Takes',
                'model' => \App\Models\StockTake::class,
                'grouped' => true,
                'note' => 'Use one row per line item. Rows sharing the same stock_take_number are combined into a single stock take. Items are matched by variant_sku, the branch by branch_code and the creator/approver by username.',
                'required' => ['stock_take_number', 'branch_code', 'conducted_date', 'variant_sku', 'physical_qty', 'system_qty'],
                'columns' => [
                    'stock_take_number', 'branch_code', 'conducted_date', 'status', 'notes',
                    'variant_sku', 'physical_qty', 'system_qty', 'variance'
                ],
            ],
        ];
    }

    /**
     * Perform the actual import execution.
     */
    public function execute(string $filePath, string $table, ?int $userId): array
    {
        $config = $this->tables()[$table] ?? null;

        if (!$config) {
            throw new Exception("Unknown table '{$table}'");
        }

        // Enforce admin-only tables: only Super Admin users may import users/reporting_managers
        if (!empty($config['admin_only'])) {
            $caller = \App\Models\User::find($userId);
            $isSuperAdmin = $caller && $caller->hasRole(['Super Admin', 'SUPER ADMIN']);
            if (!$isSuperAdmin) {
                throw new Exception("Table '{$table}' can only be imported by a Super Admin.");
            }
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("Could not open CSV file at '{$filePath}'");
        }

        // Header row
        $header = fgetcsv($handle, null, ',', '"', '\\');
        if ($header === false || $header === null) {
            fclose($handle);
            throw new Exception("The CSV file is empty.");
        }
        
        $header = array_map(fn ($h) => trim(str_replace("\xEF\xBB\xBF", '', (string) $h)), $header);

        // Grouped parsed tables
        if (!empty($config['grouped'])) {
            if ($table === 'purchase_orders') {
                $result = $this->importPurchaseOrders($handle, $header, $userId);
            } elseif ($table === 'grns') {
                $result = $this->importGrns($handle, $header, $userId);
            } elseif ($table === 'purchase_return_notes') {
                $result = $this->importPurchaseReturnNotes($handle, $header, $userId);
            } elseif ($table === 'stock_transfers') {
                $result = $this->importStockTransfers($handle, $header, $userId);
            } elseif ($table === 'stock_takes') {
                $result = $this->importStockTakes($handle, $header, $userId);
            } else {
                fclose($handle);
                throw new Exception("Grouped parser not implemented for table '{$table}'");
            }
            fclose($handle);
            return $result;
        }

        // Flat tables
        $modelClass = $config['model'];
        $columns = $config['columns'];
        $required = $config['required'] ?? [];
        $bool = $config['bool'] ?? [];
        $int = $config['int'] ?? [];
        $decimal = $config['decimal'] ?? [];
        $hash = $config['hash'] ?? [];
        $slugFrom = $config['slug_from'] ?? [];
        $defaults = $config['default'] ?? [];
        $uniqueKey = $config['unique'] ?? null;

        $imported = 0;
        $failed = 0;
        $rowNumber = 1;
        $errors = [];

        try {
            DB::beginTransaction();

            while (($rowData = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $rowNumber++;

                // Skip blank lines
                if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                // Map columns
                $row = [];
                foreach ($header as $i => $colName) {
                    if (in_array($colName, $columns, true)) {
                        $row[$colName] = isset($rowData[$i]) ? trim((string) $rowData[$i]) : null;
                    }
                }

                // Apply defaults
                foreach ($defaults as $col => $value) {
                    if (empty($row[$col])) {
                        $row[$col] = $value;
                    }
                }

                // Auto slug
                foreach ($slugFrom as $slugCol => $sourceCol) {
                    if (in_array($slugCol, $columns, true) && empty($row[$slugCol]) && !empty($row[$sourceCol])) {
                        $row[$slugCol] = Str::slug($row[$sourceCol]);
                    }
                }

                try {
                    DB::beginTransaction();

                    // Validate required fields
                    $missing = [];
                    foreach ($required as $req) {
                        if (!isset($row[$req]) || $row[$req] === '' || $row[$req] === null) {
                            $missing[] = $req;
                        }
                    }
                    if (!empty($missing)) {
                        throw new Exception("Missing required fields: " . implode(', ', $missing));
                    }

                    // CSV formula-injection neutralisation
                    foreach ($row as $col => $val) {
                        if (is_string($val) && $val !== '' && in_array($val[0], ['=', '+', '-', '@'], true)) {
                            $row[$col] = "'" . $val;
                        }
                    }

                    // Type coercion
                    foreach ($bool as $col) {
                        if (array_key_exists($col, $row)) {
                            $row[$col] = $this->toBool($row[$col]);
                        }
                    }
                    foreach ($int as $col) {
                        if (array_key_exists($col, $row)) {
                            $row[$col] = ($row[$col] === '' || $row[$col] === null) ? null : (int) $row[$col];
                        }
                    }
                    foreach ($decimal as $col) {
                        if (array_key_exists($col, $row)) {
                            $row[$col] = ($row[$col] === '' || $row[$col] === null) ? null : (float) $row[$col];
                        }
                    }
                    foreach ($hash as $col) {
                        if (!empty($row[$col])) {
                            $row[$col] = Hash::make($row[$col]);
                        }
                    }

                    // Persist
                    if ($uniqueKey && !empty($row[$uniqueKey])) {
                        $modelClass::updateOrCreate([$uniqueKey => $row[$uniqueKey]], $row);
                    } else {
                        $modelClass::create($row);
                    }
                    DB::commit();
                    $imported++;
                } catch (\Throwable $rowEx) {
                    DB::rollBack();
                    $failed++;
                    $errors[] = "Row {$rowNumber}: " . $rowEx->getMessage();
                }
            }
        } catch (\Throwable $th) {
            fclose($handle);
            throw new Exception("File processing error: " . $th->getMessage());
        }

        fclose($handle);

        return [
            'total' => $imported + $failed,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function toBool($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function importPurchaseOrders($handle, array $header, ?int $userId): array
    {
        $records = [];
        $line = 1;
        while (($rowData = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $line++;
            if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = ['_line' => $line];
            foreach ($header as $i => $col) {
                $assoc[$col] = isset($rowData[$i]) ? trim((string) $rowData[$i]) : null;
            }
            $records[] = $assoc;
        }

        $supplierCodes = collect($records)->pluck('supplier_code')->filter()->unique()->all();
        $branchCodes = collect($records)->pluck('branch_code')->filter()->unique()->all();
        $variantSkus = collect($records)->pluck('variant_sku')->filter()->unique()->all();

        $suppliers = \App\Models\Supplier::whereIn('supplier_code', $supplierCodes)->get()->keyBy('supplier_code');
        $branches = \App\Models\Branch::whereIn('branch_code', $branchCodes)->get()->keyBy('branch_code');
        $variants = \App\Models\ProductVariant::whereIn('sku', $variantSkus)->get()->keyBy('sku');

        $groups = [];
        foreach ($records as $r) {
            $po = $r['po_number'] ?? '';
            $groups[$po === '' ? '__missing__' : $po][] = $r;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($groups as $poNumber => $rows) {
            $firstLine = $rows[0]['_line'];

            if ($poNumber === '__missing__') {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => 'Rows missing po_number were skipped.'];
                continue;
            }

            if (\App\Models\PurchaseOrder::where('po_number', $poNumber)->exists()) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PO {$poNumber} already exists — skipped."];
                continue;
            }

            $head = $rows[0];
            $supplier = $suppliers->get($head['supplier_code'] ?? '');
            $branch = $branches->get($head['branch_code'] ?? '');

            $problems = [];
            if (empty($head['supplier_code']) || !$supplier) {
                $problems[] = "supplier_code '" . ($head['supplier_code'] ?? '') . "' not found";
            }
            if (empty($head['branch_code']) || !$branch) {
                $problems[] = "branch_code '" . ($head['branch_code'] ?? '') . "' not found";
            }
            if (empty($head['order_date'])) {
                $problems[] = 'order_date is required';
            }
            if (!empty($problems)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PO {$poNumber}: " . implode('; ', $problems)];
                continue;
            }

            $lineRows = [];
            $subtotal = $taxTotal = $discTotal = $grandTotal = 0.0;
            $itemErrors = [];

            foreach ($rows as $it) {
                $variant = $variants->get($it['variant_sku'] ?? '');
                if (empty($it['variant_sku']) || !$variant) {
                    $itemErrors[] = "line {$it['_line']}: variant_sku '" . ($it['variant_sku'] ?? '') . "' not found";
                    continue;
                }
                $qty = (int) ($it['quantity_ordered'] ?? 0);
                $cost = (float) ($it['unit_cost'] ?? 0);
                if ($qty <= 0) {
                    $itemErrors[] = "line {$it['_line']}: quantity_ordered must be greater than 0";
                    continue;
                }
                $tax = (float) ($it['tax_rate'] ?? 0);
                $disc = (float) ($it['discount_percentage'] ?? 0);

                $base = $qty * $cost;
                $lineDisc = $base * $disc / 100;
                $lineTax = ($base - $lineDisc) * $tax / 100;
                $lineTotal = round($base - $lineDisc + $lineTax, 2);

                $subtotal += $base;
                $discTotal += $lineDisc;
                $taxTotal += $lineTax;
                $grandTotal += $lineTotal;

                $lineRows[] = [
                    'variant_id' => $variant->id,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'quantity_pending' => $qty,
                    'unit_cost' => $cost,
                    'tax_rate' => $tax,
                    'discount_percentage' => $disc,
                    'line_total' => $lineTotal,
                    'notes' => $it['notes'] ?? null,
                ];
            }

            if (empty($lineRows)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PO {$poNumber}: no valid items (" . implode('; ', $itemErrors) . ')'];
                continue;
            }

            try {
                DB::transaction(function () use ($poNumber, $head, $supplier, $branch, $userId, $lineRows, $subtotal, $taxTotal, $discTotal, $grandTotal) {
                    $po = \App\Models\PurchaseOrder::create([
                        'supplier_id' => $supplier->id,
                        'branch_id' => $branch->id,
                        'created_by' => $userId,
                        'po_number' => $poNumber,
                        'order_date' => $head['order_date'],
                        'expected_delivery_date' => !empty($head['expected_delivery_date']) ? $head['expected_delivery_date'] : null,
                        'status' => !empty($head['status']) ? $head['status'] : 'draft',
                        'payment_method' => !empty($head['payment_method']) ? $head['payment_method'] : null,
                        'notes' => !empty($head['notes']) ? $head['notes'] : null,
                        'delivery_address' => !empty($head['delivery_address']) ? $head['delivery_address'] : null,
                        'subtotal' => round($subtotal, 2),
                        'tax_amount' => round($taxTotal, 2),
                        'discount_amount' => round($discTotal, 2),
                        'shipping_cost' => 0,
                        'total_amount' => round($grandTotal, 2),
                        'amount_paid' => 0,
                        'amount_due' => round($grandTotal, 2),
                    ]);
                    $po->items()->createMany($lineRows);
                });

                $imported++;
                if (!empty($itemErrors)) {
                    $errors[] = ['row' => $firstLine, 'message' => "PO {$poNumber} imported, but some items were skipped: " . implode('; ', $itemErrors)];
                }
            } catch (\Throwable $th) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PO {$poNumber}: " . $th->getMessage()];
            }
        }

        return [
            'total' => $imported + $failed,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    private function importGrns($handle, array $header, ?int $userId): array
    {
        $records = [];
        $line = 1;
        while (($rowData = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $line++;
            if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = ['_line' => $line];
            foreach ($header as $i => $col) {
                $assoc[$col] = isset($rowData[$i]) ? trim((string) $rowData[$i]) : null;
            }
            $records[] = $assoc;
        }

        $grnNumbers = collect($records)->pluck('grn_number')->filter()->unique()->all();
        $poNumbers = collect($records)->pluck('po_number')->filter()->unique()->all();
        $supplierCodes = collect($records)->pluck('supplier_code')->filter()->unique()->all();
        $branchCodes = collect($records)->pluck('branch_code')->filter()->unique()->all();
        $variantSkus = collect($records)->pluck('variant_sku')->filter()->unique()->all();

        $suppliers = \App\Models\Supplier::whereIn('supplier_code', $supplierCodes)->get()->keyBy('supplier_code');
        $branches = \App\Models\Branch::whereIn('branch_code', $branchCodes)->get()->keyBy('branch_code');
        $variants = \App\Models\ProductVariant::with('product')->whereIn('sku', $variantSkus)->get()->keyBy('sku');
        $pos = \App\Models\PurchaseOrder::whereIn('po_number', $poNumbers)->get()->keyBy('po_number');

        $groups = [];
        foreach ($records as $r) {
            $grnNum = $r['grn_number'] ?? '';
            $groups[$grnNum === '' ? '__missing__' : $grnNum][] = $r;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($groups as $grnNumber => $rows) {
            $firstLine = $rows[0]['_line'];

            if ($grnNumber === '__missing__') {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => 'Rows missing grn_number were skipped.'];
                continue;
            }

            if (\App\Models\Grn::where('grn_number', $grnNumber)->exists()) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "GRN {$grnNumber} already exists — skipped."];
                continue;
            }

            $head = $rows[0];
            $supplier = $suppliers->get($head['supplier_code'] ?? '');
            $branch = $branches->get($head['branch_code'] ?? '');
            $po = !empty($head['po_number']) ? $pos->get($head['po_number']) : null;

            $problems = [];
            if (empty($head['supplier_code']) || !$supplier) {
                $problems[] = "supplier_code '" . ($head['supplier_code'] ?? '') . "' not found";
            }
            if (empty($head['branch_code']) || !$branch) {
                $problems[] = "branch_code '" . ($head['branch_code'] ?? '') . "' not found";
            }
            if (empty($head['received_date'])) {
                $problems[] = 'received_date is required';
            }
            if (!empty($problems)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "GRN {$grnNumber}: " . implode('; ', $problems)];
                continue;
            }

            $lineRows = [];
            $itemErrors = [];

            foreach ($rows as $it) {
                $variant = $variants->get($it['variant_sku'] ?? '');
                if (empty($it['variant_sku']) || !$variant) {
                    $itemErrors[] = "line {$it['_line']}: variant_sku '" . ($it['variant_sku'] ?? '') . "' not found";
                    continue;
                }
                $qtyOrdered = (float) ($it['quantity_ordered'] ?? 0);
                $qtyReceived = (float) ($it['quantity_received'] ?? 0);
                $price = (float) ($it['unit_price'] ?? 0);

                if ($qtyReceived <= 0) {
                    $itemErrors[] = "line {$it['_line']}: quantity_received must be greater than 0";
                    continue;
                }

                $lineRows[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $variant->product->unit_id ?? optional(\App\Models\Unit::first())->id,
                    'quantity_ordered' => $qtyOrdered,
                    'quantity_received' => $qtyReceived,
                    'unit_price' => $price,
                    'expiry_date' => !empty($it['expiry_date']) ? $it['expiry_date'] : null,
                    'batch_number' => !empty($it['batch_number']) ? $it['batch_number'] : null,
                ];
            }

            if (empty($lineRows)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "GRN {$grnNumber}: no valid items (" . implode('; ', $itemErrors) . ')'];
                continue;
            }

            try {
                DB::transaction(function () use ($grnNumber, $head, $supplier, $branch, $po, $userId, $lineRows) {
                    $status = !empty($head['status']) ? strtolower($head['status']) : 'draft';
                    $grn = \App\Models\Grn::create([
                        'purchase_order_id' => $po?->id,
                        'supplier_id' => $supplier->id,
                        'branch_id' => $branch->id,
                        'received_by' => $userId,
                        'grn_number' => $grnNumber,
                        'received_date' => $head['received_date'],
                        'status' => $status,
                        'notes' => !empty($head['notes']) ? $head['notes'] : null,
                    ]);

                    foreach ($lineRows as $lineItem) {
                        $item = $grn->items()->create($lineItem);

                        if ($status === 'received') {
                            \App\Services\StockLedgerService::recordIn(
                                productId:       $item->product_id,
                                variantId:       $item->product_variant_id,
                                branchId:        $grn->branch_id,
                                quantity:        (float) $item->quantity_received,
                                unitId:          $item->unit_id,
                                referenceType:   \App\Models\Grn::class,
                                referenceId:     $grn->id,
                                transactionDate: $grn->received_date,
                                createdBy:       $userId,
                            );

                            if ($item->expiry_date) {
                                \App\Models\ExpiryRecord::create([
                                    'grn_item_id'        => $item->id,
                                    'product_id'         => $item->product_id,
                                    'product_variant_id' => $item->product_variant_id,
                                    'branch_id'          => $grn->branch_id,
                                    'batch_number'       => $item->batch_number,
                                    'expiry_date'        => $item->expiry_date,
                                    'quantity'           => (float) $item->quantity_received,
                                    'status'             => 'active',
                                    'is_active'          => true,
                                ]);
                            }
                        }
                    }
                });

                $imported++;
            } catch (\Throwable $th) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "GRN {$grnNumber}: " . $th->getMessage()];
            }
        }

        return [
            'total' => $imported + $failed,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    private function importPurchaseReturnNotes($handle, array $header, ?int $userId): array
    {
        $records = [];
        $line = 1;
        while (($rowData = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $line++;
            if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = ['_line' => $line];
            foreach ($header as $i => $col) {
                $assoc[$col] = isset($rowData[$i]) ? trim((string) $rowData[$i]) : null;
            }
            $records[] = $assoc;
        }

        $prnNumbers = collect($records)->pluck('prn_number')->filter()->unique()->all();
        $grnNumbers = collect($records)->pluck('grn_number')->filter()->unique()->all();
        $supplierCodes = collect($records)->pluck('supplier_code')->filter()->unique()->all();
        $branchCodes = collect($records)->pluck('branch_code')->filter()->unique()->all();
        $variantSkus = collect($records)->pluck('variant_sku')->filter()->unique()->all();

        $suppliers = \App\Models\Supplier::whereIn('supplier_code', $supplierCodes)->get()->keyBy('supplier_code');
        $branches = \App\Models\Branch::whereIn('branch_code', $branchCodes)->get()->keyBy('branch_code');
        $variants = \App\Models\ProductVariant::with('product')->whereIn('sku', $variantSkus)->get()->keyBy('sku');
        $grns = \App\Models\Grn::whereIn('grn_number', $grnNumbers)->get()->keyBy('grn_number');

        $groups = [];
        foreach ($records as $r) {
            $prnNum = $r['prn_number'] ?? '';
            $groups[$prnNum === '' ? '__missing__' : $prnNum][] = $r;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($groups as $prnNumber => $rows) {
            $firstLine = $rows[0]['_line'];

            if ($prnNumber === '__missing__') {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => 'Rows missing prn_number were skipped.'];
                continue;
            }

            if (\App\Models\PurchaseReturnNote::where('prn_number', $prnNumber)->exists()) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PRN {$prnNumber} already exists — skipped."];
                continue;
            }

            $head = $rows[0];
            $supplier = $suppliers->get($head['supplier_code'] ?? '');
            $branch = $branches->get($head['branch_code'] ?? '');
            $grn = !empty($head['grn_number']) ? $grns->get($head['grn_number']) : null;

            $problems = [];
            if (empty($head['supplier_code']) || !$supplier) {
                $problems[] = "supplier_code '" . ($head['supplier_code'] ?? '') . "' not found";
            }
            if (empty($head['branch_code']) || !$branch) {
                $problems[] = "branch_code '" . ($head['branch_code'] ?? '') . "' not found";
            }
            if (empty($head['return_date'])) {
                $problems[] = 'return_date is required';
            }
            if (!empty($problems)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PRN {$prnNumber}: " . implode('; ', $problems)];
                continue;
            }

            $lineRows = [];
            $itemErrors = [];

            foreach ($rows as $it) {
                $variant = $variants->get($it['variant_sku'] ?? '');
                if (empty($it['variant_sku']) || !$variant) {
                    $itemErrors[] = "line {$it['_line']}: variant_sku '" . ($it['variant_sku'] ?? '') . "' not found";
                    continue;
                }
                $qtyReturned = (float) ($it['quantity_returned'] ?? 0);
                $price = (float) ($it['unit_price'] ?? 0);

                if ($qtyReturned <= 0) {
                    $itemErrors[] = "line {$it['_line']}: quantity_returned must be greater than 0";
                    continue;
                }

                $lineRows[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $variant->product->unit_id ?? optional(\App\Models\Unit::first())->id,
                    'quantity_returned' => $qtyReturned,
                    'unit_price' => $price,
                    'reason' => !empty($it['reason']) ? $it['reason'] : null,
                ];
            }

            if (empty($lineRows)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PRN {$prnNumber}: no valid items (" . implode('; ', $itemErrors) . ')'];
                continue;
            }

            try {
                DB::transaction(function () use ($prnNumber, $head, $supplier, $branch, $grn, $userId, $lineRows) {
                    $status = !empty($head['status']) ? strtolower($head['status']) : 'draft';
                    $prn = \App\Models\PurchaseReturnNote::create([
                        'grn_id' => $grn?->id,
                        'supplier_id' => $supplier->id,
                        'branch_id' => $branch->id,
                        'created_by' => $userId,
                        'prn_number' => $prnNumber,
                        'return_date' => $head['return_date'],
                        'status' => $status,
                        'notes' => !empty($head['notes']) ? $head['notes'] : null,
                    ]);

                    foreach ($lineRows as $lineItem) {
                        $item = $prn->items()->create($lineItem);

                        \App\Services\StockLedgerService::assertSufficientStock(
                            $item->product_id,
                            $prn->branch_id,
                            (float) $item->quantity_returned
                        );

                        \App\Services\StockLedgerService::recordOut(
                            productId:       $item->product_id,
                            variantId:       $item->product_variant_id,
                            branchId:        $prn->branch_id,
                            quantity:        (float) $item->quantity_returned,
                            unitId:          $item->unit_id,
                            referenceType:   \App\Models\PurchaseReturnNote::class,
                            referenceId:     $prn->id,
                            transactionDate: $prn->return_date,
                            createdBy:       $userId,
                        );
                    }
                });

                $imported++;
            } catch (\Throwable $th) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "PRN {$prnNumber}: " . $th->getMessage()];
            }
        }

        return [
            'total' => $imported + $failed,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    private function importStockTransfers($handle, array $header, ?int $userId): array
    {
        $records = [];
        $line = 1;
        while (($rowData = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $line++;
            if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = ['_line' => $line];
            foreach ($header as $i => $col) {
                $assoc[$col] = isset($rowData[$i]) ? trim((string) $rowData[$i]) : null;
            }
            $records[] = $assoc;
        }

        $branchCodes = collect($records)->pluck('from_branch_code')->merge(collect($records)->pluck('to_branch_code'))->filter()->unique()->all();
        $usernames = collect($records)->pluck('from_employee_username')
            ->merge(collect($records)->pluck('to_employee_username'))
            ->merge(collect($records)->pluck('requested_username'))
            ->merge(collect($records)->pluck('approved_username'))
            ->filter()->unique()->all();
        $variantSkus = collect($records)->pluck('variant_sku')->filter()->unique()->all();

        $branches = \App\Models\Branch::whereIn('branch_code', $branchCodes)->get()->keyBy('branch_code');
        $users = \App\Models\User::whereIn('username', $usernames)->get()->keyBy('username');
        $variants = \App\Models\ProductVariant::with('product')->whereIn('sku', $variantSkus)->get()->keyBy('sku');

        $groups = [];
        foreach ($records as $r) {
            $tfNum = $r['transfer_number'] ?? '';
            $groups[$tfNum === '' ? '__missing__' : $tfNum][] = $r;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($groups as $transferNumber => $rows) {
            $firstLine = $rows[0]['_line'];

            if ($transferNumber === '__missing__') {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => 'Rows missing transfer_number were skipped.'];
                continue;
            }

            if (\App\Models\StockTransfer::where('transfer_number', $transferNumber)->exists()) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Transfer {$transferNumber} already exists — skipped."];
                continue;
            }

            $head = $rows[0];
            $fromBranch = !empty($head['from_branch_code']) ? $branches->get($head['from_branch_code']) : null;
            $toBranch = !empty($head['to_branch_code']) ? $branches->get($head['to_branch_code']) : null;

            $fromEmployee = !empty($head['from_employee_username']) ? $users->get($head['from_employee_username']) : null;
            $toEmployee = !empty($head['to_employee_username']) ? $users->get($head['to_employee_username']) : null;

            $requester = !empty($head['requested_username']) ? $users->get($head['requested_username']) : null;
            $approver = !empty($head['approved_username']) ? $users->get($head['approved_username']) : null;

            $problems = [];
            if (empty($head['transfer_type'])) {
                $problems[] = 'transfer_type is required';
            }
            if (empty($head['transfer_date'])) {
                $problems[] = 'transfer_date is required';
            }
            if (!empty($problems)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Transfer {$transferNumber}: " . implode('; ', $problems)];
                continue;
            }

            $lineRows = [];
            $itemErrors = [];

            foreach ($rows as $it) {
                $variant = $variants->get($it['variant_sku'] ?? '');
                if (empty($it['variant_sku']) || !$variant) {
                    $itemErrors[] = "line {$it['_line']}: variant_sku '" . ($it['variant_sku'] ?? '') . "' not found";
                    continue;
                }
                $qtyReq = (float) ($it['quantity_requested'] ?? 0);
                $qtySent = isset($it['quantity_sent']) ? (float) $it['quantity_sent'] : null;
                $qtyRec = isset($it['quantity_received']) ? (float) $it['quantity_received'] : null;

                if ($qtyReq <= 0) {
                    $itemErrors[] = "line {$it['_line']}: quantity_requested must be greater than 0";
                    continue;
                }

                $lineRows[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $variant->product->unit_id ?? optional(\App\Models\Unit::first())->id,
                    'quantity_requested' => $qtyReq,
                    'quantity_sent' => $qtySent,
                    'quantity_received' => $qtyRec,
                ];
            }

            if (empty($lineRows)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Transfer {$transferNumber}: no valid items (" . implode('; ', $itemErrors) . ')'];
                continue;
            }

            try {
                DB::transaction(function () use ($transferNumber, $head, $fromBranch, $toBranch, $fromEmployee, $toEmployee, $requester, $approver, $userId, $lineRows) {
                    $status = !empty($head['status']) ? strtolower($head['status']) : 'draft';
                    $stockTransfer = \App\Models\StockTransfer::create([
                        'transfer_type' => $head['transfer_type'],
                        'from_branch_id' => $fromBranch?->id,
                        'to_branch_id' => $toBranch?->id,
                        'from_employee_id' => $fromEmployee?->id,
                        'to_employee_id' => $toEmployee?->id,
                        'requested_by' => $requester?->id ?? $userId,
                        'approved_by' => $approver?->id,
                        'transfer_number' => $transferNumber,
                        'transfer_date' => $head['transfer_date'],
                        'status' => $status,
                        'notes' => !empty($head['notes']) ? $head['notes'] : null,
                    ]);

                    $sentStatuses = ['approved', 'in_transit', 'received'];
                    $isSent = in_array($status, $sentStatuses, true);
                    $createdBy = $stockTransfer->approved_by ?? $stockTransfer->requested_by;

                    foreach ($lineRows as $lineItem) {
                        $item = $stockTransfer->items()->create($lineItem);

                        if ($isSent && $stockTransfer->from_branch_id) {
                            $qtyOut = (float) ($item->quantity_sent ?? $item->quantity_requested ?? 0);
                            if ($qtyOut > 0) {
                                \App\Services\StockLedgerService::assertSufficientStock(
                                    $item->product_id,
                                    $stockTransfer->from_branch_id,
                                    $qtyOut
                                );

                                \App\Services\StockLedgerService::recordOut(
                                    productId:       $item->product_id,
                                    variantId:       $item->product_variant_id,
                                    branchId:        $stockTransfer->from_branch_id,
                                    quantity:        $qtyOut,
                                    unitId:          $item->unit_id,
                                    referenceType:   \App\Models\StockTransfer::class,
                                    referenceId:     $stockTransfer->id,
                                    transactionDate: $stockTransfer->transfer_date,
                                    createdBy:       $createdBy,
                                );
                            }
                        }

                        if ($status === 'received' && $stockTransfer->to_branch_id) {
                            $qtyIn = (float) ($item->quantity_received ?? $item->quantity_sent ?? $item->quantity_requested ?? 0);
                            if ($qtyIn > 0) {
                                \App\Services\StockLedgerService::recordIn(
                                    productId:       $item->product_id,
                                    variantId:       $item->product_variant_id,
                                    branchId:        $stockTransfer->to_branch_id,
                                    quantity:        $qtyIn,
                                    unitId:          $item->unit_id,
                                    referenceType:   \App\Models\StockTransfer::class,
                                    referenceId:     $stockTransfer->id,
                                    transactionDate: $stockTransfer->transfer_date,
                                    createdBy:       $createdBy,
                                );
                            }
                        }
                    }
                });

                $imported++;
            } catch (\Throwable $th) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Transfer {$transferNumber}: " . $th->getMessage()];
            }
        }

        return [
            'total' => $imported + $failed,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    private function importStockTakes($handle, array $header, ?int $userId): array
    {
        $records = [];
        $line = 1;
        while (($rowData = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $line++;
            if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = ['_line' => $line];
            foreach ($header as $i => $col) {
                $assoc[$col] = isset($rowData[$i]) ? trim((string) $rowData[$i]) : null;
            }
            $records[] = $assoc;
        }

        $branchCodes = collect($records)->pluck('branch_code')->filter()->unique()->all();
        $variantSkus = collect($records)->pluck('variant_sku')->filter()->unique()->all();

        $branches = \App\Models\Branch::whereIn('branch_code', $branchCodes)->get()->keyBy('branch_code');
        $variants = \App\Models\ProductVariant::with('product')->whereIn('sku', $variantSkus)->get()->keyBy('sku');

        $groups = [];
        foreach ($records as $r) {
            $stNum = $r['stock_take_number'] ?? '';
            $groups[$stNum === '' ? '__missing__' : $stNum][] = $r;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($groups as $stockTakeNumber => $rows) {
            $firstLine = $rows[0]['_line'];

            if ($stockTakeNumber === '__missing__') {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => 'Rows missing stock_take_number were skipped.'];
                continue;
            }

            if (\App\Models\StockTake::where('stock_take_number', $stockTakeNumber)->exists()) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Stock Take {$stockTakeNumber} already exists — skipped."];
                continue;
            }

            $head = $rows[0];
            $branch = !empty($head['branch_code']) ? $branches->get($head['branch_code']) : null;

            $problems = [];
            if (empty($head['branch_code']) || !$branch) {
                $problems[] = "branch_code '" . ($head['branch_code'] ?? '') . "' not found";
            }
            if (empty($head['conducted_date'])) {
                $problems[] = 'conducted_date is required';
            }
            if (!empty($problems)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Stock Take {$stockTakeNumber}: " . implode('; ', $problems)];
                continue;
            }

            $lineRows = [];
            $itemErrors = [];

            foreach ($rows as $it) {
                $variant = $variants->get($it['variant_sku'] ?? '');
                if (empty($it['variant_sku']) || !$variant) {
                    $itemErrors[] = "line {$it['_line']}: variant_sku '" . ($it['variant_sku'] ?? '') . "' not found";
                    continue;
                }
                $physical = (float) ($it['physical_qty'] ?? 0);
                $system = (float) ($it['system_qty'] ?? 0);
                $variance = isset($it['variance']) ? (float) $it['variance'] : ($physical - $system);

                $lineRows[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $variant->product->unit_id ?? optional(\App\Models\Unit::first())->id,
                    'physical_qty' => $physical,
                    'system_qty' => $system,
                    'variance' => $variance,
                ];
            }

            if (empty($lineRows)) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Stock Take {$stockTakeNumber}: no valid items (" . implode('; ', $itemErrors) . ')'];
                continue;
            }

            try {
                DB::transaction(function () use ($stockTakeNumber, $head, $branch, $userId, $lineRows) {
                    $status = !empty($head['status']) ? strtolower($head['status']) : 'pending';
                    $stockTake = \App\Models\StockTake::create([
                        'branch_id' => $branch->id,
                        'created_by' => $userId,
                        'approved_by' => $status === 'approved' ? $userId : null,
                        'stock_take_number' => $stockTakeNumber,
                        'conducted_date' => $head['conducted_date'],
                        'status' => $status,
                        'notes' => !empty($head['notes']) ? $head['notes'] : null,
                    ]);

                    foreach ($lineRows as $lineItem) {
                        $item = $stockTake->items()->create($lineItem);

                        if ($status === 'approved' && $item->variance != 0) {
                            $lastBalance = \App\Models\StockLedger::query()
                                ->where('product_id', $item->product_id)
                                ->where('product_variant_id', $item->product_variant_id)
                                ->where('branch_id', $stockTake->branch_id)
                                ->latest('id')
                                ->value('balance') ?? 0;

                            $qtyIn = $item->variance > 0 ? $item->variance : 0;
                            $qtyOut = $item->variance < 0 ? abs($item->variance) : 0;
                            $newBalance = $lastBalance + $item->variance;

                            \App\Models\StockLedger::create([
                                'product_id' => $item->product_id,
                                'product_variant_id' => $item->product_variant_id,
                                'branch_id' => $stockTake->branch_id,
                                'reference_type' => \App\Models\StockTake::class,
                                'reference_id' => $stockTake->id,
                                'transaction_date' => $stockTake->conducted_date,
                                'quantity_in' => $qtyIn,
                                'quantity_out' => $qtyOut,
                                'balance' => $newBalance,
                                'unit_id' => $item->unit_id,
                                'created_by' => $userId,
                            ]);
                        }
                    }
                });

                $imported++;
            } catch (\Throwable $th) {
                $failed++;
                $errors[] = ['row' => $firstLine, 'message' => "Stock Take {$stockTakeNumber}: " . $th->getMessage()];
            }
        }

        return [
            'total' => $imported + $failed,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ];
    }
}
