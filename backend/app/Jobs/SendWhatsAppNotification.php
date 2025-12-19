<?php

namespace App\Jobs;

use App\Models\WhatsAppLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendWhatsAppNotification implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Disable retry since WA messages often sent even if response parsing fails
    public $tries = 1;
    public $timeout = 30;
    
    // Unique for 60 seconds to prevent duplicate dispatch
    public $uniqueFor = 60;

    protected $phoneNumber;
    protected $message;
    protected $type;
    protected $trigger;
    protected $relatedId;
    
    // Track if we've already logged to prevent duplicates
    private $hasLogged = false;

    public function __construct($phoneNumber, $message, $type, $trigger, $relatedId = null)
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->type = $type;
        $this->trigger = $trigger;
        $this->relatedId = $relatedId;
    }

    /**
     * Unique ID for this job (prevents duplicate jobs)
     */
    public function uniqueId(): string
    {
        return $this->phoneNumber . '_' . $this->type . '_' . $this->trigger . '_' . ($this->relatedId ?? 'null');
    }

    public function handle()
    {
        $whatsappServiceUrl = env('WHATSAPP_SERVICE_URL', 'http://localhost:3030');
        $formattedPhone = $this->formatPhoneNumber($this->phoneNumber);

        Log::info("Sending WhatsApp to {$formattedPhone}", [
            'original' => $this->phoneNumber,
            'type' => $this->type,
            'trigger' => $this->trigger
        ]);

        try {
            $response = Http::timeout(15)->post("{$whatsappServiceUrl}/send-message", [
                'phone' => $formattedPhone,
                'message' => $this->message,
            ]);

            $responseData = $response->json() ?? [];
            
            // Check multiple success conditions
            $isSuccess = $response->successful() && 
                         (($responseData['success'] ?? false) === true || 
                          isset($responseData['message']));

            if ($isSuccess) {
                $this->logNotification('success', null);
                Log::info("WhatsApp sent successfully to {$formattedPhone}");
                return; // SUCCESS - don't throw, job completes normally
            }

            // If we get here, response was not successful
            $errorMessage = $responseData['error'] ?? $responseData['message'] ?? $response->body() ?? 'Unknown error';
            $this->logNotification('failed', $errorMessage);
            Log::error("WhatsApp failed to {$formattedPhone}: {$errorMessage}");
            
            // Don't throw - just log the failure. Throwing causes FAIL status and potential retry confusion

        } catch (\Exception $e) {
            // Only log if we haven't already
            if (!$this->hasLogged) {
                $this->logNotification('failed', $e->getMessage());
            }
            Log::error("WhatsApp exception for {$this->phoneNumber}: " . $e->getMessage());
            
            // Rethrow so Laravel marks job as failed
            throw $e;
        }
    }

    protected function formatPhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // Add 62 if not present (Indonesia country code)
        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        // Add WhatsApp suffix
        return $phone . '@s.whatsapp.net';
    }

    protected function logNotification($status, $errorMessage = null)
    {
        // Prevent double logging
        if ($this->hasLogged) {
            return;
        }
        
        $this->hasLogged = true;
        
        try {
            WhatsAppLog::create([
                'phone_number' => $this->phoneNumber,
                'message' => $this->message,
                'type' => $this->type,
                'trigger' => $this->trigger,
                'related_id' => $this->relatedId,
                'status' => $status,
                'error_message' => $errorMessage,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log WhatsApp notification: " . $e->getMessage());
        }
    }

    /**
     * Handle a job failure - only called if handle() throws exception
     */
    public function failed(\Throwable $exception)
    {
        Log::error("WhatsApp job failed permanently for {$this->phoneNumber}", [
            'exception' => $exception->getMessage(),
            'type' => $this->type,
            'trigger' => $this->trigger
        ]);

        // Only log if handle() didn't already log
        if (!$this->hasLogged) {
            $this->logNotification('failed', "Job failed: " . $exception->getMessage());
        }
    }
}