<?php

namespace App\Jobs;

use App\Models\MyAgenda;
use App\Models\OfficeAgenda;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckUpcomingAgendas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reminderType;

    public function __construct($reminderType = 'h_minus_1')
    {
        $this->reminderType = $reminderType; // 'h_minus_1' or 'h_day'
    }

    public function handle()
    {
        Log::info("🔔 Checking {$this->reminderType} reminders...");

        if ($this->reminderType === 'h_minus_1') {
            $this->checkHMinus1Reminders();
        } elseif ($this->reminderType === 'h_day') {
            $this->checkHDayReminders();
        }
    }

    protected function checkHMinus1Reminders()
    {
        $tomorrow = Carbon::tomorrow('Asia/Jakarta');

        // ✅ MODIFIKASI: Hapus filter status, gunakan withTrashed
        // Status otomatis dihitung dari backend

        // H-1 Office Agendas
        $officeAgendas = OfficeAgenda::query()
            ->withTrashed() // Include soft deleted
            ->with(['userParticipants', 'room'])
            ->whereDate('start_at', $tomorrow->toDateString())
            ->get()
            // ✅ Filter di PHP setelah status ter-calculate
            ->filter(function($agenda) {
                // Hanya kirim reminder jika status 'comming_soon'
                // Status otomatis dari accessor getStatusAttribute()
                return $agenda->status === 'comming_soon';
            });

        foreach ($officeAgendas as $agenda) {
            foreach ($agenda->userParticipants as $user) {
                if ($user->whatsapp_number) {
                    $message = "⏰ *REMINDER H-1 AGENDA KANTOR*\n\n" .
                              "📌 {$agenda->title}\n" .
                              "📅 Besok: " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                              "🕐 Jam: " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                              "📍 Lokasi: {$agenda->location}\n" .
                              ($agenda->room ? "🚪 Ruangan: {$agenda->room->name}\n" : "") .
                              ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
                              "\n_Jangan lupa hadir ya! 🙏_";

                    SendWhatsAppNotification::dispatch(
                        $user->whatsapp_number,
                        $message,
                        'office_agenda',
                        'h_minus_1',
                        $agenda->id
                    );
                }
            }
        }

        // H-1 My Agendas
        $myAgendas = MyAgenda::query()
            ->withTrashed()
            ->with('creator')
            ->whereDate('start_at', $tomorrow->toDateString())
            ->get()
            ->filter(function($agenda) {
                return $agenda->status === 'comming_soon';
            });

        foreach ($myAgendas as $agenda) {
            $user = $agenda->creator;
            if ($user && $user->whatsapp_number) {
                $message = "⏰ *REMINDER H-1 AGENDA PRIBADI*\n\n" .
                          "📌 {$agenda->title}\n" .
                          "📅 Besok: " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                          "🕐 Jam: " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                          ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
                          "\n_Persiapkan diri Anda! 💪_";

                SendWhatsAppNotification::dispatch(
                    $user->whatsapp_number,
                    $message,
                    'my_agenda',
                    'h_minus_1',
                    $agenda->id
                );
            }
        }

        Log::info("✅ H-1 reminders sent: {$officeAgendas->count()} office agendas, {$myAgendas->count()} personal agendas");
    }

    protected function checkHDayReminders()
    {
        $today = Carbon::today('Asia/Jakarta');

        // ✅ MODIFIKASI: Sama seperti H-1

        // H-Day Office Agendas
        $officeAgendas = OfficeAgenda::query()
            ->withTrashed()
            ->with(['userParticipants', 'room'])
            ->whereDate('start_at', $today->toDateString())
            ->get()
            ->filter(function($agenda) {
                // Kirim reminder untuk yang 'comming_soon' atau 'in_progress'
                return in_array($agenda->status, ['comming_soon', 'in_progress']);
            });

        foreach ($officeAgendas as $agenda) {
            foreach ($agenda->userParticipants as $user) {
                if ($user->whatsapp_number) {
                    $message = "🔔 *REMINDER HARI INI - AGENDA KANTOR*\n\n" .
                              "📌 {$agenda->title}\n" .
                              "📅 Hari ini: " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                              "🕐 Jam: " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                              "📍 Lokasi: {$agenda->location}\n" .
                              ($agenda->room ? "🚪 Ruangan: {$agenda->room->name}\n" : "") .
                              ($agenda->metting_link ? "🔗 Link: {$agenda->metting_link}\n" : "") .
                              "\n_Agenda akan segera dimulai! ⏰_";

                    SendWhatsAppNotification::dispatch(
                        $user->whatsapp_number,
                        $message,
                        'office_agenda',
                        'h_day',
                        $agenda->id
                    );
                }
            }
        }

        // H-Day My Agendas
        $myAgendas = MyAgenda::query()
            ->withTrashed()
            ->with('creator')
            ->whereDate('start_at', $today->toDateString())
            ->get()
            ->filter(function($agenda) {
                return in_array($agenda->status, ['comming_soon', 'in_progress']);
            });

        foreach ($myAgendas as $agenda) {
            $user = $agenda->creator;
            if ($user && $user->whatsapp_number) {
                $message = "🔔 *REMINDER HARI INI - AGENDA PRIBADI*\n\n" .
                          "📌 {$agenda->title}\n" .
                          "📅 Hari ini: " . Carbon::parse($agenda->start_at)->format('d M Y') . "\n" .
                          "🕐 Jam: " . Carbon::parse($agenda->start_at)->format('H:i') . " - " . Carbon::parse($agenda->until_at)->format('H:i') . " WIB\n" .
                          ($agenda->description ? "\n📝 {$agenda->description}\n" : "") .
                          "\n_Waktunya melaksanakan agenda! 🚀_";

                SendWhatsAppNotification::dispatch(
                    $user->whatsapp_number,
                    $message,
                    'my_agenda',
                    'h_day',
                    $agenda->id
                );
            }
        }

        Log::info("✅ H-Day reminders sent: {$officeAgendas->count()} office agendas, {$myAgendas->count()} personal agendas");
    }
}
