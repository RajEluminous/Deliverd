<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\v1\APIBaseController as APIBaseController;
use Illuminate\Database\Eloquent\Model;
use App\Mail\DeliveryDispatchedMail; 
use App\Mail\DeliveryCompleteMail; 
use App\Delivery;
use App\Service;
use App\RegisteredPartyUser;
use App\RegisteredPartySender;
use Validator;
use DB;
use QRCode;
use Mail;

class DeliveryAPIController extends APIBaseController
{	
	private $ServiceModel;
	private $RptModel;
	private $RptSenderModel;
	
	public function __construct(Service $Service, RegisteredPartyUser $rptUser, RegisteredPartySender $RptSenderModel)
    {
       $this->ServiceModel = $Service; 
	   $this->RptModel = $rptUser; 
	   $this->RptSenderModel = $RptSenderModel; 
	   
	  /*  $ua = $this->getUserAgent();
	   print_r($ua);
	   die(); */
    }
		
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       $delivery = Delivery::all();
       // return $this->sendResponse($delivery->toArray(), 'Empty delivery request.');
	   return $this->sendError('Invalid soap request', 'Missing request data');
    }
	
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {		
		//echo url("/showqr?ord=123&key=78hdfghdf784hgf87dfjhfdfdf78435ghd784");
		 
		// To get header api token key	
		$authToken = getallheaders(); 
		$authToken = array_change_key_case($authToken, CASE_LOWER );
		 
		$api_token_key = isset($authToken['api-token-key'])?$authToken['api-token-key']:'';
		
		$svcObj = $this->ServiceModel->select('*')->where('type',request('service_type'))->where('status',1)->first();	// ServiceType tbl 
		if(!isset($svcObj)) {	// throw error if service type not found
			return $this->sendError('Validation Error.', 'Invalid service type'); 			 
		}	
		 
		$input = $request->all();
		 
		//print_r($input);
		//die();
        $validator = Validator::make($input, [
            'order_id' => 'required',
        /*  'rpt_usr_id' => 'required',
			'service_type_id' => 'required',
            'service_type_fees' => 'required', */
			'service_type' => 'required',
			'package_type' => 'required',
            'package_size' => 'required',
			'sender_components.sender_email' => 'required|email',
			'sender_components.sender_mobileno' => 'required|min:10',
			'pickup_components.pickup_contact_person' => 'required',
            'pickup_components.pickup_contact_mobileno' => 'required|min:10',
			'pickup_components.pickup_address' => 'required',
            'pickup_components.pickup_zipcode' => 'required',
			'pickup_components.pickup_datetime' => 'required|date_format:Y-m-d H:i:s||after_or_equal:now',
            'dropoff_components.dropoff_contact_person' => 'required',			
			'dropoff_components.dropoff_contact_mobileno' => 'required|min:10',
			'dropoff_components.dropoff_contact_email' => 'required|email',
            'dropoff_components.dropoff_address' => 'required',
			'dropoff_components.dropoff_zipcode' => 'required',
            'dropoff_components.dropoff_datetime' => 'required|date_format:Y-m-d H:i:s||after:pickup_datetime'
        ]);
			
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());       
        } 
		
		// Validation: check Duplicate order id
		$ordSQL = DB::table('delivery')->select('id')->where(['order_id'=> request('order_id')])->first();		 
		if(isset($ordSQL->id) && $ordSQL->id > 0) {
			return $this->sendError('Validation Error.', 'Duplicate order id found.');    
		}  
		 		
		/* 2nd level DateTime validation for Pickup and Dropoff ------------ PENDING
		   - Allow immediate deliveries here.
		   - validate cutoff time. 		
		   - delivery date should not be past date			
		*/
		
		$pickupDateTime = request('pickup_components')['pickup_datetime'];
		$pickupDateTime_stt = strtotime($pickupDateTime);
		$pickupDateTime_str = strtotime(date('Y-m-d', $pickupDateTime_stt));
		
		$dropOffDateTime = request('dropoff_components')['dropoff_datetime'];
		$dropOffDateTime_stt = strtotime($dropOffDateTime);
		
		$dropOffDate_str = strtotime(date('Y-m-d', $dropOffDateTime_stt));
		$currentDate_str = strtotime(date("Y-m-d"));
		$datediff = $dropOffDate_str - $currentDate_str;
		$difference = floor($datediff/(60*60*24));
		
		$pickupdiff  = $pickupDateTime_str - $currentDate_str;
		$difference_pickup_str = floor($pickupdiff/(60*60*24));
				 
		if(isset($svcObj->id) && ($svcObj->id > 0) && $svcObj->type != 'Standard') {
			
			$cutOffTime =  $svcObj->cutoff; // hours
			$cutOffDateTime = date("d M Y $cutOffTime");
			$cutOffDateTime_stt = strtotime($cutOffDateTime);
			$currentDateTime_str = strtotime(date('d M Y H:i'));
			if($currentDateTime_str < $cutOffDateTime_stt) {
				//echo '  Delivery Allowed...With in cuttoff time.';
			} else {
				return $this->sendError('Validation Error.', "Today's cutoff time is expired. Please try with another Service type.");
			}
						
			if($difference < 0){
				return $this->sendError('Validation Error.', "Please select valid drop off date time.");
			}   
			//echo $cutOffTime = $pickUpDateTime.'|'.$pickUpDateTime_stt.' >> '.$cutOffTime;			 			
			//print_r($svcObj->cutoff);
			//echo  $ldate = date('H:i:s');//.'|'.$svcObj>cutoff;//.'_'.date('H',strtotime($svcObj>cutoff)) ;			
		}  		 	
		else if(isset($svcObj->id) && $svcObj->id > 0 && $svcObj->type == 'Standard') {  // For delayed delivery
			//echo 'In Delayed   ';
			 
			if($difference_pickup_str <= 0){
				return $this->sendError('Validation Error.', "Please select valid Pickup date time. Standard delivery will be picked on same day or on next day of getting delivery.");
			}
			
			if($difference <= 0){
				return $this->sendError('Validation Error.', "Please select valid Dropoff date time. Standard delivery will be delivered on next day or afterwards of getting delivery.");
			}
			
		}	
		 
		/* 3rd level Geo Map Coordinates validation for Pickup and Dropoff ------------ PENDING
		   - Get lognitude and latitude for Pickup and Dropoff address.
		   - If failed to get any of the coordinates then throw error and don't accept delivery.
		*/
		
		  $pick_geo = $this->getAddsLatLong(request('pickup_components')['pickup_address']);
		  $drop_geo = $this->getAddsLatLong(request('dropoff_components')['dropoff_address']);
		  
		  // Check for valid lat/long for pickup/dropoff address
		  $pickup_geo_address = "";
		  $dropoff_geo_address = "";
		  $pickup_longitude= "";
		  $pickup_latitude = "";
		  $dropoff_longitude= "";
		  $dropoff_latitude = "";
		  
		  if(isset($pick_geo['status']) && $pick_geo['status']=='OK' && isset($drop_geo['status']) && $drop_geo['status']=='OK' && $pick_geo['isStreetAddressExist']=='Y' && $drop_geo['isStreetAddressExist']=='Y') {
			   $pickup_geo_address = $pick_geo['address_components'];
			   $pickup_longitude= $pick_geo['lng'];
			   $pickup_latitude = $pick_geo['lat'];
			   	
			   $dropoff_geo_address = $drop_geo['address_components'];
			   $dropoff_longitude= $drop_geo['lng'];
			   $dropoff_latitude = $drop_geo['lat'];
			   
		  }	else { 
			  return $this->sendError('Validation Error.', "Please provide valid pickup and dropoff address with street information.");
		  } 
				 
		 
		/********* 4th level Validation *************
		   'ServiceType' exist or not and, 
		   RPT and Sender both exist or not
		*/   		
		$rptObj = $this->RptModel->select('*')->where('api_token_key',$api_token_key)->first();							// RPT USR tbl
		if(isset($rptObj->id)) {
		$rptSndrObj = $this->RptSenderModel->select('*')->where('rpt_usr_id',$rptObj->id)->where('sender_email',request('sender_components')['sender_email'])->where('status',1)->first();																				// RPT SNDR tbl
		}		
		$rpt_sender_id = 0;
		$rpt_usr_id = 0;
		
		// Check if RPT and Sender both exist.
		
		if(isset($rptObj->id) && ($rptObj->id > 0) && isset($rptSndrObj->id) && ($rptSndrObj->id > 0)) {
			//echo "RPT And Sender found ".$rptObj->id.':'.$rptSndrObj->id;   
			$rpt_usr_id = $rptObj->id;
			$rpt_sender_id = $rptSndrObj->id;  
		} 
		else {	// Added validation block in case both registerd party (token/sender) info is invalid: DLV Sprint 2 (72/73)	
		
			return $this->sendError('Validation Error.', "Invalid token key or sender information.");
			/*  
				INDIVIDUAL USER: if "RPT id And Sender not found " 	## Assuming that API TOKEN is generated randomly for web/mobile users.
				Save Sender info, SET IN flag to RPT table,  
				pass new rpt_usr_id 
			
			//echo "INDIVIDUAL USER   ";
			$rpt_usr_id = DB::table('registered_party_user')->insertGetId(
						['type'=> 'IN', 
						 'name'=> request('sender_components')['sender_name'], 
						 'email'=> request('sender_components')['sender_email'], 
						 'address'=> request('sender_components')['sender_address'],  
						 'city'=> request('sender_components')['sender_city'],  
						 'contact_no'=> request('sender_components')['sender_mobileno'],  
						 'api_token_key'=> $api_token_key, 
						 'host'=> '', 
						 'status'=> '1', 
						 'is_deleted'=> '0']
					);	*/  
			 
		} 
		  
		// To generate unique Pickup/Dropoff Key
		$uniq_pickup_key = $this->getUniquePickupDropoffKey(request('order_id')); 
		$uniq_dropoff_key = $this->getUniquePickupDropoffKey(request('order_id')); 
		
		// Generate base64 PNG string
		$qrcode_str_pickup = $this->getQRCodePickupDropoff('pickup',request('order_id'),$uniq_pickup_key);			
		$qrcode_str_dropoff = $this->getQRCodePickupDropoff('dropoff',request('order_id'),$uniq_dropoff_key);
		 
		// Final input array to save data		
		$input_final = array();
		$input_final['order_id'] = request('order_id');
		$input_final['rpt_usr_id'] = $rpt_usr_id;
		$input_final['rpt_sender_id'] = $rpt_sender_id;
		
		$input_final['service_type_id'] = $svcObj->id;
		$input_final['service_type_fees'] = $svcObj->fees ;
		
		$input_final['package_type'] = request('package_type');
		$input_final['package_size'] = request('package_size');
		
		$input_final['pickup_contact_person'] = request('pickup_components')['pickup_contact_person'];
		$input_final['pickup_contact_mobileno'] = request('pickup_components')['pickup_contact_mobileno'];
		$input_final['pickup_address'] = request('pickup_components')['pickup_address'];
		$input_final['pickup_zipcode'] = request('pickup_components')['pickup_zipcode'];
		$input_final['pickup_city'] = request('pickup_components')['pickup_city'];		
		$input_final['pickup_geo_address'] = json_encode($pickup_geo_address) ;
		$input_final['pickup_latitude'] = $pickup_latitude;
		$input_final['pickup_longitude'] = $pickup_longitude;		
		$input_final['pickup_datetime'] = request('pickup_components')['pickup_datetime'];
		$input_final['pickup_notes'] = request('pickup_components')['pickup_notes'];
		$input_final['pickup_key'] = $uniq_pickup_key;
		$input_final['pickup_qrcode_str'] = $qrcode_str_pickup;
		
		$input_final['dropoff_contact_person'] = request('dropoff_components')['dropoff_contact_person'];
		$input_final['dropoff_contact_mobileno'] = request('dropoff_components')['dropoff_contact_mobileno'];
		$input_final['dropoff_contact_email'] = request('dropoff_components')['dropoff_contact_email'];
		$input_final['dropoff_address'] = request('dropoff_components')['dropoff_address'];
		$input_final['dropoff_zipcode'] = request('dropoff_components')['dropoff_zipcode'];
		$input_final['dropoff_city'] = request('dropoff_components')['dropoff_city'];
		$input_final['dropoff_geo_address'] = json_encode($dropoff_geo_address) ;
		$input_final['dropoff_latitude'] = $dropoff_latitude;
		$input_final['dropoff_longitude'] = $dropoff_longitude;
		$input_final['dropoff_datetime'] = request('dropoff_components')['dropoff_datetime'];
		$input_final['dropoff_notes'] = request('dropoff_components')['dropoff_notes'];  
		$input_final['dropoff_key'] = $uniq_dropoff_key;
		$input_final['dropoff_qrcode_str'] = $qrcode_str_dropoff;
		//echo request('order_id').'_'.$uniq_dropoff_key;
		  $urlParams = "ord=".request('order_id')."&key=".$uniq_dropoff_key;
		 $postURL = url("/showqr?".$urlParams);
		
		$smsMsg = "Dear Customer, Your order has been processed. Please present the QR code in the below link to the driver. QR code link ".$postURL." Thank you - etYay";
		
		$this->sendsms(request('dropoff_components')['dropoff_contact_mobileno'],$smsMsg);
		//print_r($input_final);
		//die();
		 
		// return $this->sendResponse($input_final , 'Delivery created successfully.');
		//die(); 
        $post = Delivery::create($input_final);
		
        return $this->sendResponse($post->toArray(), 'Delivery created successfully.');
    }
 
	// To parse the user agent and get the device details
	public function getUserAgent() {
		$u_agent = $_SERVER['HTTP_USER_AGENT'];
		$bname = 'Unknown';
		$platform = 'Unknown';
		$version= "";

		//First get the platform?
		if (preg_match('/linux/i', $u_agent)) {
			$platform = 'linux';
		}
		elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
			$platform = 'mac';
		}
		elseif (preg_match('/windows|win32/i', $u_agent)) {
			$platform = 'windows';
		}
		$ub = '';
		// Next get the name of the useragent yes seperately and for good reason
		if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
		{
			$bname = 'Internet Explorer';
			$ub = "MSIE";
		}
		elseif(preg_match('/Trident/i',$u_agent))
		{ // this condition is for IE11
			$bname = 'Internet Explorer';
			$ub = "rv";
		}
		elseif(preg_match('/Firefox/i',$u_agent))
		{
			$bname = 'Mozilla Firefox';
			$ub = "Firefox";
		}
		elseif(preg_match('/Chrome/i',$u_agent))
		{
			$bname = 'Google Chrome';
			$ub = "Chrome";
		}
		elseif(preg_match('/Safari/i',$u_agent))
		{
			$bname = 'Apple Safari';
			$ub = "Safari";
		}
		elseif(preg_match('/Opera/i',$u_agent))
		{
			$bname = 'Opera';
			$ub = "Opera";
		}
		elseif(preg_match('/Netscape/i',$u_agent))
		{
			$bname = 'Netscape';
			$ub = "Netscape";
		}
		
		// finally get the correct version number
		// Added "|:"
		$known = array('Version', $ub, 'other');
		$pattern = '#(?<browser>' . join('|', $known) .
		 ')[/|: ]+(?<version>[0-9.|a-zA-Z.]*)#';
		if (!preg_match_all($pattern, $u_agent, $matches)) {
			// we have no matching number just continue
		}

		// see how many we have
		$i = count($matches['browser']);
		if ($i != 1) {
			//we will have two since we are not using 'other' argument yet
			//see if version is before or after the name
			if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
				$version= $matches['version'][0];
			}
			else {
				$version= $matches['version'][1];
			}
		}
		else {
			$version= $matches['version'][0];
		}

		// check if we have a number
		if ($version==null || $version=="") {$version="?";}

		return array(
			'userAgent' => $u_agent,
			'name'      => $bname,
			'version'   => $version,
			'platform'  => $platform,
			'pattern'    => $pattern
		);
	  
	}
	
	public function getAddsLatLong($address){
		
		$url = "https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&amp;sensor=false&key=AIzaSyBdFRcM7GFoZH7zriMnwhKsoZaOAFstEtQ";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($response, true);
		
		//print_r($response['results'][0]['types']);
		
		$isStreetAddressExist = 'N';	
		$allowed_types = array('street_address','establishment','premise','subpremise');
		if(!empty(array_intersect($allowed_types, $response['results'][0]['types']))) {
			$isStreetAddressExist = 'Y';
		}	
		/* if(in_array('street_address',$response['results'][0]['types'])) {
			$isStreetAddressExist = 'Y';
		}  */
		 
		$arr['geo'] =  array();
		$arr['geo']['status'] = $response['status'];
		$arr['geo']['isStreetAddressExist'] = $isStreetAddressExist;	
		if($response['status']=='OK') { 	 	
			// print_r($response);			 
			$arr['geo']['address_components'] = $response['results'][0]['address_components'];
			$arr['geo']['lat'] = $response['results'][0]['geometry']['location']['lat'];
			$arr['geo']['lng'] = $response['results'][0]['geometry']['location']['lng'];   
		}
		 
		return $arr['geo']; 
		
	}
	
	// To Generate unique key for pickup and dropoff
	public function getUniquePickupDropoffKey($orderid){		 
		return hash('sha256',$orderid.time().mt_rand());
	}
	
	// To generate QRCode for pickup and dropoff
	public function getQRCodePickupDropoff($type,$rpt_order_id,$key) {
		
		//$str_url = "type=".$type."&rpt_order_id=".$rpt_order_id."&key=".$key;
		$str_url = "$type:$rpt_order_id:$key";
		ob_start();			
		QRCode::text($str_url)->setSize(10)->png();    
		$imageString = base64_encode( ob_get_contents() );
		ob_end_clean();
		
		return $imageString;
	}
 
	// send dispatch deliver email
	public function sendDispatchDeliveryEmail($delivery_id,$delivery_status){
		 
		if($delivery_id >0) { 
			$senderObj = DB::table('delivery')
					->join('registered_party_senders', function ($join) use ($delivery_id) {

					$join->on('delivery.rpt_sender_id', '=', 'registered_party_senders.id')
					  ->where('delivery.id', '=', $delivery_id);   
					}) 
					->select('registered_party_senders.id','registered_party_senders.sender_name','registered_party_senders.sender_email','delivery.order_id')
					->first();  
					
			if(isset($senderObj) && $senderObj->id >0 && !empty($senderObj->sender_email) && !empty($senderObj->order_id)) {
				$user_email =$senderObj->sender_email; 
				$data['order_id'] = $senderObj->order_id;
				
				// for delivery dispatch
				if($delivery_status == 2) {
					$result = Mail::to($user_email)->cc(['eluminous.sse24@gmail.com'])->send(new DeliveryDispatchedMail($data));
				}
				
				// for delivery complete
				if($delivery_status == 4) {
					$result = Mail::to($user_email)->cc(['eluminous.sse24@gmail.com'])->send(new DeliveryCompleteMail($data));
				}
			}
		}
	}
 
	public function deliveryHandshake(Request $request){
		 
		/* 
			- Check for basic validation
			- Check if delivery exist/not
			- If key exists
					- Check `trip_has_deliveries` and `trip` table and update.
					- Also check if its last dropoff delivery and update the `Completed` flag.
					- Also update `driver_has_vehicle` table for lat, long, and status.
		*/
		 
		$input = $request->all();		 		
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
		
		if(isset($request->delivery_id) && $request->delivery_id==0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Invalid delivery'
	    	]);
		}
		
		$validator = Validator::make($input, [
			'trip_id' => 'required',
            'driver_id' => 'required', 
			'delivery_id' => 'required',
			'type' => 'required',
			'rpt_order_id' => 'required',
			'key' => 'required'
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check the current status of the delivery
		$thd_status_id = $this->getTripHasDeliveryStatus($request->trip_id,$request->delivery_id,$request->type);
		 		
		$dsSQL = DB::table((new Delivery)->getTable())->where(['id'=> $request->delivery_id])->first();
		 
		// During pickup/dropoff update status of Trip to 'Process' [ 1=>Started,2=>Process,3=>Completed,4=>Rejected ]		
		$trpSQL = DB::table('trip')->where(['id' => $request->trip_id])->update(['status' => 3]); 
		  
		if($request->type == 'pickup') {
			
			// Check if already picked up. 
			if($thd_status_id == 2) {
				return response()->json([
					'status' => 'failed',
					'message' => 'This item is already picked up.'
				]); 
			}
			 			
			if($request->key == $dsSQL->pickup_key) {
				 
				// update `trip_has_deliveries` table set STATUS as `Pickedup`	i.e. 2			
				$thdSQL = DB::table('trip_has_deliveries')
						->where([
									'trip_id' => $request->trip_id,
									'delivery_id' => $request->delivery_id,
									'delivery_type' => 'pickup',
								])
						->update([
							 'status' => 2 
						]);
				
					 
					// Send mail to RPT sender.
					$this->sendDispatchDeliveryEmail($request->delivery_id,2);
					  
					 
				// if first delivery update the TRIP table same and in 
				
				//Show message 
				return response()->json([
					'status' => 'success',
					'message' => 'Delivery record updated successfully'
				]);
				
			} 
			else {
				return response()->json([
					'status' => 'failed',
					'message' => 'Pickup key authentication failed'
				]);
			}			
		} //pickup 
		
		if($request->type == 'dropoff') {
			
			// Check if already delivered. 
			if($thd_status_id == 4) {
				return response()->json([
					'status' => 'failed',
					'message' => 'This item is already delivered.'
				]); 
			}	
			
			if($request->key == $dsSQL->dropoff_key ) {
				
				// update `trip_has_deliveries` table set STATUS as `Delivered`	i.e. 4			
				  $thdSQL = DB::table('trip_has_deliveries')
						->where([
									'trip_id' => $request->trip_id,
									'delivery_id' => $request->delivery_id,
									'delivery_type' => 'dropoff',
								])
						->update([
							 'status' => 4 
						]);  
				
				// Send mail to RPT sender.
				 $this->sendDispatchDeliveryEmail($request->delivery_id,4);
					
				// if last Delivery in `trip_has_deliveries` table then update `TRIP` table as COMPLETED and completion time.		
				$thdSelSQL = DB::table('trip_has_deliveries')->select('id','delivery_id')->where(['trip_id'=> $request->trip_id,'delivery_type' => 'dropoff'])->orderByDesc('id')->first();

				if($thdSelSQL->delivery_id == $request->delivery_id) {
					$thdSQL = DB::table('trip')
						->where([
									'id' => $request->trip_id									 
								])
						->update([
							'status' => 3,
							'completion_time' => date('Y-m-d H:i:s') 		
						]); 
				}  
				
				//Show message 
				return response()->json([
					'status' => 'success',
					'message' => 'Delivery record updated successfully'
				]);
				
			} else {
				return response()->json([
					'status' => 'failed',
					'message' => 'Dropoff key authentication failed'
				]);
			}
		} // dropoff
		
		
	}
	
	// To get the status of the delivery of a Trip
	public function getTripHasDeliveryStatus($trip_id,$delivery_id,$delivery_type){
		
		$thdSelSQL = DB::table('trip_has_deliveries')->select('status')->where(['trip_id'=> $trip_id,'delivery_id' => $delivery_id,'delivery_type' => $delivery_type])->first();	
		if(isset($thdSelSQL))
		  return $thdSelSQL->status;
	}	
	
	// Selfie image based handhake..save the img and proceed
	public function deliverySelfieHandshake(Request $request){
		 
		/* 
			- Check for basic validation
			- Check if delivery exist/not
			- If selfie img exists
					- Check `trip_has_deliveries` and `trip` table and update.
					- Also check if its last dropoff delivery and update the `Completed` flag.
					- Also update `driver_has_vehicle` table for lat, long, and status.
		*/
				
		$input = $request->all();		 		
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
		
		if(isset($request->delivery_id) && $request->delivery_id==0) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Invalid delivery'
	    	]);
		}
		
		$validator = Validator::make($input, [
			'trip_id' => 'required',
            'driver_id' => 'required', 
			'delivery_id' => 'required',
			'type' => 'required', 
			'selfie_img_str' => 'required'
        ]);
			
        if($validator->fails()){
            return response()->json([
				'status' => 'failed',
				'message' => $validator->errors()
	    	]);     
        }
		
		// check the current status of the delivery
		$thd_status_id = $this->getTripHasDeliveryStatus($request->trip_id,$request->delivery_id,$request->type);
		 		
		$dsSQL = DB::table((new Delivery)->getTable())->where(['id'=> $request->delivery_id])->first();
		 
		// During pickup/dropoff update status of Trip to 'Process' [ 1=>Started,2=>Process,3=>Completed,4=>Rejected ]		
		$trpSQL = DB::table('trip')->where(['id' => $request->trip_id])->update(['status' => 3]); 
		  
		if($request->type == 'pickup') {
			
			//echo "pickup";
			//die();
			// Check if already picked up. 
			if($thd_status_id == 2) {
				return response()->json([
					'status' => 'failed',
					'message' => 'This item is already picked up.'
				]); 
			}
			 			
			// Process data save selfie img string 
			$thdSQL = DB::table('delivery')
						->where('id', $request->delivery_id) 
						->update([
							 'pickup_selfie_img_str' => $request->selfie_img_str 
						]);  	
			
			
			// update `trip_has_deliveries` table set STATUS as `Pickedup`	i.e. 2			
				$thdSQL = DB::table('trip_has_deliveries')
						->where([
									'trip_id' => $request->trip_id,
									'delivery_id' => $request->delivery_id,
									'delivery_type' => 'pickup',
								])
						->update([
							 'status' => 2 
						]);
				
				// if first delivery update the TRIP table same and in 
				
				//Show message 
				return response()->json([
					'status' => 'success',
					'message' => 'Delivery record updated successfully'
				]);
						

		} //pickup 
		
		if($request->type == 'dropoff') {
			
			// Check if already delivered. 
			if($thd_status_id == 4) {
				return response()->json([
					'status' => 'failed',
					'message' => 'This item is already delivered.'
				]); 
			}	
			
			// Process the data and save dropoff selfie img string
			$thdSQL = DB::table('delivery')
						->where('id', $request->delivery_id) 
						->update([
							 'dropoff_selfie_img_str' => $request->selfie_img_str 
						]);  	
						
			// update `trip_has_deliveries` table set STATUS as `Delivered`	i.e. 4			
				  $thdSQL = DB::table('trip_has_deliveries')
						->where([
									'trip_id' => $request->trip_id,
									'delivery_id' => $request->delivery_id,
									'delivery_type' => 'dropoff',
								])
						->update([
							 'status' => 4 
						]);  
						
				// if last Delivery in `trip_has_deliveries` table then update `TRIP` table as COMPLETED and completion time.		
				$thdSelSQL = DB::table('trip_has_deliveries')->select('id','delivery_id')->where(['trip_id'=> $request->trip_id,'delivery_type' => 'dropoff'])->orderByDesc('id')->first();

				if($thdSelSQL->delivery_id == $request->delivery_id) {
					$thdSQL = DB::table('trip')
						->where([
									'id' => $request->trip_id									 
								])
						->update([
							'status' => 3,
							'completion_time' => date('Y-m-d H:i:s') 		
						]); 
				}  
				
				//Show message 
				return response()->json([
					'status' => 'success',
					'message' => 'Delivery record updated successfully'
				]);
			 
		} // dropoff
		 
	}
	
	// View deliveries.
	public function viewDeliveries(Request $request){ 
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
		  
		if(isset($request->trip_id))
		  $trip_id = $request->trip_id;
	    else 
		  $trip_id = "0"; 
		
		$query = DB::table('delivery')
					->select('id','order_id','rpt_usr_id','pickup_datetime','dropoff_datetime');
					
		$query1 = DB::table('delivery')
					->select('id','order_id','rpt_usr_id','pickup_datetime','dropoff_datetime');
		// if trip id is available
		if($trip_id > 0) { 
		  $del_ids = $this->getTripDeliveries($trip_id); 
		  if(!empty($del_ids) && is_array($del_ids)) {
			  $query->whereIn('id', $del_ids);
			  $query1->whereIn('id', $del_ids);
		  }
		}
		 
		// if keyword is present	
		if(isset($request->keyword) && !empty($request->keyword)) {
			$query->where('order_id', 'like', '%'.$request->keyword.'%');	
			$query1->where('order_id', 'like', '%'.$request->keyword.'%');	
		}  
					
		// for getting Neglected Deliveries
		if(isset($request->isneglected) && $request->isneglected==1) {
			$arr_not_in = $this->getInProcessOrCompletedDeliveries();	 
			if(!empty($arr_not_in)) { 
			$query->whereNotIn('id', $arr_not_in); 
			$query1->whereNotIn('id', $arr_not_in);
			}
		}	
  
		$query->offset($offset);
		$query->limit($recordLimit); 
		$result = $query->get();
		$dataCount = $query1->count();
		  
		$rs_arr = array();
		foreach($result as $rs) {
			
				// get TPT user name.
				$regThirdPartyName = $this->getRegisteredThirdParty($rs->rpt_usr_id);
				
				// get Delivery status
				$deliveryStatus = $this->getDeliveryStatus($rs->id);
			
				// Driver information
				$driverName = $this->getDeliveryDriverInfo($rs->id);
			
				$arrVal = array();
				$arrVal['id'] = $rs->id;
				$arrVal['order_id'] = $rs->order_id;	 
				$arrVal['driver_name'] = $driverName;	 
				$arrVal['registered_third_party'] = $regThirdPartyName;
				$arrVal['pickup_datetime'] = date('d / F / Y',strtotime($rs->pickup_datetime));	
				$arrVal['dropoff_datetime'] = date('d / F / Y',strtotime($rs->dropoff_datetime));		
				$arrVal['status'] = $deliveryStatus;				
				$rs_arr[] = $arrVal;
		} 
	  
		return response()->json([
					'status' => 'success',
					'count' => $dataCount,
					'data' =>  $rs_arr 
				]);
	} 
	
	public function getDeliveryDriverInfo($deliveryID) {
		 
		$sqlDrvr = DB::table('trip_has_deliveries')
				   ->leftJoin('trip', 'trip_has_deliveries.trip_id', '=', 'trip.id')	
				   ->leftJoin('driver', 'trip.driver_id', '=', 'driver.id')	
				   ->where('delivery_id','=',$deliveryID)
				   ->select('first_name','last_name')
				   ->first();	
		$driverName = "";
		if(!empty($sqlDrvr)) {
			$driverName = $sqlDrvr->first_name.' '.$sqlDrvr->last_name;		
		}	
		return $driverName;
 		
	}
	
	public function getRegisteredThirdParty($rpt_id){
		
		if($rpt_id>0) {
		$rsFltHasDrvrtbl = DB::table('registered_party_user') 
					->select('name')
					->Where('id', '=', $rpt_id) 
					->first();
		return $rsFltHasDrvrtbl->name;
		} else {
			return;
		}			
	}
	
	public function getDeliveryStatus($id) {
		if($id>0) {
				$returnVal = "Pending";
				$rsTHD = DB::table('trip_has_deliveries') 
					->select('status')
					->Where('delivery_id', '=', $id) 
					->Where('delivery_type', '=', 'dropoff') 
					->first();
		
				if(isset($rsTHD->status)) {
					 
					if($rsTHD->status==4) {
						$returnVal = "Complete";
					}  
				}  
				return $returnVal;
		
		} else {
			return "Pending";
		}		
	}
	
	public function getTripDeliveries($trip_id) {
		 
		if($trip_id > 0) {
			
			$drrs = DB::table('trip_has_deliveries')->select('delivery_id')->where(['trip_id'=> $trip_id])->get();
			if(count($drrs)>0) { 
				$drvs_id = '';
				foreach($drrs as $drv) {
					$drvs_id .= $drv->delivery_id.',';
				}		
				$drvs_id = substr($drvs_id,0,-1);
				$myArray = explode(',', $drvs_id);
				return $myArray;
			} else {
				return ;
			}
		}	
		return ;
	}
	
	public function getInProcessOrCompletedDeliveries() {
		// here it retries the deliveries which are in pickedup/inprocess/completed
		$delsIPOC = DB::table('trip_has_deliveries')->select('delivery_id')->distinct()->where( 'status','>=',2)->get();
		if(isset($delsIPOC) && count($delsIPOC)>0) {
			$drvs_id = '';
				foreach($delsIPOC as $drv) {
					$drvs_id .= $drv->delivery_id.',';
				}		
				$drvs_id = substr($drvs_id,0,-1);
				$myArray = explode(',', $drvs_id);
				return $myArray;
		} 
		return;   
	}
	
 
}