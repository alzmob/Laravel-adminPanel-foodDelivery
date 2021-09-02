<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCampaign extends Model
{
    use HasFactory;

    protected $dates = ['created_at', 'updated_at', 'start_date', 'end_date', 'start_time', 'end_time'];

    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'discount' => 'float',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orderdetails()
    {
        return $this->hasMany(OrderDetail::class)->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }
    
    public function scopeRunning($query)
    {
        return $query->whereDate('end_date', '>=', date('Y-m-d'));
    }
    // public function reviews()
    // {
    //     return $this->hasMany(Review::class)->latest();
    // }

    // public function rating()
    // {
    //     return $this->hasMany(Review::class)
    //         ->select(DB::raw('avg(rating) average, item_campaign_id'))
    //         ->groupBy('item_campaign_id');
    // }
}
