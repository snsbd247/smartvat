<?php

namespace App\Modules\Notification\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use BelongsToCompany;

    protected $table = 'app_notifications';

    protected $fillable = ['company_id', 'user_id', 'type', 'title', 'message', 'link', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
