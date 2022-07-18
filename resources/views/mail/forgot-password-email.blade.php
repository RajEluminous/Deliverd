<html>
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
</head>
<body style="background:#fff; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:25px;">
  <table border="0" cellspacing="0" cellpadding="0" style="background:#eaeaea; max-width:800px; width:100%; padding:0px 15px;" align="center">
    <tr>
      <td>
        <table border="0" cellspacing="0" cellpadding="0" align="center" style="margin-bottom: 35px;max-width:700px;width:95%">  
          <tr>
            <td>
              <table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding: 40px 0px 20px;">
              <tr>
                <td></td>
                <td style="font-size: 120%; text-align: right; font-weight: bold; color: rgb(102, 102, 102);"></td>
              </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="background:#fff;">
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td style="text-align: justify; padding: 5%">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                      <tr>
                          <td> 
                              Dear {{ $user['name'] }}, <br/><br/> We have received a request to reset the password for the Delivery application account associated with this email address. <br/><br/>
                              If you made this request, please refer the below OTP number. 
                            <br/>                                
                           <b> {{ $user['otp'] }}</b>
                            <br/><br>
                            Thank You
                          </td>
                        </tr>
                    </table>
                </td>
              </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="background:#ffb400; text-align:center;">
              <center>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
                  <tr>
                    <td style="text-align: center;"></td>
                  </tr>
                </table>
              </center>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

