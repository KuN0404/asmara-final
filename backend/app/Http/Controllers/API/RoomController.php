<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::query();
        
        // Search filter
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }
        
        // Availability filter
        if ($request->has('is_available')) {
            $query->where('is_available', $request->is_available);
        }
        
        $rooms = $query->orderBy('name', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'location' => 'required|string',
            'capacity' => 'nullable|integer',
            'is_available' => 'boolean',
        ]);

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Ruang berhasil dibuat',
            'room' => $room,
        ], 201);
    }

    public function show($id)
    {
        $room = Room::with('officeAgendas')->findOrFail($id);
        return response()->json($room);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'location' => 'sometimes|string',
            'capacity' => 'nullable|integer',
            'is_available' => 'boolean',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Ruang berhasil diperbarui',
            'room' => $room,
        ]);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json([
            'message' => 'Ruang berhasil dihapus',
        ]);
    }
}
