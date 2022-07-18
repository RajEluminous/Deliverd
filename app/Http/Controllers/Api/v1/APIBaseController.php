<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;

class APIBaseController extends Controller
{
    public function sendResponse($result, $message)
    {
    	$response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
        return response()->json($response, 200);
    }

    public function sendError($error, $errorMessages = [], $code = 404)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];
		
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
		
        return response()->json($response, $code);
    }

    protected function _sendResult($message,$data,$errors = [],$status = true)
    {
        // $errorCode = $status ? 200 : 422;
        $result = [
            "message" => $message,
            "status" => $status,
            "data" => $data,
            "errors" => $errors
        ];
        return response()->json($result);  
    }

    public function _getDistance($latitudeFrom, $longitudeFrom,$latitudeTo,$longitudeTo, $unit = ''){

        // Google API key
        /*$apiKey = config('constants.GOOGLE_KEY');
        
        // Change address format
        $formattedAddrFrom    = str_replace(' ', '+', $addressFrom);
        $formattedAddrTo     = str_replace(' ', '+', $addressTo);
        
        // Geocoding API request with start address
        $geocodeFrom = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddrFrom.'&sensor=false&key='.$apiKey);
        $outputFrom = json_decode($geocodeFrom);
        if(!empty($outputFrom->error_message)){
            return $outputFrom->error_message;
        }
        
        // Geocoding API request with end address
        $geocodeTo = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddrTo.'&sensor=false&key='.$apiKey);
        $outputTo = json_decode($geocodeTo);
        if(!empty($outputTo->error_message)){
            return $outputTo->error_message;
        }
        
        // Get latitude and longitude from the geodata
        $latitudeFrom    = $outputFrom->results[0]->geometry->location->lat;
        $longitudeFrom    = $outputFrom->results[0]->geometry->location->lng;
        $latitudeTo        = $outputTo->results[0]->geometry->location->lat;
        $longitudeTo    = $outputTo->results[0]->geometry->location->lng;*/
        
        // Calculate distance between latitude and longitude
        $theta    = $longitudeFrom - $longitudeTo;
        $dist    = sin(deg2rad($latitudeFrom)) * sin(deg2rad($latitudeTo)) +  cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * cos(deg2rad($theta));
        $dist    = acos($dist);
        $dist    = rad2deg($dist);
        $miles    = $dist * 60 * 1.1515;
        
        // Convert unit and return distance
        $unit = strtoupper($unit);
        if($unit == "K"){
            return round($miles * 1.609344, 2);//.' km';
        }elseif($unit == "M"){
            return round($miles * 1609.344, 2).' meters';
        }else{
            return round($miles, 2).' miles';
        }
    }
	
	// To send sms to customer with qrcode url
	public function sendsms($mobileno,$message) {
		 
		// Mobile no. validation
		$phone_to_check = str_replace("-", "", $mobileno);
		if(strlen($phone_to_check)==13) {
			$mobNum = $phone_to_check;
		} else {
			$output = preg_replace( '/[^0-9]/', '', $mobileno );
			$mobNum = '+27'.(int)$output;
		}			 
		  
		$clientID = 'fdba11fc-0dd9-4062-99ca-86a645b46581'; 
		$apiSecret = '6tJZflMtXHPQWQhkKRuptrIe10pv4y7d'; 
		$headers = array(
			'Content-Type: application/json',
			'Authorization: Basic '. base64_encode("$clientID:$apiSecret")
		);
		
		// 1 - Create Token
		$url = "https://rest.smsportal.com/v1/Authentication";
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);		
		$response = curl_exec($ch);
		//Check for errors.
		if(curl_errno($ch)){ 
			$errMsg = curl_error($ch);
		}
		curl_close($ch);
		$response = json_decode($response, true);
		//print_r($response); 	
//die();		
		// 2 - Send SMS		
		if(isset($response['token']) && $response['token']!='') {
			
			// Process CURL
			$headers1 = array(
				'Content-Type: application/json',
				'Authorization: Bearer '.$response['token']
			);
			 
			$data = array(
				'Destination' => $mobNum,
				'Content' => $message
			);
			 $payload = '{
						  "Messages": [
							{
							  "Content": "'.$message.'",
							  "Destination": "'.$mobNum.'"
							}
						  ]
						}';

			 
			$url1 = "https://rest.smsportal.com/v1/bulkmessages";
					
			$ch1 = curl_init();
			curl_setopt($ch1, CURLOPT_URL, $url1);
			curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch1, CURLOPT_PROXYPORT, 3128);
			curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers1);	
			curl_setopt($ch1, CURLOPT_POSTFIELDS, $payload);	
			$response1 = curl_exec($ch1);
			//Check for errors.
			if(curl_errno($ch1)){ 
				$errMsg1 = curl_error($ch1);
			}
			curl_close($ch1);
			$response1 = json_decode($response1, true);
			
			//print_r($response1);
		}
		//die();
	}	
}