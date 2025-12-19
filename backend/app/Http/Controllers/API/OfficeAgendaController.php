<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OfficeAgenda;
use App\Models\Attachment;
use App\Models\User;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppNotification;
use Carbon\Carbon;

class OfficeAgendaController extends Controller
{
    // public function index(Request $request)
    // {
    //     try {
    //         $query = OfficeAgenda::query();

    //         $query->with([
    //             'room:id,name,capacity',
    //             'creator:id,name,email',
    //             'participants' => function($q) {
    //                 $q->select('participants.id', 'participants.name', 'participants.email', 'participants.organization');
    //             },
    //             'userParticipants' => function($q) {
    //                 $q->select('users.id', 'users.name', 'users.email');
    //             },
    //             'attachments' => function($q) {
    //                 $q->select('attachments.id', 'attachments.file_name', 'attachments.file_path', 'attachments.file_type', 'attachments.file_size');
    //             }
    //         ]);

    //         if ($request->has('start_date')) {
    //             $query->whereDate('start_at', '>=', $request->start_date);
    //         }

    //         if ($request->has('end_date')) {
    //             $query->whereDate('start_at', '<=', $request->end_date);
    //         }


    //         if ($request->has('agenda_type')) {
    //             $query->where('agenda_type', $request->agenda_type);
    //         }

    //         $perPage = $request->get('per_page', 15);

    //         if ($perPage == 500) {
    //             $agendas = $query->orderBy('start_at', 'desc')->get();
    //             return response()->json(['data' => $agendas]);
    //         }

    //         $agendas = $query->orderBy('start_at', 'desc')->paginate($perPage);

    //         return response()->json($agendas);
    //     } catch (\Exception $e) {
    //         Log::error('Error loading agendas: ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Failed to load agendas',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


       // ========== OPTIMIZED INDEX ==========
    public function index(Request $request)
    {
        try {
            $query = OfficeAgenda::query()
                ->withTrashed() // Include cancelled
                ->with([ // Eager loading untuk hindari N+1
                    'room:id,name,capacity',
                    'creator:id,name,email',
                    'approver:id,name,email',
                    'updater:id,name,email',
                    'participants:id,name,email,organization',
                    'userParticipants:id,name,email',
                    'attachments:id,file_name,file_path,file_type,file_size'
                ]);

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('start_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('start_at', '<=', $request->end_date);
            }

            if ($request->has('agenda_type')) {
                $query->byAgendaType($request->agenda_type);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);

            if ($perPage == 500) {
                $agendas = $query->orderBy('start_at', 'desc')->get();
                return response()->json(['data' => $agendas]);
            }

            $agendas = $query->orderBy('start_at', 'desc')
                ->simplePaginate($perPage);

            return response()->json($agendas);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load agendas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== SOFT DELETE (CANCEL) ==========
    public function destroy($id)
    {
        try {
            $officeAgenda = OfficeAgenda::findOrFail($id);
            
            // Cek status - hanya bisa cancel jika comming_soon atau pending
            if (!in_array($officeAgenda->status, ['comming_soon', 'pending'])) {
                return response()->json([
                    'message' => 'Agenda tidak dapat dibatalkan karena sudah berlangsung atau selesai'
                ], 403);
            }
            
            // Kirim notifikasi pembatalan HANYA jika sudah approved sebelumnya
            if ($officeAgenda->is_approved) {
                $userParticipantIds = $officeAgenda->userParticipants->pluck('id')->toArray();
                $participantIds = $officeAgenda->participants->pluck('id')->toArray();
                $this->sendAgendaNotification($officeAgenda, 'cancelled', $userParticipantIds, $participantIds);
            }
            
            $officeAgenda->delete(); // Soft delete

            return response()->json([
                'message' => 'Agenda berhasil dibatalkan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel agenda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_at' => 'required|date',
                'until_at' => 'required|date|after:start_at',
                'agenda_type' => 'required|string|in:meeting,event,training,conference,other',
                'activity_type' => 'required|string|in:online,offline,hybrid',
                'location' => 'nullable|string|max:255',
                'room_id' => 'nullable|integer|exists:rooms,id',
                'metting_link' => 'nullable|url|max:500',
                'description' => 'nullable|string',
                'attachment_links' => 'nullable|array',
                'attachment_links.*' => 'nullable|url|max:500',
                'participant_ids' => 'nullable|array',
                'participant_ids.*' => 'integer|exists:participants,id',
                'user_participant_ids' => 'required|array|min:1',
                'user_participant_ids.*' => 'integer|exists:users,id',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:2048|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
            ]);

            DB::beginTransaction();

            $agendaData = [
                'title' => $validated['title'],
                'start_at' => $validated['start_at'],
                'until_at' => $validated['until_at'],
                'agenda_type' => $validated['agenda_type'],
                'activity_type' => $validated['activity_type'],
                'location' => $validated['location'] ?? null,
                'created_by' => auth()->id(),
            ];

            // Cek role pembuat untuk auto-approve
            $user = auth()->user();
            $isAutoApproved = $user->hasRole('super_admin') || $user->hasRole('kepala');
            
            if ($isAutoApproved) {
                $agendaData['is_approved'] = true;
                $agendaData['approved_by'] = auth()->id();
                $agendaData['approved_at'] = now();
            } else {
                $agendaData['is_approved'] = false;
            }

            if (isset($validated['room_id'])) {
                $agendaData['room_id'] = $validated['room_id'];
            }
            if (isset($validated['metting_link'])) {
                $agendaData['metting_link'] = $validated['metting_link'];
            }
            if (isset($validated['description'])) {
                $agendaData['description'] = $validated['description'];
            }
            
            // Handle attachment links - filter empty values
            if (isset($validated['attachment_links'])) {
                $agendaData['attachment_links'] = array_values(array_filter($validated['attachment_links'], fn($link) => !empty($link)));
            }

            $agenda = OfficeAgenda::create($agendaData);

            // Attach external participants
            if (!empty($validated['participant_ids'])) {
                foreach ($validated['participant_ids'] as $participantId) {
                    DB::table('office_agenda_participant')->insert([
                        'office_agenda_id' => $agenda->id,
                        'participant_id' => $participantId,
                        'user_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Attach internal participants (users)
            foreach ($validated['user_participant_ids'] as $userId) {
                DB::table('office_agenda_participant')->insert([
                    'office_agenda_id' => $agenda->id,
                    'participant_id' => null,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                $attachmentIds = $this->handleFileUploads($request->file('attachments'));

                foreach ($attachmentIds as $attachmentId) {
                    DB::table('office_agenda_attachment')->insert([
                        'office_agenda_id' => $agenda->id,
                        'attachment_id' => $attachmentId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 🚀 SEND WHATSAPP NOTIFICATION
            if ($agenda->is_approved) {
                // Jika sudah approved, kirim ke partisipan
                $this->sendAgendaNotification($agenda, 'created', $validated['user_participant_ids'], $validated['participant_ids'] ?? []);
            } else {
                // Jika butuh approval, kirim ke kepala
                $this->sendApprovalRequestNotification($agenda, 'created');
            }

            DB::commit();

            $agenda->load([
                'room:id,name,capacity',
                'participants:id,name,email,organization',
                'userParticipants:id,name,email',
                'attachments:id,file_name,file_path,file_type,file_size',
                'creator:id,name,email',
                'approver:id,name,email'
            ]);

            $message = $agenda->is_approved 
                ? 'Agenda berhasil dibuat' 
                : 'Agenda berhasil dibuat dan menunggu persetujuan dari Kepala';

            return response()->json([
                'message' => $message,
                'data' => $agenda
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating agenda: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to create agenda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $agenda = OfficeAgenda::with([
                'room:id,name,capacity',
                'participants:id,name,email,organization',
                'userParticipants:id,name,email',
                'attachments:id,file_name,file_path,file_type,file_size',
                'creator:id,name,email'
            ])->findOrFail($id);

            return response()->json($agenda);
        } catch (\Exception $e) {
            Log::error('Error loading agenda: ' . $e->getMessage());

            return response()->json([
                'message' => 'Agenda not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $officeAgenda = OfficeAgenda::findOrFail($id);
            
            // Cek status - bisa edit jika comming_soon atau pending
            if (!in_array($officeAgenda->status, ['comming_soon', 'pending'])) {
                return response()->json([
                    'message' => 'Agenda tidak dapat diedit karena sudah berlangsung atau selesai'
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'start_at' => 'sometimes|date',
                'until_at' => 'sometimes|date|after:start_at',
                'agenda_type' => 'sometimes|string|in:meeting,event,training,conference,other',
                'activity_type' => 'sometimes|string|in:online,offline,hybrid',
                'location' => 'nullable|string|max:255',
                'room_id' => 'nullable|exists:rooms,id',
                'metting_link' => 'nullable|url|max:500',
                'description' => 'nullable|string',
                'attachment_links' => 'nullable|array',
                'attachment_links.*' => 'nullable|url|max:500',
                'participant_ids' => 'nullable|array',
                'participant_ids.*' => 'exists:participants,id',
                'user_participant_ids' => 'sometimes|array|min:1',
                'user_participant_ids.*' => 'exists:users,id',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:2048|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
            ]);

            DB::beginTransaction();

            $updateData = [];
            if (isset($validated['title'])) $updateData['title'] = $validated['title'];
            if (isset($validated['start_at'])) $updateData['start_at'] = $validated['start_at'];
            if (isset($validated['until_at'])) $updateData['until_at'] = $validated['until_at'];
            if (isset($validated['agenda_type'])) $updateData['agenda_type'] = $validated['agenda_type'];
            if (isset($validated['activity_type'])) $updateData['activity_type'] = $validated['activity_type'];
            if (array_key_exists('location', $validated)) $updateData['location'] = $validated['location'];
            if (array_key_exists('room_id', $validated)) $updateData['room_id'] = $validated['room_id'];
            if (array_key_exists('metting_link', $validated)) $updateData['metting_link'] = $validated['metting_link'];
            if (array_key_exists('description', $validated)) $updateData['description'] = $validated['description'];
            
            // Handle attachment links - filter empty values
            if (array_key_exists('attachment_links', $validated)) {
                $updateData['attachment_links'] = array_values(array_filter($validated['attachment_links'] ?? [], fn($link) => !empty($link)));
            }

            // Track who updated the agenda
            $updateData['updated_by'] = auth()->id();
            $updateData['updated_at_by_user'] = now();

            // Jika agenda sudah di-approve dan user bukan super_admin/kepala, reset ke pending
            $user = auth()->user();
            $isAutoApproved = $user->hasRole('super_admin') || $user->hasRole('kepala');
            $needsReapproval = false;
            
            if ($officeAgenda->is_approved && !$isAutoApproved) {
                // Reset ke pending, perlu approval ulang
                $updateData['is_approved'] = false;
                $updateData['approved_by'] = null;
                $updateData['approved_at'] = null;
                $needsReapproval = true;
            }

            $officeAgenda->update($updateData);
            
            // Kirim notifikasi ke kepala jika butuh approval ulang
            if ($needsReapproval) {
                $this->sendApprovalRequestNotification($officeAgenda, 'updated');
            } elseif ($isAutoApproved && $officeAgenda->is_approved) {
                // Jika kepala/super_admin edit agenda yang sudah approved, kirim notifikasi perubahan ke partisipan
                $userParticipantIds = $officeAgenda->userParticipants->pluck('id')->toArray();
                $participantIds = $officeAgenda->participants->pluck('id')->toArray();
                $this->sendAgendaNotification($officeAgenda, 'updated', $userParticipantIds, $participantIds);
            }

            // Update external participants
            if (isset($validated['participant_ids'])) {
                DB::table('office_agenda_participant')
                    ->where('office_agenda_id', $officeAgenda->id)
                    ->whereNotNull('participant_id')
                    ->delete();

                foreach ($validated['participant_ids'] as $participantId) {
                    DB::table('office_agenda_participant')->insert([
                        'office_agenda_id' => $officeAgenda->id,
                        'participant_id' => $participantId,
                        'user_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update internal participants
            if (isset($validated['user_participant_ids'])) {
                DB::table('office_agenda_participant')
                    ->where('office_agenda_id', $officeAgenda->id)
                    ->whereNotNull('user_id')
                    ->delete();

                foreach ($validated['user_participant_ids'] as $userId) {
                    DB::table('office_agenda_participant')->insert([
                        'office_agenda_id' => $officeAgenda->id,
                        'participant_id' => null,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Handle new file uploads
            if ($request->hasFile('attachments')) {
                $attachmentIds = $this->handleFileUploads($request->file('attachments'));

                foreach ($attachmentIds as $attachmentId) {
                    DB::table('office_agenda_attachment')->insert([
                        'office_agenda_id' => $officeAgenda->id,
                        'attachment_id' => $attachmentId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $officeAgenda->load([
                'room:id,name,capacity',
                'participants:id,name,email,organization',
                'userParticipants:id,name,email',
                'attachments:id,file_name,file_path,file_type,file_size',
                'creator:id,name,email',
                'approver:id,name,email',
                'updater:id,name,email'
            ]);

            return response()->json([
                'message' => 'Agenda updated successfully',
                'data' => $officeAgenda
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update agenda: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update agenda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function destroy($id)
    // {
    //     try {
    //         $officeAgenda = OfficeAgenda::findOrFail($id);
    //         $officeAgenda->delete();

    //         return response()->json([
    //             'message' => 'Agenda deleted successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Failed to delete agenda: ' . $e->getMessage());

    //         return response()->json([
    //             'message' => 'Failed to delete agenda',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    private function handleFileUploads($files)
    {
        $attachmentIds = [];

        foreach ($files as $file) {
            try {
                $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('attachments/agendas', $fileName, 'public');

                $attachment = Attachment::create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                $attachmentIds[] = $attachment->id;
            } catch (\Exception $e) {
                Log::error('Failed to upload file: ' . $e->getMessage());
                continue;
            }
        }

        return $attachmentIds;
    }

    public function deleteAttachment(Request $request, $officeAgendaId)
    {
        $validated = $request->validate([
            'attachment_id' => 'required|exists:attachments,id'
        ]);

        try {
            $attachment = Attachment::findOrFail($validated['attachment_id']);

            DB::table('office_agenda_attachment')
                ->where('office_agenda_id', $officeAgendaId)
                ->where('attachment_id', $attachment->id)
                ->delete();

            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $attachment->delete();

            return response()->json([
                'message' => 'Attachment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete attachment: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== APPROVE AGENDA ==========
    public function approve($id)
    {
        try {
            $agenda = OfficeAgenda::findOrFail($id);
            
            // Cek apakah user bisa approve (super_admin atau kepala)
            $user = auth()->user();
            if (!$user->hasRole('super_admin') && !$user->hasRole('kepala')) {
                return response()->json([
                    'message' => 'Anda tidak memiliki izin untuk menyetujui agenda'
                ], 403);
            }
            
            // Cek apakah sudah approved
            if ($agenda->is_approved) {
                return response()->json([
                    'message' => 'Agenda sudah disetujui sebelumnya'
                ], 400);
            }
            
            $agenda->update([
                'is_approved' => true,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            
            // Kirim notifikasi WA ke semua partisipan
            $userParticipantIds = $agenda->userParticipants->pluck('id')->toArray();
            $participantIds = $agenda->participants->pluck('id')->toArray();
            $this->sendAgendaNotification($agenda, 'approved', $userParticipantIds, $participantIds);
            
            $agenda->load(['room', 'creator', 'approver', 'participants', 'userParticipants', 'attachments']);
            
            return response()->json([
                'message' => 'Agenda berhasil disetujui',
                'data' => $agenda
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyetujui agenda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== REJECT APPROVAL ==========
    public function reject($id)
    {
        try {
            $agenda = OfficeAgenda::findOrFail($id);
            
            // Cek apakah user bisa reject (super_admin atau kepala)
            $user = auth()->user();
            if (!$user->hasRole('super_admin') && !$user->hasRole('kepala')) {
                return response()->json([
                    'message' => 'Anda tidak memiliki izin untuk menolak agenda'
                ], 403);
            }
            
            // Kirim notifikasi ke pembuat bahwa approval ditolak
            $creator = User::find($agenda->created_by);
            if ($creator && $creator->whatsapp_number) {
                $message = "❌ *APPROVAL AGENDA DITOLAK*\n\n" .
                          "📌 {$agenda->title}\n" .
                          "📆 " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                          "🕐 " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n\n" .
                          "_Agenda Anda ditolak oleh: " . auth()->user()->name . "_\n" .
                          "_Silakan hubungi kepala untuk informasi lebih lanjut._";

                SendWhatsAppNotification::dispatch(
                    $creator->whatsapp_number,
                    $message,
                    'office_agenda',
                    'rejected',
                    $agenda->id
                );
            }
            
            // Soft delete agenda yang ditolak
            $agenda->delete();
            
            return response()->json([
                'message' => 'Agenda berhasil ditolak'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menolak agenda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== SEND REMINDER ==========
    public function sendReminder($id)
    {
        try {
            $agenda = OfficeAgenda::with(['userParticipants', 'participants', 'room', 'creator'])->findOrFail($id);
            
            // Hanya bisa kirim reminder untuk agenda yang sudah di-approve
            if (!$agenda->is_approved) {
                return response()->json([
                    'message' => 'Hanya bisa mengirim reminder untuk agenda yang sudah disetujui'
                ], 400);
            }
            
            // Hanya bisa kirim reminder untuk agenda yang belum lewat
            if (Carbon::parse($agenda->start_at)->isPast()) {
                return response()->json([
                    'message' => 'Tidak bisa mengirim reminder untuk agenda yang sudah lewat'
                ], 400);
            }
            
            // Check 3x/1hr limit - jika sudah 3x reminder dalam 1 jam terakhir, block
            if ($agenda->reminder_count >= 3 && $agenda->last_reminder_at) {
                $lastReminderAt = Carbon::parse($agenda->last_reminder_at);
                $nextAllowedTime = $lastReminderAt->addHour();
                
                if (Carbon::now()->lt($nextAllowedTime)) {
                    $minutesRemaining = Carbon::now()->diffInMinutes($nextAllowedTime);
                    return response()->json([
                        'message' => "Sudah mengirim reminder 3x. Silakan tunggu {$minutesRemaining} menit lagi.",
                        'next_allowed_at' => $nextAllowedTime->toIso8601String(),
                        'minutes_remaining' => $minutesRemaining
                    ], 429); // Too Many Requests
                } else {
                    // Reset counter after 1 hour
                    $agenda->reminder_count = 0;
                }
            }
            
            // Build reminder message
            $message = "🔔 *PENGINGAT AGENDA*\n\n" .
                      "📌 {$agenda->title}\n" .
                      "📆 " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                      "🕐 " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                      "📍 {$agenda->location}\n";
            
            if ($agenda->room) {
                $message .= "🏢 Ruangan: {$agenda->room->name}\n";
            }
            
            $message .= "\n_Dikirim oleh: " . auth()->user()->name . "_";
            
            $sentCount = 0;
            
            // Kirim ke user participants (internal)
            foreach ($agenda->userParticipants as $user) {
                if ($user->whatsapp_number) {
                    SendWhatsAppNotification::dispatch(
                        $user->whatsapp_number,
                        $message,
                        'office_agenda',
                        'manual',
                        $agenda->id
                    );
                    $sentCount++;
                }
            }
            
            // Kirim ke external participants
            foreach ($agenda->participants as $participant) {
                if ($participant->phone) {
                    SendWhatsAppNotification::dispatch(
                        $participant->phone,
                        $message,
                        'office_agenda',
                        'manual',
                        $agenda->id
                    );
                    $sentCount++;
                }
            }
            
            // Update reminder tracking
            $agenda->update([
                'reminder_count' => $agenda->reminder_count + 1,
                'last_reminder_at' => now()
            ]);
            
            $remainingReminders = 3 - $agenda->reminder_count - 1;
            $limitMessage = $remainingReminders > 0 
                ? "Sisa {$remainingReminders}x reminder sebelum limit 1 jam." 
                : "Reminder selanjutnya bisa dikirim setelah 1 jam.";
            
            return response()->json([
                'message' => "Reminder berhasil dikirim ke {$sentCount} peserta. {$limitMessage}",
                'sent_count' => $sentCount,
                'reminder_count' => $agenda->reminder_count + 1,
                'remaining_reminders' => max(0, $remainingReminders)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reminder: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengirim reminder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== HELPER: SEND AGENDA NOTIFICATION ==========
    private function sendAgendaNotification($agenda, $type, $userParticipantIds = [], $externalParticipantIds = [])
    {
        $agenda->load(['room', 'creator']);
        
        // Build message body (common part for all recipients)
        $messageBody = "📌 {$agenda->title}\n" .
                      "📆 " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                      "🕐 " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                      "📍 {$agenda->location}\n" .
                      ($agenda->room ? "🚪 Ruangan: {$agenda->room->name}\n" : "") .
                      ($agenda->metting_link ? "🔗 Link: {$agenda->metting_link}\n" : "") .
                      ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
                      "\n_Dibuat oleh: " . ($agenda->creator->name ?? 'Unknown') . "_";
        
        // Headers for different message types
        $typeLabels = [
            'created' => '📅 *AGENDA KANTOR BARU*',
            'approved' => '✅ *AGENDA KANTOR DISETUJUI*',
            'approved_new' => '📅 *AGENDA KANTOR BARU*', // For participants when approved
            'updated' => '📝 *AGENDA KANTOR DIPERBARUI*',
            'cancelled' => '❌ *AGENDA KANTOR DIBATALKAN*',
        ];
        
        // Build messages
        $defaultHeader = $typeLabels[$type] ?? '📅 *AGENDA KANTOR*';
        $defaultMessage = "{$defaultHeader}\n\n{$messageBody}";
        
        // For approved type, prepare different message for participants
        $participantMessage = $defaultMessage;
        if ($type === 'approved') {
            $participantHeader = $typeLabels['approved_new'];
            $participantMessage = "{$participantHeader}\n\n{$messageBody}";
        }
        
        // Kumpulkan penerima
        $recipients = collect();
        $creatorId = $agenda->created_by;
        
        // 1. Partisipan internal yang diajak
        if (!empty($userParticipantIds)) {
            $internalUsers = User::whereIn('id', $userParticipantIds)
                ->whereNotNull('whatsapp_number')
                ->get();
            foreach ($internalUsers as $user) {
                $recipients->push([
                    'user' => $user,
                    'is_creator' => $user->id === $creatorId
                ]);
            }
        }
        
        // 2. Kepala dan Kasubbag (selalu dapat notif)
        $kepalaKasubbag = User::role(['kepala', 'kasubbag'])
            ->whereNotNull('whatsapp_number')
            ->get();
        foreach ($kepalaKasubbag as $user) {
            $recipients->push([
                'user' => $user,
                'is_creator' => $user->id === $creatorId
            ]);
        }
        
        // 3. Hapus duplikat berdasarkan ID
        $recipients = $recipients->unique(fn($item) => $item['user']->id)->values();
        
        // Kirim ke semua penerima internal dengan pesan sesuai
        foreach ($recipients as $recipient) {
            $user = $recipient['user'];
            $isCreator = $recipient['is_creator'];
            
            // Pilih pesan: creator dapat "disetujui", lainnya dapat "baru" (hanya untuk type approved)
            $messageToSend = ($type === 'approved' && !$isCreator) ? $participantMessage : $defaultMessage;
            
            SendWhatsAppNotification::dispatch(
                $user->whatsapp_number,
                $messageToSend,
                'office_agenda',
                $type,
                $agenda->id
            );
        }
        
        // 4. Partisipan eksternal yang diajak (selalu dapat pesan partisipan)
        if (!empty($externalParticipantIds)) {
            $externalParticipants = Participant::whereIn('id', $externalParticipantIds)
                ->whereNotNull('whatsapp_number')
                ->get();
            
            foreach ($externalParticipants as $participant) {
                // Eksternal selalu dapat pesan "baru" saat approved
                $messageToSend = ($type === 'approved') ? $participantMessage : $defaultMessage;
                
                SendWhatsAppNotification::dispatch(
                    $participant->whatsapp_number,
                    $messageToSend,
                    'office_agenda',
                    $type,
                    $agenda->id
                );
            }
        }
    }

    // ========== HELPER: SEND APPROVAL REQUEST NOTIFICATION ==========
    private function sendApprovalRequestNotification($agenda, $type)
    {
        $agenda->load(['room', 'creator']);
        
        $typeLabels = [
            'created' => '📋 *PERMINTAAN PERSETUJUAN AGENDA BARU*',
            'updated' => '📝 *PERMINTAAN PERSETUJUAN ULANG AGENDA*',
        ];
        
        $header = $typeLabels[$type] ?? '📋 *PERMINTAAN PERSETUJUAN AGENDA*';
        
        $message = "{$header}\n\n" .
                  "📌 {$agenda->title}\n" .
                  "📆 " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                  "🕐 " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                  "📍 {$agenda->location}\n" .
                  ($agenda->room ? "🚪 Ruangan: {$agenda->room->name}\n" : "") .
                  ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
                  "\n_Diajukan oleh: " . ($agenda->creator->name ?? 'Unknown') . "_\n\n" .
                  "⏳ _Mohon segera disetujui melalui sistem agenda_";
        
        // Kirim hanya ke kepala
        $kepalaUsers = User::role(['kepala'])
            ->whereNotNull('whatsapp_number')
            ->get();
        
        foreach ($kepalaUsers as $user) {
            SendWhatsAppNotification::dispatch(
                $user->whatsapp_number,
                $message,
                'office_agenda',
                'approval_request',
                $agenda->id
            );
        }
    }
}
