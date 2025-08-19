<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelSurcharge extends Model
{
    protected $fillable = [
        'ftl_surcharge',
        'ltl_surcharge',
        'from_date',
        'to_date',
    ];
}
