<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeacherController extends Controller
{
    /**
     * Devuelve el listado público de profesores aprobados.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = max(1, min(100, $request->integer('per_page', 12)));

        try {
            $teachers = User::query()
                ->where('role', 'teacher')
                ->where('teacher_status', 'approved')
                ->orderBy('name')
                ->paginate($perPage, [
                    'id',
                    'name',
                    'subject',
                    'bio',
                    'price_per_hour',
                    'avatar_path',
                    'role',
                    'teacher_status',
                    'created_at',
                    'updated_at',
                ])
                ->appends($request->query());

            return response()->json($teachers);
        } catch (Throwable $exception) {
            Log::error('Unable to fetch teachers', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Unable to fetch teachers',
            ], 500);
        }
    }
}