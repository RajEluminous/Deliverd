<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Delivery;

class TripHasDeliveriesModel extends Model 
{
    protected $table 		= 'trip_has_deliveries';

    protected $fillable = [
        'trip_id',
        'delivery_id', 
        'delivery_type', 
        'status', 
    ];

    public function assignedTrip()
    {
        return $this->belongsTo(TripModel::class, 'trip_id', 'id');
    } 

    public function assignedDelivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'id');
    }
}
