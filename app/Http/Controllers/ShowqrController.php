<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB; 
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
 
class ShowqrController extends Controller
{
	// default call	
	public function index(Request $request)
    {	 	
		//$input = $request->all();
		/* echo $request->key;
		echo $request->ord;	 */	
		$errCode = 0; 
		$order_id = "";
		$package_type = "";
		$package_size = "";
		$dropoff_contact_person = "";
		$dropoff_address = "";
		$dropoff_zipcode = "";
		$dropoff_city = "";
		$dropoff_qrcode_str = "";
		$msg = "Invalid Order";
		if(isset($request->ord) && !empty($request->ord) && isset($request->key) && !empty($request->key)) {	
			
			$rsVal = $this->getDropoffInfo($request->ord,$request->key);
			
			if(!empty($rsVal['dropoff_qrcode_str'])) {
				$errCode = 1;
				$order_id = $rsVal['order_id'];
				$package_type = $rsVal['package_type'];
				$package_size = $rsVal['package_size'];
				$dropoff_contact_person = $rsVal['dropoff_contact_person'];
				$dropoff_address = $rsVal['dropoff_address'];
				$dropoff_zipcode = $rsVal['dropoff_zipcode'];
				$dropoff_city = $rsVal['dropoff_city'];
				$dropoff_qrcode_str = $rsVal['dropoff_qrcode_str']; 
			} 
			 
		}  
		 
		return view('showqr' ,['errcode' => $errCode, 'msg' => $msg, 'order_id' => $order_id, 'package_type' => $package_type, 'package_size' => $package_size ,
							   'dropoff_contact_person' => $dropoff_contact_person, 'dropoff_address' => $dropoff_address, 'dropoff_zipcode' => $dropoff_zipcode, 'dropoff_zipcode' => $dropoff_zipcode, 'dropoff_city' => $dropoff_city ,
							   'dropoff_qrcode_str' => $dropoff_qrcode_str ]); 				 
	}	
	
	public function getDropoffInfo($ord,$dropoffkey) {
		 
		$delSQL = DB::table('delivery')->select('order_id','package_type','package_size','dropoff_contact_person','dropoff_address','dropoff_zipcode','dropoff_city','dropoff_qrcode_str')->where(['order_id'=> $ord,'dropoff_key' => $dropoffkey])->first();
		
		//print_r($delSQL);
		 
		$order_id = "";
		$package_type = "";
		$package_size = "";
		$dropoff_contact_person = "";
		$dropoff_address = "";
		$dropoff_zipcode = "";
		$dropoff_city = "";
		$dropoff_qrcode_str = "";
		if(isset($delSQL->dropoff_qrcode_str)) {
			 
			$order_id = $delSQL->order_id;
			$package_type = $delSQL->package_type;
			$package_size = $delSQL->package_size;
			$dropoff_contact_person = $delSQL->dropoff_contact_person;
			$dropoff_address = $delSQL->dropoff_address;
			$dropoff_zipcode = $delSQL->dropoff_zipcode;
			$dropoff_city = $delSQL->dropoff_city;
			$dropoff_qrcode_str = $delSQL->dropoff_qrcode_str;
			
		}
		return array('order_id' => $order_id, 'package_type' => $package_type, 'package_size' => $package_size, 'dropoff_contact_person' => $dropoff_contact_person, 'dropoff_address' => $dropoff_address, 'dropoff_zipcode' => $dropoff_zipcode, 'dropoff_city' => $dropoff_city, 'dropoff_qrcode_str' => $dropoff_qrcode_str);
	}
}


?>