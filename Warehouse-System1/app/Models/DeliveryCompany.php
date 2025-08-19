<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryCompany extends Model
{
    use HasUuids;
    protected $guarded = ['id'];


    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function driver(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function order(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
