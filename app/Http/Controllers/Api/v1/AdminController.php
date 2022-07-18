<?php

namespace App\Http\Controllers\Api\v1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Mail\ForgotPasswordMail; 
use \Illuminate\Auth\Passwords\PasswordBroker;
use Validator;
use App\PasswordReset;
use App;
use DB;
use App\Models\FleetModel;

use Mail;
use Hash;

class AdminController extends Controller
{	
	Private $BaseModel;
	Private $PasswordReset;

	public function __construct(FleetModel $FleetModel,PasswordReset $PasswordReset,PasswordBroker $PasswordBroker
    )
    {
        $this->BaseModel = $FleetModel; 
		$this->PasswordReset   = $PasswordReset;
		$this->PasswordBroker = $PasswordBroker;
    }
	
    public function adminlogin(Request $request) 
    {
		$input = $request->all();
		//print_r($input);
		//$password = '123456';
		//echo $hasPwd = Hash::make($password);
		
		// Do basic validation
		$validator = Validator::make($input, [
            'email' => 'required',
			'password' => 'required'			 
        ]);
			
		if($validator->fails()){
            return response()->json([
				'status' => 'falied',
				'message' => $validator->errors()
	    	]);     
        }	
		
		// check for Admin (in user table)
		$admUser = 	self::_validateAdminEmail($request->email,$request->password);
	
		if($admUser) {
			if (!Hash::check(request()->password, $admUser->password)) {
				return response()->json([
				'status' => 'Error',
				'message' => 'Invalid Admin credentials',
				]);
			} else {
				 
				$credentials = request(['email', 'password']);
				if(Auth::attempt($credentials)) {
				 
				$tokenResult = $admUser->createToken('Personal Access Token');	
				  
				return response()->json([
					'status' => 'success',
					'message' => 'successfully login',
					'id' => $admUser->id,
					'name' => $admUser->name,					
					'role' => 'admin',
					'token' => $tokenResult->accessToken,					
					'expires_at' => Carbon::parse($tokenResult->token->expires_at)->toDateTimeString()
				]);   				 
				}
			}	
		} else {
			// Check in Fleet's table
			$fleetUser = self::_validateFleetEmail($request->email,$request->password);
			 
			
			  
			if($fleetUser) {
				if (!Hash::check(request()->password, $fleetUser->password)) {
					return response()->json([
					'status' => 'Error',
					'message' => 'Invalid fleet credentials',
					]);
				} else {					
					 
						Auth::login($fleetUser);   			  
						 
						$tokenResult = $fleetUser->createToken('Personal Access Token');							  
						return response()->json([
							'status' => 'success',
							'message' => 'successfully login',
							'id' => $fleetUser->id,
							'name' => $fleetUser->first_name.' '.$fleetUser->last_name,					
							'email' => $fleetUser->email,					
							'role' => 'fleet',
							'token' => $tokenResult->accessToken,					
							'expires_at' => Carbon::parse($tokenResult->token->expires_at)->toDateTimeString()
						]);  

					
				}	
			} else {
				return response()->json([
					'status' => 'Error',
					'message' => 'Invalid credentials',
					]);
			} 
		}
				
	}
	
	public function _validateFleetEmail($email,$pass) {
		 
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        	return false;                     
        }else{
			
			$fleetUser = $this->BaseModel->where('email',$email)->first();		 
			if(!empty($fleetUser)){
        		return $fleetUser;
        	}else{
        		return false;                     
        	}
        }
        return true;
    }
	
	public function _validateAdminEmail($email,$pass) {
		 
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        	return false;                     
        }else{
			
			$admUser = App\User::where('email',$email)->first();		 
			if(!empty($admUser)){
        		return $admUser;
        	}else{
        		return false;                     
        	}
        }
       
    }
	
	/*
		admin@tigerfishsoftware.co.za
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
					'name' => $user->name,
					'id' => $user->id,
					'expires_at' => Carbon::parse($tokenResult->token->expires_at)->toDateTimeString()
				]);     
           
        }    
	*/
		

     
	 
	
}
