<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class OfficeAgenda extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'start_at',
        'until_at',
        'agenda_type',
        'activity_type',
        'metting_link',
        'location',
        'room_id',
        'description',
        'attachment_links',
        'created_by',
        'is_approved',
        'approved_by',
        'approved_at',
        'updated_by',
        'updated_at_by_user',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'until_at' => 'datetime',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'updated_at_by_user' => 'datetime',
        'attachment_links' => 'array',
    ];

    // APPEND status ke response JSON
    protected $appends = ['status'];

    // ========== ACCESSOR: STATUS DINAMIS ==========
    public function getStatusAttribute(): string
    {
        if ($this->trashed()) {
            return 'cancelled';
        }

        // Cek approval - jika belum di-approve, status pending
        if (!$this->is_approved) {
            return 'pending';
        }

        $now = Carbon::now();
        $startAt = Carbon::parse($this->start_at);
        $untilAt = Carbon::parse($this->until_at);

        if ($now->greaterThan($untilAt)) {
            return 'completed';
        }

        if ($now->between($startAt, $untilAt)) {
            return 'in_progress';
        }

        return 'comming_soon';
    }

    // ========== SCOPES ==========

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_at', [$startDate, $endDate]);
    }

    public function scopeByAgendaType($query, $type)
    {
        return $query->where('agenda_type', $type);
    }

    // Relasi (existing)
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function participants()
    {
        return $this->belongsToMany(
            Participant::class,
            'office_agenda_participant',
            'office_agenda_id',
            'participant_id'
        )->whereNotNull('participant_id');
    }

    public function userParticipants()
    {
        return $this->belongsToMany(
            User::class,
            'office_agenda_participant',
            'office_agenda_id',
            'user_id'
        )->whereNotNull('user_id');
    }

    public function attachments()
    {
        return $this->belongsToMany(
            Attachment::class,
            'office_agenda_attachment',
            'office_agenda_id',
            'attachment_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}