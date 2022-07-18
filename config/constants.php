<?php
return [
	
	/*--------------------------------------------------------
	|  General Constants
	------------------------------------*/
		//'ADMINEMAIL' 		=> 'eluminous_se42@eluminoustechnologies.com',
		'SITENAME'     			=> 'Delivered',
		'DELIVERLIMIT'     		=> '10',
		'ESTIMATEDMINUTEPERKM'  => '4',
		'ESTIMATEDPRICEPERKM'  	=> '2',
		'DELIVERYRADIUS'        => 10,  /* 10 KM */
		
	
	/*--------------------------------------------------------
	|  API CONSTANTS
	------------------------------------*/  
		'API_VERSION_ONE'=> 'v1',
		'APP_TOKEN' => env('APP_TOKEN', 'admin123456'),
    	'API_URL' => env('APP_URL', 'http://localhost/puregyn').'/api/v1/',

    /*--------------------------------------------------------
	|  Google Keys
	------------------------------------*/  
		'GOOGLE_KEY'=> 'AIzaSyBdFRcM7GFoZH7zriMnwhKsoZaOAFstEtQ',
		
		'EMAIL_BASE_URL' => 'http://13.245.64.169/',
							 		 
		'LIVE_LOGO_URL' => 'http://13.244.188.225/assets/images/logo.png',
		
		'LIVE_LOGO_APPSTORE' => 'http://13.244.188.225/assets/images/appstore.png',
		'LIVE_URL_APPSTORE' => 'https://i.diawi.com/GuHfAk',
		
		'LIVE_LOGO_PLAYSTORE' => 'http://13.244.188.225/assets/images/playstore.png',
		'LIVE_URL_PLAYSTORE' => 'https://drive.google.com/file/d/1E_AyfVaOxDTupZmo3cbgTX0o3HT31E5F/view?usp=sharing',
		
];

?>