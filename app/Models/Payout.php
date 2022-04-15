<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;
    public $timestamps = true;

    /**
     * @var string[]
     */
    protected $fillable = [
        'office_id',
        'date',
        'amount',
        'currency',
        'exchange_rate',
        'percent',
        'comment'
    ];
}
