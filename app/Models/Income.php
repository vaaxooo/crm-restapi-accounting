<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasFactory;
    public $timestamps = true;

    /**
     * @var string[]
     */
    protected $fillable = [
        'office_id',
        'date',
        'comment',
        'manager',
        'total_amount',
        'payout',
        'percent',
        'salary'
    ];
}
