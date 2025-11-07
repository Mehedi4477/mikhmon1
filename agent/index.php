<?php
session_start();
error_reporting(0);

// Check if already logged in
if (isset($_SESSION['agent_id'])) {
    header("Location: dashboard.php");
    exit();
}

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    
    if (!empty($phone) && !empty($password)) {
        $agent = new Agent();
        $result = $agent->verifyLogin($phone, $password);
        
        if ($result['success']) {
            $_SESSION['agent_id'] = $result['agent']['id'];
            $_SESSION['agent_name'] = $result['agent']['agent_name'];
            $_SESSION['agent_code'] = $result['agent']['agent_code'];
            $_SESSION['agent_theme'] = 'dark'; // Default theme
            $_SESSION['agent_themecolor'] = '#3a4149';
            
            // Generate simple token for API calls
            $_SESSION['agent_token'] = md5($result['agent']['id'] . $result['agent']['phone']);
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>' . htmlspecialchars($result['message']) . '</div>';
        }
    } else {
        $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>Phone and password are required.</div>';
    }
}

include_once('include_head.php');
?>

<div style="padding-top: 5%;" class="login-box">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-users"></i> Agent Login</h3>
    </div>
    <div class="card-body">
      <div class="text-center pd-5">
        <img src="../img/favicon.png" alt="MIKHMON Logo">
      </div>
      <div class="text-center">
        <span style="font-size: 25px; margin: 10px;">MIKHMON Agent</span>
      </div>
      <center>
      <form autocomplete="off" action="" method="post">
      <table class="table" style="width:90%">
        <tr>
          <td class="align-middle text-center">
            <input style="width: 100%; height: 35px; font-size: 16px;" class="form-control" type="text" name="phone" id="phone" placeholder="Phone Number" required="1" autofocus>
          </td>
        </tr>
        <tr>
          <td class="align-middle text-center">
            <input style="width: 100%; height: 35px; font-size: 16px;" class="form-control" type="password" name="password" placeholder="Password" required="1">
          </td>
        </tr>
        <tr>
          <td class="align-middle text-center">
            <input style="width: 100%; margin-top:20px; height: 35px; font-weight: bold; font-size: 17px;" class="btn-login bg-primary pointer" type="submit" name="login" value="Login">
          </td>
        </tr>
        <tr>
          <td class="align-middle text-center">
            <?= $error; ?>
          </td>
        </tr>
      </table>
      </form>
      </center>
    </div>
  </div>
</div>

<?php include_once('include_foot.php'); ?>
