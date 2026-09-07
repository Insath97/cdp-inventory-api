<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Brand;
use App\Http\Requests\CreateBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\TogglesActiveStatus;

class BrandController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Brand Index', only: ['index', 'show']),
            new Middleware('permission:Brand Create', only: ['store']),
            new Middleware('permission:Brand Update', only: ['update']),
            new Middleware('permission:Brand Delete', only: ['destroy']),
            new Middleware('permission:Brand Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $perPage = $request->get('per_page', 15);
            $query = Brand::query();

             if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $brands = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Brands fetched successfully',
                'data' => $brands
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch brands',
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
    public function store(CreateBrandRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $brand = Brand::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Brand', "Created brand: {$brand->name} ({$brand->slug})");

            return response()->json([
                'status' => 'success',
                'message' => 'Brand created successfully',
                'data' => $brand
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create brand',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show( string $id)
    {
        try {
            $brand = Brand::query()->find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Brand retrieved successfully',
                'data' => $brand
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve brand',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
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
    public function update(UpdateBrandRequest $request, string $id)
    {
        try {
            $brand = Brand::query()->find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $brand->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Brand', "Updated brand: {$brand->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Brand updated successfully',
                'data' => $brand
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update brand',
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
            $brand = Brand::query()->find($id);
            if (! $brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => [],
                ], 404);
            }

            $hasProducts = DB::table('products')->where('brand_id', $id)->whereNull('deleted_at')->exists();
            if ($hasProducts) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete brand because it is currently referenced by products.'
                ], 422);
            }

            $title = $brand->name;
            if (! Brand::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete brand',
                ], 500);
            }

            $this->logActivity('DELETE', 'Brand', "Deleted brand: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Brand deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete brand',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


     public function toggleStatus(string $id)
    {
        return $this->setActiveState(Brand::class, $id, null, [
            'not_found' => 'Brand not found',
            'success' => 'Brand status updated successfully',
            'failed' => 'Failed to toggle brand status',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($brand) {
                Log::info('Brand status toggled', [
                    'user_id' => Auth::id(),
                    'brand_id' => $brand->id,
                    'new_status' => $brand->is_active
                ]);
            },
        ]);
    }

     /**
     * Activate the brand.
     */
    public function activate(string $id)
    {
        return $this->setActiveState(Brand::class, $id, true, [
            'not_found' => 'Brand not found',
            'already' => 'Brand is already active',
            'success' => 'Brand activated successfully',
            'failed' => 'Failed to activate brand',
        ], [
            'log' => function ($brand) {
                $this->logActivity('ACTIVATE', 'Brand', "Activated brand: {$brand->name}");
            },
        ]);
    }

    /**
     * Deactivate the brand.
     */
    public function deactivate(string $id)
    {
        return $this->setActiveState(Brand::class, $id, false, [
            'not_found' => 'Brand not found',
            'already' => 'Brand is already inactive',
            'success' => 'Brand deactivated successfully',
            'failed' => 'Failed to deactivate brand',
        ], [
            'log' => function ($brand) {
                $this->logActivity('DEACTIVATE', 'Brand', "Deactivated brand: {$brand->name}");
            },
        ]);
    }
}
