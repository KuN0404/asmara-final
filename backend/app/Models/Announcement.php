<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcement';

    protected $fillable = [
        'title',
        'content',
        'attachment_links',
        'is_notification',
        'created_by',
    ];

    protected $casts = [
        'is_notification' => 'boolean',
        'attachment_links' => 'array',
    ];

    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'announcement_attachment');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}