<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    public function index()
    {
        // Urutkan berdasarkan ID
        $schedules = Schedule::orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil semua jadwal',
            'data'    => $schedules
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'day'            => 'required|string|max:50',
            'type'           => 'required|string|max:255',
            'subjects'       => 'required|string',
            'dismissal_time' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors()
            ], 422);
        }

        $schedule = Schedule::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil ditambahkan',
            'data'    => $schedule
        ], 201);
    }

    public function show($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail jadwal ditemukan',
            'data'    => $schedule
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan',
                'data'    => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'day'            => 'sometimes|required|string|max:50',
            'type'           => 'sometimes|required|string|max:255',
            'subjects'       => 'sometimes|required|string',
            'dismissal_time' => 'sometimes|required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors()
            ], 422);
        }

        $schedule->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil diupdate',
            'data'    => $schedule
        ], 200);
    }

    public function destroy($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan',
                'data'    => null
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dihapus',
            'data'    => null
        ], 200);
    }
}
