<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fleet Registration Email</title>
</head>
<body>
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width:600px;margin: auto; font-family:Gotham, 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:14px;">
  <tbody>
    <tr>
      <td style="background: #0085b2;text-align: center;padding: 10px;"><img src="{{ config('constants.LIVE_LOGO_URL') }}" style="width: 270px;"></td>
    </tr> 
    <tr>
      <td style="text-align: center;"><h2 style="margin-top: 30px;margin-bottom: 0;">Congratulations!</h2></td>
    </tr>
	 <tr>
      <td style="text-align: center;"><span style="margin-top: 30px;margin-bottom: 0;text-align: center;"><strong>You are now a Fleet Owner on the etYay</strong>.</span></td>
    </tr>
	 <tr>
      <td style="text-align: center;"><span style="margin-top: 30px;margin-bottom: 0;text-align: center;"><strong>Delivery Platform</strong>.</span></td>
    </tr>
	<tr>
      <td style="text-align: center;"><p style="line-height: 22px;text-align: center;">Please click on the link below to start adding drivers and vehicles</p></td>
    </tr>
	<tr>
      <td style="text-align: center;"><a href="{{ $user['link'] }}" style="display: inline-block;text-decoration: navajowhite;background: #0085b2;color: #fff;padding: 15px 30px;border-radius: 30px;">Login now</a></td>
    </tr>
	<tr>
      <td>&nbsp;</td>
    </tr>    	 
    <tr>
      <td style="background: #2c2c2c;text-align: center;padding: 20px 0px; color:#fff;margin-top: 30px !important;display: block;">© etYay</td>
    </tr>
  </tbody>
</table>

</body>
</html>
