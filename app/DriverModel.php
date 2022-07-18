<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class DriverModel extends Authenticatable 
{
   use HasApiTokens,Notifiable;

    protected $table 		= 'driver'; 

    protected $fillable = [ 
        'first_name',
		'last_name',
        'email',
		'password',
        'licence_no', 
		'mobile',
		'uniqstring',
		'profile_img_str', 
		'licence_img_str', 
		'identification_img_str', 
		'criminaldoc_img_str', 
		'is_verified',
        'status',
		'is_deleted',
		'created_at',
		'updated_at'
    ];
}
