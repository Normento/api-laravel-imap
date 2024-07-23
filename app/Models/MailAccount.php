<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MailAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'password',
        'imap_host',
        'imap_port',
        'encryption',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
