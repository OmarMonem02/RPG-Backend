<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * Get system history / audit logs.
     * Accessible by Admin only (enforced via routes).
     */
    public function index(Request $request): JsonResponse
    {
        $query = History::with('user')->latest();

        if ($request->has('model_type')) {
            $query->where('model_type', 'like', '%' . $request->model_type . '%');
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $history = $query->paginate((int) $request->query('per_page', 20));

        return response()->json($history);
    }
}
