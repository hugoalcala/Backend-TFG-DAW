<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class TeacherController extends Controller
{
    /**
     * Devuelve el listado público de profesores aprobados.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $teachers = User::query()
            ->where('role', 'teacher')
            ->where('teacher_status', 'approved')
            ->orderBy('name')
            ->get([
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
            ]);

        return response()->json([
            'teachers' => $teachers,
            'data' => $teachers,
        ]);
    }
}