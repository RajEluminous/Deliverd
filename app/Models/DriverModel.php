<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class DriverModel extends Authenticatable 
{
   use HasApiTokens,Notifiable;

    protected $table 		= 'driver'; 

   
}
