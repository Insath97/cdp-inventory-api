<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReorderLevel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Create dependencies manually to avoid email_verified_at factory issues
    $this->user = User::create([
        'name' => 'Development Admin',
        'username' => 'devadmin_' . uniqid(),
        'email' => 'dev_' . uniqid() . '@localhost.com',
        'password' => bcrypt('password'),
        'user_type' => 'admin',
        'is_active' => true,
        'can_login' => true,
    ]);

    // Assign role with permissions
    $role = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'Super Admin']);
    $permissions = [
        'Reorder Level Index',
        'Reorder Level Create',
        'Reorder Level Update',
        'Reorder Level Delete',
        'Reorder Level Toggle Status',
    ];
    
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'api']);
    }
    
    $role->syncPermissions(Permission::all());
    $this->user->assignRole($role);

    // Login user
    $this->token = auth('api')->login($this->user);
    $this->headers = ['Authorization' => "Bearer {$this->token}"];

    // Create branch
    $this->branch = Branch::create([
        'name' => 'Test Branch',
        'address' => 'Test Address',
        'code_number' => 'BR001_' . uniqid(),
        'branch_code' => 'BRC001_' . uniqid(),
        'email' => 'branch_' . uniqid() . '@test.com',
        'contact_number' => '1234567890',
        'is_active' => true,
    ]);

    // Create product
    $this->product = Product::create([
        'product_name' => 'Test Product',
        'product_code' => 'PRD001_' . uniqid(),
        'is_variant' => false,
        'is_active' => true,
    ]);

    // Create product variant
    $this->productVariant = ProductVariant::create([
        'product_id' => $this->product->id,
        'variant_name' => 'Default Variant',
        'sku' => 'SKU001_' . uniqid(),
        'barcode' => 'BARCODE001_' . uniqid(),
        'is_active' => true,
    ]);
});

it('can fetch reorder levels', function () {
    ReorderLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 10.00,
        'reorder_quantity' => 50.00,
        'is_active' => true,
    ]);

    $response = $this->json('GET', '/api/v1/reorder-levels', [], $this->headers);

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'status',
                 'message',
                 'data' => [
                     'data' => [
                         '*' => [
                             'id',
                             'product_id',
                             'product_variant_id',
                             'branch_id',
                             'min_quantity',
                             'reorder_quantity',
                             'is_active',
                             'product',
                             'product_variant',
                             'branch'
                         ]
                     ]
                 ]
             ]);
});

it('can create a reorder level', function () {
    $payload = [
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 5.50,
        'reorder_quantity' => 20.00,
        'is_active' => true,
    ];

    $response = $this->json('POST', '/api/v1/reorder-levels', $payload, $this->headers);

    $response->assertStatus(201)
             ->assertJsonPath('status', 'success')
             ->assertJsonPath('data.min_quantity', '5.50')
             ->assertJsonPath('data.reorder_quantity', '20.00');

    $this->assertDatabaseHas('reorder_levels', [
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
    ]);
});

it('enforces uniqueness on combinations during creation', function () {
    ReorderLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 10.00,
        'reorder_quantity' => 50.00,
        'is_active' => true,
    ]);

    $payload = [
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 5.50,
        'reorder_quantity' => 20.00,
    ];

    $response = $this->json('POST', '/api/v1/reorder-levels', $payload, $this->headers);

    $response->assertStatus(422);
    expect($response->json('errors')[0]['field'])->toBe('product_id');
});

it('can show a specific reorder level', function () {
    $level = ReorderLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 10.00,
        'reorder_quantity' => 50.00,
        'is_active' => true,
    ]);

    $response = $this->json('GET', "/api/v1/reorder-levels/{$level->id}", [], $this->headers);

    $response->assertStatus(200)
             ->assertJsonPath('data.id', $level->id);
});

it('can update a reorder level', function () {
    $level = ReorderLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 10.00,
        'reorder_quantity' => 50.00,
        'is_active' => true,
    ]);

    $payload = [
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 15.00,
        'reorder_quantity' => 75.00,
        'is_active' => false,
    ];

    $response = $this->json('PUT', "/api/v1/reorder-levels/{$level->id}", $payload, $this->headers);

    $response->assertStatus(200)
             ->assertJsonPath('data.min_quantity', '15.00')
             ->assertJsonPath('data.is_active', false);
});

it('can toggle the active status of a reorder level', function () {
    $level = ReorderLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 10.00,
        'reorder_quantity' => 50.00,
        'is_active' => true,
    ]);

    $response = $this->json('PATCH', "/api/v1/reorder-levels/{$level->id}/toggle-status", [], $this->headers);
    $response->assertStatus(200)
             ->assertJsonPath('data.is_active', false);

    $response2 = $this->json('PATCH', "/api/v1/reorder-levels/{$level->id}/activate", [], $this->headers);
    $response2->assertStatus(200)
             ->assertJsonPath('data.is_active', true);

    $response3 = $this->json('PATCH', "/api/v1/reorder-levels/{$level->id}/deactivate", [], $this->headers);
    $response3->assertStatus(200)
             ->assertJsonPath('data.is_active', false);
});

it('can delete a reorder level', function () {
    $level = ReorderLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->productVariant->id,
        'branch_id' => $this->branch->id,
        'min_quantity' => 10.00,
        'reorder_quantity' => 50.00,
        'is_active' => true,
    ]);

    $response = $this->json('DELETE', "/api/v1/reorder-levels/{$level->id}", [], $this->headers);

    $response->assertStatus(200);
    $this->assertDatabaseMissing('reorder_levels', ['id' => $level->id]);
});
