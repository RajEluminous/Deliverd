<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Driver Approval Status</title>
</head>
<body>
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width:600px;margin: auto; font-family:Gotham, 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:14px;">
  <tbody>
    <tr>
      <td style="background: #0085b2;text-align: center;padding: 10px;"><img src="{{ config('constants.LIVE_LOGO_URL') }}" style="width: 270px;"></td>
    </tr> 
    <tr>
      <td><h2 style="margin-top: 30px;margin-bottom: 0;">Your password has been updated successfully.</h2></td>
    </tr>
	<tr>
      <td><p style="line-height: 22px;text-align: center;">New password: {{ $user['password'] }}</p></td>
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
