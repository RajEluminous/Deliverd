<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverHasVehicleModel extends Model 
{
    protected $table 		= 'driver_has_vehicle'; 

    protected $fillable = [ 
        'driver_id',
        'vehicle_id',
        'latitude',
        'longitude',
        'status',
    ];
    
}
