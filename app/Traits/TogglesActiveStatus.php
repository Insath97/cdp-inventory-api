<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait TogglesActiveStatus
{
    /**
     * Shared implementation for the activate()/deactivate()/toggleStatus() endpoints.
     *
     * @param string $modelClass Eloquent model class to act on
     * @param mixed $id Route id of the record
     * @param bool|null $state true = activate, false = deactivate, null = toggle
     * @param array $messages [
     *     'not_found' => 404 message,
     *     'already' => message when the record is already in the requested state
     *                  (omit to skip the already-in-state check),
     *     'success' => success message,
     *     'failed' => 500 message,
     * ]
     * @param array $options [
     *     'log' => callable(model): void — runs after the state change (activity/log writes),
     *     'already' => 'success' (200 with data, default) or 'error' (422, no data),
     *     'data' => 'model' (default) or 'subset' ({id, is_active}),
     *     'with' => relations passed to ->load() on the success payload ('model' data only),
     *     'raw_error' => true to expose $th->getMessage() directly in the 500 response
     *                    (default: gated behind config('app.debug')),
     * ]
     */
    protected function setActiveState(string $modelClass, $id, ?bool $state, array $messages, array $options = []): JsonResponse
    {
        try {
            $model = $modelClass::query()->find($id);

            if (!$model) {
                return response()->json([
                    'status' => 'error',
                    'message' => $messages['not_found'],
                ], 404);
            }

            if ($state !== null && isset($messages['already']) && (bool) $model->is_active === $state) {
                if (($options['already'] ?? 'success') === 'error') {
                    return response()->json([
                        'status' => 'error',
                        'message' => $messages['already'],
                    ], 422);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $messages['already'],
                    'data' => $this->activeStateData($model, $options),
                ]);
            }

            if ($state === null) {
                $model->is_active = !$model->is_active;
                $model->save();
            } else {
                $model->update(['is_active' => $state]);
            }

            if (isset($options['log'])) {
                $options['log']($model);
            }

            return response()->json([
                'status' => 'success',
                'message' => $messages['success'],
                'data' => $this->activeStateData($model, $options),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $messages['failed'],
                'error' => ($options['raw_error'] ?? false)
                    ? $th->getMessage()
                    : (config('app.debug') ? $th->getMessage() : 'Internal server error'),
            ], 500);
        }
    }

    /**
     * Build the success payload for setActiveState().
     */
    private function activeStateData($model, array $options)
    {
        if (($options['data'] ?? 'model') === 'subset') {
            return [
                'id' => $model->id,
                'is_active' => $model->is_active,
            ];
        }

        return isset($options['with']) ? $model->load($options['with']) : $model;
    }
}
