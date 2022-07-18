<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeliveryDispatchedMail extends Mailable
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

    public function build() {   
		
        $viewName='mail.delivery-dispatched-email';
        
        $this->from('admin@tigerfishsoftware.co.za', 'etYay');
        $this->replyTo('admin@tigerfishsoftware.co.za', 'etYay');
        $this->subject('Delivery Dispatched');
		
        return $this->view($viewName, ['user' => $this->data]);
    }
}