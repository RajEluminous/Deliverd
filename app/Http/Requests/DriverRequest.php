<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class DriverRequest extends FormRequest
{

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
  
          return [                
                'email'     => 'required',
                'password'  => 'required'
            ];
       
    }

    public function messages()
    {

        return [   
            'email.required'        =>  'Email address is required',       
            'password.required'     =>  'Password is required',
        ];
    }
}