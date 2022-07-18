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
	@if ($user['status'] == 'approved')
    <tr>
      <td><h2 style="margin-top: 30px;margin-bottom: 0;">Congratulations, you have been approved on the etYay platform.</h2></td>
    </tr>
	<tr>
      <td><p style="line-height: 22px;text-align: center;">Please click on the link below to download the application and to start earning.</p></td>
    </tr>
	<tr>
      <td>&nbsp;</td>
    </tr> 
	<tr>
      <td><table><tr><td style="text-align: right;"><a href="{{ config('constants.LIVE_URL_APPSTORE') }}"><img src="{{ config('constants.LIVE_LOGO_APPSTORE') }}" style="width: 250px;"></a></td><td style="text-align: left;"><a href="{{ config('constants.LIVE_URL_PLAYSTORE') }}"><img src="{{ config('constants.LIVE_LOGO_PLAYSTORE') }}" style="width: 270px;"></a></td></tr></table></td>
    </tr>
	@endif
    @if ($user['status'] == 'rejected')
    <tr>
      <td><h2 style="margin-top: 30px;margin-bottom: 0;">We regret to inform you that your application on the etYay platform has been rejected.</h2></td>
    </tr>
	<tr>
      <td><p style="line-height: 22px;text-align: center;">{{$user['reason']}}</p></td>
    </tr>
	<tr>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td style="text-align: center;"><a href="{{ $user['link'] }}" style="display: inline-block;text-decoration: navajowhite;background: #0085b2;color: #fff;padding: 15px 30px;border-radius: 30px;">{{$user['link_rejected_text']}}</a></td>
    </tr>
	@endif 
    <tr>
      <td style="background: #2c2c2c;text-align: center;padding: 20px 0px; color:#fff;margin-top: 30px !important;display: block;">© etYay</td>
    </tr>
  </tbody>
</table>

</body>
</html>
