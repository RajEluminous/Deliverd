<?php

namespace App\Http\Controllers\Api\v1;


use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Validator;
use App\Models\TripModel;
use App\Models\TripHasDeliveriesModel;
use App\Models\DriverHasVehicleModel;
use App\Models\FleetModel;
use App\Models\DriverModel;

use App\Delivery;

use Mail;
use Hash;
use DB; 

class TripApiController extends APIBaseController
{	
	Private $BaseModel;
	Private $PasswordReset;

	public function __construct(
									TripModel $TripModel,
									TripHasDeliveriesModel $TripHasDeliveriesModel,
									Delivery $Delivery,
									DriverHasVehicleModel $DriverHasVehicleModel,
									FleetModel $FleetModel,
									DriverModel $DriverModel
    								)
    {
       $this->BaseModel = $TripModel; 
       $this->Delivery 	= $Delivery; 
       $this->TripHasDeliveriesModel = $TripHasDeliveriesModel; 
       $this->DriverHasVehicleModel  = $DriverHasVehicleModel; 
       $this->FleetModel   = $FleetModel; 
       $this->DriverModel   = $DriverModel; 


       $this->available_points 			= [];
       $this->sequence_delivery_points 	= [];
       $this->dropoffs_delivery_points 	= [];
       $this->shortest_distance_key 	= 0;
		
    }


    public function _findClosestPoint($seq_index ,$latitudeFrom,$longitudeFrom) 
    {
    	if($seq_index>0){

    		$last_value = end($this->sequence_delivery_points);
			$lastkey 	= key($this->sequence_delivery_points);

		    $latitudeFrom    = $this->sequence_delivery_points[$lastkey]['latitude'];
		    $longitudeFrom   = $this->sequence_delivery_points[$lastkey]['longitude'];

    	}

    	if(!empty($this->available_points) && count($this->available_points)>0){
    		$index_key = 0;
    		foreach ($this->available_points as $avail_key=>$available_point) {

    			$latitudeTo    = $available_point['latitude'];
        		$longitudeTo   = $available_point['longitude'];

    			$this->available_points[$avail_key]['distance'] = self::_getDistance($latitudeFrom, $longitudeFrom,$latitudeTo,$longitudeTo, "K");

    			if($index_key>0){
    				if($this->available_points[$this->shortest_distance_key]['distance']>$this->available_points[$avail_key]['distance'])
    				{
    					$this->shortest_distance_key = $avail_key;
    				}
    			}else{
    				$this->shortest_distance_key = $avail_key;
    			}

    			$index_key++;
    		}

    		$this->sequence_delivery_points[] = $this->available_points[$this->shortest_distance_key];

    		$dropoff_key = $this->available_points[$this->shortest_distance_key]['order_id'];
    		if(array_key_exists($dropoff_key, $this->dropoffs_delivery_points)){
    			$this->available_points[] = $this->dropoffs_delivery_points[$dropoff_key];
    		}

    		unset($this->available_points[$this->shortest_distance_key]);
    		unset($this->dropoffs_delivery_points[$dropoff_key]);

    	}

    	return ;

    }

    public function createTrip(Request $request) 
    {
    	//get all the deliveries
    	$validator  = Validator::make($request->all(),[
                              'driver_id'      => 'required',
                            ], 
                            [
                              'driver_id.required'    => 'driver id is required.',     
                            ]
                            ); 

        if ($validator->fails()) 
        {           
          $errors[] = $validator->errors(); 
        }else
        {
          try {
          		
          		//driver validation if driver not exist then give error
	        	$driver_id  		= $request->driver_id;
	        	$today_date  		= $request->today_date;
	        	$deliveryLimit		= config('constants.DELIVERLIMIT');
	        	$minutePerKm		= config('constants.ESTIMATEDMINUTEPERKM');
	        	$pricePerKm			= config('constants.ESTIMATEDPRICEPERKM');
	        	
	        	$total_distance		= 0;
	        	$total_est_earning	= 0;
	        	$total_est_time 	= 0;

	        	if(empty($today_date)){
	                $today_date =  date('Y-m-d', strtotime(now()));
	            }else{
	                $today_date =  date('Y-m-d', strtotime($today_date));
	            }

          		$onlineDriver = $this->DriverHasVehicleModel
			          			->where('driver_id',$driver_id)
			          			->whereStatus(1)
			          			->first();

				
				$fleet_details = $this->FleetModel
									->join('fleet_has_drivers','fleet_has_drivers.fleet_id','=','fleet.id')
									->where('fleet_has_drivers.driver_id',$driver_id)
				          			->whereStatus(1)
				          			->first();		       
			    //  dd($onlineDriver);

			    if(!empty($onlineDriver)){

			    	$driver_vehicle_id	= $onlineDriver->id;
			    	$vehicle_id  		= $onlineDriver->vehicle_id;
			    	$fleet_id  			= $fleet_details->id;
		        	$driver_latitude  	= $onlineDriver->latitude;
		        	$driver_longitude 	= $onlineDriver->longitude;

			    	$deliveries = $this->Delivery
    								->where('is_booked','=',0)
    								->whereDate('dropoff_datetime','=',$today_date)
                                    //->take($deliveryLimit)
    								->get([
		    								'delivery.id',
		    								'delivery.order_id',
		    								'delivery.package_type',
		    								'delivery.package_size',
		    								
		    								'delivery.pickup_contact_person',
		    								'delivery.pickup_contact_mobileno',
		    								'delivery.pickup_address',
		    								'delivery.pickup_zipcode',
		    								'delivery.pickup_city',
		    								'delivery.pickup_latitude',
		    								'delivery.pickup_longitude',
		    								'delivery.pickup_datetime',
		    								'delivery.pickup_notes',

		    								'delivery.dropoff_contact_person',
		    								'delivery.dropoff_contact_mobileno',
		    								'delivery.dropoff_contact_email',
		    								'delivery.dropoff_latitude',
		    								'delivery.dropoff_longitude',
		    								'delivery.dropoff_datetime',
		    								'delivery.dropoff_notes',
		    							]);
					
					
					################ New Code - Process the Distance between Lat/Long ################
					// echo "<br>count:".count($deliveries);
					// echo '<br>deliveryLimit:'.$deliveryLimit;
					$counterVal = 1;
					foreach($deliveries as $key => $value) { 
					
						$rsPick = $this->getDistanceBetweenPoints($driver_latitude, $driver_longitude, $value->pickup_latitude,$value->pickup_longitude);
						//$rsDrop = $this->getDistanceBetweenPoints($driver_latitude, $driver_longitude, $value->dropoff_latitude,$value->dropoff_longitude);
						 
						if($rsPick['status'] && ($counterVal<=$deliveryLimit))  //  && $rsDrop['status']   
						{
							// This is with in radius--Keep the delivery.
							//  echo "<br>Yes:".$value->id.'  Distance:'.$rsPick['distance'] ; 							 
							$counterVal++;
						} else {
							// This is not in the radius -- Forgot/Remove the record. 
							//echo "<br>------------No:".$value->id; 
							$deliveries->forget($key);
						}     
					}
					/*   
					foreach ($deliveries as $delivery) {
						echo "<br>Final ID :".$delivery->id; 
					} 					
					die();  */ 
					################ End - Process the Distance between the Lat/Long ######
					
					
					/* ################## OLD CODE - For Limited Radius ################
					  $delArr = array();
					$destString = '';
					foreach($deliveries as $drv) {
						//print_r($drv);
						$destString .= $drv['pickup_latitude'].','.$drv['pickup_longitude'].'|'.$drv['dropoff_latitude'].','.$drv['dropoff_longitude'].'|';
						$delArr[$drv['id']] = $drv['id'];
					}
					
					print_r($delArr);
					
					$destString = substr($destString,0,-1);
					 
					// driver's location
					$origin = $driver_latitude.','.$driver_longitude; 	
					
					// Shows all delivers with reqd distance values.
					$rsParams = $this->getDistanceMetrixLatLong($origin,$destString);	
					 print_r($rsParams);
					 
					// Processes the deliveries and fetches the nearby deliveries id
					$inRadiusDeliveries = $this->getInRadiusDeliveries($delArr,$rsParams); 	
					 print_r($inRadiusDeliveries);
					
					// Removes the otherdeliveries records and keeps the '$finalDeliveries' id record.
					if(!empty($deliveries) && count($deliveries)>0)  {
						foreach ($deliveries as  $key => $value) {
							if(!in_array($value->id,$inRadiusDeliveries)) {
								echo "<br>Forgot ID :".$value->id;
								$deliveries->forget($key);
							}
						}
					}	
					
					  //echo "<br>Final id:";
					foreach ($deliveries as $delivery) {
						echo "<br>Final ID :".$delivery->id; 
					}     
					//print_r($deliveries);		// here getting proper records...hwevr in response getting double records.
					 die();  
					################## END: Limited Radius ###############   */
					
		        	//splits the points into pickup and dropoffs
		        	if(!empty($deliveries) && count($deliveries)>0)
		        	{	
		        		DB::beginTransaction();
          				$all_transactions = [];

			    		$deliveryIndex = 0;
			    		$bookedDeliveryIds = [];
			    		foreach ($deliveries as $delivery) 
			    		{
			    			$this->available_points[$deliveryIndex]['id'] = $delivery['id'];
			    			$this->available_points[$deliveryIndex]['order_id'] = $delivery['order_id'];
			    			$this->available_points[$deliveryIndex]['latitude'] = $delivery['pickup_latitude'];
			    			$this->available_points[$deliveryIndex]['longitude'] = $delivery['pickup_longitude'];
			    			$this->available_points[$deliveryIndex]['record'] = 'pickup';


			    			$deliveryIndex++;


			    			$this->dropoffs_delivery_points[$delivery['order_id']]['id'] = $delivery['id'];
			    			$this->dropoffs_delivery_points[$delivery['order_id']]['order_id'] = $delivery['order_id'];
			    			$this->dropoffs_delivery_points[$delivery['order_id']]['latitude'] = $delivery['dropoff_latitude'];
			    			$this->dropoffs_delivery_points[$delivery['order_id']]['longitude'] = $delivery['dropoff_longitude'];
			    			$this->dropoffs_delivery_points[$delivery['order_id']]['dropoff_datetime'] = $delivery['dropoff_datetime'];
			    			$this->dropoffs_delivery_points[$delivery['order_id']]['record'] = 'dropoff';
			    			$this->dropoffs_delivery_points[$delivery['order_id']]['distance'] = 0;

			    			$bookedDeliveryIds[] = $delivery['id'];

			    		}

			    		// dump($this->available_points);

			    		for ($i=0; $i <=(($deliveryIndex*2)-1) ; $i++) {
			    			
			    			self::_findClosestPoint($i,$driver_latitude,$driver_longitude);
			    		}

			    		// dd('Availble points:',$this->available_points,'Final',$this->sequence_delivery_points,'Dropoff',$this->dropoffs_delivery_points);

			    		$total_distance = array_sum(array_column($this->sequence_delivery_points,"distance"));
			    		
			    		$total_est_time    = (float)($minutePerKm *  $total_distance);
			    		$total_est_time    = (float)($total_est_time *  60);
			    		
						$total_est_time_in_hr_min_sec = gmdate("H:i:s", $total_est_time);

						$split_est_time = explode(":", $total_est_time_in_hr_min_sec);
						$total_est_time = $split_est_time[0]."h ".$split_est_time[1]."m ".$split_est_time[2]."s";
			   			// $hours = floor($total_est_time / 3600);
						// $minutes = floor(($total_est_time / 60) % 60);
						// $seconds = $total_est_time % 60;
						// dd($hours,$minutes,$seconds,gmdate("H:i:s", $total_est_time));
						 // dd($total_est_time);


			    		$total_est_earning = (float)($pricePerKm  *  $total_distance);
			    		//dd($this->sequence_delivery_points,$test);
			    		//store trip
				    	$collection     					= new $this->BaseModel; 
				    	// $collection->driver_vehicle_id  	= $driver_vehicle_id;  
				    	$collection->driver_id  			= $driver_id;  
				    	$collection->vehicle_id  			= $vehicle_id;  
				    	$collection->fleet_id  				= $fleet_id;  
						$collection->start_time 			= date("Y-m-d H:i:s",time());
						$collection->total_distance 		= $total_distance;
						$collection->total_est_earning		= $total_est_earning;
						$collection->total_est_time 		= $total_est_time;
						$collection->total_no_of_deliveries	= $deliveryIndex;
						$collection->status     			= 0;  //created

						//Save data
						if($collection->save()){

							if(!empty($this->sequence_delivery_points) && count($this->sequence_delivery_points)>0){

								foreach ($this->sequence_delivery_points as $sequence_delivery_point) {
									
									//1=>Pending, 2=>Pickedup, 3=>OnRoute, 4=>Delivered, 5=>Rescheduled
									$tripDeliveryObj 			= new $this->TripHasDeliveriesModel;
									$tripDeliveryObj->trip_id  	= $collection->id;
									$tripDeliveryObj->delivery_id  = $sequence_delivery_point['id'];
									$tripDeliveryObj->distance  = $sequence_delivery_point['distance'];
									 
									if($sequence_delivery_point['record']=='pickup'){
									 	$tripDeliveryObj->delivery_type  = 'pickup';
									}else if($sequence_delivery_point['record']=='dropoff'){
									 	$tripDeliveryObj->delivery_type  = 'dropoff';
									}
									$tripDeliveryObj->status  		= 1;

									if ($tripDeliveryObj->save()) 
			                        {

			                        	$this->Delivery
										    ->whereIn('id', $bookedDeliveryIds)            
										    ->update(['is_booked'=>1]); 

			                        	$all_transactions[] = 1;
			                        }else{
			                            $all_transactions[] = 0;
			                        }
								}
							}

						}else{
			                $all_transactions[] = 0;
			            }

						if (!in_array(0,$all_transactions)) 
			            {
			                
			                DB::commit();

			                $getTrips = $this->BaseModel
					        				->with(['hasDeliveries'=>function($query){
					                                    $query->with(['assignedDelivery']);
					                                }])
					        				->where('id','=',$collection->id)	
					        				->get();  
					        

					        $trip_data = [];
					        foreach ($getTrips as $key=>$getTrip) {

					        	foreach ($getTrip['hasDeliveries'] as $delKey => $has_delivery) {
									     		
						        	$trip_data['hasDeliveries'][$delKey]['id'] = $has_delivery['id'];
						        	$trip_data['hasDeliveries'][$delKey]['delivery_id'] = $has_delivery['delivery_id'];
						        	$trip_data['hasDeliveries'][$delKey]['order_id'] = $has_delivery['assignedDelivery']['order_id'];
						        	$trip_data['hasDeliveries'][$delKey]['delivery_type'] = $has_delivery['delivery_type'];
						        	$trip_data['hasDeliveries'][$delKey]['distance'] = $has_delivery['distance'];

						        	if($has_delivery['delivery_type'] == 'pickup'){

						        		$trip_data['hasDeliveries'][$delKey]['latitude'] = $has_delivery['assignedDelivery']['pickup_latitude'];
						        		$trip_data['hasDeliveries'][$delKey]['longitude'] = $has_delivery['assignedDelivery']['pickup_longitude'];
						        		$trip_data['hasDeliveries'][$delKey]['address'] = $has_delivery['assignedDelivery']['pickup_address'];
						        		$trip_data['hasDeliveries'][$delKey]['message'] = $has_delivery['assignedDelivery']['pickup_notes'];
						        	}else{
						        		$trip_data['hasDeliveries'][$delKey]['latitude'] = $has_delivery['assignedDelivery']['dropoff_latitude'];
						        		$trip_data['hasDeliveries'][$delKey]['longitude'] = $has_delivery['assignedDelivery']['dropoff_longitude'];
						        		$trip_data['hasDeliveries'][$delKey]['address'] = $has_delivery['assignedDelivery']['dropoff_address'];
						        		$trip_data['hasDeliveries'][$delKey]['message'] = $has_delivery['assignedDelivery']['dropoff_notes'];
						        	}

					        	}
					        }
			                return response()->json([
										'status' 		=> 'Success',
										'id'			=> $getTrip['id'],
										'driver_id'		=> $driver_id,
										'vehicle_id'	=> $vehicle_id,
										'total_distance'=> $getTrip['total_distance'],
										'total_est_earning'	=> $getTrip['total_est_earning'],
										'total_est_time'	=> $getTrip['total_est_time'],
										'total_no_of_deliveries'=> $getTrip['total_no_of_deliveries'],
										'hasDeliveries'	=> $trip_data['hasDeliveries'],
										'message' 		=> 'Trip created successfully.'
									]);  
			                
			            }else
			            {
			            	return response()->json([
									'status' 	=> 'Error',
									'message' 	=> 'Failed to create trip.',
									]);  
			                DB::rollback();
			               
			            }
			    	}else{
			    		return response()->json([
								'status' 	=> 'Error',
								'message' 	=> 'No deliveries are available.',
								]);  
			    	}

			    	


			    }else{

			    	return response()->json([
								'status' 	=> 'Error',
								'message' 	=> 'Driver is not online.',
								]);  


			    }
          		

	      	}catch(\Exception $e) {

            	return response()->json([
							'status' 	=> 'Error',
							'message' 	=> 'Failed to create trip.',
							'error_msg'	=> $e->getMessage(),
							]);  
            	 DB::rollback();
        	}

        }

    }

  	public function getTrips(Request $request)
    {     

    	try {


    		$driver_id  	= $request->driver_id;
    		$onlineDriver 	= $this->DriverHasVehicleModel
			          			->where('driver_id',$driver_id)
			          			// ->whereStatus(1)
			          			->first();
			if(!empty($onlineDriver)){

				// dd($request->all());      
		        $getTrips = $this->BaseModel
		        				->with(['hasDeliveries'=>function($query){
		                                    $query->with(['assignedDelivery']);
		                                }])
		        				->where('driver_vehicle_id','=',$onlineDriver->id)	
		        				->get();  
		        // dd($getTrips->toArray());

		        $trip_data = [];
		        foreach ($getTrips as $key=>$getTrip) {
		        	$trip_data['trip'][$key]['id'] = $getTrip['id'];
		        	$trip_data['trip'][$key]['driver_id'] = $driver_id;
		        	$trip_data['trip'][$key]['vehicle_id'] = $onlineDriver->vehicle_id;
		        	$trip_data['trip'][$key]['status'] = $getTrip['status'];
		        	
		        	// print_r($getTrip['hasDeliveries']);
		        	// exit();
		        	foreach ($getTrip['hasDeliveries'] as $delKey => $has_delivery) {
					
						// print_r($has_delivery);
			   			//exit();	        		
			        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['id'] = $has_delivery['id'];
			        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['order_id'] = $has_delivery['assignedDelivery']['order_id'];
			        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['delivery_type'] = $has_delivery['delivery_type'];
			        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['distance'] = $has_delivery['distance'];

			        	if($has_delivery['assignedDelivery']['delivery_type'] == 'pickup'){

			        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['latitude'] = $has_delivery['assignedDelivery']['pickup_latitude'];
			        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['longitude'] = $has_delivery['assignedDelivery']['pickup_longitude'];
			        	}else{
			        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['latitude'] = $has_delivery['assignedDelivery']['dropoff_latitude'];
			        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['longitude'] = $has_delivery['assignedDelivery']['dropoff_longitude'];
			        	}

		        	}
		        }  

		        return response()->json([
									'status' 	=> 'Success',
									'data'		=> $trip_data,
									'message' 	=> 'Trips with delivery data.'
								]);  

			}

    		


        }catch(\Exception $e) {

        	return response()->json([
						'status' 	=> 'Error',
						'message' 	=> 'Failed to get trip data.',
						'error_msg'	=> $e->getMessage(),
						]);  
    	}


    }

    public function getDriverEarnings(Request $request)
    {
    	//get all the deliveries
    	$validator  = Validator::make($request->all(),[
                              'driver_id'      => 'required',
                              'today_date'     => 'required',
                            ], 
                            [
                              'driver_id.required'  => 'driver id field is required.',     
                              'today_date.required' => 'today date field is required.',     
                            ]
                            ); 

        if ($validator->fails()) 
        {           
          $errors[] = $validator->errors(); 
          return response()->json([
							'status' 	=> 'Error',
							'message' 	=> 'Failed to get driver earnings.',
							'errors'	=> $errors,
							]);  
        }else
        {
        	
        	try{

        		$driver_id 	= $request->driver_id;
        		$today_date = $request->today_date;

        		if(empty($today_date)){
	                $today_date =  date('Y-m-d', strtotime(now()));
	            }else{
	                $today_date =  date('Y-m-d', strtotime($today_date));
	            }

	            $start_date = $today_date." 00:00:00";
	            $end_date 	= $today_date." 23:59:59";


        		$getTotalEarnings = $this->BaseModel
        							->where('trip.driver_id','=',$driver_id)
    								->where('completion_time','>=',$start_date)
    								->where('completion_time','<=',$end_date)
        							->where('trip.status','=',3)
    								->selectRaw('SUM(trip.total_est_earning) as total_earning')
    								// ->groupBy('completion_time')
    								->first();
    			$total_earning = "0";
				if(!empty($getTotalEarnings)){
					if(!empty($getTotalEarnings->total_earning)){

						$total_earning = $getTotalEarnings->total_earning;
					}else{
						$total_earning = "0";
					}

				}
				// dd($total_earning,$getTotalEarnings);

				return response()->json([
									'status' 	=> 'Success',
									'data'		=> $total_earning,
									'message' 	=> 'Total Driver Earnings.'
								]);  

        	}catch(\Exception $e) {

            	return response()->json([
							'status' 	=> 'Error',
							'message' 	=> 'Failed to get driver earnings.',
							'error_msg'	=> $e->getMessage(),
							]);  
        	}


        }

    }    

	// To get Fleet info	
	public function getTripFleetInfo($tripid) {
		
		if($tripid >0 ) {		 	
			$fleetObj = DB::table('fleet')
			->join('trip', function ($join) use ($tripid) {

			$join->on('fleet.id', '=', 'trip.fleet_id')
			  ->where('trip.fleet_id', '=', $tripid);   
			}) 
			->select('fleet.id','fleet.first_name','fleet.last_name','fleet.email','fleet.mobile','fleet.type','fleet.id_number','fleet.status')
			->first();  
			$fleet_name = ""; 
			if(isset($fleetObj)) 
				$fleet_name = $fleetObj;//$fleetObj->first_name.' '.$fleetObj->last_name; 
			
			return $fleet_name;
			
	    } else {
			return "";
		}
		
	}
	 
    public function getTripHistory(Request $request)
    {     
	
		
		//die();
    	try {

    		$driver_id  	= $request->driver_id;
    		$fleet_id  		= $request->fleet_id;
        	$length = 5;
        	$start = 0;
        	// offset and page calculation
			$page = 1;
			if(!empty($request->page)) {	
				$page = $request->page;	
				if(false === $page) {
					$page = 1;
				}
			}
			$start = ($page - 1) * $length;

    		if(!empty($driver_id)){

		        $getTrips = $this->BaseModel
		        				// ->join('driver_has_vehicle','driver_has_vehicle.id','=','trip.driver_vehicle_id')
		        				->with(['hasDeliveries'=>function($query){
		                                    $query->with(['assignedDelivery']);
		                                },'assignedFleet'])
		        				->where('driver_id','=',$driver_id);
    		}else{
    			$getTrips = $this->BaseModel
    							// ->join('driver_has_vehicle','driver_has_vehicle.id','=','trip.driver_vehicle_id')
		        				->with(['hasDeliveries'=>function($query){
		                                    $query->with(['assignedDelivery']);
		                                },'assignedFleet']);

    		}

    		if(!empty($fleet_id)){
    			$getTrips = $getTrips->where('fleet_id','=',$fleet_id);
    		}
    		
	       	// get total count 
	        $countQuery = clone($getTrips);            
        	$totalData  = $countQuery->count();
			
			// dd($start,$length);
        	$getTrips = $getTrips->skip($start)
                             ->take($length)
                             ->get();
	        //dd($getTrips->toArray());

	        $trip_data = [];
	        foreach ($getTrips as $key=>$getTrip) {
	        	   
				$drvObj = $this->getDriverInfobyDriverId($getTrip['driver_id']); 
				isset($drvObj)? $driverName = $drvObj->first_name.' '.$drvObj->last_name: $driverName = ""; 
				 
	        	$trip_data['trip'][$key]['id'] = $getTrip['id'];
	        	$trip_data['trip'][$key]['driver_id'] = $getTrip['driver_id'];
				$trip_data['trip'][$key]['driver_name'] = $driverName;
	        	$trip_data['trip'][$key]['vehicle_id'] = $getTrip['vehicle_id'];
	        	$trip_data['trip'][$key]['status'] = $getTrip['status'];
	        	$trip_data['trip'][$key]['start_time'] = $getTrip['start_time'];
	        	$trip_data['trip'][$key]['completion_time'] = $getTrip['completion_time'];
	        	$trip_data['trip'][$key]['total_distance'] = $getTrip['total_distance'];
	        	$trip_data['trip'][$key]['total_est_earning'] = $getTrip['total_est_earning'];
	        	$trip_data['trip'][$key]['total_no_of_deliveries'] = $getTrip['total_no_of_deliveries'];
	        	$trip_data['trip'][$key]['fleet'] = $this->getTripFleetInfo($getTrip['fleet_id']) ;//$getTrip['assignedFleet'];
	        	
	        	// print_r($getTrip['hasDeliveries']);
	        	// exit();
	        	foreach ($getTrip['hasDeliveries'] as $delKey => $has_delivery) {
					
					// print_r($has_delivery);
		   			//exit();	        		
		        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['id'] = $has_delivery['id'];
		        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['order_id'] = $has_delivery['assignedDelivery']['order_id'];
		        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['delivery_type'] = $has_delivery['delivery_type'];
		        	$trip_data['trip'][$key]['hasDeliveries'][$delKey]['distance'] = $has_delivery['distance'];

		        	if($has_delivery['assignedDelivery']['delivery_type'] == 'pickup'){

		        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['latitude'] = $has_delivery['assignedDelivery']['pickup_latitude'];
		        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['longitude'] = $has_delivery['assignedDelivery']['pickup_longitude'];
		        	}else{
		        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['latitude'] = $has_delivery['assignedDelivery']['dropoff_latitude'];
		        		$trip_data['trip'][$key]['hasDeliveries'][$delKey]['longitude'] = $has_delivery['assignedDelivery']['dropoff_longitude'];
		        	}

	        	}
	        }  

	        return response()->json([
								'status' 	=> 'Success',
								'data'		=> $trip_data,
								'recordsTotal'=> intval($totalData),
								'message' 	=> 'Trips history with delivery data.'
							]);  


        }catch(\Exception $e) {

        	return response()->json([
						'status' 	=> 'Error',
						'message' 	=> 'Failed to get trip history data.',
						'error_msg'	=> $e->getMessage(),
						]);  
    	}


    }
	
	// Get Fleet info by driver id
	public function getFleetNameByDriverId($driverid) {
		
		if($driverid > 0) {	
			$fleetObj = DB::table('fleet')
			->join('fleet_has_drivers', function ($join) use ($driverid) {

			$join->on('fleet.id', '=', 'fleet_has_drivers.fleet_id')
			  ->where('fleet_has_drivers.driver_id', '=', $driverid);   
			}) 
			->select('first_name','last_name')
			->first();  
			
			$fleet_name = "";
			if(isset($fleetObj))
			  $fleet_name = $fleetObj->first_name.' '.$fleetObj->last_name;
			 
			return $fleet_name; 
		} else {
			return "";
		}
		
	}

	public function getDriverInfobyDriverId($driverid) {
		if($driverid > 0) {	
			$driverObj = DB::table('driver')
			->Where('id', '=', $driverid) 
			->first();  
			 
			if(isset($driverObj))
			  return $driverObj;
			  
		} else {
			return "";
		}
	}

    public function getTripStatistics(Request $request)
    {     
		 

    	try {
    		//dd('getTripStatistics');

    		$driver_id = $request->driver_id;
			$today_date = $request->today_date;
			$today_stats = [];
			$week_stats = [];
			$month_stats = [];

			$driver_details = $this->DriverModel
								->leftjoin('driver_has_vehicle','driver_has_vehicle.driver_id','=','driver.id')
								->where('driver.id','=',$driver_id) 
								->first([
										'driver.id',
										'driver.first_name',
										'driver.last_name',
										'driver.email',
										'driver.licence_no',
										'driver.mobile',
										'driver.uniqstring',
										'driver.profile_img_str',
										'driver.licence_img_str',
										'driver.identification_img_str',
										'driver.criminaldoc_img_str',
										'driver.reject_reason',
										'driver.is_verified',
										'driver.is_deleted',
										'driver.created_at',
										'driver_has_vehicle.latitude',
										'driver_has_vehicle.longitude',
										'driver_has_vehicle.status as online_status',
										'driver.status'
										]);
			 
			//For Today Stats
    		if(empty($today_date)){
                $today_date =  date('Y-m-d', strtotime(now()));
            }else{
                $today_date =  date('Y-m-d', strtotime($today_date));
            }

            $start_time = ' 00:00:00';
            $end_time 	= ' 23:59:59';

            //Today
            $start_date = $today_date.$start_time;
            $end_date 	= $today_date.$end_time;

            //Week Sunday to Saturday
			$day = date('w');
            $week_start = date('Y-m-d', strtotime('-'.$day.' days')).$start_time;
			$week_end = date('Y-m-d', strtotime('+'.(6-$day).' days')).$end_time;

            //Month
            $month_start_date =  date('Y-m-01', strtotime($today_date)).$start_time;
            $month_end_date   = date('Y-m-t', strtotime($today_date)).$end_time;
			// dd($day,$start_date,$end_date,$week_end,$week_start,$month_start_date,$month_end_date);

    		$getTodayTotal = $this->BaseModel
    							->where('driver_id','=',$driver_id)
								->selectRaw('SUM(trip.total_est_earning) as total_earning,SUM(trip.total_distance) as total_distance,COUNT(trip.id) as total_trips');
								// ->first();
								// ->whereNotNull('completion_time')
    							// ->where('trip.status','=',3)
								//->groupBy('completion_time')
			
			// get
            $getWeekTotal = clone($getTodayTotal);     
            $getMonthTotal = clone($getTodayTotal);   


			$getTodayTotal = $getTodayTotal
								->where('start_time','>=',$start_date)
								->where('start_time','<=',$end_date)
								->first();

			$total_earning = "0";
			if(!empty($getTodayTotal)){
				if(!empty($getTodayTotal->total_earning)){

					$total_distance = $getTodayTotal->total_distance;
					$total_earning  = $getTodayTotal->total_earning;
					$total_trips    = $getTodayTotal->total_trips;

					$today_stats['total_distance'] = $total_distance;
					$today_stats['total_earning'] = $total_earning;
					$today_stats['total_trips'] = $total_trips;
					$today_stats['avg_distance_per_trip'] = (float)number_format(($total_distance/$total_trips),2);
				}else{
					$today_stats['total_distance'] = 0;
					$today_stats['total_earning'] = 0;
					$today_stats['total_trips'] = 0;
					$today_stats['avg_distance_per_trip'] = 0;
				}
				

			}

			$getWeekTotal = $getWeekTotal
								->where('start_time','>=',$week_start)
								->where('start_time','<=',$week_end)
								->first();  

			$total_earning = "0";
			if(!empty($getWeekTotal)){
				if(!empty($getWeekTotal->total_earning)){

					$total_distance = $getWeekTotal->total_distance;
					$total_earning  = $getWeekTotal->total_earning;
					$total_trips    = $getWeekTotal->total_trips;

					$week_stats['total_distance'] = $total_distance;
					$week_stats['total_earning'] = $total_earning;
					$week_stats['total_trips'] = $total_trips;
					$week_stats['avg_distance_per_trip'] = (float)number_format(($total_distance/$total_trips),2);
				}else{
					$week_stats['total_distance'] = 0;
					$week_stats['total_earning'] = 0;
					$week_stats['total_trips'] = 0;
					$week_stats['avg_distance_per_trip'] = 0;
				}
				

			}


            $getMonthTotal = $getMonthTotal
								->where('start_time','>=',$month_start_date)
								->where('start_time','<=',$month_end_date)
								->first();

			$total_earning = "0";
			if(!empty($getMonthTotal)){
				if(!empty($getMonthTotal->total_earning)){

					$total_distance = $getMonthTotal->total_distance;
					$total_earning  = $getMonthTotal->total_earning;
					$total_trips    = $getMonthTotal->total_trips;

					$month_stats['total_distance'] = $total_distance;
					$month_stats['total_earning'] = $total_earning;
					$month_stats['total_trips'] = $total_trips;
					$month_stats['avg_distance_per_trip'] = (float)number_format(($total_distance/$total_trips),2);
				}else{
					$month_stats['total_distance'] = 0;
					$month_stats['total_earning'] = 0;
					$month_stats['total_trips'] = 0;
					$month_stats['avg_distance_per_trip'] = 0;
				}
			}


			$acceptedTodayTripCount = $this->BaseModel
    							->where('driver_id','=',$driver_id)
    							->whereIn('trip.status',[1,2,3]);

			$rejectedTodayTripCount = $this->BaseModel
    							->where('driver_id','=',$driver_id)
    							->where('trip.status','=',4);

			// get
            $acceptedWeekTripCount = clone($acceptedTodayTripCount);     
            $rejectedWeekTripCount = clone($rejectedTodayTripCount);  
            $acceptedMonthTripCount = clone($acceptedTodayTripCount);     
            $rejectedMonthTripCount = clone($rejectedTodayTripCount);  

            $acceptedTodayTripCount = $acceptedTodayTripCount
           						 			->where('start_time','>=',$start_date)
											->where('start_time','<=',$end_date)
											->count();

			$rejectedTodayTripCount = $rejectedTodayTripCount
           						 			->where('start_time','>=',$start_date)
											->where('start_time','<=',$end_date)
											->count();

			$today_stats['trip_acceptance_rate'] = 0;
			if($acceptedTodayTripCount>=$rejectedTodayTripCount){
				//$today_stats['trip_acceptance_rate'] = (float)(($rejectedTodayTripCount/$acceptedTodayTripCount)*100);
				$today_stats['trip_acceptance_rate'] = (float)(($acceptedTodayTripCount-$rejectedTodayTripCount)*100);
			}



			$acceptedWeekTripCount = $acceptedWeekTripCount
           						 			->where('start_time','>=',$week_start)
											->where('start_time','<=',$week_end)
											->count();

			$rejectedWeekTripCount = $rejectedWeekTripCount
           						 			->where('start_time','>=',$week_start)
											->where('start_time','<=',$week_end)
											->count();
			$week_stats['trip_acceptance_rate'] = 0;
			if($acceptedWeekTripCount>=$rejectedWeekTripCount){
				//$week_stats['trip_acceptance_rate'] = (float)(($rejectedWeekTripCount/$acceptedWeekTripCount)*100);
				$week_stats['trip_acceptance_rate'] = (float)(($acceptedWeekTripCount - $rejectedWeekTripCount)*100);
			}

			$acceptedMonthTripCount = $acceptedMonthTripCount
           						 			->where('start_time','>=',$month_start_date)
											->where('start_time','<=',$month_end_date)
											->count();

			$rejectedMonthTripCount = $rejectedMonthTripCount
           						 			->where('start_time','>=',$month_start_date)
											->where('start_time','<=',$month_end_date)
											->count();
			$month_stats['trip_acceptance_rate'] = 0;
			if($acceptedMonthTripCount>=$rejectedMonthTripCount){
				//$month_stats['trip_acceptance_rate'] = (float)(($rejectedMonthTripCount/$acceptedMonthTripCount)*100);
				$month_stats['trip_acceptance_rate'] = (float)(($acceptedMonthTripCount - $rejectedMonthTripCount)*100);
			}
			
			$driver_details->online_status = $driver_details->online_status==1?'online':'offline';
			
			// dd($today_stats,$acceptedTodayTripCount,$rejectedTodayTripCount,$getTodayTotal);
			$data['today'] = $today_stats;
			$data['week'] = $week_stats;
			$data['month'] = $month_stats;
			$data['driver_details'] = $driver_details;
			$data['fleet_name'] = $this->getFleetNameByDriverId($request->driver_id);

	        return response()->json([
								'status' 	=> 'Success',
								'data'		=> $data,
								'message' 	=> 'Trips Statistics.'
							]);  
    		


        }catch(\Exception $e) {

        	return response()->json([
						'status' 	=> 'Error',
						'message' 	=> 'Failed to get trip statistics data.',
						'error_msg'	=> $e->getMessage(),
						]);  
    	}


    }

	public function getDistanceMetrixLatLong($origin, $destString) {
		$url = "https://maps.googleapis.com/maps/api/distancematrix/json?units=metric&origins=".$origin."&destinations=".$destString."&mode=driving&key=AIzaSyBdFRcM7GFoZH7zriMnwhKsoZaOAFstEtQ";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);
		curl_close($ch);
		$response_a = json_decode($response, true);
		
		$responseElement = $response_a['rows'][0]['elements'];
		//print_r($responseElement);
		return $responseElement;
	}

	// To parse the DistanceMatrixResponse and get deliveries with in radius
	public function getInRadiusDeliveries($delArr,$rsParams) {
		
		$radiusLimit	= config('constants.DELIVERYRADIUS'); // 170000 ;// in meters
		 
		// array chunk - separate in 2 chunks 	
		$arrChunk = array_chunk($rsParams, 2);
		//print_r($arrChunk);
		
	
		$cnt =0;
		$arrDels = array();
		$newArrDels = array();
		foreach($delArr as $drvKey=>$drvVal) {
			//echo '<br>'.$drvKey;
			$cVals = $arrChunk[$cnt];
			//  print_r($cVals); 
			/*  echo '<br> -------------<br>';
			 echo '<br> distance:'.$cVals[0]['distance']['text'];		
			 echo '<br> duration:'.$cVals[0]['duration']['text'];	
			 echo '<br> distance:'.$cVals[1]['distance']['text'];		
			 echo '<br> duration:'.$cVals[1]['duration']['text'];	 */		
			 
			 $arrDels[$drvKey]['pickup'] = array('distance_text' => 0,'distance_value' =>0,'duration_text' => 0,'duration_value' => 0);
			 $arrDels[$drvKey]['dropoff'] = array('distance_text' => 0,'distance_value' =>0,'duration_text' => 0,'duration_value' => 0);
			 
			 if(isset($cVals[0]['status']) && $cVals[0]['status'] == 'OK') {
			 $arrDels[$drvKey]['pickup'] = array('distance_text' => $cVals[0]['distance']['text'],'distance_value' => $cVals[0]['distance']['value'],'duration_text' => $cVals[0]['duration']['text'],'duration_value' => $cVals[0]['duration']['value']);
			 }
			 if(isset($cVals[1]['status']) && $cVals[1]['status'] == 'OK') {
			 $arrDels[$drvKey]['dropoff'] = array('distance_text' => $cVals[1]['distance']['text'],'distance_value' => $cVals[1]['distance']['value'],'duration_text' => $cVals[1]['duration']['text'],'duration_value' => $cVals[1]['duration']['value']); 
			 }		
			 
			 // New code
			if(isset($cVals[0]['status']) && $cVals[0]['status'] == 'OK' && isset($cVals[1]['status']) && $cVals[1]['status'] == 'OK') {
				$pickupDistanceMeters = $cVals[0]['distance']['value'];
				$dropoffDistanceMeters = $cVals[1]['distance']['value'];
				//print("<pre> drvKe:$drvKey".print_r($cVals,true)."</pre>");
				 
				if($pickupDistanceMeters < $radiusLimit && $dropoffDistanceMeters < $radiusLimit) {
					$newArrDels[] = $drvKey; 
				}	
				
			}	 
			 
			 
			 
			$cnt++;		 
		} 	
		return $newArrDels;
	}	
	
	/** Function to calculate distance between 2 sources having longitude and latitude.
	 *  Reference: 
		1. https://martech.zone/calculate-distance/ 
		2. https://www.geeksforgeeks.org/program-distance-two-points-earth
		3. https://www.geodatasource.com/distance-calculator - For getting distance online for TESTINg purpose.
	*/
	
	public function getDistanceBetweenPoints($latitude1, $longitude1, $latitude2, $longitude2, $unit = 'Km') {
	  $radiusLimit	= config('constants.DELIVERYRADIUS');	
		
	  $theta = $longitude1 - $longitude2; 
	  $distance = (sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))) + (cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta))); 
	  $distance = acos($distance); 
	  $distance = rad2deg($distance); 
	  $distance = $distance * 60 * 1.1515; 
	  switch($unit) { 
		case 'Mi': 
		  break; 
		case 'Km' : 
		  $distance = $distance * 1.609344; 
	  } 
	  
	  $distanceVal = round($distance,2);
	  $status = false;
	   
	  if($distanceVal < $radiusLimit)  //$radiusLimit = 10 km
		  $status = true;
	  
	  return array('status' => $status, 'distance' => $distanceVal);
	  
	  //return (round($distance,2)); 
	}
	
	
}
