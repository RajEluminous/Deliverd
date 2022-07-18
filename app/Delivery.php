<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model {
	
	public $table = 'delivery';	
	protected $fillable = [
        'order_id', 'rpt_usr_id', 'rpt_sender_id',
		'service_type_id', 'service_type_fees', 
		'package_type', 'package_size', 
		'pickup_contact_person', 'pickup_contact_mobileno', 'pickup_address', 'pickup_zipcode', 'pickup_city', 'pickup_geo_address','pickup_latitude','pickup_longitude', 'pickup_datetime', 'pickup_notes', 'pickup_key', 'pickup_qrcode_str',
		'dropoff_contact_person', 'dropoff_contact_mobileno', 'dropoff_contact_email', 'dropoff_address', 'dropoff_zipcode', 'dropoff_city','dropoff_geo_address', 'dropoff_latitude','dropoff_longitude','dropoff_datetime', 'dropoff_notes' , 'dropoff_key', 'dropoff_qrcode_str'
    ];
}