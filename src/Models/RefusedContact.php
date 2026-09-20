<?php

namespace Cotiga\SpamGuard\Models;

use Illuminate\Database\Eloquent\Model;

class RefusedContact extends Model
{
    protected $table = 'spam_guard_refused_contacts';

    protected $fillable = [
        'form_name',
        'mel',
        'nom',
        'ip',
        'pays',
        'raison',
        'message',
    ];
}
