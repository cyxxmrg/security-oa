<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = \Illuminate\Support\Facades\DB::table('system_logs');
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('module')) $query->where('module', $request->module);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('start_date')) $query->where('created_at', '>=', $request->start_date);
        if ($request->filled('end_date')) $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20)]);
    }
}
