<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DriverInviteMail extends Mailable
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

        $viewName='mail.driver-invite-email';
        
          $this->from('admin@tigerfishsoftware.co.za', 'etYay');
        $this->replyTo('admin@tigerfishsoftware.co.za', 'etYay');
        $this->subject('Driver Invitation');
		
        return $this->view($viewName, ['user' => $this->data]);
    }
}