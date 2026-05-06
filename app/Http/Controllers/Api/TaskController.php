<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * 1. index(): Mengambil semua data tugas
     * Diurutkan dari deadline paling dekat (ascending)
     */
    public function index()
    {
        $tasks = Task::orderBy('deadline', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil semua data tugas',
            'data'    => $tasks
        ], 200);
    }

    /**
     * 2. store(): Menambah tugas baru
     */
    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'subject'     => 'required|string|max:255',
            'deadline'    => 'required|date',
            'description' => 'nullable|string',
            'status'      => 'in:pending,completed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal, cek inputan kamu',
                'data'    => $validator->errors()
            ], 422); // 422 Unprocessable Entity
        }

        // Kalau lolos, insert data
        $task = Task::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tugas berhasil ditambahkan',
            'data'    => $task
        ], 201); // 201 Created
    }

    /**
     * 3. show($id): Menampilkan detail satu tugas
     */
    public function show($id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Tugas tidak ditemukan',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail tugas ditemukan',
            'data'    => $task
        ], 200);
    }

    /**
     * 4. update(Request $request, $id): Mengubah data tugas
     */
    public function update(Request $request, $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Tugas tidak ditemukan',
                'data'    => null
            ], 404);
        }

        // Pakai 'sometimes' biar field yang nggak dikirim gak ikut divalidasi/wajib
        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|required|string|max:255',
            'subject'     => 'sometimes|required|string|max:255',
            'deadline'    => 'sometimes|required|date',
            'description' => 'nullable|string',
            'status'      => 'in:pending,completed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors()
            ], 422);
        }

        // Update data
        $task->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tugas berhasil diupdate',
            'data'    => $task
        ], 200);
    }

    /**
     * 5. destroy($id): Menghapus tugas
     */
    public function destroy($id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Tugas tidak ditemukan',
                'data'    => null
            ], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tugas berhasil dihapus',
            'data'    => null
        ], 200);
    }
}
