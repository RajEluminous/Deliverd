<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Raleway:100,600" rel="stylesheet" type="text/css">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Raleway', sans-serif;
                font-weight: 100;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">           
            <div class="content">
                <h2>Deliverd Customer QRCode</h2>
				@if ($errcode == 0)
					<div class="alert alert-success text-center">
						<p>{{ $msg }}</p>
					</div>
				@else	
					<div>
						<h2>Please present the QR code below to the driver</h2>
					</div>  
				@endif
               
                    @if ($dropoff_qrcode_str) 
						<div><img src="data:image/png;base64,{{$dropoff_qrcode_str}}" /></div> 
						<div><table align="center">
							<tr><td align="left">Order Id:</td><td align="left"><strong>{{$order_id}}</strong></td></tr>
							<tr><td align="left">Package Type:</td><td align="left"><strong>{{$package_type}}</strong></td></tr>
							<tr><td align="left">Package Size:</td><td align="left"><strong>{{$package_size}}</strong></td></tr>
							<tr><td align="left">Contact Person:</td><td align="left"><strong>{{$dropoff_contact_person}}</strong></td></tr>
							<tr><td align="left">Delivery Address:</td><td align="left"><strong>{{$dropoff_address}}</strong></td></tr>
							<tr><td align="left">ZipCode:</td><td align="left"><strong>{{$dropoff_zipcode}}</strong></td></tr>
							<tr><td align="left">City:</td><td align="left"><strong>{{$dropoff_city}}</strong></td></tr>
						</table></div>
					@endif	
                
            </div>
        </div>
    </body>
</html>
