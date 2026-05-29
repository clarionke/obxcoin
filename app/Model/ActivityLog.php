<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['action','user_id','source','ip_address','location','created_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
