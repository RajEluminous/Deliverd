<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model 
{
    protected $table = 'vehicles'; 
	
    protected $fillable = [ 
        'type',
        'make',
        'model',
		'year',
		'registration_no',
		'boot_capacity',	
        'profile_img_str_1',
		'profile_img_str_2',
		'profile_img_str_3',
		'profile_img_str_4',
		'profile_img_str_5',
		'licensedisk_img_str',
		'insurance_img_str',
		'status',
		'is_deleted',
		'is_verified',
		'created_at',
		'updated_at'
    ];
    
}
