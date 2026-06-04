<?php

namespace Cotiga\SpamGuard\Models;

use Illuminate\Database\Eloquent\Model;

class HttpError extends Model
{
    protected $table = 'spam_guard_errors';

    protected $fillable = [
        'status_code',
        'url',
        'ip',
        'user_agent',
        'error_message',
        'count',
    ];
}
