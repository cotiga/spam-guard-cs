<?php

namespace Cotiga\SpamGuard\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorIgnored extends Model
{
    protected $table = 'spam_guard_error_ignoreds';

    protected $fillable = ['pattern'];
}
