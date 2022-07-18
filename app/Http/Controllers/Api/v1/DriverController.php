<?php

namespace App\Http\Controllers\Api\v1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Mail\ForgotPasswordMail;
use App\Mail\DriverInviteMail; 
use App\Mail\DriverApprovalRequestMail; 
use App\Mail\DriverAdminApprovalRejectedMail;   
use App\Mail\DriverUpdatePasswordMail; 
use \Illuminate\Auth\Passwords\PasswordBroker;
use Validator;
use App\PasswordReset;
use DB;
use App\DriverModel;

use Mail;
use Hash;

class DriverController extends Controller
{	
	Private $BaseModel;
	Private $PasswordReset;

	public function __construct(DriverModel $DriverModel,PasswordReset $PasswordReset,PasswordBroker $PasswordBroker
    )
    {
       $this->BaseModel = $DriverModel; 
		$this->PasswordReset   = $PasswordReset;
		$this->PasswordBroker = $PasswordBroker;
    }

    public function login(Request $request) 
    {
        // check for valid username 
        $credentials = [];

        $user = self::_validateEmail($request->email);
        if(!$user)
        {
			return response()->json([
				'status' => 'Error',
				'message' => 'Wrong email address',
			]);
        }
	 
        $credentials['email']    = $request->email;
        $credentials['password'] = $request->password;   
		 
		if (!Hash::check(request()->password, $user->password)) 
		{
			return response()->json([
			'status' => 'Error',
			'message' => 'Invalid credentials',
			]);
		}
		else
		{ 
			if($user->status==1 && $user->is_verified==1) {	
				Auth::login($user);   

				$tokenResult = $user->createToken('Personal Access Token');			

				$token = $tokenResult->token;

				if ($request->remember_me)
					$token->expires_at = Carbon::now()->addWeeks(1);
					$token->save();
					
					return response()->json([
						'status' => 'Success',
						'message' => 'Successfully Login',
						'token' => $tokenResult->accessToken,
						'name' => $user->first_name.' '.$user->last_name,
						'id' => $user->id,
						'expires_at' => Carbon::parse($tokenResult->token->expires_at)->toDateTimeString()
					]);     
			} else {
				return response()->json([
					'status' => 'Error',
					'message' => 'Inactive driver',
					]);
			}	
           
        }           

    }

	public function logout(Request $request)
	{ 	

		if (Auth::check()) {
			Auth::user()->token()->revoke();
			return response()->json([
				'status' => 'Success',
	          'message' => 'Successfully logged out'
	    	]);
		}else
		{
			return response()->json([
				'status' => 'Error',
				'message' => 'Invalid credentials',
			]);
		}
	}

	public function resetPasswordSubmit(Request $request)
    {           
    	$user = Auth::user(); 
    	
    	$old_password = $request->old_password; 
    	$new_password = $request->new_password; 

		if (Hash::check($old_password, $user->password)) 
		{
			$user = $this->BaseModel->select('id')->where('email',$user->email)->first();

			$this->BaseModel->where('id',$user->id)->update(['password' => Hash::make($new_password)]);

			return response()->json([
			'status' => 'Success',
			'message' => 'Password updated'
			]);
		}
		else
		{			
			return response()->json([
			'status' => 'Error',
			'message' => 'Wrong old password',
			]);
		}  
        
    }    

    public function _validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
        {
        		return false;                     
        }else{
        	$user = $this->BaseModel->where('email', $email)->first();
        	if(!empty($user)){
        		return $user;
        	}else{
        		return false;                     
        	}
        }
        return true;
    }

    public function forgotPassword(Request $request)
	{
		$email =$request->email;

		$user_data = $this->BaseModel->where('email',$email)->first();

		if(!isset($user_data) && $user_data=='')
		{
			return response()->json([
			'status' => 'Error',
			'message' => 'Invalid credentials',
			]);
		}
		else
		{			
			$user_id     	= $user_data->id;
			$user_email   	= $user_data->email;
			$name    		= $user_data->first_name.' '.$user_data->last_name;                            
			$data       = [];
			try 
			{	
				$token = $this->PasswordBroker->createToken($user_data);
				$data['name'] = $name;		
				$data['otp'] = $token; 				
				$data['otp'] = (rand(1000,9999)); 

				// dd($data);
	            $result = Mail::to($user_email)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new ForgotPasswordMail($data));
	            $addToken = $this->PasswordReset->insert([
					                        'email' => $user_email,
					                        'token' => '',
					                        'otp' => $data['otp'],
					                        'created_at' => date('Y-m-d H:i:s')
					                    ]);				
			} 
	        catch (\Throwable $th) 
	        {

	        	//dd($th);
	        	return response()->json([
				'status' => 'Error',
				'message' => 'Server error']);
	        }
	            
			return response()->json([
				'status' => 'Success',
				'message' => 'Mail send Successfully',
			]);
	    }  	
    }


    public function checkOtp(Request $request)
    {    

		$otp =  $request->otp;	
    	$email = $request->email;      

		$isValidObject = $this->PasswordReset->where(['email'=>$email,'otp'=>$otp])->first(); 

		if($isValidObject)
		{
			$startTime = Carbon::now();
			$finishTime = Carbon::parse($isValidObject->created_at);

			$totalDuration = $finishTime->diffInSeconds($startTime);
			
			if($totalDuration > 600)
			{
				$this->PasswordReset->where(['email'=>$email,'otp'=>$otp])->delete();        
	        	return response()->json([
					'status' => 'Error',
					'message' => 'otp is expired.'
				]);
			}else
	        {
	        	return response()->json([
					'status' => 'Success',
					'message' => 'Opt is validated.'
				]);
	    	} 
	    }
	    else
	    {
	    	return response()->json([
					'status' => 'Error',
					'message' => 'Otp is wrong.'
				]);
	    } 
    } 


     public function updatePassword(Request $request) {
			
		$input = $request->all();
		$validator = Validator::make($input, [
            'email' => 'required'  
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }
			
    	$email = $request->email;  
		if(!empty($request->password)) {
			$password = $request->password;       
		} else {
			$len = 10;
			$password = $this->getUniqueDriverString($len);
		} 
		  
		$user_data = $this->BaseModel->where('email',$email)->first();
		 		
		if($user_data)
		{
	            $this->BaseModel->where('id',$user_data->id)->update(['password' => Hash::make($password)]);
	            $this->PasswordReset->where(['email'=>$email])->delete();        
				
				$user_email = $email; 
				$data['password'] = $password;
				$result = Mail::to($user_email)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new DriverUpdatePasswordMail($data));
				
	        	return response()->json([
					'status' => 'Success',
					'message' => 'Password updated.'
				]);
	    	
	    }
	    else
	    {
	    	return response()->json([
					'status' => 'Error',
					'message' => 'Invalid data.'
				]);
	    } 
    } 

	// To update driver online/offline status - RM20200709
	public function driverStatus(Request $request) {  	
				 
		$input = $request->all();
		$validator = Validator::make($input, [
            'driver_id' => 'required',
			'lat' => 'required',
			'lng' => 'required',
			'status' => 'required'
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }
		
		$rs_data = $this->BaseModel->where('id',$request->driver_id)->first();
		if($rs_data->status == 1) {
			 
			/* Check if record exist in 'driver_has_vehicle' table or not
			   if Yes
					- update status
						 (if=1) {
							 
						 }	
				if No 
					- Create entry
					- with driverid, vehicle=0, lat/long
			*/
			
			if($request->status == 'online') {
				$driver_status = '1';
				$driver_msg = 'Driver is online';
			} else {
				$driver_status = '0';
				$driver_msg = 'Driver is offline'; 
			}
				
			$rs_dhv = DB::table('driver_has_vehicle')->where('driver_id','=',$request->driver_id)->first();
			  
			if(isset($rs_dhv->id) && $rs_dhv->id > 0) {
				 
				$dSQL = DB::table('driver_has_vehicle')
						->where('id', $rs_dhv->id)
						->update([
							 'latitude' => $request->lat ,
							 'longitude' => $request->lng ,
							 'vehicle_id'=> '0',
							 'status' => $driver_status
						]);
				 
				return response()->json([
				'status' => 'success',
				'driver_id' => $request->driver_id,
				'message' => $driver_msg
				]);
				 
				
			} else {
				
				$ins_id_fhv = DB::table('driver_has_vehicle')->insertGetId(
					[ 
					 'driver_id'=> $request->driver_id, 
					 'latitude' => $request->lat ,
					 'longitude' => $request->lng ,
					 'vehicle_id'=> '0',  
					 'status' => '1',
					 'created_at'=> date('Y-m-d H:i:s'),  
					 'updated_at'=> date('Y-m-d H:i:s')
					]);
					
				if($ins_id_fhv) {
				return response()->json([
					'status' => 'success',
					'driver_id' => $request->driver_id,
					'message' => 'Driver is online'
					]);
				} else {
					return response()->json([
					'status' => 'failed', 
					'message' => 'Error while driver insert'
					]);
				}
				/*  return response()->json([
					'status' => 'failed',
					'message' => 'Driver has not vehicle'
				]); */
			}
			
		}
		else {
			 return response()->json([
				'status' => 'failed',
				'message' => 'Driver is not active'
	    	]);
		}
		 		
		 
	}	
	
	public function tripAcceptRejectStatus(Request $request){
		
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'driver_id' => 'required',
			'trip_id' => 'required', 
			'status' => 'required'
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }
		
		if(isset($request->driver_id) && $request->driver_id==0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Invalid driver'
	    	]);
		}
		
		if(isset($request->trip_id) && $request->trip_id==0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Invalid trip'
	    	]);
		}
		
		// Process if TRIP = Accepted		
		if($request->status == 'accept') {
			
			// Update 'TRIP' table and set flag 1 = Accepted and Start time.
			$thdSQL = DB::table('trip')
						->where([
									'id' => $request->trip_id									 
								])
						->update([
							'status' => 1,
							'start_time' => date('Y-m-d H:i:s') 		
						]); 
			return response()->json([
				'status' => 'success',
				'message' => 'Trip accepted successfully.'
	    	]);
			
		}
		
		// Process if TRIP = Rejected
		if($request->status == 'reject') {
			// Update 'TRIP' table and set flag 4 = Rejected and Start time.
			$trpSQL = DB::table('trip')
						->where([
									'id' => $request->trip_id									 
								])
						->update([
							'status' => 4,
							'updated_at' => date('Y-m-d H:i:s') 		
						]); 
			// Fetch delivery_id from 'trip_has_deliveries' for TRIP
			$thdSelSQL = DB::table('trip_has_deliveries')->select('delivery_id')->where(['trip_id'=> $request->trip_id])->groupBy('delivery_id')->get();
			//print_r($thdSelSQL);
			$thd_ids = array();
			foreach($thdSelSQL as $rs) {
				$thd_ids[] = $rs->delivery_id ;
			}
				
			// Update the 'Deliveries' Table and set is_booked=0
			$thdSQL = DB::table('delivery')
						->whereIn('id', $thd_ids) 
						->update([
							 'is_booked' => 0 
						]);  
			
			return response()->json([
				'status' => 'success',
				'message' => 'Trip rejected successfully.'
	    	]);
		}				
	}
	
	// To Generate unique key for driver
	public function getUniqueDriverString($len=20){		 
		 
		 $hex = md5("yourSaltHere" . uniqid("", true));

		$pack = pack('H*', $hex);
		$tmp =  base64_encode($pack);

		$uid = preg_replace("#(*UTF8)[^A-Za-z0-9]#", "", $tmp);

		$len = max(4, min(128, $len));

		while (strlen($uid) < $len)
			$uid .= $this->getUniqueDriverString(22);

		return substr($uid, 0, $len);
	}
	
	// To add new driver
	public function newDriver(Request $request){ 
		$input = $request->all();
	 		   
		// Do basic validation
		$validator = Validator::make($input, [
            'first_name' => 'required',
			'last_name' => 'required',			
			'email' => 'required',			
			'mobile' => 'required',
			'fleet_id' => 'required'
			//'password' => 'required', 
			//'licence_no' => 'required',
        ]);
			
		if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }	
		
		// check if email already exists.
		$user_data = $this->BaseModel->select('id')->where('email',$request->email)->first();
		if(isset($user_data) && $user_data->id > 0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Email id already exist.'
	    	]);
		} 
		
		// check if mobile no. already exists.
		$user_data1 = $this->BaseModel->select('id')->where('mobile',$request->mobile)->first();
		if(isset($user_data1) && $user_data1->id > 0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Mobile number already exist.'
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
			$rec_fhd_count = DB::table('fleet_has_drivers')->select('driver_id')->where(['fleet_id'=> $request->fleet_id])->count();	 
			
			if($rec_fhd_count >= 1) {
				return response()->json([
					'success' => 'false',
					'message' => 'Driver already assigned to Owner driver. Owner driver can add only one driver.'
				]); 
			}				
		}		
		 
		$driverUniqString = $this->getUniqueDriverString();
		// Save the data. 
		$password = '123456';  
		
		$input_final = array();
		$input_final['first_name'] = request('first_name');
		$input_final['last_name'] = request('last_name');
		$input_final['password'] = Hash::make($password);
		$input_final['email'] = request('email');
		$input_final['licence_no'] = 'XXXXXXXXXXX';//request('licence_no'); 
		$input_final['mobile'] = request('mobile'); 
		$input_final['uniqstring'] = $driverUniqString;
		$input_final['is_verified'] = '0';
		$input_final['status'] = '0';
		$input_final['is_deleted'] = '0';
		$input_final['created_at'] = date('Y-m-d H:i:s');
		$input_final['updated_at'] = date('Y-m-d H:i:s');
		//print_r($input_final);
		//die();	
		//Inser driver record			
		$driver = DriverModel::create($input_final);
		
		if($driver) {
			
			//insert record in the `fleet_has_drivers` table
			if($driver->id>0){
				$ins_id_fhd = DB::table('fleet_has_drivers')->insertGetId(
						[ 
						 'fleet_id'=> $request->fleet_id, 
						 'driver_id'=> $driver->id,  
						 'created_at'=> date('Y-m-d H:i:s'),  
						 'updated_at'=> date('Y-m-d H:i:s')
						]);		
			}
			
			$uniqueDriverStringUrl = config('constants.EMAIL_BASE_URL').'driver-register/'.$driverUniqString;		
			$user_email = request('email');
			$data['name'] = request('first_name').' '.request('last_name');
			$data['link'] = $uniqueDriverStringUrl;
			$result = Mail::to($user_email)->cc(['eluminous.sse24@gmail.com'])->send(new DriverInviteMail($data));
			
		}
		 
		return response()->json([
				'status' => 'success',
				'message' => 'Driver invited successfully. Invitation email has been sent.'
	    	]);
		
	}
	
	// Function to get Driver info by unique string.
	public function getInviteDriverInfo(Request $request){ 
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'driver_code' => 'required' 
        ]);
			
		if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }	
		
		// check if driver exists
		if($request->driver_code) {
			
			$drv_data = $this->BaseModel->where('uniqstring',$request->driver_code)->first();
			 
			if(empty($drv_data)){
				return response()->json([
					'success' => 'false',
					'message' => 'Invalid driver'
				]);
			} 
			
			if($drv_data->is_verified==1) {
				return response()->json([
					'success' => 'false',
					'message' => 'Driver already approved'
				]);
			}	
			
			$fleet_name = '';
			if($drv_data->id >0) {
				
				$driverid = $drv_data->id;	
				$fleetObj = DB::table('fleet')
				->join('fleet_has_drivers', function ($join) use ($driverid) {

				$join->on('fleet.id', '=', 'fleet_has_drivers.fleet_id')
				  ->where('fleet_has_drivers.driver_id', '=', $driverid);   
				}) 
				->select('first_name','last_name')
				->first();  
				 
				if(isset($fleetObj)) 
					$fleet_name = $fleetObj->first_name.' '.$fleetObj->last_name;				 
			}			 
			 
			$drv_data['fleet_name'] = $fleet_name;
			
			//dont show password in response
			unset($drv_data['password']);
			
			return response()->json([
				'status' => 'success', 
				'data' => $drv_data
			]);
		}					 
	}
	
	// Function to get Driver info by unique string.
	public function getDriverVerificationInfo(Request $request){ 
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'driver_id' => 'required' 
        ]);
			
		if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }	
		
		// check if driver exists
		if($request->driver_id) {
			
			$drv_data = $this->BaseModel->where('id',$request->driver_id)->first();
			 
			if(empty($drv_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
			} 
			
			$fleet_name = '';
			if($drv_data->id >0) {
				
				$driverid = $drv_data->id;	
				$fleetObj = DB::table('fleet')
				->join('fleet_has_drivers', function ($join) use ($driverid) {

				$join->on('fleet.id', '=', 'fleet_has_drivers.fleet_id')
				  ->where('fleet_has_drivers.driver_id', '=', $driverid);   
				}) 
				->select('first_name','last_name')
				->first();  
				
				if(isset($fleetObj))
				 $fleet_name = $fleetObj->first_name.' '.$fleetObj->last_name;
				
			}
			 
			// process the data.
			$rs_final = array();		
			$rs_final['driver_id'] = $drv_data->id;	
			$rs_final['first_name'] = $drv_data->first_name;
			$rs_final['last_name'] = $drv_data->last_name;
			$rs_final['mobile'] = $drv_data->mobile;
			$rs_final['email'] = $drv_data->email;
			$rs_final['fleet_name'] =$fleet_name;
			
			$rs_final['profile_img_str'] = $drv_data->profile_img_str;
			$rs_final['licence_img_str'] = $drv_data->licence_img_str;
			$rs_final['identification_img_str'] = $drv_data->identification_img_str;
			$rs_final['criminaldoc_img_str'] = $drv_data->criminaldoc_img_str; 
			
			$rs_final['reject_reason'] = $drv_data->reject_reason;
			$rs_final['is_verified'] = $drv_data->is_verified;
			$rs_final['status'] = $drv_data->status;
			$rs_final['is_deleted'] = $drv_data->is_deleted;
			$rs_final['created_at'] = $drv_data->created_at;
			$rs_final['updated_at'] = $drv_data->updated_at;
			
			return response()->json([
				'status' => 'success', 
				'data' => $rs_final
			]);
		}		
	}
	
	public function driverAdminApproveReject(Request $request) {
		  
		$input = $request->all();
		//print_r($input);
		$arrStatus = array('approved','rejected');
		$validator = Validator::make($input, [
            'driver_id' => 'required', 
			'status' => 'required' 			 
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		//check if its valid driver.
		if(isset($request->driver_id) && $request->driver_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
		}
		
		if(isset($request->status) && !in_array($request->status,$arrStatus)) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid status'
	    	]);
		}
		
		// Check is driver exists.			
		$drv_data = $this->BaseModel->where('id',$request->driver_id)->first();
		
		if(empty($drv_data)){
			return response()->json([
			'success' => 'false',
			'message' => 'Invalid driver id'
		]);
		} 
					
		if($request->status=='approved')	{
			/*
			 - Updated db for status and is_verified 
			 - sendmail	
			*/	
			
			 $thdSQL = DB::table('driver')
						->where([
									'id' => $request->driver_id									 
								])
						->update([
							'status' => '1', 
							'is_verified' => '1', 
							'updated_at' => date('Y-m-d H:i:s') 		
						]);   
						
			 $url_info =  url('/');	
			 $this->sendAdminApprovalRejectedMail($drv_data->email,'approved',$url_info);
			 
			
		} else {
			/*
			 - Updated db for status and reason 
			 - sendmail	
			*/	
			
			 $thdSQL = DB::table('driver')
						->where([
									'id' => $request->driver_id									 
								])
						->update([
							'is_verified' => '3', 
							'status' => '0',
							'reject_reason' => $request->reject_reason, 
							'updated_at' => date('Y-m-d H:i:s') 		
						]);  
			
			$url_info = config('constants.EMAIL_BASE_URL').'driver-register/'.$drv_data->uniqstring;
			$this->sendAdminApprovalRejectedMail($drv_data->email,'rejected',$url_info,$request->reject_reason);
		} 
		
		return response()->json([
			'success' => 'true',
			'message' => 'Driver status updated successfully.'
		]);	
		
	}
	
	// Fucntion to send approve/reject email to driver
	function sendAdminApprovalRejectedMail($drv_email,$approvalStatus, $url_info, $reason=""){
		 
		$user_email = $drv_email; // Driver email //Admin email
		$data['link_approved_text'] = "Go to etYay and Download app";
		$data['link_rejected_text'] = "Fix your application";
		$data['link'] = $url_info;
		$data['status'] = $approvalStatus;
		$data['reason'] = $reason;
		$to = explode(',', $user_email); 
		$result = Mail::to($to)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new DriverAdminApprovalRejectedMail($data));
		
	}
	
	public function driverRegistration(Request $request) {
		 
		$input = $request->all();
		//print_r($input);
				
		// Do basic validation on the basis of driver id		
		if(isset($request->driver_id) && $request->driver_id==0) { 
			// driver with 0
			$validator = Validator::make($input, [				 
				'first_name' => 'required', 
				'last_name' => 'required', 
				'mobile' => 'required', 
				'email' => 'required', 
				'password' => 'required', 			
				'profile_img_str' => 'required',
				'licence_img_str' => 'required',
				'identification_img_str' => 'required',
				'criminaldoc_img_str' => 'required'
			]);		
		} else {
			// driver has id
			$validator = Validator::make($input, [
				'driver_id' => 'required', 
				'first_name' => 'required', 
				'last_name' => 'required', 
				'mobile' => 'required', 
				'password' => 'required', 			
				'profile_img_str' => 'required',
				'licence_img_str' => 'required',
				'identification_img_str' => 'required',
				'criminaldoc_img_str' => 'required'
			]);					
		}		
					
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check if mobile no. already exists.
		$user_data1 = $this->BaseModel->select('id')
					  ->where('mobile',$request->mobile)
					  ->whereNotIn('id',[$request->driver_id])
					  ->first();
		if(isset($user_data1) && $user_data1->id > 0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Mobile number already exist.'
			]);
		}	
				
		$passwd = Hash::make($request->password);
		
		if(isset($request->driver_id) && $request->driver_id > 0) {
						
			$driver_id = $request->driver_id;
			
			$drv_data = $this->BaseModel->where('id',$request->driver_id)->first();
			 
			if(empty($drv_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
			} 
			
			$thdSQL = DB::table('driver')
						->where([
									'id' => $request->driver_id									 
								])
						->update([
							'first_name' => $request->first_name,
							'last_name' => $request->last_name,
							'mobile' => $request->mobile,
							'password' => $passwd,							 
							'profile_img_str' => $request->profile_img_str,
							'licence_img_str' => $request->licence_img_str,
							'identification_img_str' => $request->identification_img_str,
							'criminaldoc_img_str' => $request->criminaldoc_img_str,
							'updated_at' => date('Y-m-d H:i:s') 		
						]);			 
			
		} else {			// if driver id not exists ---save record.
				
			// check if email already exists.
			$user_data = $this->BaseModel->select('id')->where('email',$request->email)->first();
			if(isset($user_data) && $user_data->id > 0) {
				return response()->json([
					'status' => 'failed',
					'message' => 'Email id already exist.'
				]);
			}
			
			$driverUniqString = $this->getUniqueDriverString(); 
			
			$input_final = array();
			$input_final['first_name'] = request('first_name');
			$input_final['last_name'] = request('last_name');
			$input_final['password'] = $passwd;
			$input_final['email'] = request('email');
			$input_final['licence_no'] = 'XXXXXXXXXXX';//request('licence_no'); 
			$input_final['mobile'] = request('mobile'); 
			$input_final['profile_img_str'] = request('profile_img_str');
			$input_final['licence_img_str'] = request('licence_img_str');
			$input_final['identification_img_str'] = request('identification_img_str');
			$input_final['criminaldoc_img_str'] = request('criminaldoc_img_str');			
			$input_final['uniqstring'] = $driverUniqString;
			$input_final['is_verified'] = '0';
			$input_final['status'] = '0';
			$input_final['is_deleted'] = '0';
			$input_final['created_at'] = date('Y-m-d H:i:s');
			$input_final['updated_at'] = date('Y-m-d H:i:s');
			//print_r($input_final);
			//die();	
			//Inser driver record			
			$thdSQL = DriverModel::create($input_final);
			$driver_id = $thdSQL->id;
		}
		 		 
		// Send email to Admin for approval request
		if($thdSQL) {
			$uniqueDriverStringUrl = config('constants.EMAIL_BASE_URL').'admin/approve-driver/'.$driver_id;		
			$user_email = "clayton@tigerfishsoftware.co.za"; // Admin email 
			$data['link_text'] = "Go to etYay and Approve now";
			$data['link'] = $uniqueDriverStringUrl;
			$result = Mail::to($user_email)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new DriverApprovalRequestMail($data));
		}
		
		return response()->json([
			'success' => 'true',
			'message' => 'Driver registration completed successfully.'
		]);	 		
	}
	
	public function driverRegistrationNEW_SEP112020(Request $request) {
		 
		$input = $request->all();
		//print_r($input);
				
		// Do basic validation on the basis of driver id		
		if(isset($request->driver_id) && $request->driver_id==0) { 
			// driver with 0
			$validator = Validator::make($input, [				 
				'first_name' => 'required', 
				'last_name' => 'required', 
				'mobile' => 'required', 
				'email' => 'required', 
				'password' => 'required', 			
				'profile_img_str' => 'required',
				'licence_img_str' => 'required',
				'identification_img_str' => 'required',
				'criminaldoc_img_str' => 'required'
			]);		
		} else {
			// driver has id
			$validator = Validator::make($input, [
				'driver_id' => 'required', 
				'first_name' => 'required', 
				'last_name' => 'required', 
				'mobile' => 'required', 
				'password' => 'required', 			
				'profile_img_str' => 'required',
				'licence_img_str' => 'required',
				'identification_img_str' => 'required',
				'criminaldoc_img_str' => 'required'
			]);					
		}		
					
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check if mobile no. already exists.
		$user_data1 = $this->BaseModel->select('id')
					  ->where('mobile',$request->mobile)
					  ->whereNotIn('id',[$request->driver_id])
					  ->first();
		if(isset($user_data1) && $user_data1->id > 0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Mobile number already exist.'
			]);
		}	
				
		$passwd = Hash::make($request->password);
		
		// Process and save driver imgs 
		$profile_img_str = $this->storeDriverImgs($request->driver_id,$request->profile_img_str);
		$licence_img_str = $this->storeDriverImgs($request->driver_id,$request->licence_img_str);
		$identification_img_str = $this->storeDriverImgs($request->driver_id,$request->identification_img_str);
		$criminaldoc_img_str = $this->storeDriverImgs($request->driver_id,$request->criminaldoc_img_str);
		 
		/* echo '<br>profile_img_str_path:'.$profile_img_str_path = $this->getDriverImgUrl($profile_img_str);
		echo '<br>licence_img_str:'.$licence_img_str = $this->getDriverImgUrl($licence_img_str);
		echo '<br>identification_img_str:'.$identification_img_str = $this->getDriverImgUrl($identification_img_str);
		echo '<br>criminaldoc_img_str:'.$criminaldoc_img_str = $this->getDriverImgUrl($criminaldoc_img_str); */
		  
		if(isset($request->driver_id) && $request->driver_id > 0) {
						
			$driver_id = $request->driver_id;
			
			$drv_data = $this->BaseModel->where('id',$request->driver_id)->first();
			 
			if(empty($drv_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
			} 
			  
			$thdSQL = DB::table('driver')
						->where([
									'id' => $request->driver_id									 
								])
						->update([
							'first_name' => $request->first_name,
							'last_name' => $request->last_name,
							'mobile' => $request->mobile,
							'password' => $passwd,							 
							'profile_img_str' => $profile_img_str,
							'licence_img_str' => $licence_img_str,
							'identification_img_str' => $identification_img_str,
							'criminaldoc_img_str' => $criminaldoc_img_str,
							'updated_at' => date('Y-m-d H:i:s') 		
						]);			 
			
		} else {			// if driver id not exists ---save record.
				
			// check if email already exists.
			$user_data = $this->BaseModel->select('id')->where('email',$request->email)->first();
			if(isset($user_data) && $user_data->id > 0) {
				return response()->json([
					'status' => 'failed',
					'message' => 'Email id already exist.'
				]);
			}
			
			$driverUniqString = $this->getUniqueDriverString(); 
			
			$input_final = array();
			$input_final['first_name'] = request('first_name');
			$input_final['last_name'] = request('last_name');
			$input_final['password'] = $passwd;
			$input_final['email'] = request('email');
			$input_final['licence_no'] = 'XXXXXXXXXXX';//request('licence_no'); 
			$input_final['mobile'] = request('mobile'); 
			$input_final['profile_img_str'] = $profile_img_str;
			$input_final['licence_img_str'] = $licence_img_str;
			$input_final['identification_img_str'] = $identification_img_str;
			$input_final['criminaldoc_img_str'] = $criminaldoc_img_str;			
			$input_final['uniqstring'] = $driverUniqString;
			$input_final['is_verified'] = '0';
			$input_final['status'] = '0';
			$input_final['is_deleted'] = '0';
			$input_final['created_at'] = date('Y-m-d H:i:s');
			$input_final['updated_at'] = date('Y-m-d H:i:s');
			//print_r($input_final);
			//die();	
			//Inser driver record			
			$thdSQL = DriverModel::create($input_final);
			$driver_id = $thdSQL->id;
		}
		 		 
		// Send email to Admin for approval request
		if($thdSQL) {
			$uniqueDriverStringUrl = config('constants.EMAIL_BASE_URL').'admin/approve-driver/'.$driver_id;		
			$user_email = "clayton@tigerfishsoftware.co.za"; // Admin email 
			$data['link_text'] = "Go to etYay and Approve now";
			$data['link'] = $uniqueDriverStringUrl;
			$result = Mail::to($user_email)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new DriverApprovalRequestMail($data));
		}
		
		return response()->json([
			'success' => 'true',
			'message' => 'Driver registration completed successfully.'
		]);	 		
	}
	
	public function getDriverImgUrl($img) {
		$external_link = asset('storage/drivers/'.$img);
		$returnVal = "";
		if (@getimagesize($external_link)) {
		 $returnVal = $external_link;
		} 
		return $returnVal;
	}
	
	public function storeDriverImgs($uid,$base64_img) {
		
			$s = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);

			//$rsVal = $this->validateBase64Image($request->profile_img_str);
			$extension = explode('/', mime_content_type($base64_img))[1];
			
			//$base64_image = $request->profile_img_str;
			
			list($type, $data) = explode(';', $base64_img);
			list(, $data)      = explode(',', $data);
			 
			$imageName = $uid.'_'.time().'_'.$s.'.png';
			  
			\File::put(storage_path(). '/drivers/' . $imageName, base64_decode($data));	
			
			return $imageName;
	}
	
	
	public function validateBase64Image($file) {
		$file_data = base64_decode($file);
		$f = finfo_open();
		$mime_type = finfo_buffer($f, $file_data, FILEINFO_MIME_TYPE);
		$file_type = explode('/', $mime_type)[0];
		$extension = explode('/', $mime_type)[1];

		//echo '<br>mime_type:'.$mime_type; // will output mimetype, f.ex. image/jpeg
		//echo '<br>file_type:'. $file_type; // will output file type, f.ex. image
		//echo '<br>extension:'. $extension; // will output extension, f.ex. jpeg

		$acceptable_mimetypes = [
			'application/pdf',
			'image/jpeg',
			'image/png'
		];

		// you can write any validator below, you can check a full mime type or just an extension or file type
		if (!in_array($mime_type, $acceptable_mimetypes)) {
			//throw new \Exception('File mime type not acceptable');
			return false;
		}

		// or example of checking just a type
		if ($file_type !== 'image') {
			//throw new \Exception('File is not an image');
			return false;
		}
		return true;
	}	
	
	// To fetch all drivers. fleet=0 (Admin)
	public function getDrivers(Request $request){ 
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
		
		//Get all fleets
		$fltNameByDriver = $this->getAllFleetsDrivers();
		 
		// check for status
		$drvStatus = '';
		if(isset($request->status) && $request->status >=0){ 
			$drvStatus = $request->status;
		}	
		 
		if(isset($request->fleet_id) && $request->fleet_id>0) {
			
			//check for inactive or invalid fleet
			$rsFltSQL = DB::table('fleet')
					->Where('id', '=', $request->fleet_id)
					->Where('status', '=', 1)
					->first();
			
			if(!$rsFltSQL) {
				return response()->json([
					'status' => 'failed',
					'message' => 'Invalid/Inactive fleet'
				]);
			}  
			
			// Process the data.
			$drrs = DB::table('fleet_has_drivers')->select('driver_id')->where(['fleet_id'=> $fleet_id])->get();
			$drvs_id = '';
			foreach($drrs as $drv) {
				$drvs_id .= $drv->driver_id.',';
			}		
			$drvs_id = substr($drvs_id,0,-1);
			$myArray = explode(',', $drvs_id);
			
			$driversql = DB::table('driver')
						->select('driver.id','driver.first_name','driver.last_name','driver.email','driver.status','driver.is_verified','driver.created_at','driver_has_vehicle.status as online_status')
						->leftJoin('driver_has_vehicle', 'driver.id', '=', 'driver_has_vehicle.driver_id')
						->whereIn('driver.id', $myArray);
			if(strlen($drvStatus)>0)
			  $driversql->whereRaw ("driver.status='$drvStatus'");			
						 
						$driversql->orderBy('driver.id','desc');
					    $driversql->offset($offset);
						$driversql->limit($recordLimit);
						
			$drivers = $driversql->get();	
			
			$drivers_count_sql = DB::table('driver')
						->select('id')
						->whereIn('id', $myArray);
			if(strlen($drvStatus)>0)
			  $drivers_count_sql->whereRaw ("status='$drvStatus'");			
							
			$drivers_count = $drivers_count_sql->count();	

			####### To get Weekly earnings for drivers - To show on Fleet admin - SEP112020RM ###############
			$start_time = ' 00:00:00';
            $end_time 	= ' 23:59:59';
			
			 //Week Sunday to Saturday
			$day = date('w');
            $week_start = date('Y-m-d', strtotime('-'.$day.' days')).$start_time;
			$week_end = date('Y-m-d', strtotime('+'.(6-$day).' days')).$end_time;
			
			$getWeekTotal = DB::table('trip')
    							//->whereIn('driver_id', $myArray)
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
			####### END - To Show Weekly Earnings of Driver ###############
			
		} else {
			DB::enableQueryLog();
		 	$driversql = DB::table('driver')
						->select('driver.id','driver.first_name','driver.last_name','driver.email','driver.status','driver.is_verified','driver.created_at','driver_has_vehicle.status as online_status')
						->leftJoin('driver_has_vehicle', 'driver.id', '=', 'driver_has_vehicle.driver_id') ;
						
			if(strlen($drvStatus)>0) 
			  $driversql->whereRaw("driver.status='$drvStatus'");  
			  
			$driversql->orderBy('driver.id','desc');
			$driversql->offset($offset);
			$driversql->limit($recordLimit);
			$drivers = $driversql->get();  
						 
			//	echo $drvStatus;		
			//dd(DB::getQueryLog()); 			
			$drivers_count_sql = DB::table('driver') ;
			if(strlen($drvStatus)>0) 
			  $drivers_count_sql->whereRaw ("status='$drvStatus'");
		  
			$drivers_count = $drivers_count_sql->count();	
			$week_stats = "";	
		}
		 
		$driArr = array();
		 
		foreach($drivers as $drv) {
						
			$fleetDrvName = '';
			if(isset($fltNameByDriver[$drv->id])) {
				$fleetDrvName = $fltNameByDriver[$drv->id];	
			}
			$drv_last_name = '';
			if(isset($drv->last_name) && $drv->last_name!=null){
				$drv_last_name =  $drv->last_name;
			}
			
			$onlineStatus = $drv->online_status==1?'online':'offline';
			
			$arrNew = array();
			$arrNew['id'] = $drv->id; 
		  	$arrNew['first_name'] = $drv->first_name;
			$arrNew['last_name'] = $drv_last_name;
			$arrNew['email'] = $drv->email;
			$arrNew['status'] = $drv->status;
			$arrNew['current_status'] = $onlineStatus;
			$arrNew['is_verified'] = $drv->is_verified;
			$arrNew['date_added'] = date('F d, Y', strtotime($drv->created_at));
			$arrNew['fleet'] = $fleetDrvName;  
			 
			$driArr[] = $arrNew;
		}		 
		
		return response()->json([
			'status' => 'success',
			'count' => $drivers_count,
			'data' => $driArr,
			'week_stats' => $week_stats
			
		]);
       
	}	
	
	// To get all Drivers with Fleet
	public function getAllFleetsDrivers(){ 
		
		$flSQL = DB::table('fleet_has_drivers')
					->select('fleet.first_name','fleet.last_name','fleet_has_drivers.driver_id')
					->leftJoin('fleet', 'fleet_has_drivers.fleet_id', '=', 'fleet.id')
					->get();
		 		
	 	$fltArr = array();
		foreach($flSQL as $frs) {
			$fltArr[$frs->driver_id] = $frs->first_name.' '.$frs->last_name;	
		}
		return $fltArr;
	}
	
	// To enable/disable driver
	public function driverEnableDisable(Request $request) {
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'driver_id' => 'required', 
			'status' => 'required'
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
			
			$drv_data = $this->BaseModel->where('id',$request->driver_id)->first();
			 
			if(empty($drv_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
			} 
		}
				
		if($request->status == 0) { 
			
			// Update 'DRIVER' table and set status 1 = Enabled and update time.
			// PENDING: Driver has vehicles status need to be checked ---------------------------- PENDING 
			$thdSQL = DB::table('driver')
						->where([
									'id' => $request->driver_id									 
								])
						->update([
							'status' => '1',
							'updated_at' => date('Y-m-d H:i:s') 		
						]); 
			return response()->json([
				'success' => 'true',
				'status' => '1',
				'message' => 'Driver enabled successfully.'
	    	]);		
			
		} else {
			// Update 'DRIVER' table and set status 0 = Disabled and update time.
			$thdSQL = DB::table('driver')
						->where([
									'id' => $request->driver_id									 
								])
						->update([
							'status' => '0',
							'updated_at' => date('Y-m-d H:i:s') 		
						]); 
						
			return response()->json([
				'success' => 'true',
				'status' => '0',
				'message' => 'Driver disabled successfully.'
	    	]);		
			
		} 
		
	}
	
	// TO get all non-fleet drivers
	public function getNonFleetDrivers(Request $request){ 
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
		
		// process to get non fleet driver ids
		$fltNameByDriver = $this->getAllFleetsDrivers();
		$nonFleetDrivers = '';
		foreach($fltNameByDriver as $dKey=>$dVAl) {
			$nonFleetDrivers .= $dKey.',';
		}
		$nonFleetDrivers = substr($nonFleetDrivers,0,-1);
		$nonFleetDrivers = explode(',',$nonFleetDrivers);
		
		// process in db and get data
		$drivers = DB::table('driver')->select('*')
						->whereNotIn('id', $nonFleetDrivers)
						->Where('status', '=', 1)
						->orderBy('id','desc')
					    ->offset($offset)
						->limit($recordLimit)
						->get(); 
						
		$drivers_count = DB::table('driver')->select('*')
						->whereNotIn('id', $nonFleetDrivers) 
						->Where('status', '=', 1)
						->count(); 				
						
		unset($drivers["password"]);
		return response()->json([
			'status' => 'success',
			'count' => $drivers_count,
			'data' => $drivers
		]);
		
	}
	
	public function searchNonFleetDrivers(Request $request){ 
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
		// -- get all non fleet drivers ---
		
		// process to get non fleet driver ids
		$fltNameByDriver = $this->getAllFleetsDrivers();
		$nonFleetDrivers = '';
		foreach($fltNameByDriver as $dKey=>$dVAl) {
			$nonFleetDrivers .= $dKey.',';
		}
		$nonFleetDrivers = substr($nonFleetDrivers,0,-1);
		$nonFleetDrivers = explode(',',$nonFleetDrivers);
				
		if(isset($request->keyword))
		   $keyword = $request->keyword;
	    else 
		  $keyword = "";
		 
		
		/* $validator = Validator::make($input, [
			'keyword' => 'required' 		
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }	 */
				
		$drivers = DB::table('driver')
						->select('driver.id','driver.first_name','driver.last_name','driver.email','driver.status','driver.is_verified','driver.created_at','driver_has_vehicle.status as online_status')
						->leftJoin('driver_has_vehicle', 'driver.id', '=', 'driver_has_vehicle.driver_id')
						->Where('driver.status', '=', 1)  
						->Where('driver.is_verified', '=', 1)
						->whereNotIn('driver.id', $nonFleetDrivers)
						->Where(function ($query) use ($keyword){
							$query->orWhere('driver.first_name', 'like', '%' . $keyword . '%')
								  ->orWhere('driver.last_name', 'like', '%' . $keyword . '%')
								  ->orWhere('driver.email', 'like', '%' . $keyword . '%')
								  ->orWhere('driver.mobile', 'like', '%' . $keyword . '%');
						})  
						->orderBy('driver.id','desc')
					    ->offset($offset)
						->limit($recordLimit)		
						->get(); 
		 
		 $drivers_count = DB::table('driver')->select('*')
						->Where('status', '=', 1)  
						->Where('is_verified', '=', 1)
						->whereNotIn('id', $nonFleetDrivers)
						->Where(function ($query) use ($keyword){
							$query->orWhere('first_name', 'like', '%' . $keyword . '%')
								  ->orWhere('last_name', 'like', '%' . $keyword . '%')
								  ->orWhere('email', 'like', '%' . $keyword . '%')
								  ->orWhere('mobile', 'like', '%' . $keyword . '%');
						})   
						->count(); 	
		
		
		foreach($drivers as $drv) {
			  $onlineStatus = $drv->online_status==1?'online':'offline';
			  $drv->current_status = $onlineStatus;
			  unset($drv->online_status);
			  unset($drv->password);
			  unset($drv->profile_img_str);
			  unset($drv->licence_img_str);
			  unset($drv->identification_img_str);
			  unset($drv->criminaldoc_img_str);
		}
		
		return response()->json([
			'status' => 'success',
			'count' => $drivers_count,
			'data' => $drivers
		]);	 		
	}
	
	/* Removing driver from FleethasDriver & DriverhasVehicle table..
	   so that then driver will be considered as Non-Fleet driver.
	*/   
	public function sendToPool(Request $request){ 
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
				'message' => 'Invalid driver id'
	    	]);
		}
		
		// check if driver exists
		if($request->driver_id > 0) {
			DB::beginTransaction();

			try {
				 
				$drv_data = $this->BaseModel->where('id',$request->driver_id)->first();
			 
				if(empty($drv_data)){
					return response()->json([
					'success' => 'false',
					'message' => 'Invalid driver'
				]);
				} 
				
				/* 
					Process to send driver to Pool 
				*/
				//---- Remove from 'fleet_has_drivers' table			
				$sqlDelFromFHD = DB::table('fleet_has_drivers')->where('driver_id', '=', $request->driver_id)->delete();
				
				//---- Remove from 'driver_has_vehicle' table		
				$sqlDelFromDHV = DB::table('driver_has_vehicle')->where('driver_id', '=', $request->driver_id)->delete();
				
				DB::commit();
				return response()->json([
					'success' => 'true',
					'message' => 'Driver moved to pool successfully.'
				]);	 
				
				// all good
			} catch (\Exception $e) {
				DB::rollback();
				return response()->json([
					'success' => 'false',
					'message' => 'Failed to move Driver to pool.'
				]); 
			} 
			
		} else {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
		}
	}
	
	public function approveFleetToDriver(Request $request){ 
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
            'invite_token' => 'required' 
        ]);
			
		if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }	
		 		
		// check if invite token exists
		if($request->invite_token) {
			DB::beginTransaction();
			
			$inv_data = DB::table('invite_fleet_to_driver')->where('token',$request->invite_token)->first();
			 	
			if(empty($inv_data)){
				return response()->json([
				'success' => 'false',
				'message' => 'Invalid invite token'
	    	]);
			} 
			
			if($inv_data->is_approved=='approved') {
				return response()->json([
					'success' => 'false',
					'message' => 'Error in processing request.'
				]);
			}			
			
			// is fleet exist and active
			$rsFltSQL = DB::table('fleet')
						->Where('id', '=', $inv_data->fleet_id)
						->Where('status', '=', 1)
						->first();
			
			if(!$rsFltSQL) {
				return response()->json([
					'success' => 'false',
					'message' => 'Invalid/Inactive fleet'
				]);
			}  
			 
			//is driver exists and active
			$rsDrvSQL = DB::table('driver')
						->Where('id', '=', $inv_data->driver_id)
						->Where('status', '=', 1)
						->first();
			
			 
			if(!$rsDrvSQL) {
				return response()->json([
					'success' => 'false',
					'message' => 'Invalid/Inactive driver'
				]);
			} 
			 
			try {
				//---- Remove from 'fleet_has_drivers' table			
				$sqlDelFromFHD = DB::table('fleet_has_drivers')->where('driver_id', '=', $inv_data->driver_id)->delete();
				
				//---- Remove from 'driver_has_vehicle' table		
				$sqlDelFromDHV = DB::table('driver_has_vehicle')->where('driver_id', '=', $inv_data->driver_id)->delete();
				
				// Save to fleet_has_drivers
				$ins_id_fhd = DB::table('fleet_has_drivers')->insertGetId(
						[ 
						 'fleet_id'=> $inv_data->fleet_id, 
						 'driver_id'=> $inv_data->driver_id,  
						 'created_at'=> date('Y-m-d H:i:s'),  
						 'updated_at'=> date('Y-m-d H:i:s')
						]);
								
				// update `invite_fleet_to_driver` with approved status
				$thdSQL = DB::table('invite_fleet_to_driver')
						->where([
									'id' => $inv_data->id									 
								])
						->update([
							'is_approved' => 'approved',
							'updated_at' => date('Y-m-d H:i:s') 		
						]); 
						
				DB::commit();
				return response()->json([
					'success' => 'true',
					'fleet_info' => $rsFltSQL,
					'message' => 'Driver transferred to fleet successfully.'
				]);	 
				
				// all good
			} catch (\Exception $e) {
				DB::rollback();
				return response()->json([
					'success' => 'false',
					'message' => 'Failed to transferred Driver to Fleet.'
				]); 
			} 	
			
		
		} else {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid invite request'
	    	]);
		}	
		
	}
	
}
