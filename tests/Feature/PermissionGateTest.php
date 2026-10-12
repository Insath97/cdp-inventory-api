<?php

/**
 * Guards the permission strings declared in each controller's middleware()
 * against the names PermissionsSeeder actually seeds.
 *
 * A name that is not seeded is deleted from the permissions table by the
 * seeder, so Spatie denies it for every role — but AppServiceProvider's
 * Gate::before hands "Super Admin" every ability, which hides the breakage
 * unless you test as a lesser role. Every case below therefore runs as a
 * throwaway non-Super-Admin holding exactly the permissions under test.
 */

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** Log in a fresh user whose brand-new role holds exactly $permissions. */
function actingWith(array $permissions): array
{
    $user = User::create([
        'name'      => 'Gate Test User',
        'username'  => 'gate_' . uniqid(),
        'email'     => 'gate_' . uniqid() . '@localhost.test',
        'password'  => bcrypt('password'),
        'user_type' => 'admin',
        'is_active' => true,
        'can_login' => true,
    ]);

    // A new role every time, so no real ADMIN/MANAGER grant is ever touched.
    $role = Role::create(['guard_name' => 'api', 'name' => 'GATETEST_' . uniqid()]);
    foreach ($permissions as $p) {
        $role->givePermissionTo($p);
    }
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return ['Authorization' => 'Bearer ' . auth('api')->login($user)];
}

/** Log in a fresh user carrying an existing role by name (e.g. SUPER ADMIN). */
function actingAsRole(string $roleName): array
{
    $user = User::create([
        'name'      => 'Gate Test ' . $roleName,
        'username'  => 'gate_' . uniqid(),
        'email'     => 'gate_' . uniqid() . '@localhost.test',
        'password'  => bcrypt('password'),
        'user_type' => 'admin',
        'is_active' => true,
        'can_login' => true,
    ]);
    $user->assignRole(Role::where('name', $roleName)->where('guard_name', 'api')->firstOrFail());

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return ['Authorization' => 'Bearer ' . auth('api')->login($user)];
}

/*
|--------------------------------------------------------------------------
| A. Previously broken endpoints now admit the holder of the seeded name
|--------------------------------------------------------------------------
| 403 is the failure. A 404/422 still proves the gate was cleared.
*/

dataset('repaired endpoints', [
    'main categories index'    => ['GET',    '/api/v1/main-categories',         'Main Category Index'],
    'main categories store'    => ['POST',   '/api/v1/main-categories',         'Main Category Create'],
    'main categories update'   => ['PUT',    '/api/v1/main-categories/999999',  'Main Category Update'],
    'main categories destroy'  => ['DELETE', '/api/v1/main-categories/999999',  'Main Category Delete'],

    'sub categories index'     => ['GET',    '/api/v1/sub-categories',          'Sub Category Index'],
    'sub categories store'     => ['POST',   '/api/v1/sub-categories',          'Sub Category Create'],
    'sub categories update'    => ['PUT',    '/api/v1/sub-categories/999999',   'Sub Category Update'],
    'sub categories destroy'   => ['DELETE', '/api/v1/sub-categories/999999',   'Sub Category Delete'],

    'reporting managers index'   => ['GET',    '/api/v1/reporting-managers',        'Reporting Manager Index'],
    'reporting managers store'   => ['POST',   '/api/v1/reporting-managers',        'Reporting Manager Create'],
    'reporting managers update'  => ['PUT',    '/api/v1/reporting-managers/999999', 'Reporting Manager Update'],
    'reporting managers destroy' => ['DELETE', '/api/v1/reporting-managers/999999', 'Reporting Manager Delete'],
]);

it('lets the seeded permission through', function (string $verb, string $url, string $permission) {
    $response = $this->json($verb, $url, [], actingWith([$permission]));

    expect($response->status())->not->toBe(403);
})->with('repaired endpoints');

/*
|--------------------------------------------------------------------------
| B. Regression — the gate still denies someone who lacks the permission
|--------------------------------------------------------------------------
*/

it('still denies a role holding only an unrelated permission', function (string $verb, string $url, string $permission) {
    $response = $this->json($verb, $url, [], actingWith(['Brand Index']));

    expect($response->status())->toBe(403);
})->with('repaired endpoints');

/*
|--------------------------------------------------------------------------
| C. Child item endpoints accept the parent's permission
|--------------------------------------------------------------------------
| The UI only ever gates the parent, then POSTs the child rows right after
| the header, so the parent permission has to satisfy both calls.
*/

dataset('child item endpoints', [
    'stock take items'          => ['/api/v1/stock-take-items',          'StockTake Create'],
    'stock transfer items'      => ['/api/v1/stock-transfer-items',      'StockTransfer Create'],
    'purchase return note items' => ['/api/v1/purchase-return-note-items', 'PurchaseReturnNote Create'],
    'grn items'                 => ['/api/v1/grn-items',                 'Grn Create'],
]);

it('accepts the parent permission on child item creation', function (string $url, string $parentPermission) {
    $response = $this->json('POST', $url, [], actingWith([$parentPermission]));

    expect($response->status())->not->toBe(403);
})->with('child item endpoints');

it('still denies child item creation without the parent permission', function (string $url, string $parentPermission) {
    $response = $this->json('POST', $url, [], actingWith(['Brand Index']));

    expect($response->status())->toBe(403);
})->with('child item endpoints');

/*
|--------------------------------------------------------------------------
| D. Super Admin still bypasses everything via Gate::before
|--------------------------------------------------------------------------
*/

it('keeps Super Admin reaching the repaired endpoints', function (string $verb, string $url, string $permission) {
    $response = $this->json($verb, $url, [], actingAsRole('SUPER ADMIN'));

    expect($response->status())->not->toBe(403);
})->with('repaired endpoints');

/*
|--------------------------------------------------------------------------
| E. The names the controllers used to require are genuinely absent
|--------------------------------------------------------------------------
| If any of these ever come back, the old spelling would start "working"
| again and mask a future regression.
*/

it('has no trace of the pre-fix permission names', function () {
    $stale = \Spatie\Permission\Models\Permission::whereIn('name', [
        'manage main categories', 'view main categories',
        'manage sub categories', 'view sub categories',
        'ReportingManager Index', 'ReportingManager Create',
        'ReportingManager Update', 'ReportingManager Delete',
        'GrnItem Index', 'GrnItem Create', 'GrnItem Update', 'GrnItem Delete',
    ])->pluck('name')->all();

    expect($stale)->toBe([]);
});
