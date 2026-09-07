<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Container;
use App\Http\Requests\CreateContainerRequest;
use App\Http\Requests\UpdateContainerRequest;
use Illuminate\Support\Facades\DB;
use App\Traits\ActivityLogTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Log;
use App\Traits\TogglesActiveStatus;

class ContainerController extends Controller implements HasMiddleware
{
   use ActivityLogTrait;
   use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Container Index', only: ['index', 'show']),
            new Middleware('permission:Container Create', only: ['store']),
            new Middleware('permission:Container Update', only: ['update']),
            new Middleware('permission:Container Delete', only: ['destroy']),
            new Middleware('permission:Container Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Container::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            $containers = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Containers fetched successfully',
                'data' => $containers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch containers',
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
    public function store(CreateContainerRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $container = Container::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Container', "Created container: {$container->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Container created successfully',
                'data' => $container
            ], 201);
        }  catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create container',
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
            $container = Container::query()->find($id);

            if (!$container) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Container not found'
                ], 404);
            }

            Log::info('Container viewed', [
                'user_id' => Auth::id(),
                'container_id' => $container->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Container retrieved successfully',
                'data' => $container
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve container', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve container',
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

   
    public function update(UpdateContainerRequest $request, string $id)
    {
        try {
            $container = Container::query()->find($id);

            if (!$container) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Container not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $container->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Container', "Updated container: {$container->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Container updated successfully',
                'data' => $container
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update container',
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
            $container = Container::query()->find($id);
            if (! $container) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Container not found',
                    'data' => [],
                ], 404);
            }

            $title = $container->name;
            if (! Container::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete container',
                ], 500);
            }

            $this->logActivity('DELETE', 'Container', "Deleted container: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Container deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete container',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


     public function toggleStatus(string $id)
    {
        return $this->setActiveState(Container::class, $id, null, [
            'not_found' => 'Container not found',
            'success' => 'Container status updated successfully',
            'failed' => 'Failed to toggle container status',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($container) {
                Log::info('Container status toggled', [
                    'user_id' => Auth::id(),
                    'container_id' => $container->id,
                    'new_status' => $container->is_active
                ]);
            },
        ]);
    }


     public function activate(string $id)
    {
        return $this->setActiveState(Container::class, $id, true, [
            'not_found' => 'Container not found',
            'already' => 'Container is already active',
            'success' => 'Container activated successfully',
            'failed' => 'Failed to activate container',
        ], [
            'raw_error' => true,
            'log' => function ($container) {
                Log::info('Container activated', [
                    'user_id' => Auth::id(),
                    'container_id' => $container->id,
                ]);
            },
        ]);
    }


    public function deactivate(string $id)
    {
        return $this->setActiveState(Container::class, $id, false, [
            'not_found' => 'Container not found',
            'already' => 'Container is already inactive',
            'success' => 'Container deactivated successfully',
            'failed' => 'Failed to deactivate container',
        ], [
            'raw_error' => true,
            'log' => function ($container) {
                Log::info('Container deactivated', [
                    'user_id' => Auth::id(),
                    'container_id' => $container->id,
                ]);
            },
        ]);
    }
}
