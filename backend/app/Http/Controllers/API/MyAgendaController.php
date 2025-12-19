<?php

// namespace App\Http\Controllers\API;

// use Carbon\Carbon;
// use App\Models\MyAgenda;
// use Illuminate\Http\Request;
// use App\Http\Controllers\Controller;
// use App\Jobs\SendWhatsAppNotification;

// class MyAgendaController extends Controller
// {
// public function index(Request $request)
// {
//     $query = MyAgenda::with('creator')
//         ->withTrashed()
//         ->where('created_by', $request->user()->id);

//     // Tambah filter tanggal
//     if ($request->start_date) {
//         $query->whereDate('start_at', '>=', $request->start_date);
//     }

//     if ($request->end_date) {
//         $query->whereDate('start_at', '<=', $request->end_date);
//     }

//     if ($request->status) {
//         $query->where('status', $request->status);
//     }

//     $agendas = $query->orderBy('start_at', 'desc')
//         ->paginate($request->per_page ?? 15);

//     return response()->json($agendas);
// }

//     // public function store(Request $request)
//     // {
//     //     $validated = $request->validate([
//     //         'start_at' => 'required|date',
//     //         'until_at' => 'required|date|after:start_at',
//     //         'title' => 'required|string',
//     //         'description' => 'nullable|string',
//     //         'is_show_to_other' => 'required|boolean',
//     //         'status' => 'required|in:comming_soon,in_progress,schedule_change,completed,cancelled',
//     //     ]);

//     //     $validated['created_by'] = $request->user()->id; // FIXED

//     //     $agenda = MyAgenda::create($validated);

//     // $agenda = MyAgenda::create([
//     //     'title' => $request->title,
//     //     'start_at' => $request->start_at,
//     //     'until_at' => $request->until_at,
//     //     'description' => $request->description,
//     //     'is_show_to_other' => $request->is_show_to_other ?? false,
//     //     'status' => $request->status ?? 'comming_soon',
//     //     'created_by' => auth()->id(),
//     // ]);

//     // // 🚀 SEND WHATSAPP NOTIFICATION TO CREATOR
//     // $user = auth()->user();
//     // if ($user->whatsapp_number) {
//     //     $message = "📝 *AGENDA PRIBADI BARU*\n\n" .
//     //               "📌 {$agenda->title}\n" .
//     //               "📅 " . $agenda->start_at->format('d M Y') . "\n" .
//     //               "🕐 " . $agenda->start_at->format('H:i') . " - " . $agenda->until_at->format('H:i') . "\n" .
//     //               ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
//     //               "\n_Agenda pribadi Anda telah dibuat._";

//     //     SendWhatsAppNotification::dispatch(
//     //         $user->whatsapp_number,
//     //         $message,
//     //         'my_agenda',
//     //         'created',
//     //         $agenda->id
//     //     );
//     // }

//     // // return response()->json($agenda, 201);

//     //     return response()->json([
//     //         'message' => 'Agenda berhasil dibuat',
//     //         'agenda' => $agenda,
//     //     ], 201);
//     // }


// public function store(Request $request)
// {
//     $validated = $request->validate([
//         'start_at' => 'required|date',
//         'until_at' => 'required|date|after:start_at',
//         'title' => 'required|string',
//         'description' => 'nullable|string',
//         'is_show_to_other' => 'required|boolean',
//     ]);

//     // HANYA 1 CREATE
//     $agenda = MyAgenda::create([
//         'title' => $validated['title'],
//         'start_at' => $validated['start_at'],
//         'until_at' => $validated['until_at'],
//         'description' => $validated['description'] ?? null,
//         'is_show_to_other' => $validated['is_show_to_other'],
//         'created_by' => auth()->id(),
//     ]);

//     // Send WhatsApp - HANYA 1X
//     $user = auth()->user();
//     if ($user->whatsapp_number) {
//         $message = "📝 *AGENDA PRIBADI BARU*\n\n" .
//                   "📌 {$agenda->title}\n" .
//                   "📅 " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
//                   "🕐 " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . "\n" .
//                   ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
//                   "\n_Agenda pribadi Anda telah dibuat._";

//         SendWhatsAppNotification::dispatch(
//             $user->whatsapp_number,
//             $message,
//             'my_agenda',
//             'created',
//             $agenda->id
//         );
//     }

//     return response()->json([
//         'message' => 'Agenda berhasil dibuat',
//         'agenda' => $agenda,
//     ], 201);
// }

// public function show($id)
// {
//     $agenda = MyAgenda::with('creator')
//         ->withTrashed() // TAMBAHKAN INI
//         ->where('created_by', auth()->id())
//         ->findOrFail($id);

//     return response()->json($agenda);
// }

// public function update(Request $request, $id)
// {
//     $agenda = MyAgenda::withTrashed() // TAMBAHKAN INI
//         ->where('created_by', $request->user()->id)
//         ->findOrFail($id);

//     $validated = $request->validate([
//         'start_at' => 'sometimes|date',
//         'until_at' => 'sometimes|date|after:start_at',
//         'title' => 'sometimes|string',
//         'description' => 'nullable|string',
//         'is_show_to_other' => 'sometimes|boolean',
//     ]);

//     $agenda->update($validated);

//     return response()->json([
//         'message' => 'Agenda berhasil diperbarui',
//         'agenda' => $agenda,
//     ]);
// }

//     public function destroy($id)
//     {
//         $agenda = MyAgenda::where('created_by', auth()->id())->findOrFail($id); // FIXED
//         $agenda->delete();

//         return response()->json([
//             'message' => 'Agenda berhasil dihapus',
//         ]);
//     }

// public function publicAgendas(Request $request)
// {
//     $query = MyAgenda::with('creator')
//         ->withTrashed()
//         ->where('is_show_to_other', true)
//         ->where('created_by', '!=', $request->user()->id);

//     // Tambah filter tanggal
//     if ($request->start_date) {
//         $query->whereDate('start_at', '>=', $request->start_date);
//     }

//     if ($request->end_date) {
//         $query->whereDate('start_at', '<=', $request->end_date);
//     }

//     $agendas = $query->orderBy('start_at', 'desc')
//         ->paginate($request->per_page ?? 15);

//     return response()->json($agendas);
// }

// }

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Jobs\SendWhatsAppNotification;
use App\Models\MyAgenda;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MyAgendaController extends Controller
{
    // ========== OPTIMIZED INDEX WITH EAGER LOADING ==========
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('super_admin') || $user->hasRole('kepala') || $user->hasRole('ketua_tim');
        
        $query = MyAgenda::query()
            ->withTrashed() // Include soft deleted
            ->with('creator:id,name,email'); // Eager loading
        
        // Admin roles can see ALL agendas
        if ($isAdmin) {
            // Filter by specific user if requested
            if ($request->user_id) {
                $query->ownedBy($request->user_id);
            }
            // Else show all agendas
        } else {
            // Regular users only see their own agendas
            $query->ownedBy($user->id);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->whereDate('start_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('start_at', '<=', $request->end_date);
        }

        // Gunakan simplePaginate untuk performa lebih baik
        $agendas = $query->orderBy('start_at', 'desc')
            ->simplePaginate($request->per_page ?? 15);

        return response()->json($agendas);
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser->hasRole('super_admin') || $currentUser->hasRole('kepala') || $currentUser->hasRole('ketua_tim');
        
        $validated = $request->validate([
            'start_at' => 'required|date',
            'until_at' => 'required|date|after:start_at',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'is_show_to_other' => 'required|boolean',
            'user_id' => 'nullable|exists:users,id', // Admin can set target user
        ]);

        // Determine the owner of the agenda
        $ownerId = auth()->id();
        if ($isAdmin && $request->user_id) {
            $ownerId = $request->user_id;
        }

        $agenda = MyAgenda::create([
            'title' => $validated['title'],
            'start_at' => $validated['start_at'],
            'until_at' => $validated['until_at'],
            'description' => $validated['description'] ?? null,
            'is_show_to_other' => $validated['is_show_to_other'],
            'created_by' => $ownerId,
        ]);

        // Load relationship untuk response
        $agenda->load('creator:id,name,email');

        // Send WhatsApp notification to the agenda owner
        $targetUser = \App\Models\User::find($ownerId);
        if ($targetUser && $targetUser->whatsapp_number) {
            $creatorName = $ownerId !== auth()->id() ? " (oleh: {$currentUser->name})" : "";
            $message = "📝 *AGENDA PRIBADI BARU*{$creatorName}\n\n" .
                      "📌 {$agenda->title}\n" .
                      "📅 " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                      "🕐 " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . "\n" .
                      ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
                      "\n_Agenda pribadi Anda telah dibuat._";

            SendWhatsAppNotification::dispatch(
                $targetUser->whatsapp_number,
                $message,
                'my_agenda',
                'created',
                $agenda->id
            );
        }

        return response()->json([
            'message' => 'Agenda berhasil dibuat',
            'agenda' => $agenda, // status otomatis ter-append
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('super_admin') || $user->hasRole('kepala') || $user->hasRole('ketua_tim');
        
        $query = MyAgenda::withTrashed()
            ->with('creator:id,name,email');
        
        // Admin can view any agenda
        if (!$isAdmin) {
            $query->ownedBy(auth()->id());
        }
        
        $agenda = $query->findOrFail($id);

        return response()->json($agenda);
    }

    public function update(Request $request, $id)
    {
        $agenda = MyAgenda::withTrashed()
            ->ownedBy($request->user()->id)
            ->findOrFail($id);
        
        // Cek status - hanya bisa edit jika comming_soon
        if ($agenda->status !== 'comming_soon') {
            return response()->json([
                'message' => 'Agenda tidak dapat diedit karena sudah berlangsung atau selesai'
            ], 403);
        }

        $validated = $request->validate([
            'start_at' => 'sometimes|date',
            'until_at' => 'sometimes|date|after:start_at',
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'is_show_to_other' => 'sometimes|boolean',
        ]);

        $agenda->update($validated);
        $agenda->load('creator:id,name,email');

        return response()->json([
            'message' => 'Agenda berhasil diperbarui',
            'agenda' => $agenda,
        ]);
    }

    // ========== SOFT DELETE (CANCEL) ==========
    public function destroy($id)
    {
        $agenda = MyAgenda::ownedBy(auth()->id())->findOrFail($id);
        
        // Cek status - hanya bisa cancel jika comming_soon
        if ($agenda->status !== 'comming_soon') {
            return response()->json([
                'message' => 'Agenda tidak dapat dibatalkan karena sudah berlangsung atau selesai'
            ], 403);
        }
        
        $agenda->delete(); // Soft delete

        return response()->json([
            'message' => 'Agenda berhasil dibatalkan',
        ]);
    }

    // ========== RESTORE CANCELED AGENDA ==========
    public function restore($id)
    {
        $agenda = MyAgenda::onlyTrashed()
            ->ownedBy(auth()->id())
            ->findOrFail($id);

        $agenda->restore();

        return response()->json([
            'message' => 'Agenda berhasil dipulihkan',
            'agenda' => $agenda,
        ]);
    }

    // ========== PUBLIC AGENDAS ==========
    public function publicAgendas(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('super_admin') || $user->hasRole('kepala') || $user->hasRole('ketua_tim');
        
        $query = MyAgenda::query()
            ->withTrashed()
            ->with('creator:id,name,email')
            ->where('created_by', '!=', $user->id); // Exclude own agendas
        
        // Admin roles can see ALL other users' agendas
        // Regular users only see public agendas
        if (!$isAdmin) {
            $query->publicAgendas(); // Scope for is_show_to_other = true
        }

        if ($request->start_date) {
            $query->whereDate('start_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('start_at', '<=', $request->end_date);
        }

        $agendas = $query->orderBy('start_at', 'desc')
            ->simplePaginate($request->per_page ?? 15);

        return response()->json($agendas);
    }
}
