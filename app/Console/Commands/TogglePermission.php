<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Grant/revoke a permission on a role from the CLI, so permission gating can be
 * exercised without clicking through the roles screen.
 *
 *   php artisan permission:toggle MANAGER "PurchaseOrder Update" --revoke
 *   php artisan permission:toggle MANAGER "PurchaseOrder Update" --grant
 *   php artisan permission:toggle MANAGER --list
 */
class TogglePermission extends Command
{
    protected $signature = 'permission:toggle
                            {role : Role name, e.g. MANAGER}
                            {permission? : Permission name, e.g. "PurchaseOrder Update"}
                            {--grant : Give the permission to the role}
                            {--revoke : Take the permission away from the role}
                            {--list : Show the role\'s current permissions}';

    protected $description = 'Grant or revoke a single permission on a role (for testing permission gates)';

    public function handle(): int
    {
        $role = Role::where('name', $this->argument('role'))->first();

        if (! $role) {
            $this->error("Role '{$this->argument('role')}' not found. Available: "
                . Role::pluck('name')->implode(', '));
            return self::FAILURE;
        }

        if ($this->option('list')) {
            $names = $role->permissions()->orderBy('name')->pluck('name');
            $this->info("{$role->name} has {$names->count()} permission(s):");
            foreach ($names as $name) {
                $this->line("  {$name}");
            }
            return self::SUCCESS;
        }

        $permissionName = $this->argument('permission');

        if (! $permissionName) {
            $this->error('Pass a permission name, or use --list.');
            return self::FAILURE;
        }

        $permission = Permission::where('name', $permissionName)->first();

        if (! $permission) {
            $this->error("Permission '{$permissionName}' not found.");
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $role->revokePermissionTo($permission);
            $this->warn("REVOKED  {$permission->name}  from  {$role->name}");
        } elseif ($this->option('grant')) {
            $role->givePermissionTo($permission);
            $this->info("GRANTED  {$permission->name}  to  {$role->name}");
        } else {
            $has = $role->hasPermissionTo($permission) ? 'HAS' : 'DOES NOT HAVE';
            $this->line("{$role->name} {$has} '{$permission->name}'. Pass --grant or --revoke to change it.");
            return self::SUCCESS;
        }

        // Spatie caches the permission map; stale cache makes the change look
        // like it did not apply.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->line('Permission cache cleared. Log out and back in so the UI picks up the new list.');

        return self::SUCCESS;
    }
}
