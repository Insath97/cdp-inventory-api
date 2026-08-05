<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupplierBankAccount;
use App\Http\Requests\CreateSupplierBankAccountRequest;
use App\Http\Requests\UpdateSupplierBankAccountRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupplierBankAccountController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Supplier Bank Account Index', only: ['index']),
            new Middleware('permission:Supplier Bank Account Show', only: ['show']),
            new Middleware('permission:Supplier Bank Account Create', only: ['store']),
            new Middleware('permission:Supplier Bank Account Update', only: ['update']),
            new Middleware('permission:Supplier Bank Account Delete', only: ['destroy']),
            new Middleware('permission:Supplier Bank Account Toggle Status', only: ['toggleStatus']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         try {
            $perPage = $request->get('per_page', 15);
            $query = SupplierBankAccount::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            $supplierbankaccount = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank accounts fetched successfully',
                'data' => $supplierbankaccount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch supplier bank accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSupplierBankAccountRequest $request)
    {
         try {
            DB::beginTransaction();

            $data = $request->validated();
            $supplierbankaccount = SupplierBankAccount::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'SupplierBankAccount', "Created supplier bank account: {$supplierbankaccount->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account created successfully',
                'data' => $supplierbankaccount
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier bank account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
         try {
            $supplierbankaccount = SupplierBankAccount::query()->find($id);

            if (!$supplierbankaccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account not found'
                ], 404);
            }

            Log::info('Supplier bank account viewed', [
                'user_id' => Auth::id(),
                'supplier_bank_account_id' => $supplierbankaccount->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account retrieved successfully',
                'data' => $supplierbankaccount
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve supplier bank account', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve supplier bank account',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierBankAccountRequest $request, string $id)
    {
        try {
            $supplierbankaccount = SupplierBankAccount::query()->find($id);

            if (!$supplierbankaccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $supplierbankaccount->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'SupplierBankAccount', "Updated supplier bank account: {$supplierbankaccount->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account updated successfully',
                'data' => $supplierbankaccount
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update supplier bank account',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
         try {
            $supplierbankaccount = SupplierBankAccount::query()->find($id);
            if (! $supplierbankaccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account not found',
                    'data' => [],
                ], 404);
            }

            $title = $supplierbankaccount->bank_name . ' (' . $supplierbankaccount->account_number . ')';
            if (! SupplierBankAccount::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete supplier bank account',
                ], 500);
            }

            $this->logActivity('DELETE', 'SupplierBankAccount', "Deleted supplier bank account: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete supplier bank account',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


      public function toggleStatus(string $id)
    {
        try {
            $supplierBankAccount = SupplierBankAccount::query()->find($id);

            if (!$supplierBankAccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account not found'
                ], 404);
            }

            $supplierBankAccount->is_active = !$supplierBankAccount->is_active;
            $supplierBankAccount->save();

            Log::info('Supplier bank account status toggled', [
                'user_id' => Auth::id(),
                'supplier_bank_account_id' => $supplierBankAccount->id,
                'new_status' => $supplierBankAccount->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account status updated successfully',
                'data' => [
                    'id' => $supplierBankAccount->id,
                    'is_active' => $supplierBankAccount->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle supplier bank account status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

     public function activate(string $id)
    {
        try {
            $supplierBankAccount = SupplierBankAccount::query()->find($id);

            if (! $supplierBankAccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account not found',
                ], 404);
            }

            if ($supplierBankAccount->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account is already active',
                ], 422);
            }

            $supplierBankAccount->update(['is_active' => true]);

            Log::info('Supplier bank account activated', [
                'user_id' => Auth::id(),
                'supplier_bank_account_id' => $supplierBankAccount->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account activated successfully',
                'data' => [
                    'id' => $supplierBankAccount->id,
                    'is_active' => $supplierBankAccount->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate supplier bank account',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $supplierBankAccount = SupplierBankAccount::query()->find($id);

            if (! $supplierBankAccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account not found',
                ], 404);
            }

            if (! $supplierBankAccount->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier bank account is already inactive',
                ], 422);
            }

            $supplierBankAccount->update(['is_active' => false]);

            Log::info('Supplier bank account deactivated', [
                'user_id' => Auth::id(),
                'supplier_bank_account_id' => $supplierBankAccount->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier bank account deactivated successfully',
                'data' => [
                    'id' => $supplierBankAccount->id,
                    'is_active' => $supplierBankAccount->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate supplier bank account',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
