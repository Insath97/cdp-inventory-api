<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MainCategory;
use App\Http\Requests\CreateMainCategoryRequest;
use App\Http\Requests\UpdateMainCategoryRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\ActivityLogTrait;

class MainCategoryController extends Controller
{
    use ActivityLogTrait;

     public static function middleware(): array
    {
        return [
           new Middleware('permission:Main Category Index', only: ['index']),
           new Middleware('permission:Main Category Show|Main Category Index', only: ['show']),
           new Middleware('permission:Main Category Create', only: ['store']),
           new Middleware('permission:Main Category Update', only: ['update']),
           new Middleware('permission:Main Category Delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         try {
            $perPage = $request->get('per_page', 15);
            $query = MainCategory::query()->with(['subCategories', 'creator']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            $mainCategories = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Main categories fetched successfully',
                'data' => $mainCategories
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
    public function store(CreateMainCategoryRequest $request)
    {
          try {
            DB::beginTransaction();

            $data = $request->validated();
            if (empty($data['created_by'])) {
                $data['created_by'] = Auth::id();
            }
            $mainCategory = MainCategory::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'MainCategory', "Created main category: {$mainCategory->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Main category created successfully',
                'data' => $mainCategory->load('creator')
            ], 201);
        }  catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create main category',
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
            $mainCategory = MainCategory::query()->find($id);

            if (!$mainCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category not found'
                ], 404);
            }

            Log::info('Main category viewed', [
                'user_id' => Auth::id(),
                'main_category_id' => $mainCategory->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Main category retrieved successfully',
                'data' => $mainCategory
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve main category', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve main category',
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
    public function update(UpdateMainCategoryRequest $request, string $id)
    {
         try {
            $mainCategory = MainCategory::query()->find($id);

            if (!$mainCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $mainCategory->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'MainCategory', "Updated main category: {$mainCategory->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Main category updated successfully',
                'data' => $mainCategory
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update main category',
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
            $mainCategory = MainCategory::query()->find($id);
            if (! $mainCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category not found',
                    'data' => [],
                ], 404);
            }

            $hasProducts = DB::table('products')->where('main_category_id', $id)->exists();
            $hasSubCategories = DB::table('sub_categories')->where('main_category_id', $id)->exists();

            if ($hasProducts || $hasSubCategories) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete category because it is currently referenced by products or sub-categories.'
                ], 422);
            }

            $title = $mainCategory->name;
            if (! MainCategory::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete main category',
                ], 500);
            }

            $this->logActivity('DELETE', 'MainCategory', "Deleted main category: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Main category deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete main category',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    public function toggleStatus(string $id)
    {
        try {
            $mainCategory = MainCategory::query()->find($id);

            if (!$mainCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category not found'
                ], 404);
            }

            $mainCategory->is_active = !$mainCategory->is_active;
            $mainCategory->save();

            Log::info('Main category status toggled', [
                'user_id' => Auth::id(),
                'main_category_id' => $mainCategory->id,
                'new_status' => $mainCategory->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Main category status updated successfully',
                'data' => [
                    'id' => $mainCategory->id,
                    'is_active' => $mainCategory->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle main category status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

     public function activate(string $id)
    {
        try {
            $mainCategory = MainCategory::query()->find($id);

            if (! $mainCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category not found',
                ], 404);
            }

            if ($mainCategory->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category is already active',
                ], 422);
            }

            $mainCategory->update(['is_active' => true]);

            Log::info('Main category activated', [
                'user_id' => Auth::id(),
                'main_category_id' => $mainCategory->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Main category activated successfully',
                'data' => [
                    'id' => $mainCategory->id,
                    'is_active' => $mainCategory->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate main category',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $mainCategory = MainCategory::query()->find($id);

            if (! $mainCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category not found',
                ], 404);
            }

            if (! $mainCategory->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main category is already inactive',
                ], 422);
            }

            $mainCategory->update(['is_active' => false]);

            Log::info('Main category deactivated', [
                'user_id' => Auth::id(),
                'main_category_id' => $mainCategory->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Main category deactivated successfully',
                'data' => [
                    'id' => $mainCategory->id,
                    'is_active' => $mainCategory->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate main category',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
