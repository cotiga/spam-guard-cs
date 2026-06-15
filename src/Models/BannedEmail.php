<?php

namespace Cotiga\SpamGuard\Models;

use Illuminate\Database\Eloquent\Model;

class BannedEmail extends Model
{
    protected $table = 'spam_guard_banned_emails';

    protected $fillable = ['mel'];
}
