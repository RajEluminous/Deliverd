<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetHasDriversModel extends Model 
{
    protected $table 		= 'fleet_has_drivers'; 

    protected $fillable = [ 
        'fleet_id',
        'driver_id',
    ];
    
}
