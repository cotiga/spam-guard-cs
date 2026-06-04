<?php

namespace Cotiga\SpamGuard\Models;

use Illuminate\Database\Eloquent\Model;

class BannedIp extends Model
{
    protected $table = 'spam_guard_banned_ips';

    protected $fillable = ['ip'];
}
