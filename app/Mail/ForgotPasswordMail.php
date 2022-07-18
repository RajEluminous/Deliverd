<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {   

        $viewName='mail.forgot-password-email';
        
        $this->from('admin@tigerfishsoftware.co.za', 'etYay');
        $this->replyTo('admin@tigerfishsoftware.co.za', 'etYay');
        $this->subject('Forgot Password');


        return $this->view($viewName, ['user' => $this->data]);
    }
}