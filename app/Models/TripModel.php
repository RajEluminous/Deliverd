<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripModel extends Model 
{
	use SoftDeletes; 
    protected $table 		= 'trip'; 

    protected $fillable = [ 
        'driver_id',
        'vehicle_id',
        'start_time',
        'completion_time',
        'status',
    ];
    protected $dates = ['deleted_at']; 

    public function hasDeliveries(){
    	return $this->hasMany(TripHasDeliveriesModel::class, 'trip_id','id');
    }

    public function assignedDriver(){
        return $this->belongsTo(DriverModel::class, 'driver_id', 'id');
    }

    public function assignedVehicle(){
        return $this->belongsTo(VehicleModel::class, 'vehicle_id', 'id');
    }

    public function assignedFleet(){
        return $this->belongsTo(FleetModel::class, 'fleet_id', 'id');
    }
}
