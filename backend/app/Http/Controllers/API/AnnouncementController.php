<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\Attachment;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppNotification;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::with(['attachments', 'creator'])
            ->when($request->is_notification !== null, fn($q) => $q->where('is_notification', $request->is_notification))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($announcements);
    }

// public function store(Request $request)
// {
//     $validated = $request->validate([
//         'title' => 'required|string',
//         'content' => 'required|string',
//         'is_displayed' => 'boolean',
//         'attachments' => 'nullable|array',
//         'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
//     ]);

//     DB::beginTransaction();
//     try {
//         // HANYA 1 CREATE
//         $announcement = Announcement::create([
//             'title' => $validated['title'],
//             'content' => $validated['content'],
//             'is_displayed' => $validated['is_displayed'] ?? true,
//             'created_by' => auth()->id(),
//         ]);

//         // Handle attachments
//         if ($request->hasFile('attachments')) {
//             $attachmentIds = [];
//             foreach ($request->file('attachments') as $file) {
//                 $path = $file->store('attachments', 'public');
//                 $attachment = Attachment::create([
//                     'file_name' => $file->getClientOriginalName(),
//                     'file_path' => $path,
//                     'file_type' => $file->getMimeType(),
//                     'file_size' => $file->getSize(),
//                 ]);
//                 $attachmentIds[] = $attachment->id;
//             }
//             $announcement->attachments()->attach($attachmentIds);
//         }

//         // Send WhatsApp - HANYA 1X
//         $users = User::whereNotNull('whatsapp_number')->get();
//         foreach ($users as $user) {
//             $message = "📢 *PENGUMUMAN BARU*\n\n" .
//                       "📌 {$announcement->title}\n\n" .
//                       "{$announcement->content}\n\n" .
//                       "_Pengumuman dari: " . auth()->user()->name . "_";

//             SendWhatsAppNotification::dispatch(
//                 $user->whatsapp_number,
//                 $message,
//                 'announcement',
//                 'created',
//                 $announcement->id
//             );
//         }

//         DB::commit();

//         return response()->json([
//             'message' => 'Pengumuman berhasil dibuat',
//             'announcement' => $announcement->load('attachments'),
//         ], 201);

//     } catch (\Exception $e) {
//         DB::rollBack();
//         return response()->json(['message' => $e->getMessage()], 500);
//     }
// }


public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string',
        'content' => 'required|string',
        'is_notification' => 'boolean',
        'attachment_links' => 'nullable|array',
        'attachment_links.*' => 'nullable|url|max:500',
        'attachments' => 'nullable|array',
        'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
    ]);

    DB::beginTransaction();
    try {
        // Filter empty links
        $attachmentLinks = isset($validated['attachment_links']) 
            ? array_values(array_filter($validated['attachment_links'], fn($link) => !empty($link))) 
            : null;
        
        // HANYA 1 CREATE
        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'attachment_links' => $attachmentLinks,
            'is_notification' => $validated['is_notification'] ?? false,
            'created_by' => auth()->id(),
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            $attachmentIds = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');
                $attachment = Attachment::create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
                $attachmentIds[] = $attachment->id;
            }
            $announcement->attachments()->attach($attachmentIds);
        }

        // Send WhatsApp - HANYA jika is_notification = true
        if ($announcement->is_notification) {
            $users = User::whereNotNull('whatsapp_number')->get();
            foreach ($users as $user) {

                // --- CLEAN HTML QUILL → WA FRIENDLY ---
                // ubah <br> jadi newline
                $cleanContent = preg_replace('/<br\s*\/?>/i', "\n", $announcement->content);

                // hapus semua tag HTML lain
                $cleanContent = strip_tags($cleanContent);

                // konversi bold
                $cleanContent = str_replace(['<strong>', '<b>'], '*', $cleanContent);
                $cleanContent = str_replace(['</strong>', '</b>'], '*', $cleanContent);

                // konversi italic
                $cleanContent = str_replace(['<em>', '<i>'], '_', $cleanContent);
                $cleanContent = str_replace(['</em>', '</i>'], '_', $cleanContent);

                // rapikan newline berlebihan
                $cleanContent = preg_replace('/\n{3,}/', "\n\n", $cleanContent);

                // trim whitespace
                $cleanContent = trim($cleanContent);
                // --- END CLEAN ---

                $message = "📢 *PENGUMUMAN BARU*\n\n" .
                    "📌 *{$announcement->title}*\n\n" .
                    "{$cleanContent}\n\n" .
                    "_Pengumuman dari: " . auth()->user()->name . "_";

                SendWhatsAppNotification::dispatch(
                    $user->whatsapp_number,
                    $message,
                    'announcement',
                    'created',
                    $announcement->id
                );
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Pengumuman berhasil dibuat',
            'announcement' => $announcement->load('attachments'),
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => $e->getMessage()], 500);
    }
}


public function show($id)
{
$announcement = Announcement::with(['attachments', 'creator'])->findOrFail($id);
return response()->json($announcement);
}

// public function update(Request $request, $id)
// {
// $announcement = Announcement::findOrFail($id);

// $validated = $request->validate([
// 'title' => 'sometimes|string',
// 'content' => 'sometimes|string',
// 'is_displayed' => 'boolean',
// 'attachments' => 'nullable|array',
// 'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
// ]);

// $announcement->update($validated);

// // Handle new attachments
// if ($request->hasFile('attachments')) {
// $attachmentIds = [];
// foreach ($request->file('attachments') as $file) {
// $path = $file->store('attachments', 'public');
// $attachment = Attachment::create([
// 'file_name' => $file->getClientOriginalName(),
// 'file_path' => $path,
// 'file_type' => $file->getMimeType(),
// 'file_size' => $file->getSize(),
// ]);
// $attachmentIds[] = $attachment->id;
// }
// // Sync akan replace semua attachment lama dengan yang baru
// // Atau gunakan attach() jika mau tambah tanpa hapus yang lama
// $announcement->attachments()->sync($attachmentIds);
// }

// return response()->json([
// 'message' => 'Pengumuman berhasil diperbarui',
// 'announcement' => $announcement->load('attachments'),
// ]);
// }

public function update(Request $request, $id)
{
$announcement = Announcement::findOrFail($id);

$validated = $request->validate([
'title' => 'sometimes|string',
'content' => 'sometimes|string',
'is_notification' => 'boolean',
'attachment_links' => 'nullable|array',
'attachment_links.*' => 'nullable|url|max:500',
'attachments' => 'nullable|array',
'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
'keep_attachment_ids' => 'nullable|array',
'keep_attachment_ids.*' => 'integer|exists:attachments,id',
]);

// Filter empty links
$attachmentLinks = isset($validated['attachment_links']) 
    ? array_values(array_filter($validated['attachment_links'], fn($link) => !empty($link))) 
    : $announcement->attachment_links;

// Update data announcement
$announcement->update([
'title' => $validated['title'] ?? $announcement->title,
'content' => $validated['content'] ?? $announcement->content,
'attachment_links' => $attachmentLinks,
'is_notification' => $validated['is_notification'] ?? $announcement->is_notification,
]);

// Ambil ID attachment yang akan dipertahankan
$keepIds = $request->input('keep_attachment_ids', []);

// Hapus attachment yang tidak ada di keep_attachment_ids
$currentAttachments = $announcement->attachments->pluck('id')->toArray();
$toRemove = array_diff($currentAttachments, $keepIds);

foreach ($toRemove as $attachmentId) {
$attachment = Attachment::find($attachmentId);
if ($attachment) {
// Hapus file dari storage
Storage::disk('public')->delete($attachment->file_path);

// Detach dari announcement
$announcement->attachments()->detach($attachmentId);

// Hapus record jika tidak dipakai announcement lain
if ($attachment->announcements()->count() === 0) {
$attachment->delete();
}
}
}

// Handle attachment baru
if ($request->hasFile('attachments')) {
$newAttachmentIds = [];
foreach ($request->file('attachments') as $file) {
$path = $file->store('attachments', 'public');
$attachment = Attachment::create([
'file_name' => $file->getClientOriginalName(),
'file_path' => $path,
'file_type' => $file->getMimeType(),
'file_size' => $file->getSize(),
]);
$newAttachmentIds[] = $attachment->id;
}
// Attach attachment baru tanpa menghapus yang lama
$announcement->attachments()->attach($newAttachmentIds);
}

return response()->json([
'message' => 'Pengumuman berhasil diperbarui',
'announcement' => $announcement->load('attachments'),
]);
}

public function destroy($id)
{
$announcement = Announcement::findOrFail($id);

// Hapus file attachments dari storage
foreach ($announcement->attachments as $attachment) {
Storage::disk('public')->delete($attachment->file_path);

// Hapus record attachment jika tidak dipakai announcement lain
if ($attachment->announcements()->count() === 1) {
$attachment->delete();
}
}

$announcement->delete();

return response()->json([
'message' => 'Pengumuman berhasil dihapus',
]);
}
}
