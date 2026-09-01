<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSubCategoriesRequest;
use Illuminate\Http\Request;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UpdateSubCategoriesRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\ActivityLogTrait;


class SubCategoryController extends Controller
{
    use ActivityLogTrait;
     public static function middleware(): array
    {
        return [
           new Middleware('permission:Sub Category Index', only: ['index']),
           new Middleware('permission:Sub Category Show|Sub Category Index', only: ['show']),
           new Middleware('permission:Sub Category Create', only: ['store']),
           new Middleware('permission:Sub Category Update', only: ['update']),
           new Middleware('permission:Sub Category Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
       try {
            $perPage = $request->get('per_page', 15);
            $query = SubCategory::query()->with('creator');

             if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            $subCategories = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Main categories fetched successfully',
                'data' => $subCategories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch main categories',
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
    public function store(CreateSubCategoriesRequest $request)
    {
       try {
             DB::beginTransaction();

            $data = $request->validated();
            if (empty($data['created_by'])) {
                $data['created_by'] = Auth::id();
            }
            $subCategory = SubCategory::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'SubCategory', "Created sub category: {$subCategory->name} ({$subCategory->sub_category_code})");

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category created successfully',
                'data' => $subCategory->load('creator')
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
         try {
            $subCategory = SubCategory::query()->find($id);

            if (!$subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category not found'
                ], 404);
            }

            Log::info('Sub category viewed', [
                'user_id' => Auth::id(),
                'sub_category_id' => $subCategory->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category retrieved successfully',
                'data' => $subCategory
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve sub category', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sub category',
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
    public function update(UpdateSubCategoriesRequest $request, string $id)
    {
         try {
            $subCategory = SubCategory::query()->find($id);

            if (!$subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $subCategory->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'SubCategory', "Updated sub category: {$subCategory->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category updated successfully',
                'data' => $subCategory
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update sub category',
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
            $subCategory = SubCategory::query()->find($id);
            if (! $subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category not found',
                    'data' => [],
                ], 404);
            }

            $title = $subCategory->name;
            if (! SubCategory::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete sub category',
                ], 500);
            }

            $this->logActivity('DELETE', 'SubCategory', "Deleted sub category: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete sub category',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    public function toggleStatus(string $id)
    {
        try {
            $subCategory = SubCategory::query()->find($id);

            if (!$subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category not found'
                ], 404);
            }

            $subCategory->is_active = !$subCategory->is_active;
            $subCategory->save();

            Log::info('Sub category status toggled', [
                'user_id' => Auth::id(),
                'sub_category_id' => $subCategory->id,
                'new_status' => $subCategory->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category status updated successfully',
                'data' => [
                    'id' => $subCategory->id,
                    'is_active' => $subCategory->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle sub category status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

     public function activate(string $id)
    {
        try {
            $subCategory = SubCategory::query()->find($id);

            if (! $subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category not found',
                ], 404);
            }

            if ($subCategory->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category is already active',
                ], 422);
            }

            $subCategory->update(['is_active' => true]);

            Log::info('Sub category activated', [
                'user_id' => Auth::id(),
                'sub_category_id' => $subCategory->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category activated successfully',
                'data' => [
                    'id' => $subCategory->id,
                    'is_active' => $subCategory->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate sub category',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $subCategory = SubCategory::query()->find($id);

            if (! $subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category not found',
                ], 404);
            }

            if (! $subCategory->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub category is already inactive',
                ], 422);
            }

            $subCategory->update(['is_active' => false]);

            Log::info('Sub category deactivated', [
                'user_id' => Auth::id(),
                'sub_category_id' => $subCategory->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sub category deactivated successfully',
                'data' => [
                    'id' => $subCategory->id,
                    'is_active' => $subCategory->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate sub category',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

}
