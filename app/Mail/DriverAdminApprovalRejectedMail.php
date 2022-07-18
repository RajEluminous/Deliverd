<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DriverAdminApprovalRejectedMail extends Mailable
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
          $viewName='mail.driver-admin-approval-rejected-email';
        
        $this->from('admin@tigerfishsoftware.co.za', 'etYay');
        $this->replyTo('admin@tigerfishsoftware.co.za', 'etYay');
		if($this->data['status'] == 'approved') {
			$this->subject('Congratulations, you have been approved.');
		} else {
			$this->subject('Your application has been rejected.');
		}
        return $this->view($viewName, ['user' => $this->data]);  
		
		
		/* $address = 'admin@tigerfishsoftware.co.za';
        $subject = 'This is a demo!';
        $name = 'Jane Doe';

        return $this->view('mail.driver-admin-approval-rejected-email')
                    ->from($address, $name)
                    ->cc($address, $name)
                    ->bcc($address, $name)
                    ->replyTo($address, $name)
                    ->subject($subject)
                    ->with([ 'user' => $this->data ]); */
		
    }
}