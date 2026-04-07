<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliationCode extends Model
{
    use SoftDeletes;

    protected $fillable=['user_id','code','status'];

    protected $casts = ['deleted_at' => 'datetime'];

    public function AffiliateUser(){
        return $this->belongsTo(User::class,'user_id');
    }
}
