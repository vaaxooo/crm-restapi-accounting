<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceHistory extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'office_id',
        'amount',
        'currency',
        'exchange_rate',
        'date'
    ];
}
