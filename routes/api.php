<?php

use Illuminate\Http\Request;

/*
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');
*/

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });


$PREFIX = 'Api\v1';  


Route::group(['prefix' => 'v1'], function()use($PREFIX) 
{
    Route::post('login',  $PREFIX.'\DriverController@login');   
    Route::post('forgot_password',  $PREFIX.'\DriverController@forgotPassword');
    Route::post('check_otp',  $PREFIX.'\DriverController@checkOtp');  
    Route::post('update_password',  $PREFIX.'\DriverController@updatePassword');
	
	Route::post('driverstatus',  $PREFIX.'\DriverController@driverStatus');
	Route::resource('articles',  $PREFIX.'\ArticlesAPIController');
	Route::resource('delivery', $PREFIX.'\DeliveryAPIController');
	 	
	//Route::match(['get', 'post'],'create-fleet',  $PREFIX.'\FleetController');
	Route::match(['get', 'post'], 'newfleet', $PREFIX.'\FleetController@newFleet');
	Route::match(['get', 'post'], 'savefleetbankdetails', $PREFIX.'\FleetController@saveFleetBankDetails');
	Route::match(['get', 'post'], 'getfleetbankdetails', $PREFIX.'\FleetController@getFleetBankDetails');
	Route::match(['get', 'post'], 'newvehicle', $PREFIX.'\VehicleController@newVehicle');
	Route::match(['get', 'post'], 'newdriver', $PREFIX.'\DriverController@newDriver');
	Route::match(['get', 'post'], 'assign_driver_to_fleet', $PREFIX.'\FleetController@assign_driver_to_fleet');
		  
	// Web Admin APIs	
	Route::post('adminlogin',  $PREFIX.'\AdminController@adminlogin');   
	Route::post('getdrivers',  $PREFIX.'\DriverController@getDrivers');
	Route::post('getnonfleetdrivers',  $PREFIX.'\DriverController@getNonFleetDrivers');
	Route::post('searchnonfleetdrivers',  $PREFIX.'\DriverController@searchNonFleetDrivers');
	Route::post('sendtopool',  $PREFIX.'\DriverController@sendToPool');						// To remove driver from fleet and then driver will be considered as non-fleet driver.
	Route::post('invitefleettodriver',  $PREFIX.'\FleetController@inviteFleetToDriver');	// To invite driver by fleet
	Route::post('approvefleettodriver',  $PREFIX.'\DriverController@approveFleetToDriver');
	Route::post('showallfleets',  $PREFIX.'\FleetController@showAllFleets');
	 
	Route::match(['get', 'post'], 'getvehicles', $PREFIX.'\VehicleController@getVehicles');
	Route::match(['get', 'post'], '/driverenabledisable', $PREFIX.'\DriverController@driverEnableDisable');
	Route::match(['get', 'post'], '/getdriverinviteinfo', $PREFIX.'\DriverController@getInviteDriverInfo');	// based on unique string
	Route::match(['get', 'post'], '/driverregistration', $PREFIX.'\DriverController@driverRegistration');	 
	Route::match(['get', 'post'], '/getdriververificationinfo', $PREFIX.'\DriverController@getDriverVerificationInfo');	 
	Route::match(['get', 'post'], '/driveradminapprovereject', $PREFIX.'\DriverController@driverAdminApproveReject');	 
	Route::match(['get', 'post'], '/vehicleenabledisable', $PREFIX.'\VehicleController@vehicleEnableDisable');
	Route::match(['get', 'post'], '/getvehicleverificationinfo', $PREFIX.'\VehicleController@getVehicleVerificationInfo');	
	Route::match(['get', 'post'], '/vehicleadminapprovereject', $PREFIX.'\VehicleController@vehicleAdminApproveReject');
	Route::match(['get', 'post'], '/editvehicle', $PREFIX.'\VehicleController@editVehicle');	
	
	Route::match(['get', 'post'], '/getfleetavailablevehicles', $PREFIX.'\VehicleController@getFleetAvailableVehicles');	
	Route::match(['get', 'post'], '/assign_vehicle_to_driver', $PREFIX.'\VehicleController@assign_vehicle_to_driver');
	Route::match(['get', 'post'], '/deliveryselfiehandshake', $PREFIX.'\DeliveryAPIController@deliverySelfieHandshake');
	Route::match(['get', 'post'], '/viewdeliveries', $PREFIX.'\DeliveryAPIController@viewDeliveries');
	Route::match(['get', 'post'], 'getcarmakes', $PREFIX.'\VehicleController@getCarMakes');
	Route::match(['get', 'post'], 'getcarmodels', $PREFIX.'\VehicleController@getCarModels');
});
 
Route::group(['prefix' => 'v1','middleware' => 'auth:drivers'], function()use($PREFIX)
{  
    Route::post('create-trip',  $PREFIX.'\TripApiController@createTrip');
    Route::post('get-trips',  $PREFIX.'\TripApiController@getTrips');
    Route::post('get-driver-earnings',  $PREFIX.'\TripApiController@getDriverEarnings');
    
    Route::post('get-trip-history',  $PREFIX.'\TripApiController@getTripHistory');
    Route::post('get-trip-statistics',  $PREFIX.'\TripApiController@getTripStatistics');


    Route::post('reset_password',  $PREFIX.'\DriverController@resetPasswordSubmit');
    Route::post('logout',  $PREFIX.'\DriverController@logout');	
	
	Route::match(['get', 'post'], '/deliveryhandshake', $PREFIX.'\DeliveryAPIController@deliveryHandshake');
	Route::match(['get', 'post'], '/tripacceptrejectstatus', $PREFIX.'\DriverController@tripAcceptRejectStatus');
});

 
