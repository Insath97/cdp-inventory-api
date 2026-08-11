<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\CheckIn;
use App\Models\CheckOut;
use App\Models\Container;
use App\Models\DamagedRecord;
use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\GrnItemSerial;
use App\Models\MainCategory;
use App\Models\MeasurementUnit;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductAssignment;
use App\Models\ProductReturn;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturnNote;
use App\Models\PurchaseReturnNoteItem;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\StockLedgerService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private Generator $faker;

    private array $userIds = [1, 2, 3, 4];

    /** Populated by seedUnits()/seedMeasurementUnits()/seedContainers()/seedBranches()/seedBrands()/seedCategories(). */
    private array $unitIds = [];
    private array $measurementIds = [];
    private array $containerIds = [];
    private array $branchIds = [];
    private array $branchNames = [];
    private array $brandIds = [];
    /** Each entry: ['main_category_id' => int, 'sub_category_id' => int]. */
    private array $subCategoryPairs = [];

    public function run(): void
    {
        $this->faker = FakerFactory::create();

        // TRUNCATE causes an implicit commit in MySQL, which would break an
        // enclosing DB::transaction() ("There is no active transaction") —
        // so it must run before the transaction starts, not inside it.
        $this->truncateTables();

        DB::transaction(function () {
            $this->seedUnits();
            $this->seedMeasurementUnits();
            $this->seedContainers();
            $this->seedBranches();
            $this->seedBrands();
            $this->seedCategories();

            $suppliers = $this->seedSuppliers();
            ['products' => $products, 'variants' => $variants] = $this->seedProducts($suppliers);
            $purchaseOrders = $this->seedPurchaseOrders($suppliers, $variants);
            $grns = $this->seedGrns($suppliers, $products, $variants, $purchaseOrders);
            $this->seedPurchaseReturnNotes($suppliers, $grns);
            $this->seedPayments($suppliers, $purchaseOrders);
            $this->seedCheckIns($suppliers, $products);
            $this->seedCheckOuts($products);
            $this->seedProductAssignments($products);
            $this->seedProductReturns($products);
            $this->seedStockTransfers($products, $variants);
            $this->seedStockTakes($products, $variants);
            $this->seedDamagedRecords($products, $variants);
        });

        $this->command?->info('Demo data seeded successfully.');
    }

    private function truncateTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'purchase_order_items', 'purchase_orders',
            'grn_item_serials', 'grn_items', 'grns',
            'purchase_return_note_items', 'purchase_return_notes',
            'payments', 'check_ins', 'check_outs',
            'product_assignments', 'product_returns',
            'stock_transfer_items', 'stock_transfers',
            'stock_take_items', 'stock_takes',
            'damaged_records', 'stock_ledger',
            'product_variants', 'products', 'suppliers',
            'containers', 'branches', 'brands', 'units', 'measurement_units',
            'sub_categories', 'main_categories',
        ] as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
    }

    private function randomBranch(): int
    {
        return $this->faker->randomElement($this->branchIds);
    }

    private function randomUser(): int
    {
        return $this->faker->randomElement($this->userIds);
    }

    private function randomUnit(): int
    {
        return $this->faker->randomElement($this->unitIds);
    }

    private function randomContainer(): int
    {
        return $this->faker->randomElement($this->containerIds);
    }

    // ── 0a. Units ────────────────────────────────────────────────────
    private function seedUnits(): void
    {
        $units = [
            ['name' => 'Piece', 'code' => 'Pcs', 'base' => true],
            ['name' => 'Box', 'code' => 'Box', 'base' => false],
            ['name' => 'Kilogram', 'code' => 'Kg', 'base' => false],
            ['name' => 'Litre', 'code' => 'L', 'base' => false],
        ];
        foreach ($units as $u) {
            $unit = Unit::create([
                'unit_name' => $u['name'],
                'slug' => Str::slug($u['name']),
                'short_code' => $u['code'],
                'is_base_unit' => $u['base'],
                'is_active' => true,
                'description' => 'Demo seeded unit.',
            ]);
            $this->unitIds[] = $unit->id;
        }
    }

    // ── 0b. Measurement Units ────────────────────────────────────────
    private function seedMeasurementUnits(): void
    {
        $measurements = [
            ['name' => 'Weight', 'code' => 'kg', 'type' => 'Weight'],
            ['name' => 'Volume', 'code' => 'L', 'type' => 'Volume'],
            ['name' => 'Length', 'code' => 'm', 'type' => 'Length'],
        ];
        foreach ($measurements as $m) {
            $measurement = MeasurementUnit::create([
                'name' => $m['name'],
                'slug' => Str::slug($m['name']),
                'short_code' => $m['code'],
                'type' => $m['type'],
                'is_active' => true,
                'description' => 'Demo seeded measurement unit.',
            ]);
            $this->measurementIds[] = $measurement->id;
        }
    }

    // ── 0c. Containers (needs units + measurement units) ────────────
    private function seedContainers(): void
    {
        $containers = [
            ['name' => 'Box', 'capacity' => 100],
            ['name' => 'Carton', 'capacity' => 50],
            ['name' => 'Pallet', 'capacity' => 500],
            ['name' => 'Crate', 'capacity' => 200],
        ];
        foreach ($containers as $c) {
            $container = Container::create([
                'base_unit_id' => $this->randomUnit(),
                'measurement_unit_id' => $this->faker->randomElement($this->measurementIds),
                'name' => $c['name'],
                'slug' => Str::slug($c['name']),
                'capacity' => $c['capacity'],
                'is_active' => true,
                'description' => 'Demo seeded container.',
            ]);
            $this->containerIds[] = $container->id;
        }
    }

    // ── 0d. Branches ─────────────────────────────────────────────────
    private function seedBranches(): void
    {
        $branches = [
            ['name' => 'Colombo', 'main' => true],
            ['name' => 'Kandy', 'main' => false],
            ['name' => 'Galle', 'main' => false],
            ['name' => 'Jaffna', 'main' => false],
        ];
        foreach ($branches as $i => $b) {
            $branch = Branch::create([
                'name' => $b['name'],
                'address' => $this->faker->streetAddress() . ', ' . $b['name'],
                'branch_code' => 'BR-DEMO-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'email' => 'demo.branch' . ($i + 1) . '@example.com',
                'contact_number' => $this->faker->numerify('+94 7# #######'),
                'is_active' => true,
                'is_main_branch' => $b['main'],
            ]);
            $this->branchIds[] = $branch->id;
            $this->branchNames[] = $branch->name;
        }
    }

    // ── 0e. Brands ───────────────────────────────────────────────────
    private function seedBrands(): void
    {
        $brands = ['Dell', 'HP', 'Samsung', 'Logitech', 'Canon'];
        foreach ($brands as $name) {
            $brand = Brand::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => 'Demo seeded brand.',
                'is_active' => true,
            ]);
            $this->brandIds[] = $brand->id;
        }
    }

    // ── 0f. Main Categories + Sub Categories ─────────────────────────
    private function seedCategories(): void
    {
        $catalog = [
            'Electronics' => ['Laptops', 'Accessories'],
            'Office Supplies' => ['Paper Products', 'Writing Instruments'],
            'Furniture' => ['Seating', 'Storage'],
            'Stationery' => ['Notebooks', 'Organizers'],
        ];

        foreach ($catalog as $mainName => $subNames) {
            $mainCategory = MainCategory::create([
                'name' => $mainName,
                'slug' => Str::slug($mainName),
                'description' => 'Demo seeded main category.',
                'is_active' => true,
                'created_by' => 1,
            ]);

            foreach ($subNames as $subName) {
                $subCategory = SubCategory::create([
                    'main_category_id' => $mainCategory->id,
                    'name' => $subName,
                    'slug' => Str::slug($mainName . '-' . $subName),
                    'description' => 'Demo seeded sub category.',
                    'is_active' => true,
                    'created_by' => 1,
                ]);
                $this->subCategoryPairs[] = [
                    'main_category_id' => $mainCategory->id,
                    'sub_category_id' => $subCategory->id,
                ];
            }
        }
    }

    // ── 1. Suppliers ────────────────────────────────────────────────
    private function seedSuppliers(): array
    {
        $suppliers = [];
        for ($i = 1; $i <= 5; $i++) {
            $suppliers[] = Supplier::create([
                'supplier_name' => $this->faker->company(),
                'supplier_code' => 'SUP-DEMO-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'company_name' => $this->faker->company() . ' Pvt Ltd',
                'contact_person_name' => $this->faker->name(),
                'contact_person_phone' => $this->faker->numerify('+94 7# #######'),
                'address' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => 'Western',
                'country' => 'Sri Lanka',
                'email' => "demo.supplier{$i}@example.com",
                'is_active' => true,
                'description' => 'Demo seeded supplier.',
            ]);
        }
        return $suppliers;
    }

    // ── 2. Products + variants ──────────────────────────────────────
    private function seedProducts(array $suppliers): array
    {
        $catalog = [
            ['name' => 'Dell Latitude Laptop', 'serial' => true],
            ['name' => 'HP EliteBook Laptop', 'serial' => true],
            ['name' => 'External Hard Drive 1TB', 'serial' => true],
            ['name' => 'Wireless Mouse', 'serial' => false],
            ['name' => 'Mechanical Keyboard', 'serial' => false],
            ['name' => 'Office Chair', 'serial' => false],
            ['name' => 'Desk Lamp', 'serial' => false],
            ['name' => 'A4 Paper Ream', 'serial' => false],
            ['name' => 'Whiteboard Marker Pack', 'serial' => false],
            ['name' => 'Stapler', 'serial' => false],
            ['name' => 'USB Flash Drive 64GB', 'serial' => false],
            ['name' => 'Projector', 'serial' => false],
            ['name' => 'Extension Cord', 'serial' => false],
            ['name' => 'Steel File Cabinet', 'serial' => false],
            ['name' => 'Spiral Notebook', 'serial' => false],
        ];

        $products = [];
        $variants = [];
        $i = 0;
        foreach ($catalog as $entry) {
            $i++;
            $productCode = 'PRD-DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $unitId = $this->randomUnit();
            $containerId = $this->randomContainer();
            $categoryPair = $this->faker->randomElement($this->subCategoryPairs);
            $product = Product::create([
                'brand_id' => $this->faker->randomElement($this->brandIds),
                'main_category_id' => $categoryPair['main_category_id'],
                'sub_category_id' => $categoryPair['sub_category_id'],
                'measurement_id' => $this->faker->randomElement($this->measurementIds),
                'unit_id' => $unitId,
                'container_id' => $containerId,
                'supplier_id' => $this->faker->randomElement($suppliers)->id,
                'product_code' => $productCode,
                'product_name' => $entry['name'],
                'slug' => Str::slug($entry['name']) . '-' . $i,
                'description' => "Demo seeded product: {$entry['name']}.",
                'is_variant' => true,
                'is_active' => true,
                'is_default' => false,
                'product_type' => $this->faker->randomElement(['IT', 'Admin']),
                'created_by' => 1,
                'is_pending_setup' => false,
                'track_serial_numbers' => $entry['serial'],
            ]);
            $products[] = $product;

            $variantCount = $this->faker->numberBetween(1, 2);
            $productVariants = [];
            for ($v = 1; $v <= $variantCount; $v++) {
                $productVariants[] = ProductVariant::create([
                    'product_id' => $product->id,
                    'variant_name' => $variantCount > 1 ? $this->faker->randomElement(['Standard', 'Pro', 'Compact']) : 'Standard',
                    'unit_id' => $unitId,
                    'container_id' => $containerId,
                    'sku' => "{$productCode}-{$v}",
                    'code' => (string) $v,
                    'barcode' => 'BC' . str_pad((string) (($i * 10) + $v), 10, '0', STR_PAD_LEFT),
                    'color' => $this->faker->safeColorName(),
                    'is_default' => $v === 1,
                    'is_active' => true,
                ]);
            }
            $variants[$product->id] = $productVariants;
        }

        return ['products' => $products, 'variants' => $variants];
    }

    private function flatVariants(array $variantsByProduct): array
    {
        $flat = [];
        foreach ($variantsByProduct as $productId => $variants) {
            foreach ($variants as $variant) {
                $flat[] = $variant;
            }
        }
        return $flat;
    }

    // ── 3. Purchase Orders + items ──────────────────────────────────
    private function seedPurchaseOrders(array $suppliers, array $variantsByProduct): array
    {
        $flatVariants = $this->flatVariants($variantsByProduct);
        $statuses = ['draft', 'pending', 'approved', 'received', 'completed', 'cancelled'];
        $purchaseOrders = [];

        for ($i = 1; $i <= 8; $i++) {
            $status = $statuses[($i - 1) % count($statuses)];
            $branchId = $this->randomBranch();
            $createdBy = $this->randomUser();

            $po = PurchaseOrder::create([
                'supplier_id' => $this->faker->randomElement($suppliers)->id,
                'branch_id' => $branchId,
                'created_by' => $createdBy,
                'approved_by' => in_array($status, ['approved', 'received', 'completed']) ? $this->randomUser() : null,
                'po_number' => 'PO-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'order_date' => now()->subDays($this->faker->numberBetween(5, 60))->toDateString(),
                'status' => $status,
                'notes' => 'Demo seeded purchase order.',
            ]);

            $itemCount = $this->faker->numberBetween(1, 3);
            $pickedVariants = $this->faker->randomElements($flatVariants, min($itemCount, count($flatVariants)));
            $subtotal = 0;
            foreach ($pickedVariants as $variant) {
                $qty = $this->faker->numberBetween(5, 50);
                $unitCost = $this->faker->randomFloat(2, 10, 500);
                $lineTotal = round($qty * $unitCost, 2);
                $subtotal += $lineTotal;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'variant_id' => $variant->id,
                    'quantity_ordered' => $qty,
                    'quantity_received' => in_array($status, ['received', 'completed']) ? $qty : 0,
                    'quantity_pending' => in_array($status, ['received', 'completed']) ? 0 : $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);
            }

            $po->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);
            $purchaseOrders[] = $po;
        }

        return $purchaseOrders;
    }

    // ── 4. GRNs + items (+ serials for track_serial_numbers products) ──
    private function seedGrns(array $suppliers, array $products, array $variantsByProduct, array $purchaseOrders): array
    {
        $grns = [];

        for ($i = 1; $i <= 10; $i++) {
            $branchId = $this->randomBranch();
            $receivedBy = $this->randomUser();
            $linkToPo = $i <= 3 ? $this->faker->randomElement($purchaseOrders) : null;

            $grn = Grn::create([
                'purchase_order_id' => $linkToPo?->id,
                'supplier_id' => $this->faker->randomElement($suppliers)->id,
                'branch_id' => $branchId,
                'received_by' => $receivedBy,
                'grn_number' => 'GRN-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'received_date' => now()->subDays($this->faker->numberBetween(1, 45))->toDateString(),
                'notes' => 'Demo seeded GRN.',
            ]);
            // Grn::$fillable excludes `status` (app always creates as 'draft')
            // — mark most demo GRNs completed so they actually read as received stock.
            if ($i > 1) {
                $grn->forceFill(['status' => 'completed'])->save();
            }

            $itemCount = $this->faker->numberBetween(1, 3);
            $pickedProducts = $this->faker->randomElements($products, min($itemCount, count($products)));

            foreach ($pickedProducts as $product) {
                $variant = $this->faker->randomElement($variantsByProduct[$product->id]);
                $qty = $this->faker->numberBetween(10, 40);
                $unitPrice = $this->faker->randomFloat(2, 10, 500);
                $unitId = $this->randomUnit();

                $grnItem = GrnItem::create([
                    'grn_id' => $grn->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $unitId,
                    'container_id' => $this->randomContainer(),
                    'quantity_ordered' => $qty,
                    'quantity_received' => $qty,
                    'unit_price' => $unitPrice,
                ]);

                StockLedgerService::recordIn(
                    productId: $product->id,
                    variantId: $variant->id,
                    branchId: $branchId,
                    quantity: (float) $qty,
                    unitId: $unitId,
                    referenceType: Grn::class,
                    referenceId: $grn->id,
                    transactionDate: $grn->received_date->toDateString(),
                    createdBy: $receivedBy,
                );

                if ($product->track_serial_numbers) {
                    for ($s = 1; $s <= $qty; $s++) {
                        GrnItemSerial::create([
                            'grn_item_id' => $grnItem->id,
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'serial_number' => strtoupper('SN' . $grnItem->id . '-' . str_pad((string) $s, 3, '0', STR_PAD_LEFT)),
                        ]);
                    }
                }
            }

            $grns[] = $grn->refresh();
        }

        return $grns;
    }

    // ── 5. Purchase Return Notes + items ────────────────────────────
    private function seedPurchaseReturnNotes(array $suppliers, array $grns): void
    {
        $eligibleGrns = array_slice($grns, 0, 3);

        foreach ($eligibleGrns as $i => $grn) {
            $grnItems = GrnItem::where('grn_id', $grn->id)->get();
            if ($grnItems->isEmpty()) continue;

            $status = $this->faker->randomElement(['draft', 'approved']);
            $prn = PurchaseReturnNote::create([
                'grn_id' => $grn->id,
                'supplier_id' => $grn->supplier_id,
                'branch_id' => $grn->branch_id,
                'created_by' => $this->randomUser(),
                'prn_number' => 'PRN-' . now()->format('YmdHis') . '-' . $this->faker->numberBetween(1000, 9999),
                'return_date' => now()->subDays($this->faker->numberBetween(1, 10))->toDateString(),
                'reason' => $this->faker->randomElement(['Damaged on arrival', 'Wrong item supplied', 'Quality issue']),
                'status' => $status,
            ]);

            foreach ($grnItems->take(2) as $grnItem) {
                $available = StockLedgerService::getBalance($grnItem->product_id, $grn->branch_id);
                $maxReturnable = (int) floor(min($available, $grnItem->quantity_received) * 0.2);
                $qty = max(1, $maxReturnable);
                if ($available <= 0) continue;

                $item = PurchaseReturnNoteItem::create([
                    'purchase_return_note_id' => $prn->id,
                    'grn_item_id' => $grnItem->id,
                    'product_id' => $grnItem->product_id,
                    'product_variant_id' => $grnItem->product_variant_id,
                    'unit_id' => $grnItem->unit_id,
                    'quantity_returned' => $qty,
                    'unit_price' => $grnItem->unit_price,
                ]);

                if ($status === 'approved') {
                    StockLedgerService::assertSufficientStock($item->product_id, $prn->branch_id, (float) $qty, 'Returned Quantity');
                    StockLedgerService::recordOut(
                        productId: $item->product_id,
                        variantId: $item->product_variant_id,
                        branchId: $prn->branch_id,
                        quantity: (float) $qty,
                        unitId: $item->unit_id,
                        referenceType: PurchaseReturnNote::class,
                        referenceId: $prn->id,
                        transactionDate: $prn->return_date->toDateString(),
                        createdBy: $prn->created_by,
                    );
                }
            }
        }
    }

    // ── 6. Payments ──────────────────────────────────────────────────
    private function seedPayments(array $suppliers, array $purchaseOrders): void
    {
        $methods = ['cash', 'bank_transfer', 'cheque'];
        $statuses = ['pending', 'completed', 'cancelled'];

        for ($i = 1; $i <= 10; $i++) {
            $po = $i <= count($purchaseOrders) ? $purchaseOrders[$i - 1] : null;
            Payment::create([
                'supplier_id' => $po?->supplier_id ?? $this->faker->randomElement($suppliers)->id,
                'purchase_order_id' => $po?->id,
                'paid_by' => $this->randomUser(),
                'payment_number' => 'PAY-' . strtoupper(Str::random(8)),
                'payment_date' => now()->subDays($this->faker->numberBetween(1, 40))->toDateString(),
                'amount' => $this->faker->randomFloat(2, 500, 20000),
                'payment_method' => $methods[($i - 1) % count($methods)],
                'reference_number' => strtoupper(Str::random(10)),
                'status' => $statuses[($i - 1) % count($statuses)],
                'notes' => 'Demo seeded payment.',
            ]);
        }
    }

    // ── 7. Check-Ins (transaction record only, no ledger writes) ────
    private function seedCheckIns(array $suppliers, array $products): void
    {
        $statuses = ['pending', 'completed', 'canceled'];
        for ($i = 1; $i <= 10; $i++) {
            $product = $this->faker->randomElement($products);
            CheckIn::create([
                'check_in_no' => (string) Str::uuid(),
                'branch_id' => $this->randomBranch(),
                'container_id' => $this->randomContainer(),
                'product_id' => $product->id,
                'quantity' => $this->faker->numberBetween(1, 20),
                'date' => now()->subDays($this->faker->numberBetween(0, 30))->toDateString(),
                'supplier_id' => $this->faker->randomElement($suppliers)->id,
                'ref_no' => 'REF-' . strtoupper(Str::random(6)),
                'status' => $statuses[($i - 1) % count($statuses)],
                'is_active' => true,
                'description' => 'Demo seeded check-in.',
            ]);
        }
    }

    // ── 8. Check-Outs (transaction record only, no ledger writes) ───
    private function seedCheckOuts(array $products): void
    {
        $statuses = ['pending', 'completed', 'canceled'];
        for ($i = 1; $i <= 10; $i++) {
            $product = $this->faker->randomElement($products);
            CheckOut::create([
                'container_id' => $this->randomContainer(),
                'branch_id' => $this->randomBranch(),
                'product_id' => $product->id,
                'quantity' => $this->faker->numberBetween(1, 15),
                'status' => $statuses[($i - 1) % count($statuses)],
                'checked_out_at' => now()->subDays($this->faker->numberBetween(0, 30)),
                'is_active' => true,
                'description' => 'Demo seeded check-out.',
            ]);
        }
    }

    // ── 9. Product Assignments (no ledger writes) ───────────────────
    private function seedProductAssignments(array $products): void
    {
        $names = ['Nadeesha', 'Kasun', 'Amara', 'Ishara', 'Ruwan', 'Sanduni', 'Tharindu', 'Dilani'];

        for ($i = 1; $i <= 8; $i++) {
            $product = $this->faker->randomElement($products);
            ProductAssignment::create([
                'assignment_code' => 'ASG-DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'person_name' => $names[($i - 1) % count($names)],
                'group_name' => 'IT',
                'branch_name' => $this->faker->randomElement($this->branchNames),
                'department_name' => 'IT',
                // FK actually targets `products.id` despite the column name.
                'product_variant_id' => $product->id,
                'quantity' => $this->faker->numberBetween(1, 3),
                'product_sku' => $product->product_code,
                'product_name' => $product->product_name,
                'issue_date' => now()->subDays($this->faker->numberBetween(0, 20))->toDateString(),
                'remarks' => 'Demo seeded assignment.',
                'is_active' => true,
            ]);
        }
    }

    // ── 10. Product Returns (json line items, no ledger writes) ─────
    private function seedProductReturns(array $products): void
    {
        $names = ['Nadeesha', 'Kasun', 'Amara', 'Ishara', 'Ruwan'];

        for ($i = 1; $i <= 5; $i++) {
            $lineItems = [];
            $picked = $this->faker->randomElements($products, min(2, count($products)));
            foreach ($picked as $product) {
                $lineItems[] = [
                    'product_id' => $product->id,
                    'product_sku' => $product->product_code,
                    'product_name' => $product->product_name,
                    'quantity' => $this->faker->numberBetween(1, 3),
                ];
            }

            ProductReturn::create([
                'return_code' => 'RT-DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'person_name' => $names[($i - 1) % count($names)],
                'group_name' => 'IT',
                'branch_name' => $this->faker->randomElement($this->branchNames),
                'department_name' => 'IT',
                'products' => $lineItems,
                'return_date' => now()->subDays($this->faker->numberBetween(0, 15))->toDateString(),
                'remarks' => 'Demo seeded return.',
            ]);
        }
    }

    // ── 11. Stock Transfers + items ─────────────────────────────────
    private function seedStockTransfers(array $products, array $variantsByProduct): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $from = $this->randomBranch();
            $to = $this->faker->randomElement(array_diff($this->branchIds, [$from]));
            $status = $i <= 3 ? 'approved' : $this->faker->randomElement(['draft', 'cancelled']);
            $requestedBy = $this->randomUser();
            $unitId = $this->randomUnit();

            $transfer = StockTransfer::create([
                'transfer_type' => 'branch_to_branch',
                'from_branch_id' => $from,
                'to_branch_id' => $to,
                'requested_by' => $requestedBy,
                'approved_by' => $status === 'approved' ? $this->randomUser() : null,
                'transfer_number' => 'TRF-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'transfer_date' => now()->subDays($this->faker->numberBetween(0, 20))->toDateString(),
                'status' => $status,
                'notes' => 'Demo seeded stock transfer.',
            ]);

            $product = $this->faker->randomElement($products);
            $variant = $this->faker->randomElement($variantsByProduct[$product->id]);
            $available = StockLedgerService::getBalance($product->id, $from);
            $qty = max(1, (int) floor(min($available, 10) * 0.5));

            if ($available <= 0) continue;

            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'unit_id' => $unitId,
                'quantity_requested' => $qty,
                'quantity_sent' => $status === 'approved' ? $qty : null,
                'quantity_received' => $status === 'approved' ? $qty : null,
            ]);

            if ($status === 'approved') {
                StockLedgerService::assertSufficientStock($product->id, $from, (float) $qty);
                StockLedgerService::recordOut(
                    productId: $product->id,
                    variantId: $variant->id,
                    branchId: $from,
                    quantity: (float) $qty,
                    unitId: $unitId,
                    referenceType: StockTransfer::class,
                    referenceId: $transfer->id,
                    transactionDate: $transfer->transfer_date->toDateString(),
                    createdBy: $transfer->approved_by,
                );
                StockLedgerService::recordIn(
                    productId: $product->id,
                    variantId: $variant->id,
                    branchId: $to,
                    quantity: (float) $qty,
                    unitId: $unitId,
                    referenceType: StockTransfer::class,
                    referenceId: $transfer->id,
                    transactionDate: $transfer->transfer_date->toDateString(),
                    createdBy: $transfer->approved_by,
                );
            }
        }
    }

    // ── 12. Stock Takes + items (no ledger writes — confirmed
    // StockTakeController never calls StockLedgerService) ───────────
    private function seedStockTakes(array $products, array $variantsByProduct): void
    {
        $statuses = ['draft', 'in_progress', 'completed', 'approved'];

        for ($i = 1; $i <= 3; $i++) {
            $branchId = $this->randomBranch();
            $createdBy = $this->randomUser();
            $status = $statuses[($i - 1) % count($statuses)];

            $stockTake = StockTake::create([
                'branch_id' => $branchId,
                'created_by' => $createdBy,
                'approved_by' => $status === 'approved' ? $this->randomUser() : null,
                'take_number' => 'STK-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'take_date' => now()->subDays($this->faker->numberBetween(0, 10))->toDateString(),
                'status' => $status,
                'notes' => 'Demo seeded stock take.',
            ]);

            $itemProducts = $this->faker->randomElements($products, min(4, count($products)));
            foreach ($itemProducts as $product) {
                $variant = $this->faker->randomElement($variantsByProduct[$product->id]);
                $systemQty = StockLedgerService::getBalance($product->id, $branchId);
                $physicalQty = max(0, $systemQty + $this->faker->numberBetween(-2, 2));

                StockTakeItem::create([
                    'stock_take_id' => $stockTake->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $this->randomUnit(),
                    'system_quantity' => $systemQty,
                    'physical_quantity' => $physicalQty,
                ]);
            }
        }
    }

    // ── 13. Damaged Records ─────────────────────────────────────────
    private function seedDamagedRecords(array $products, array $variantsByProduct): void
    {
        $statuses = ['reported', 'approved', 'cancelled'];

        for ($i = 1; $i <= 4; $i++) {
            $product = $this->faker->randomElement($products);
            $variant = $this->faker->randomElement($variantsByProduct[$product->id]);
            $branchId = $this->randomBranch();
            $status = $statuses[($i - 1) % count($statuses)];
            $available = StockLedgerService::getBalance($product->id, $branchId);
            $qty = max(1, (int) floor(min($available, 5) * 0.5));

            if ($status === 'approved' && $available <= 0) {
                $status = 'reported';
            }

            $reportedBy = $this->randomUser();
            $record = DamagedRecord::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'branch_id' => $branchId,
                'reported_by' => $reportedBy,
                'approved_by' => $status === 'approved' ? $this->randomUser() : null,
                'damage_number' => 'DMG-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'damage_date' => now()->subDays($this->faker->numberBetween(0, 15))->toDateString(),
                'quantity' => $qty,
                'reason' => $this->faker->randomElement(['Dropped during handling', 'Water damage', 'Manufacturing defect']),
                'status' => $status,
                'is_active' => true,
            ]);

            if ($status === 'approved') {
                StockLedgerService::assertSufficientStock($record->product_id, $record->branch_id, (float) $qty);
                StockLedgerService::recordOut(
                    productId: $record->product_id,
                    variantId: $record->product_variant_id,
                    branchId: $record->branch_id,
                    quantity: (float) $qty,
                    unitId: null,
                    referenceType: DamagedRecord::class,
                    referenceId: $record->id,
                    transactionDate: $record->damage_date->toDateString(),
                    createdBy: $record->approved_by,
                );
            }
        }
    }
}
