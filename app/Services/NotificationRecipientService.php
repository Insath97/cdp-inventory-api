<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ReportingManager;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipientService
{
    /**
     * Resolve the creator's reporting manager as a notifiable User, if one exists.
     *
     * `reporting_manager_id` points at the standalone `reporting_managers`
     * directory table, not a User — so we look up the User account matching
     * that entry's email/username. Falls back to `parent_user_id` (a direct
     * User-to-User hierarchy link) if that doesn't resolve. Only returns the
     * manager if they actually hold the relevant permission (or an admin/
     * manager role), so we don't notify someone who can't act on it.
     */
    public function reportingManagerOf(?User $creator, array $relevantPermissions = []): ?User
    {
        if (!$creator) {
            return null;
        }

        $reportingManagerUser = null;

        if (!empty($creator->reporting_manager_id)) {
            $rmModel = ReportingManager::find($creator->reporting_manager_id);
            if ($rmModel) {
                $reportingManagerUser = User::where('email', $rmModel->email)
                    ->orWhere('username', $rmModel->username)
                    ->first();
            } else {
                $reportingManagerUser = User::find($creator->reporting_manager_id);
            }
        }

        if (!$reportingManagerUser && !empty($creator->parent_user_id)) {
            $reportingManagerUser = User::find($creator->parent_user_id);
        }

        if (!$reportingManagerUser) {
            return null;
        }

        // No permission filter asked for: the manager is the intended recipient
        // whatever they can do in the app. Notifications now go to the actor and
        // their manager and nobody else, so gating on permissions here would
        // drop the notification entirely rather than merely narrowing it.
        if (empty($relevantPermissions)) {
            return $reportingManagerUser;
        }

        // hasAnyPermission() throws PermissionDoesNotExist if any name in the
        // list was never seeded (a typo elsewhere shouldn't silently kill the
        // whole notification), so guard it and fall through to the role check.
        $hasPermission = false;
        if (!empty($relevantPermissions)) {
            try {
                $hasPermission = $reportingManagerUser->hasAnyPermission($relevantPermissions);
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                $hasPermission = false;
            }
        }

        $hasPermission = $hasPermission
            || $reportingManagerUser->hasAnyRole(['SUPER ADMIN', 'ADMIN', 'Super Admin', 'Admin', 'MANAGER', 'Manager']);

        return $hasPermission ? $reportingManagerUser : null;
    }
    /**
     * The only two people an event notification reaches: the user who performed
     * it -- so they see their own action confirmed -- and that user's reporting
     * manager. Every routine event routes through here now; the permission,
     * role and branch group lookups below remain for the system alerts that
     * have no acting user to trace a manager back from.
     */
    public function actorAndReportingManager(?User $actor): Collection
    {
        $manager = $this->reportingManagerOf($actor);

        return $this->mergeCollections(
            $actor ? collect([$actor]) : collect(),
            $manager ? collect([$manager]) : collect()
        );
    }

    /**
     * Users who are themselves reporting managers, resolved by matching the
     * reporting_managers directory back onto User accounts the same way
     * reportingManagerOf() does. Used by the alerts that fire off a schedule
     * or a stock movement rather than off somebody's action, where there is no
     * actor whose manager we could look up.
     */
    public function reportingManagers(): Collection
    {
        $entries = ReportingManager::query()
            ->where('is_active', true)
            ->get(['email', 'username']);

        $emails = $entries->pluck('email')->filter()->values();
        $usernames = $entries->pluck('username')->filter()->values();

        if ($emails->isEmpty() && $usernames->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($emails, $usernames) {
                $query->whereIn('email', $emails)
                    ->orWhereIn('username', $usernames);
            })
            ->distinct()
            ->get();
    }

    public function usersByPermissions(array $permissions): Collection
    {
        $users = User::query()
            ->where('is_active', true)
            ->permission($permissions)
            ->distinct()
            ->get();

        // Fallback: If no users match, notify SUPER ADMIN and ADMIN so notifications aren't dropped
        if ($users->isEmpty()) {
            $users = User::query()
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['SUPER ADMIN', 'ADMIN', 'Super Admin', 'Admin']);
                })
                ->distinct()
                ->get();
        }

        return $users;
    }

    public function usersByBranchPermissions(?Branch $branch, array $permissions): Collection
    {
        if (! $branch) {
            return collect();
        }

        $users = User::query()
            ->where('is_active', true)
            ->where('branch_id', $branch->id)
            ->permission($permissions)
            ->distinct()
            ->get();

        // Fallback: If no users match at the branch level, notify global SUPER ADMIN and ADMIN
        if ($users->isEmpty()) {
            $users = User::query()
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['SUPER ADMIN', 'ADMIN', 'Super Admin', 'Admin']);
                })
                ->distinct()
                ->get();
        }

        return $users;
    }

    public function usersByRoles(array $roles): Collection
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('name', $roles);
            })
            ->distinct()
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['SUPER ADMIN', 'ADMIN', 'Super Admin', 'Admin']);
                })
                ->distinct()
                ->get();
        }

        return $users;
    }

    public function usersByBranchRoles(?Branch $branch, array $roles): Collection
    {
        if (! $branch) {
            return collect();
        }

        $users = User::query()
            ->where('is_active', true)
            ->where('branch_id', $branch->id)
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('name', $roles);
            })
            ->distinct()
            ->get();

        if ($users->isEmpty()) {
            return $this->usersByRoles($roles);
        }

        return $users;
    }

    public function usersByNames(array $names): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('name', $names)
            ->distinct()
            ->get();
    }

    public function mergeCollections(Collection ...$collections): Collection
    {
        return collect($collections)
            ->flatten(1)
            ->unique('id')
            ->values();
    }

    public function procurement(): Collection
    {
        return $this->usersByPermissions(['PurchaseOrder Create', 'PurchaseOrder Update']);
    }

    public function reorderManagers($branch): Collection
    {
        $branchObj = is_numeric($branch) ? Branch::find($branch) : $branch;
        return $this->usersByBranchPermissions($branchObj, ['Reorder Level Update']);
    }

    public function inventoryAdmins(): Collection
    {
        return $this->usersByPermissions(['Product Index', 'StockLedger Index']);
    }

    public function inventoryManagers(): Collection
    {
        return $this->usersByPermissions(['StockTake Update', 'StockTransfer Update']);
    }

    public function inventoryHeads(): Collection
    {
        return $this->usersByPermissions(['StockTake Update', 'StockTransfer Update']);
    }

    public function stockControlTeam(): Collection
    {
        return $this->usersByPermissions(['StockLedger Update']);
    }

    public function auditTeam(): Collection
    {
        return $this->usersByPermissions(['StockTake Index', 'Activity Log Index']);
    }

    public function storeTeam(): Collection
    {
        return $this->usersByPermissions(['Grn Create', 'CheckIn Create', 'CheckOut Create']);
    }

    public function branchAdmins($branch): Collection
    {
        $branchObj = is_numeric($branch) ? Branch::find($branch) : $branch;
        return $this->usersByBranchPermissions($branchObj, ['Branch Request Update', 'StockTake Update', 'StockTransfer Update']);
    }

    public function branchManagers($branch): Collection
    {
        $branchObj = is_numeric($branch) ? Branch::find($branch) : $branch;
        return $this->usersByBranchPermissions($branchObj, ['Branch Request Update', 'StockTake Update']);
    }

    public function supervisorsForBranch($branch): Collection
    {
        $branchObj = is_numeric($branch) ? Branch::find($branch) : $branch;
        return $this->usersByBranchPermissions($branchObj, ['CheckIn Update', 'CheckOut Update']);
    }

    public function hrTeam(): Collection
    {
        return $this->usersByPermissions(['User Index', 'User Create']);
    }

    public function adminsAndSuperAdmins(?int $branchId = null): Collection
    {
        // For general "admin" fallback emails, we notify users with specific admin-level permissions
        $permissions = ['User Index', 'Role Index', 'Branch Index'];
        
        if ($branchId) {
            $branchObj = Branch::find($branchId);
            return $this->usersByBranchPermissions($branchObj, $permissions);
        }

        return $this->usersByPermissions($permissions);
    }
}