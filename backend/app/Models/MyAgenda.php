<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class MyAgenda extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'start_at',
        'until_at',
        'title',
        'description',
        'is_show_to_other',
        'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'until_at' => 'datetime',
        'is_show_to_other' => 'boolean',
    ];

    // APPEND status ke response JSON
    protected $appends = ['status'];

    // ========== ACCESSOR: STATUS DINAMIS ==========
    public function getStatusAttribute(): string
    {
        // 1. Cek soft delete (prioritas tertinggi)
        if ($this->trashed()) {
            return 'cancelled';
        }

        $now = Carbon::now();
        $startAt = Carbon::parse($this->start_at);
        $untilAt = Carbon::parse($this->until_at);

        // 2. Agenda sudah selesai
        if ($now->greaterThan($untilAt)) {
            return 'completed';
        }

        // 3. Agenda sedang berlangsung
        if ($now->between($startAt, $untilAt)) {
            return 'in_progress';
        }

        // 4. Agenda akan datang
        return 'comming_soon';
    }

    // ========== SCOPES ==========

    // Filter by date range
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_at', [$startDate, $endDate]);
    }

    // Filter hanya milik user tertentu
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    // Filter yang public (visible to others)
    public function scopePublicAgendas($query)
    {
        return $query->where('is_show_to_other', true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
