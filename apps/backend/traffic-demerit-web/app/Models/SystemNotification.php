<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = [
        'recipient_user_id',
        'recipient_role',
        'channel',
        'title',
        'message',
        'context',
        'related_type',
        'related_id',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'context' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
