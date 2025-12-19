<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // public function index(Request $request)
    // {
    //     $users = User::with('roles')
    //         ->when($request->role, function ($q) use ($request) {
    //             $q->role($request->role);
    //         })
    //         ->paginate($request->per_page ?? 15);

    //     return response()->json($users);
    // }

    public function index(Request $request)
    {
        $query = User::with('roles')
            // Exclude super_admin users from listing
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super_admin');
            });
        
        // Search filter
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('whatsapp_number', 'like', "%{$search}%");
            });
        }
        
        // Role filter
        if ($request->role) {
            $query->role($request->role);
        }
        
        // Status filter
        if ($request->status) {
            if ($request->status === 'active') {
                $query->whereNull('deleted_at');
            } elseif ($request->status === 'inactive') {
                $query->onlyTrashed();
            } elseif ($request->status === 'all') {
                $query->withTrashed();
            }
        }
        
        $users = $query->orderBy('name', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json($users);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users',
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'whatsapp_number' => 'required|string',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'position' => 'required|in:pns,pppk',
            'role' => 'required|in:kepala,ketua_tim,kasubbag,staff',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('users', 'public');
        }

        $user = User::create($validated);
        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'Pengguna berhasil dibuat',
            'user' => $user->load('roles'),
        ], 201);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json($user);
    }

public function update(Request $request, $id)
{
    $user = User::findOrFail($id);

    $validated = $request->validate([
        'username' => 'required|string|unique:users,username,' . $id,
        'name' => 'required|string',
        'email' => 'required|email|unique:users,email,' . $id,
        'whatsapp_number' => 'required|string',
        'address' => 'nullable|string',
        'position' => 'required|in:pns,pppk',
        'role' => 'required|in:kepala,ketua_tim,kasubbag,staff',
        'password' => 'nullable|string|min:8',
        'photo' => 'nullable|image|max:2048',
    ]);

    if ($request->hasFile('photo')) {
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $validated['photo'] = $request->file('photo')->store('users', 'public');
    }

    $user->update($validated);

    if (isset($validated['role'])) {
        $user->syncRoles([$validated['role']]);
    }

    return response()->json([
        'message' => 'Pengguna berhasil diperbarui',
        'user' => $user->load('roles'),
    ]);
}


    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }

    public function restore($id) {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return response()->json(['message' => 'Pengguna berhasil diaktifkan']);
    }

}
