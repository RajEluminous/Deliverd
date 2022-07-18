<?php

namespace App\Http\Controllers\Api\v1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\v1\APIBaseController as APIBaseController;
use App\Mail\ForgotPasswordMail;  
use App\Mail\VehicleApprovalRequestMail; 
use App\Mail\VehicleAdminApprovalRejectedMail;
use Validator;
use DB;
use App\Models\VehicleModel;
use Mail;
use Hash;
 
class VehicleController extends APIBaseController
{	
	Private $BaseModel;
	Private $PasswordReset;
	
	public function __construct(VehicleModel $VehicleModel)
    {
       $this->BaseModel = $VehicleModel; 	 
    }
	
	public function newVehicle(Request $request) {
		 
	 
		$input = $request->all();	
		//print_r($input); 
		//print_r($request->vehicle_pictures[0]);
		 
		$validator = Validator::make($input, [
			'fleet_id' => 'required',
			'type' => 'required',
            'make' => 'required', 
			'model' => 'required',
			'year' => 'required',
			'registration_no' => 'required',
			'boot_capacity' => 'required',
			'licensedisk_img_str' => 'required',
			'insurance_img_str' => 'required'			
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'falied',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check if 5 vehicle images are present		
		if(count($request->vehicle_pictures)<4) {
			$picCount = count($request->vehicle_pictures);
			return response()->json([
				'success' => 'false',
				'message' => $picCount.' vehicle picture(s) are uplodaed. 4 vehicle pictures need to be there.'
	    	]);
		}
		  
		// check if Fleet avaialable or not.
		$rsFltSQL = DB::table('fleet')
					->Where('id', '=', $request->fleet_id)
					->Where('status', '=', 1)
					->first();
		
		if(!$rsFltSQL) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid/Inactive fleet'
	    	]);
		} 
		
		// Allow only 1 driver in case of Owner Driver		
		if($rsFltSQL->type=='O') {
			$rec_fhd_count = DB::table('fleet_has_vehicles')->select('vehicle_id')->where(['fleet_id'=> $request->fleet_id])->count();	 
			
			if($rec_fhd_count >= 1) {
				return response()->json([
					'success' => 'false',
					'message' => 'Vehicle already assigned to Owner driver. Owner driver can add only one vehicle.'
				]); 
			}				
		}		
		
		// Check if name/email/mobile no. already exists
		$dsSQL = DB::table('vehicles')
					->Where('registration_no', '=', $request->registration_no)
					->first();
		
		if(isset($dsSQL->id)) {
			return response()->json([
				'status' => 'falied',
				'message' => 'Vehicle registration number already exist.'
	    	]); 
		} else {
			
			$input_final = array();
			$input_final['type'] = request('type');
			$input_final['make'] = request('make');
			$input_final['model'] = request('model');
			$input_final['year'] = request('year');
			$input_final['registration_no'] = request('registration_no');
			$input_final['boot_capacity'] = request('boot_capacity');
			$input_final['profile_img_str_1'] = $request->vehicle_pictures[0];
			$input_final['profile_img_str_2'] = $request->vehicle_pictures[1];
			$input_final['profile_img_str_3'] = $request->vehicle_pictures[2];
			$input_final['profile_img_str_4'] = $request->vehicle_pictures[3];
			$input_final['profile_img_str_5'] = "";//$request->vehicle_pictures[4];
			$input_final['licensedisk_img_str'] = request('licensedisk_img_str');
			$input_final['insurance_img_str'] = request('insurance_img_str');
			$input_final['status'] = '0';
			$input_final['is_verified'] = '0';
			$input_final['is_deleted'] = '0';
			$input_final['created_at'] = date('Y-m-d H:i:s');
			$input_final['updated_at'] = date('Y-m-d H:i:s');
			 
			// Inser vehicle record			
			$vehicle = VehicleModel::create($input_final);	
			
			if($vehicle->id>0){ 
			
				$ins_id_fhv = DB::table('fleet_has_vehicles')->insertGetId(
					[ 
					 'fleet_id'=> $request->fleet_id, 
					 'vehicle_id'=> $vehicle->id,  
					 'created_at'=> date('Y-m-d H:i:s'),  
					 'updated_at'=> date('Y-m-d H:i:s')
					]);
					
				// Send mail	-- commented as giving error Swift_TransportException: Expected response code 354 but got code 550			 	
				$user_email = "eluminous.sse24@gmail.com,clayton@tigerfishsoftware.co.za"; // Admin email
				$data['link'] =  config('constants.EMAIL_BASE_URL').'admin/approve-vehicle/'.$vehicle->id;
				$to = explode(',', $user_email); 
				$result = Mail::to($to)->cc(['emahajan@gmail.com'])->send(new VehicleApprovalRequestMail($data));
				 						
				return response()->json([
					'status' => 'success', 
					'message' => 'Vehicle added successfully. Approval pending.'
				]);
				
				//return $this->sendResponse($vehicle->toArray(), 'Vehicle added successfully. Approval pending.');	
			} else {
				return response()->json([
				'success' => false,
				'message' => 'Falied to add vehicle. Please try again.'
				]);
			}
			
			
		}  
	}
 
	public function getVehicles(Request $request){
		$input = $request->all();	
		
		$recordLimit = 5;
		
		// offset and page calculation
		$page = 1;
		if(!empty($request->page)) {	
			$page = $request->page;	
			if(false === $page) {
				$page = 1;
			}
		}
		$offset = ($page - 1) * $recordLimit;
		// -- end of offset -------------
		 
		$fleet_id = $request->fleet_id;  
		
		if(isset($request->fleet_id) && $request->fleet_id>0) {
			
			//check for inactive or invalid fleet
			$rsFltSQL = DB::table('fleet')
					->Where('id', '=', $request->fleet_id)
					->Where('status', '=', 1)
					->first();
			
			if(!$rsFltSQL) {
				return response()->json([
					'status' => 'falied',
					'message' => 'Invalid/Inactive fleet'
				]);
			}  
			
			// Process the data.
			$vehls = DB::table('fleet_has_vehicles')->select('vehicle_id')->where(['fleet_id'=> $fleet_id])->get();
			  
			$vehls_id = '';
			foreach($vehls as $vhl) {
				$vehls_id .= $vhl->vehicle_id.',';
			}		
			$vehls_id = substr($vehls_id,0,-1);
			$myArray = explode(',', $vehls_id);
			
			$vehicles = DB::table('vehicles')->select('*')
						->whereIn('id', $myArray)
						->orderBy('id','desc')
					    ->offset($offset)
						->limit($recordLimit)
						->get();	
						
			$vehicles_count = DB::table('vehicles')
						->select('id')
						->whereIn('id', $myArray)					     
						->count();
						
			####### To get Weekly earnings for vehicles - To show on Fleet admin - SEP112020RM ###############
			$start_time = ' 00:00:00';
            $end_time 	= ' 23:59:59';
			
			 //Week Sunday to Saturday
			$day = date('w');
            $week_start = date('Y-m-d', strtotime('-'.$day.' days')).$start_time;
			$week_end = date('Y-m-d', strtotime('+'.(6-$day).' days')).$end_time;
			
			$getWeekTotal = DB::table('trip')
    							//->whereIn('vehicle_id', $myArray)
								->selectRaw('SUM(trip.total_est_earning) as total_earning,SUM(trip.total_distance) as total_distance,COUNT(trip.id) as total_trips')
								->where('fleet_id','=',$fleet_id)
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
			//print_r($week_stats);	
			####### END - To Show Weekly Earnings of vehicles ###############			
			 
		} else {
			// Process for Admin.
			$vehicles = DB::table('vehicles')->select('*')
						->orderBy('id','desc')
					    ->offset($offset)
						->limit($recordLimit)
						->get();
						
			$vehicles_count = DB::table('vehicles') 
						->count();	
			$week_stats = "";					
		}	
		
		$vhlArr = array();
		 
		foreach($vehicles as $vhl) {
			
			 $fleetObj = $this->getFleetInfoByVehicleId($vhl->id);  
			 
			 $arrNew = array();
			 $arrNew['id'] = $vhl->id;
			 $arrNew['type'] = $vhl->type;
			 $arrNew['make'] = $vhl->make;
			 $arrNew['model'] = $vhl->model;
			 $arrNew['year'] = $vhl->year;
			 $arrNew['registration_no'] = $vhl->registration_no;
			 $arrNew['boot_capacity'] = $vhl->boot_capacity;
			 $arrNew['status'] = $vhl->status;	
			 $arrNew['is_verified'] = $vhl->is_verified;
			 $arrNew['fleet_name'] = $fleetObj['fleet_name'];			 
			 
			 $vhlArr[] = $arrNew;
		}
		 		
		return response()->json([
			'status' => 'success',
			'count' => $vehicles_count,
			'data' => $vhlArr,
			'week_stats' => $week_stats
		]);
				 
	}
	
	public function getFleetInfoByVehicleId($vehicleid) {
		
		$fleetObj = DB::table('fleet')
					->join('fleet_has_vehicles', function ($join) use ($vehicleid) {				
						$join->on('fleet.id', '=', 'fleet_has_vehicles.fleet_id')
						  ->where('fleet_has_vehicles.vehicle_id', '=', $vehicleid);   
						}) 
					->select('fleet.id','first_name','last_name')
					->first();
						
		$fleet_name = "";		
		if(isset($fleetObj->first_name))
		  $fleet_name .= $fleetObj->first_name;
		
		if(isset($fleetObj->last_name))
		  $fleet_name .= ' '.$fleetObj->last_name;	
			  
		return array('fleet_name' => $fleet_name);	  
	}
	
	// To enable/disable driver
	public function vehicleEnableDisable(Request $request) {
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'vehicle_id' => 'required', 
			'status' => 'required'
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		if(isset($request->vehicle_id) && $request->vehicle_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid vehicle'
	    	]);
		}
		
		// check if driver exists
		if($request->vehicle_id > 0) {
			
			$veh_data = DB::table('vehicles')
						->Where('id', '=', $request->vehicle_id)
						->first();
		
			 
			if(empty($veh_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid vehicle'
	    	]);
			} 
		}
				
		if($request->status == 0) { 
			
			// Update 'VEHICLES' table and set status 1 = Enabled and update time.
			 
			$thdSQL = DB::table('vehicles')
						->where([
									'id' => $request->vehicle_id									 
								])
						->update([
							'status' => '1',
							'updated_at' => date('Y-m-d H:i:s') 		
						]); 
			return response()->json([
				'success' => 'true',
				'message' => 'Vehicle status enabled successfully.'
	    	]);		
			
		} else {
			// Update 'VEHICLES' table and set status 0 = Disabled and update time.
			$thdSQL = DB::table('vehicles')
						->where([
									'id' => $request->vehicle_id									 
								])
						->update([
							'status' => '0',
							'updated_at' => date('Y-m-d H:i:s') 		
						]); 
						
			return response()->json([
				'success' => 'true',
				'message' => 'Vehicle status disabled successfully.'
	    	]);		
			
		} 
		
	}
		
	public function getVehicleVerificationInfo(Request $request){ 
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'vehicle_id' => 'required' 
        ]);
			
		if($validator->fails()){
            return response()->json([
				'status' => 'falied',
				'message' => $validator->errors()
	    	]);     
        }	
		
		// check if vehicle exists
		if($request->vehicle_id) {
			
			$vhl_data = $this->BaseModel->where('id',$request->vehicle_id)->first();
			 
			if(empty($vhl_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid Vehicle'
	    	]);
			} 
			
			$fleet_name = '';
			if($vhl_data->id >0) {
				
				$vehicleid = $vhl_data->id;	
				$fleetObj = DB::table('fleet')
				->join('fleet_has_vehicles', function ($join) use ($vehicleid) {
				
				$join->on('fleet.id', '=', 'fleet_has_vehicles.fleet_id')
				  ->where('fleet_has_vehicles.vehicle_id', '=', $vehicleid);   
				}) 
				->select('fleet.id','first_name','last_name')
				->first();  
				 
				if(empty($fleetObj)) {
					return response()->json([
						'success' => 'false',
						'message' => 'Fleet not assigned to this vehicle.'
					]); 
				} 
				 
				if(isset($fleetObj->first_name))
				  $fleet_name .= $fleetObj->first_name;
				
				if(isset($fleetObj->last_name))
				  $fleet_name .= ' '.$fleetObj->last_name;					
			}
			 
			// process the data.
			$rs_final = array();		
			$rs_final['driver_id'] = $vhl_data->id;	
			$rs_final['type'] = $vhl_data->type;
			$rs_final['make'] = $vhl_data->make;
			$rs_final['model'] = $vhl_data->model;
			$rs_final['year'] = $vhl_data->year;
			$rs_final['fleet_name'] =$fleet_name;
			
			$rs_final['registration_no'] = $vhl_data->registration_no;
			$rs_final['boot_capacity'] = $vhl_data->boot_capacity;
			$rs_final['profile_img_str_1'] = $vhl_data->profile_img_str_1;
			$rs_final['profile_img_str_2'] = $vhl_data->profile_img_str_2; 
			$rs_final['profile_img_str_3'] = $vhl_data->profile_img_str_3;
			$rs_final['profile_img_str_4'] = $vhl_data->profile_img_str_4;
			$rs_final['profile_img_str_5'] = $vhl_data->profile_img_str_5;
			$rs_final['licensedisk_img_str'] = $vhl_data->licensedisk_img_str;
			$rs_final['insurance_img_str'] = $vhl_data->insurance_img_str;
			$rs_final['status'] = $vhl_data->status;
			$rs_final['is_verified'] = $vhl_data->is_verified;
			
			return response()->json([
				'status' => 'success', 
				'data' => $rs_final
			]);
		}		
		
		
	}	
	
	public function vehicleAdminApproveReject(Request $request) {
		 
		$input = $request->all();
		//print_r($input);
		$arrStatus = array('approved','rejected');
		$validator = Validator::make($input, [
            'vehicle_id' => 'required', 
			'status' => 'required' 			 
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		//check if its valid driver.
		if(isset($request->vehicle_id) && $request->vehicle_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid vehicle'
	    	]);
		}
		
		if(isset($request->status) && !in_array($request->status,$arrStatus)) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid status'
	    	]);
		}
		
		// Check is vehicle exists.			
		$veh_data = $this->BaseModel->where('id',$request->vehicle_id)->first();
		 
		if(empty($veh_data)){
			return response()->json([
			'success' => 'false',
			'message' => 'Invalid vehicle id'
		]);
		} 
		
		// GEt Fleet data
		$vehicleid = $request->vehicle_id;
		$fleetObj = DB::table('fleet')
				->join('fleet_has_vehicles', function ($join) use ($vehicleid) {
				
				$join->on('fleet.id', '=', 'fleet_has_vehicles.fleet_id')
				  ->where('fleet_has_vehicles.vehicle_id', '=', $vehicleid);   
				}) 
				->select('fleet.id','email')
				->first(); 
		
		$fleetEmail = $fleetObj->email;
		
		if($request->status=='approved')	{
			/*
			 - Updated db for status and is_verified 
			 - sendmail	
			*/	
			
			 $thdSQL = DB::table('vehicles')
						->where([
									'id' => $request->vehicle_id									 
								])
						->update([
							'is_verified' => '1', 
							'updated_at' => date('Y-m-d H:i:s') 		
						]);   
						
			 $url_info = url('/');	;
			 $this->sendAdminVehicleApprovalRejectedMail($fleetEmail,'approved',$url_info,$veh_data->registration_no);
			 			
		} else {
			/*
			 - Updated db for status and reason 
			 - sendmail	
			*/	
			
			 $thdSQL = DB::table('vehicles')
						->where([
									'id' => $request->vehicle_id									 
								])
						->update([
							'is_verified' => '3', 
							'reject_reason' => $request->reject_reason, 
							'updated_at' => date('Y-m-d H:i:s') 		
						]);  
			$url_info = config('constants.EMAIL_BASE_URL').'admin/edit-vehicle/'.$request->vehicle_id;	
			$this->sendAdminVehicleApprovalRejectedMail($fleetEmail,'rejected',$url_info,$request->reject_reason);
		} 
		
		return response()->json([
			'success' => 'true',
			'message' => 'Vehicle status updated successfully.'
		]);	
		
	}

	function sendAdminVehicleApprovalRejectedMail($fleetEmail,$approvalStatus, $url_info, $reason=""){
				 	
		$user_email = "$fleetEmail,eluminous.sse24@gmail.com"; // Admin email   ,clayton@tigerfishsoftware.co.za
		$data['link_text'] = "Go to etYay and Approve now";
		$data['link'] = $url_info;
		$data['status'] = $approvalStatus;
		$data['reason'] = $reason;
		$to = explode(',', $user_email); 
		$result = Mail::to($to)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new VehicleAdminApprovalRejectedMail($data));
		
	}

	// Update vehicle information
	public function editVehicle(Request $request) {
		 		
		$input = $request->all();	
		//print_r($input); 
		//print_r($request->vehicle_pictures[0]);
		 
		$validator = Validator::make($input, [
			'vehicle_id' => 'required',
			'type' => 'required',
            'make' => 'required', 
			'model' => 'required',
			'year' => 'required',
			'registration_no' => 'required',
			'boot_capacity' => 'required',
			'licensedisk_img_str' => 'required',
			'insurance_img_str' => 'required'			
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'falied',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check if 5 vehicle images are present		
		if(count($request->vehicle_pictures)<4) {
			$picCount = count($request->vehicle_pictures);
			return response()->json([
				'success' => 'false',
				'message' => $picCount.' vehicle picture(s) are uplodaed. 4 vehicle pictures need to be there.'
	    	]);
		}
		  		 
		
		// Check if name/email/mobile no. already exists
		$dsSQL = DB::table('vehicles')
					->Where('registration_no', '=', $request->registration_no)
					->Where('id', '!=', $request->vehicle_id)
					->first();
		
		if(isset($dsSQL->id)) {
			return response()->json([
				'status' => 'falied',
				'message' => 'Vehicle registration number already exist.'
	    	]); 
		} else {
			
			$input_final = array();
			$input_final['type'] = request('type');
			$input_final['make'] = request('make');
			$input_final['model'] = request('model');
			$input_final['year'] = request('year');
			$input_final['registration_no'] = request('registration_no');
			$input_final['boot_capacity'] = request('boot_capacity');
			$input_final['profile_img_str_1'] = $request->vehicle_pictures[0];
			$input_final['profile_img_str_2'] = $request->vehicle_pictures[1];
			$input_final['profile_img_str_3'] = $request->vehicle_pictures[2];
			$input_final['profile_img_str_4'] = $request->vehicle_pictures[3];
			$input_final['profile_img_str_5'] = "";//$request->vehicle_pictures[4];
			$input_final['licensedisk_img_str'] = request('licensedisk_img_str');
			$input_final['insurance_img_str'] = request('insurance_img_str');
			$input_final['status'] = '0';
			$input_final['is_verified'] = '0';
			$input_final['is_deleted'] = '0';
			$input_final['created_at'] = date('Y-m-d H:i:s');
			$input_final['updated_at'] = date('Y-m-d H:i:s');
			 
			$vhlSQL = DB::table('vehicles')
						->where([
									'id' => $request->vehicle_id									 
								])
						->update([
							'type' => request('type'), 
							'make' => request('make'), 
							'model' =>  request('model'),
							'year' => request('year'),
							'registration_no' => request('registration_no'),
							'boot_capacity' => request('boot_capacity'),
							'profile_img_str_1' => $request->vehicle_pictures[0],
							'profile_img_str_2' => $request->vehicle_pictures[1],
							'profile_img_str_3' => $request->vehicle_pictures[2],
							'profile_img_str_4' => $request->vehicle_pictures[3],
							'profile_img_str_5' => "",//$request->vehicle_pictures[4],
							'licensedisk_img_str' => request('licensedisk_img_str'),
							'insurance_img_str' => request('insurance_img_str'), 
							'updated_at' => date('Y-m-d H:i:s') 		
						]);   
			 
			// Inser vehicle record			
			//$vehicle = VehicleModel::create($input_final);	
			
			if($vhlSQL){ 
			   
			   $user_email = "eluminous.sse24@gmail.com,clayton@tigerfishsoftware.co.za"; // Admin email
				$data['link'] =  config('constants.EMAIL_BASE_URL').'admin/approve-vehicle/'.$request->vehicle_id;
				$to = explode(',', $user_email); 
				$result = Mail::to($to)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new VehicleApprovalRequestMail($data));
			   
				return response()->json([
					'success' => true,
					'message' => 'Vehicle information updated successfully.'
				]);
				
				//return $this->sendResponse($vehicle->toArray(), 'Vehicle added successfully. Approval pending.');	
			} else {
				return response()->json([
				'success' => false,
				'message' => 'Falied to update vehicle information. Please try again.'
				]);
			}
						
		}  
	}
	 
	// For mobile app - To show list of available vehciles for a Fleet.
	public function getFleetAvailableVehicles(Request $request){
		$input = $request->all();	
		 		 
		// Do basic validation
		$validator = Validator::make($input, [
            'driver_id' => 'required'   
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		if(isset($request->driver_id) && $request->driver_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
		}
		
		// check if driver exists
		if($request->driver_id > 0) {
			 
			$drv_data = DB::table('fleet_has_drivers')
					->where([
								'driver_id' => $request->driver_id	 
							])
					->first();
			 
			if(empty($drv_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Driver has no fleet.'
	    	]);
			} 
		}
		//print_r($drv_data->fleet_id);
		
		if(isset($drv_data->fleet_id) && $drv_data->fleet_id>0) { 
		
			//check for inactive or invalid fleet
			$rsFltSQL = DB::table('fleet')
					->Where('id', '=', $drv_data->fleet_id)
					->Where('status', '=', 1)
					->first();
			
			if(!$rsFltSQL) {
				return response()->json([
					'success' => 'false',
					'message' => 'Invalid/Inactive fleet'
				]);
			}  
			
			// Process the data. - get all the vehicles for fleet from `fleet_has_vehicles` table
			$fhvehls = DB::table('fleet_has_vehicles')->select('vehicle_id')->where(['fleet_id'=> $drv_data->fleet_id])->get();			  
			$fhvehls_id = '';
			foreach($fhvehls as $vhl) {
				$fhvehls_id .= $vhl->vehicle_id.',';
			}		
			$fhvehls_id = substr($fhvehls_id,0,-1);
			$fhvehls_id_arr = explode(',', $fhvehls_id);
			
			// Process the data. - get all the vehicles from `driver_has_vehicle` table
			$dhvehls = DB::table('driver_has_vehicle')->select('vehicle_id')->get();			  
			$dhvehls_id = '';
			foreach($dhvehls as $vhl) {
				$dhvehls_id .= $vhl->vehicle_id.',';
			}		
			$dhvehls_id = substr($dhvehls_id,0,-1);
			$dhvehls_id_arr = explode(',', $dhvehls_id);
			
			$vehicles = DB::table('vehicles')->select('*')
						->Where('status', '=', 1)
						->whereIn('id', $fhvehls_id_arr)
						->whereNotIn ('id', $dhvehls_id_arr)
						->orderBy('id','desc')					  
						->get();
						
			$vhlArr = array();
		 
			foreach($vehicles as $vhl) {
						 
				 $arrNew = array();
				 $arrNew['id'] = $vhl->id;
				 $arrNew['type'] = $vhl->type;
				 $arrNew['make'] = $vhl->make;
				 $arrNew['model'] = $vhl->model;
				 $arrNew['year'] = $vhl->year;
				 $arrNew['registration_no'] = $vhl->registration_no;
				 $arrNew['boot_capacity'] = $vhl->boot_capacity;
				 $arrNew['status'] = $vhl->status;	
				 $arrNew['is_verified'] = $vhl->is_verified;
				 $arrNew['fleet_id'] = $drv_data->fleet_id;
				 
				 $vhlArr[] = $arrNew;
			}
					
			return response()->json([
				'success' => 'true', 
				'data' => $vhlArr
			]); 
			
		} else {
			return response()->json([
					'success' => 'false',
					'message' => 'Invalid fleet'
				]);	
		}
		 
	}	
	
	// Assign Vehicle to Driver 
	public function assign_vehicle_to_driver(Request $request) {
		 
		// basic validation
		// check if vehicle is active.
		// check if driver is active.
		// Check if vehicle is aleary assigned to driver.
		// Insert record
		$input = $request->all();	
		
		$validator = Validator::make($input, [
			'vehicle_id' => 'required',
            'driver_id' => 'required',
			'latitude' => 'required',
			'longitude' => 'required'			
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		if(isset($request->vehicle_id) && $request->vehicle_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid Vehicle'
	    	]);
		}
		
		if(isset($request->driver_id) && $request->driver_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
		}
		
		$rsFltSQL = DB::table('vehicles')
					->Where('id', '=', $request->vehicle_id)
					->Where('status', '=', 1)
					->first();
		
		if(!$rsFltSQL) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid/Inactive vehicle'
	    	]);
		}  
		
		$rsDrvSQL = DB::table('driver')
					->Where('id', '=', $request->driver_id)
					->Where('status', '=', 1)
					->first();
		
		if(!$rsDrvSQL) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid/Inactive driver'
	    	]);
		} 
		
		$rsDrvHasVhlSQL = DB::table('driver_has_vehicle') 
					->Where('vehicle_id', '=', $request->vehicle_id)
					->first();
					
		if($rsDrvHasVhlSQL) {
			return response()->json([
				'success' => 'false',
				'message' => 'Vehicle is already assigned to driver.'
	    	]);
		}
		
		$rsFltHasDrvrSQL = DB::table('driver_has_vehicle') 
					->Where('driver_id', '=', $request->driver_id)
					->first();
					
		
		if($rsFltHasDrvrSQL && $rsFltHasDrvrSQL->vehicle_id==0) {
			$dSQL = DB::table('driver_has_vehicle')
						->where('driver_id', $request->driver_id)
						->update([
							 'vehicle_id'=> $request->vehicle_id,
							 'latitude' => $request->latitude ,
							 'longitude' => $request->longitude ,
							 'status' => '1'
						]);
			 
			/* $ins_id_fhd = DB::table('driver_has_vehicle')->insertGetId(
						[ 
						 'vehicle_id'=> $request->vehicle_id, 
						 'driver_id'=> $request->driver_id,  
						 'latitude'=> $request->latitude,  
						 'longitude'=> $request->longitude,  
						 'status' => '1',
						 'created_at'=> date('Y-m-d H:i:s'),  
						 'updated_at'=> date('Y-m-d H:i:s')
						]); */	  
			
			if($dSQL) {
				return response()->json([
				'success' => 'true',
				'message' => 'Vehicle assigned to Driver successfully.'
				]);
			} else {
				return response()->json([
				'success' => 'false',
				'message' => 'Unable to assign Vehicle to the Driver. Try again.'
				]);
			 
			}
		} else {
			
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid processing of data.'
				]);
			 			
		} 
		
	}
	
	// To get car makes	
	public function getCarMakes(Request $request){
		$input = $request->all();	
		 	 
		$validator = Validator::make($input, [
			'car_makes' => 'required'           			
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        } 
		
		if($request->car_makes == 'true') {
			/* $car_makes = DB::table('car_makes')->select('*') 
						->orderBy('id','asc') 
						->get(); */	
			
			$car_makes = 		DB::table('car_makes')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('car_models')
                      ->whereRaw('car_models.model_make_id = car_makes.make_id');
            })
            ->get();
			
			$vhlArr = array();
			foreach($car_makes as $vhl) {
							 
				$arrNew = array();
				$arrNew['id'] = $vhl->id;
				$arrNew['make_id'] = $vhl->make_id;
				$arrNew['make_display'] = $vhl->make_display;
				$arrNew['make_country'] = $vhl->make_country; 
				 
				$vhlArr[] = $arrNew;
			}
					
			return response()->json([
				'status' => 'success', 
				'data' => $vhlArr
			]); 	
		} else {
			return response()->json([
				'success' => 'false',
				'message' => 'Car make flag missing'
	    	]); 
		} 
		
	} 
	
	// To get car makes	
	public function getCarModels(Request $request){
		$input = $request->all();	
		
		 	 
		$validator = Validator::make($input, [
			'make' => 'required'           			
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        } 
		
		$vehicles = DB::table('car_models')->select('*')
						->Where('model_make_id', '=', $request->make) 
						->orderBy('id','asc')					  
						->get();
						
		$vhlArr = array();
		
		foreach($vehicles as $vhl) {
					 
			 $arrNew = array();
			 $arrNew['id'] = $vhl->id;
			 $arrNew['model_make_id'] = $vhl->model_make_id;
			 $arrNew['model_name'] = $vhl->model_name;
			 $arrNew['model_year'] = $vhl->model_year;
			   
			 $vhlArr[] = $arrNew;
		}
				
		return response()->json([
			'status' => 'success', 
			'data' => $vhlArr
		]); 
		
		
	} 
	
	
}
