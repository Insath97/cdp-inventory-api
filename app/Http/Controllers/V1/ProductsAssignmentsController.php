<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductAssignment;
use App\Models\Product;
use App\Models\Branch;
use App\Models\GrnItemSerial;
use App\Models\User;
use App\Http\Requests\CreateProductAssignmentRequest;
use App\Http\Requests\UpdateProductAssignmentRequest;
use App\Services\NotificationRecipientService;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductsAssignmentsController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ProductAssignment Index|Product Index|CheckIn Index|CheckOut Index|Grn Index', only: ['index', 'show', 'activeForPerson']),
            new Middleware('permission:ProductAssignment Create|Product Create|CheckIn Create|CheckOut Create|Grn Create', only: ['store']),
            new Middleware('permission:ProductAssignment Update|Product Update|CheckIn Update|CheckOut Update|Grn Update', only: ['update']),
            new Middleware('permission:ProductAssignment Delete|Product Delete|CheckIn Delete|CheckOut Delete|Grn Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = ProductAssignment::query()->with('product');

            $user = Auth::user();
            if ($user && !$user->can('ProductAssignment View All')) {
                $rmIds = \App\Models\ReportingManager::where('email', $user->email)
                    ->orWhere('username', $user->username)
                    ->orWhere('name', $user->name)
                    ->pluck('id')
                    ->toArray();

                $subordinateUsers = \App\Models\User::with('branch')->where(function($q) use ($rmIds, $user) {
                        if (!empty($rmIds)) {
                            $q->whereIn('reporting_manager_id', $rmIds);
                        }
                        $q->orWhere('reporting_manager_id', $user->id)
                          ->orWhere('parent_user_id', $user->id);
                    })
                    ->get();

                $branchNames = [];
                foreach ($subordinateUsers as $subUser) {
                    if ($subUser->branch?->name) {
                        $branchNames[] = $subUser->branch->name;
                    }
                }

                if ($user->branch?->name) {
                    $branchNames[] = $user->branch->name;
                }

                $branchNames = array_unique(array_filter($branchNames));
                $subordinateNames = $subordinateUsers->pluck('name')->push($user->name)->toArray();

                $query->where(function ($q) use ($branchNames, $subordinateNames) {
                    if (!empty($branchNames)) {
                        $q->whereIn('branch_name', $branchNames);
                    }
                    if (!empty($subordinateNames)) {
                        $q->orWhereIn('person_name', $subordinateNames);
                    }
                });
            }

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('group_name')) {
                $query->where('group_name', $request->group_name);
            }

            if ($request->has('branch_name')) {
                $query->where('branch_name', $request->branch_name);
            }

            if ($request->has('department_name')) {
                $query->where('department_name', $request->department_name);
            }

            $productassignments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignments fetched successfully',
                'data' => $productassignments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch products assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Active serial-based assignments for a given user at a given branch —
     * powers the Product Return flow's "currently assigned to this person"
     * list.
     */
    public function activeForPerson(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'branch_id' => 'required|integer|exists:branches,id',
            ]);

            $assignments = ProductAssignment::query()
                ->whereNotNull('grn_item_serial_id')
                ->where('is_active', true)
                ->where('user_id', $request->integer('user_id'))
                ->where('branch_id', $request->integer('branch_id'))
                ->with(['grnItemSerial.product', 'grnItemSerial.productVariant'])
                ->orderByDesc('issue_date')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Active assignments fetched successfully',
                'data' => $assignments,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch active assignments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductAssignmentRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            if (empty($data['assignment_code'])) {
                $maxId = (ProductAssignment::withTrashed()->max('id') ?? 0) + 1;
                $code = 'ASG-' . date('Y') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);
                while (ProductAssignment::withTrashed()->where('assignment_code', $code)->exists()) {
                    $maxId++;
                    $code = 'ASG-' . date('Y') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);
                }
                $data['assignment_code'] = $code;
            } else {
                if (ProductAssignment::withTrashed()->where('assignment_code', $data['assignment_code'])->exists()) {
                    $data['assignment_code'] = $data['assignment_code'] . '-' . uniqid();
                }
            }

            // Resolve the real User/Branch records into the denormalized
            // name columns the rest of this controller (search, notifications,
            // reporting-manager scoping) already relies on.
            $user = User::find($data['user_id']);
            $branch = Branch::find($data['branch_id']);
            $data['person_name'] = $user?->name;
            $data['branch_name'] = $branch?->name;

            if (!empty($data['grn_item_serial_id'])) {
                $serial = GrnItemSerial::with('product')->find($data['grn_item_serial_id']);
                if (!$serial) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Serial number not found.',
                    ], 404);
                }

                $alreadyAssigned = ProductAssignment::where('grn_item_serial_id', $serial->id)
                    ->where('is_active', true)
                    ->exists();
                if ($alreadyAssigned) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "Serial {$serial->serial_number} is already assigned. Return it before reassigning.",
                    ], 409);
                }

                $data['quantity'] = 1;
                $data['serial_number'] = $serial->serial_number;
                $data['product_variant_id'] = $serial->product_id;
                $data['product_sku'] = $data['product_sku'] ?? $serial->product?->product_code;
                $data['product_name'] = $data['product_name'] ?? $serial->product?->product_name;
            } else {
                $productId = $data['product_variant_id'] ?? $data['product_id'] ?? null;
                $data['product_variant_id'] = $productId;

                $insufficientError = $this->checkAssignmentStock($productId, $data['branch_id'], $data['quantity']);
                if ($insufficientError) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => $insufficientError,
                    ], 422);
                }

                // Auto-fill SKU and Name if not provided
                if (empty($data['product_sku']) || empty($data['product_name'])) {
                    $product = Product::find($productId);
                    if ($product) {
                        $data['product_sku'] = $data['product_sku'] ?? $product->product_code;
                        $data['product_name'] = $data['product_name'] ?? $product->product_name;
                    }
                }
            }
            unset($data['product_id']);

            $productassignment = ProductAssignment::create($data);

            $recipientService = app(NotificationRecipientService::class);
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Product Assigned',
                'message' => ($productassignment->product_name ?? 'Product') . ' assigned to ' . $productassignment->person_name . '.',
                'type' => 'assigned',
                'module' => 'product-assignments',
                'priority' => 'medium',
                'reference_id' => $productassignment->id,
                'reference_type' => ProductAssignment::class,
                'url' => '/product-assignments/' . $productassignment->id,
            ]);

            try {
                // The person the product was assigned to, plus their reporting
                // manager — the assignment is only those two people's business.
                foreach ($recipientService->actorAndReportingManager($productassignment->user) as $target) {
                    $target->notify($notification);
                }
            } catch (\Throwable $notifyErr) {
                Log::error('Failed to send product assignment notification: ' . $notifyErr->getMessage());
            }

            DB::commit();

            $this->logActivity('CREATE', 'Product Assignment', "Created product assignment: {$productassignment->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignment created successfully',
                'data' => $productassignment->load(['product'])
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Failed to create product assignment: ' . $th->getMessage() . "\n" . $th->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product assignment',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $productassignment = ProductAssignment::with(['product'])->find($id);

            if (!$productassignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Assignment not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignment retrieved successfully',
                'data' => $productassignment
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product assignments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductAssignmentRequest $request, string $id)
    {
        try {
            $productassignment = ProductAssignment::query()->find($id);

            if (!$productassignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Assignment not found',
                    'data' => []
                ], 404);
            }

            if ($productassignment->grn_item_serial_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Serial assignments can't be edited — return it and reassign instead.",
                ], 422);
            }

            $data = $request->validated();

            if (array_key_exists('product_id', $data)) {
                $data['product_variant_id'] = $data['product_id'];
                unset($data['product_id']);
            }

            if (array_key_exists('quantity', $data) || array_key_exists('product_variant_id', $data) || array_key_exists('branch_name', $data)) {
                $productId = $data['product_variant_id'] ?? $productassignment->product_variant_id;
                $branchName = $data['branch_name'] ?? $productassignment->branch_name;
                $quantity = $data['quantity'] ?? $productassignment->quantity;
                $branchId = Branch::where('name', $branchName)->value('id');

                $insufficientError = $this->checkAssignmentStock($productId, $branchId, $quantity);
                if ($insufficientError) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $insufficientError,
                    ], 422);
                }
            }

            $productassignment->update($data);

            $this->logActivity('UPDATE', 'Product Assignment', "Updated product assignment: {$productassignment->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignment updated successfully',
                'data' => $productassignment->load(['product'])
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product assignment',
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
            $productassignment = ProductAssignment::query()->find($id);

            if (!$productassignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Assignment not found',
                    'data' => [],
                ], 404);
            }

            $title = $productassignment->product_name;
            if (!$productassignment->delete()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete product assignment',
                ], 500);
            }

            $this->logActivity('DELETE', 'ProductAssignment', "Deleted product assignment: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product assignment deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product assignment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle status.
     */
    public function toggleStatus(string $id)
    {
        try {
            $productassignment = ProductAssignment::query()->find($id);

            if (!$productassignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Assignment not found'
                ], 404);
            }

            $productassignment->update([
                'is_active' => !$productassignment->is_active
            ]);

            $this->logActivity('TOGGLE_STATUS', 'Product Assignment', "Toggled status of assignment: {$productassignment->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignment status updated successfully',
                'data' => $productassignment->load(['product'])
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle product assignment status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Activate.
     */
    public function activate(string $id)
    {
        try {
            $productassignment = ProductAssignment::query()->find($id);

            if (!$productassignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Assignment not found',
                ], 404);
            }

            $productassignment->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'Product Assignment', "Activated product assignment: {$productassignment->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignment activated successfully',
                'data' => $productassignment->load(['product'])
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate product assignment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate.
     */
    public function deactivate(string $id)
    {
        try {
            $productassignment = ProductAssignment::query()->find($id);

            if (!$productassignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Assignment not found',
                ], 404);
            }

            $productassignment->update([
                'is_active' => false,
                'returned_at' => $productassignment->grn_item_serial_id ? now() : null,
                'returned_by' => $productassignment->grn_item_serial_id ? Auth::id() : null,
            ]);

            try {
                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Product Returned',
                    'message' => ($productassignment->product_name ?? 'Product') . ' returned successfully.',
                    'type' => 'returned',
                    'module' => 'product-assignments',
                    'priority' => 'medium',
                    'reference_id' => $productassignment->id,
                    'reference_type' => ProductAssignment::class,
                    'url' => '/product-assignments/' . $productassignment->id,
                ]);

                // The person the product was assigned to, plus their reporting
                // manager — nobody else needs telling that it came back.
                foreach ($recipientService->actorAndReportingManager($productassignment->user) as $target) {
                    $target->notify($notification);
                }
            } catch (\Throwable $notifyErr) {
                Log::error('Failed to send product return notification: ' . $notifyErr->getMessage());
            }

            $this->logActivity('DEACTIVATE', 'Product Assignment', "Deactivated product assignment: {$productassignment->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product Assignment deactivated successfully',
                'data' => $productassignment->load(['product'])
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate product assignment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate the assigned quantity against the branch's current stock
     * balance. Returns an error message if it exceeds, or null if within range.
     */
    private function checkAssignmentStock(?int $productId, ?int $branchId, $quantity): ?string
    {
        $quantity = floatval($quantity ?? 0);

        if (!$productId || !$branchId || $quantity <= 0) {
            return null;
        }

        $available = StockLedgerService::getBalance($productId, $branchId);

        if ($quantity > $available) {
            return "Insufficient stock. Only {$available} units are available in this branch.";
        }

        return null;
    }
}
