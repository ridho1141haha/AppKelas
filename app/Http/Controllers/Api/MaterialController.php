<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller
{
    public function index()
    {
        // Urutkan berdasarkan yang paling baru ditambahkan (descending)
        $materials = Material::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil semua materi',
            'data'    => $materials
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'file_url'    => 'nullable|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors()
            ], 422);
        }

        $material = Material::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil ditambahkan',
            'data'    => $material
        ], 201);
    }

    public function show($id)
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Materi tidak ditemukan',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail materi ditemukan',
            'data'    => $material
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Materi tidak ditemukan',
                'data'    => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|required|string|max:255',
            'subject'     => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'file_url'    => 'nullable|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors()
            ], 422);
        }

        $material->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil diupdate',
            'data'    => $material
        ], 200);
    }

    public function destroy($id)
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Materi tidak ditemukan',
                'data'    => null
            ], 404);
        }

        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil dihapus',
            'data'    => null
        ], 200);
    }

    /**
     * Upload file materi ke storage.
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,ppt,pptx,doc,docx,jpg,jpeg,png|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('materials', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diupload',
                'data'    => [
                    'file_url' => $url,
                    'file_path' => $path
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengupload file',
        ], 500);
    }
}
