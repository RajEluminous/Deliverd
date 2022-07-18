<?php

namespace App\Http\Controllers\Api\v1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\v1\APIBaseController as APIBaseController;
use App\Mail\ForgotPasswordMail; 
use App\Mail\FleetRegistrationMail;
use App\Mail\FleetInviteDriverMail;
use Validator;
use DB;
use App\Models\FleetModel;
use Mail;
use Hash;

class FleetController extends APIBaseController
{	
	Private $BaseModel;
	Private $PasswordReset;

	public function __construct(FleetModel $FleetModel)
    {
       $this->BaseModel = $FleetModel; 	 
    }

	public function newFleet(Request $request) {
		$input = $request->all();	
		$validator = Validator::make($input, [			
			'first_name' => 'required',
            'last_name' => 'required', 
			'email' => 'required', 
			'password' => 'required', 
			'mobile' => 'required',
			'id_number' => 'required',        
			'type' => 'required',	
			'city' => 'required' 	
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		 
		// Check if name/email/mobile no. already exists
		$dsSQL = DB::table('fleet')
					->Where('id_number', '=', $request->id_number)
					->orWhere('email', '=', $request->email)
					->orWhere('mobile', '=', $request->mobile)->first();
		
		if(isset($dsSQL->id)) {
			return response()->json([
				'success' => 'false',
				'message' => "Fleet's/Owner driver's email or mobile number or id number already exist."
	    	]); 
		} else {
			$password = $request->password; 
			
			$input_final = array();
			$input_final['first_name'] = request('first_name');
			$input_final['last_name'] = request('last_name'); 
			$input_final['email'] = request('email');
			$input_final['password'] = Hash::make($password);
			$input_final['mobile'] = request('mobile');
			$input_final['id_number'] = request('id_number');
			$input_final['city'] = request('city');
			$input_final['type'] = request('type');
			$input_final['status'] = '1';
			$input_final['isDeleted'] = '0';
			$input_final['created_at'] = date('Y-m-d H:i:s');
			$input_final['updated_at'] = date('Y-m-d H:i:s');
			
			$msg_type = "Owner Driver";
			if(isset($request->type) && $request->type=='F') {
				$msg_type = "Fleet Owner";
			}
			
			// Inser Fleet record			
			$fleet = FleetModel::create($input_final);		
			
			// Send email to Fleet with details.
			$user_email = request('email');			 
			$data['type'] = $msg_type;
			$data['link'] = config('constants.EMAIL_BASE_URL').'login';	
			$result = Mail::to($user_email)->cc(['eluminous_sedk@eluminoustechnologies.com'])->send(new FleetRegistrationMail($data));
			 
			return $this->sendResponse($fleet->toArray(), "$msg_type created successfully.");
		}  
	}
	
	// Assign Driver to Fleet
	public function assign_driver_to_fleet(Request $request) {
		
		// basic validation
		// check if fleet is active.
		// check if driver is active.
		// Check if driver is aleary assigned to fleet.
		// Insert record
		$input = $request->all();	
		
		$validator = Validator::make($input, [
			'fleet_id' => 'required',
            'driver_id' => 'required' 
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		if(isset($request->fleet_id) && $request->fleet_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid Fleet'
	    	]);
		}
		
		if(isset($request->driver_id) && $request->driver_id==0) {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid driver'
	    	]);
		}
		
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
		
		$rsFltHasDrvrSQL = DB::table('fleet_has_drivers')
					->Where('fleet_id', '=', $request->fleet_id)
					->Where('driver_id', '=', $request->driver_id)
					->first();
		
		if($rsFltHasDrvrSQL) {
			return response()->json([
				'success' => 'false',
				'message' => 'Driver is already assigned to Fleet.'
	    	]);
		} else {
			
			$ins_id_fhd = DB::table('fleet_has_drivers')->insertGetId(
						[ 
						 'fleet_id'=> $request->fleet_id, 
						 'driver_id'=> $request->driver_id,  
						 'created_at'=> date('Y-m-d H:i:s'),  
						 'updated_at'=> date('Y-m-d H:i:s')
						]);	  
			
			if($ins_id_fhd>0) {
				return response()->json([
				'success' => 'true',
				'message' => 'Driver assigned to Fleet successfully.'
				]);
			} else {
				return response()->json([
				'success' => 'false',
				'message' => 'Unable to assign Driver to the Fleet. Try again.'
				]);
			}			
		}
		
		//print_r($rsFltHasDrvrSQL);
		
	}	
	
	// To Generate unique key for invite driver
	public function getUniqueInviteString($len=20){		 
		 
		$hex = md5("yourSaltHere" . uniqid("", true));

		$pack = pack('H*', $hex);
		$tmp =  base64_encode($pack);

		$uid = preg_replace("#(*UTF8)[^A-Za-z0-9]#", "", $tmp);

		$len = max(4, min(128, $len));

		while (strlen($uid) < $len)
			$uid .= $this->getUniqueInviteString(22);

		return substr($uid, 0, $len);
	}
	
	public function inviteFleetToDriver(Request $request){ 
		
		$input = $request->all();
		
		// Do basic validation
		$validator = Validator::make($input, [
			'fleet_id' => 'required',
            'driver_id' => 'required' 
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		// is fleet exist and active
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
		 
		//is driver exists and active
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
		 
		$rsFltHasDrvrSQL = DB::table('fleet_has_drivers')
					->Where('fleet_id', '=', $request->fleet_id)
					->Where('driver_id', '=', $request->driver_id)
					->first();
		
		if($rsFltHasDrvrSQL) {
			return response()->json([
				'success' => 'false',
				'message' => 'Driver is already assigned to Fleet.'
	    	]);
		}  
		 
		// check if driver is already invited by same fleet
		$rsIFDSQL = DB::table('invite_fleet_to_driver')
					->Where('fleet_id', '=', $request->fleet_id)
					->Where('driver_id', '=', $request->driver_id) 
					->orderBy('id', 'desc')
					->first();
					
		 
		if(isset($rsIFDSQL) && $rsIFDSQL->is_approved=='pending'){
			return response()->json([
				'success' => 'false',
				'message' => 'Driver already invited.'
	    	]);
		}
		  
		if(isset($rsFltSQL->id) && $rsFltSQL->id>0 && isset($rsDrvSQL) && $rsDrvSQL->id>0) {
		   		   
		    /* Get old fleet id if exist
		       save in invite table.
		       send required email.
		    */
			
		    $inviteToken = $this->getUniqueInviteString();
		    $fleetName = $rsFltSQL->first_name.' '.$rsFltSQL->last_name;
		    
		    $rsFltHasDrvrtbl = DB::table('fleet_has_drivers') 
					->Where('driver_id', '=', $request->driver_id)
					->first();
					
			$old_fleet_id = 0;		// 0 = Non fleet (etYay fleet)
			
		    if($rsFltHasDrvrtbl)
		      $old_fleet_id = $rsFltHasDrvrtbl->fleet_id;
			
		    // Save in `invite_fleet_to_driver` table
			$ins_id_fhd = DB::table('invite_fleet_to_driver')->insertGetId(
						[ 
						 'fleet_id_old'=> $old_fleet_id,
						 'fleet_id'=> $request->fleet_id, 
						 'driver_id'=> $request->driver_id,  
						 'token' => $inviteToken,
						 'created_at'=> date('Y-m-d H:i:s'),  
						 'updated_at'=> date('Y-m-d H:i:s')
						]);	  
			
			if($ins_id_fhd>0) {
				
				// Send email to Fleet with details.
 				$user_email = $rsDrvSQL->email;			 
				$data['fleet_name'] = $fleetName;
				$data['link'] = config('constants.EMAIL_BASE_URL').'accept-invitation/'.$inviteToken;	
				$result = Mail::to($user_email)->cc(['eluminous.sse24@gmail.com'])->send(new FleetInviteDriverMail($data));  
 				 
				return response()->json([
				'success' => 'true',
				'message' => 'Driver invited by Fleet successfully.'
				]);
			} else {
				return response()->json([
				'success' => 'false',
				'message' => 'Driver invitation failed. Try again.'
				]);
			}	
			
		    
		} else {
			return response()->json([
				'success' => 'false',
				'message' => 'Invalid Fleet or Driver'
	    	]);
		}
		
	} 
	
	public function showAllFleets(Request $request){ 
		$input = $request->all();
		
		if(isset($request->driver_id))
		  $driver_id = $request->driver_id;
	    else 
		  $driver_id = "0";
		
		if($driver_id > 0) {
			
			//get his fleet, as no need to show him in lisiting.
			
			$rsFltHasDrvrtbl = DB::table('fleet_has_drivers') 
					->Where('driver_id', '=', $request->driver_id) 
					->first();
					
			$fleet_id = 0;		// 0 = Non fleet (etYay fleet)
			
		    if($rsFltHasDrvrtbl)
		      $fleet_id = $rsFltHasDrvrtbl->fleet_id;
			
		    // select fleets.
			$dsSQL = DB::table('fleet')
					->select('id', 'first_name', 'last_name', 'email', 'mobile', 'id_number', 'type', 'status', 'isDeleted', 'created_at', 'updated_at')
					->whereNotIn('id', [$fleet_id])
					->Where('status', '=', 1) 
					->orderBy('first_name','asc')
					->orderBy('last_name','asc')
					->get(); 
			
		} else {
			  // select fleets.
			$dsSQL = DB::table('fleet')  
					->select('id', 'first_name', 'last_name', 'email', 'mobile', 'id_number', 'type', 'status', 'isDeleted', 'created_at', 'updated_at')
					->Where('status', '=', 1)
					->orderBy('first_name','asc')
					->orderBy('last_name','asc')
					->get();
		}
		
		$dsSQL->except(['password']);
		
		 
		return response()->json([
				'success' => 'true',
				'count' => count($dsSQL),
				'data' => $dsSQL, 
				'message' => 'Fleet list generated successfully'
				]);
		 
	}
	
	// To save fleet's bank details
	public function saveFleetBankDetails(Request $request) {
		 
		$input = $request->all();	
		
		$validator = Validator::make($input, [
			'fleet_id' => 'required',
			'bank_name' => 'required',
            'branch_name' => 'required', 
			'bank_code' => 'required|numeric|digits_between:4,10', 
			'account_holder_name' => 'required|min:5', 
			'account_number' => 'required|numeric|digits_between:5,12',
			'account_type' => 'required'         
			 
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check if fleet exists
		
		$fltSQL = DB::table('fleet')
					->Where('id', $request->fleet_id)
					->first();
		
		if(!isset($fltSQL->id)) {
			return response()->json([
				'success' => 'false',
				'message' => "Fleet not exist."
	    	]);
		}	
		
		/* Check if bank infor exist for fleet
		   if EXIST - UPDATE
           Else - CREATE		   
		*/
		
		$dsSQL = DB::table('fleet_bank_details')
					->Where('fleet_id', '=', $request->fleet_id)
					->first();
					
		if(isset($dsSQL->id)) {
			// Update
			//echo "Update";
			$upBnkSQL = DB::table('fleet_bank_details')
					->where('fleet_id', $request->fleet_id)
					->update([
						 'bank_name'=> $request->bank_name,  
						 'branch_name'=> $request->branch_name,  
						 'bank_code'=> $request->bank_code,  
						 'account_holder_name' => $request->account_holder_name,
						 'account_number' => $request->account_number,
						 'account_type' => $request->account_type, 
						 'updated_at'=> date('Y-m-d H:i:s')
					]);
			if($upBnkSQL) {
				return response()->json([
				'success' => 'true',
				'message' => 'Bank details updated successfully.'
				]);
			} else {
				return response()->json([
				'success' => 'false',
				'message' => 'Failed to updated bank details.'
				]);
			} 
			
		} else {
		    // Insert	 
			$ins_id_fhd = DB::table('fleet_bank_details')->insertGetId(
				[ 
				 'fleet_id'=> $request->fleet_id, 
				 'bank_name'=> $request->bank_name,  
				 'branch_name'=> $request->branch_name,  
				 'bank_code'=> $request->bank_code,  
				 'account_holder_name' => $request->account_holder_name,
				 'account_number' => $request->account_number,
				 'account_type' => $request->account_type,
				 'created_at'=> date('Y-m-d H:i:s'),  
				 'updated_at'=> date('Y-m-d H:i:s')
				]);
			
			if($ins_id_fhd  > 0) {
				return response()->json([
				'success' => 'true',
				'message' => 'Bank details added successfully.'
				]);
			} else {
				return response()->json([
				'success' => 'false',
				'message' => 'Failed to add bank details.'
				]);
			}
			
		} 	 
		
	}
	
	// To get fleet's bank details
	public function getFleetBankDetails(Request $request) {
		$input = $request->all();	
		
		$validator = Validator::make($input, [
			'fleet_id' => 'required'      
        ]);
			
        if($validator->fails()){
            return response()->json([
				'success' => 'false',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check if fleet exists
		
		$fltSQL = DB::table('fleet')
					->Where('id', $request->fleet_id)
					->first();
		
		if(!isset($fltSQL->id)) {
			return response()->json([
				'success' => 'false',
				'message' => "Fleet not exist."
	    	]);
		} 
		
		$dsSQL = DB::table('fleet_bank_details')
					->Where('fleet_id', '=', $request->fleet_id)
					->first();
					
		if(isset($dsSQL->id)) {
			return response()->json([
				'success' => 'success',
				'data' => $dsSQL
	    	]);
		} else {
			$blankArr = array();
			$blankArr['id'] = "";
			$blankArr['fleet_id'] = $request->fleet_id;
			$blankArr['bank_name'] = "";
			$blankArr['branch_name'] = "";
			$blankArr['bank_code'] = "";
			$blankArr['account_holder_name'] = "";
			$blankArr['account_number'] = "";
			$blankArr['account_type'] = "";
			$blankArr['created_at'] = "";
			$blankArr['updated_at'] = "";
			
			return response()->json([
				'success' => 'success',
				'data' => $blankArr
	    	]);
		}
	}	
	
}
