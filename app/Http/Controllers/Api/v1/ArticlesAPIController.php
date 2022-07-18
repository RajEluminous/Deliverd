<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\v1\APIBaseController as APIBaseController;
use App\Articles;
use Validator;


class ArticlesAPIController extends APIBaseController
{
	
	
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $articles = Articles::all();
        return $this->sendResponse($articles->toArray(), 'Delivery retrieved successfully.');
    }
	

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
		
		//$request->request->add(['variable' => 'value']); // to add custom value in the request
		
        $input = $request->all();

        $validator = Validator::make($input, [
            'name' => 'required',
            'description' => 'required'
        ]);
		
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());       
        }

		
        $post = Articles::create($input);


        return $this->sendResponse($post->toArray(), 'Delivery created successfully.');
    }
 
}