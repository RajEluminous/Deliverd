<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class VehicleAdminApprovalRejectedMail extends Mailable
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
        $viewName='mail.vehicle-admin-approval-rejected-email';
        
        $this->from('admin@tigerfishsoftware.co.za', 'etYay');
        $this->replyTo('admin@tigerfishsoftware.co.za', 'etYay');
		if($this->data['status'] == 'approved') {
			$this->subject('Congratulations, your vehicle has been approved.');
		} else {
			$this->subject('Your vehicle has been rejected.');
		}
        return $this->view($viewName, ['user' => $this->data]);
    }
}