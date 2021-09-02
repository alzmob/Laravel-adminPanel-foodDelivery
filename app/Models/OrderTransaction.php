<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTransaction extends Model
{
    use HasFactory;

    public function scopeNotRefunded($query)
    {
        return $query->where(function($query){
            $query->where('status', '!=', 'refunded')->orWhereNull('status');
        });
    }
}
