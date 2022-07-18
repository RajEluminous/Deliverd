<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Articles extends Model
{
	public $timestamps = false;	// make it 'true' for using created_at & updated_at
	
	protected $fillable = [
        'name', 'description',
    ];
}