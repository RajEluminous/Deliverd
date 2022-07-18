<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class FleetModel extends Authenticatable 
{
	use HasApiTokens,Notifiable;
    protected $table 		= 'fleet'; 
	
	protected $fillable = [ 
        'first_name',
		'last_name',
        'email',
		'password',
        'mobile', 
		'id_number',
		'city',
		'type',
        'status',
		'isDeleted',
		'created_at',
		'updated_at'
    ];
}
