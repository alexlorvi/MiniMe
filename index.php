<?php
  $radiusLog_path = "/var/log/freeradius/radius.log";
  $daloradius_cfg = "/var/www/html/library/daloradius.conf.php";
  $useProxy = false;
  $Proxy = '1.1.1.1:8080';

  function in_arr($el,$arr) {
    $res = false;
    foreach ($arr as $elm) {
      if (mb_stripos($el,$elm) > 0) $res = true;
    }
    return $res;
  }

  function get_failed_from_log($log_path) {
    $radiusLog = file_get_contents($log_path);
    preg_match_all('/Invalid user: .*$/m', $radiusLog, $out, PREG_PATTERN_ORDER);
    $users_macs = [];
    foreach($out[0] as $log_entry) {
      $usr = mb_split('[\[\]]',$log_entry);
      $remove = array("<",">");
      array_push($users_macs,[trim(str_replace($remove,"",$usr[1])),trim(rtrim(end(explode(' ', $log_entry)),")"))]);
    }
    $res2 = [];
    $skip = ["attribute"];
    foreach($users_macs as $user_mac) {
      $mac = $user_mac[1];
      $cnt = 0;
      if (in_arr($user_mac[0],$skip) == 0) {
        $text = $user_mac[0];
      } else {
        $text = '';
      }
      foreach($users_macs as $user_mac2) {
        if ($mac == $user_mac2[1]) {
          if ((in_arr($user_mac2[0],$skip) == 0) && (mb_stripos($text,$user_mac2[0]) === false)) $text .= (strlen($text)>0)?"\n".$user_mac2[0]:$user_mac2[0];
        }
      }
      foreach($res2 as $el3) {
        if ($mac == $el3[1]) $cnt += 1;
      }
      if ($cnt == 0) array_push($res2,[$text,$mac]);
    }
    return $res2;
  }

  function mac_in_db($mi, $mac) {
    global $configValues;
    $res = $mi->query('select username from '.$configValues['CONFIG_DB_TBL_DALOUSERINFO'].' where username = "'.$mac.'"');
    if ($res->num_rows > 0) {
      return true;
    } else {
      return false;
    }
  }

  function get_vendor($mac) {
    global $useProxy;
    global $Proxy;

    $aContext = array(
                'http' => array(
                'proxy' => 'tcp://'.$Proxy,
                'request_fulluri' => true,
                'timeout' => 5,
                ),
    );
    $cxContext = stream_context_create($aContext);
    if ($useProxy) {
      $contents = file_get_contents('https://api.macvendors.com/'.$mac,False, $cxContext);
    } else {
      $contents = file_get_contents('https://api.macvendors.com/'.$mac);
    }
    return $contents;
  }

  function add_mac($mi, $mac, $text=null) {
    global $configValues;

    $currDate = date('Y-m-d H:i:s');
    $currBy = 'MiniMe';

    $sql = "select username from ".$configValues['CONFIG_DB_TBL_DALOUSERINFO']." where username='$mac'";
    $res = $mi->query($sql);
    if ($res->num_rows == 0) {
      $sql1 = "INSERT INTO ".$configValues['CONFIG_DB_TBL_DALOUSERINFO'];
      $sql1 .= " (username, creationdate, creationby ";
      $sql1 .= (!empty($text))?",notes)":")";
      $sql1 .= " VALUES ('$mac', '$currDate','$currBy'";
      $sql1 .= (!empty($text))?",'%s')":")";
      $res1 = $mi->query(sprintf($sql1,$mi->real_escape_string($text)));
    }

    $sql = "select username from ".$configValues['CONFIG_DB_TBL_DALOUSERBILLINFO']." where username='$mac'";
    $res = $mi->query($sql);
    if ($res->num_rows == 0) {
      $sql2 = "INSERT INTO ".$configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'];
      $sql2 .= " (username, creationdate, creationby";
      $sql2 .= (!empty($text))?",notes)":")";
      $sql2 .= " VALUES ('$mac','$currDate', '$currBy'";
      $sql2 .= (!empty($text))?",'%s')":")";
      $res2 = $mi->query(sprintf($sql2,$mi->real_escape_string($text)));
    }

    $sql = "select username from ".$configValues['CONFIG_DB_TBL_RADCHECK']." where username='$mac'";
    $res = $mi->query($sql);
    if ($res->num_rows == 0) {
      $sql3 = "INSERT INTO ".$configValues['CONFIG_DB_TBL_RADCHECK']." (Username,Attribute,op,Value) VALUES ('$mac', 'Auth-Type', ':=', 'Accept')";
      $res3 = $mi->query($sql3);
    }

    if ($res1 & $res2) {
      return true;
    } else {
      return false;
    }
  }

  function del_mac($mi, $mac) {
    global $configValues;

    $sql = "delete from ".$configValues['CONFIG_DB_TBL_DALOUSERINFO']." where username='$mac'";
    $res1 = $mi->query($sql);

    $sql = "delete from ".$configValues['CONFIG_DB_TBL_DALOUSERBILLINFO']." where username='$mac'";
    $res2 = $mi->query($sql);

    $sql = "delete from ".$configValues['CONFIG_DB_TBL_RADCHECK']." where username='$mac'";
    $res3 = $mi->query($sql);

    if ($res1 & $res2 & $res3) {
      return true;
    } else {
      return false;
    }
  }

  function get_macs($mi) {
    global $configValues;

    $sql = "SELECT ";
    $sql .= $configValues['CONFIG_DB_TBL_DALOUSERINFO'].".username, ";
    //$sql .= $configValues['CONFIG_DB_TBL_DALOUSERINFO'].".firstname, ";
    //$sql .= $configValues['CONFIG_DB_TBL_DALOUSERINFO'].".lastname, ";
    $sql .= $configValues['CONFIG_DB_TBL_DALOUSERINFO'].".notes, ";
    $sql .= $configValues['CONFIG_DB_TBL_DALOUSERINFO'].".creationdate, ";
    $sql .= $configValues['CONFIG_DB_TBL_DALOUSERINFO'].".creationby ";
    $sql .= " FROM ".$configValues['CONFIG_DB_TBL_DALOUSERINFO'];
    $sql .= " INNER JOIN ".$configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'];
    $sql .= " ON ".$configValues['CONFIG_DB_TBL_DALOUSERINFO'].".username=";
    $sql .= $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'].".username";
    $res = $mi->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
  }


  include $daloradius_cfg;

  $mysqli = new mysqli($configValues['CONFIG_DB_HOST'], $configValues['CONFIG_DB_USER'], $configValues['CONFIG_DB_PASS'],  $configValues['CONFIG_DB_NAME']);
  if($mysqli->connect_errno) {
    printf("Connect failed: %s<br />", $mysqli->connect_error);
    exit();
  }

  if (!empty($_GET['mac'])) add_mac($mysqli,$_GET['mac'],(!empty($_GET['text']))?$_GET['text']:null);
  if (!empty($_GET['remove'])) del_mac($mysqli,$_GET['remove']);

  echo '<h1>Failed Clients:</h1>';
  $fin = get_failed_from_log($radiusLog_path);
  //print_r($fin);
  $unknown = 0;
  if (count($fin)>0) {
    echo '<table border=1>';
    echo '<tr><th>User field</th><th>MAC/Vendor</th><th>Action</th></tr>';
    foreach ($fin as $elm) {
      if (mac_in_db($mysqli,$elm[1]) == false) {
        $tr = '<td><pre>'. $elm[0] . '</pre></td>';
        $tr .= '<td align=center>'.$elm[1].'<br /><span style="font-size:0.5em">'.get_vendor($elm[1]).'</span></td>';
        $tr .= '<td><a href=?mac='.$elm[1].'>Add MAC</a><br><a href=?mac='.$elm[1].'&text='.urlencode($elm[0]).'>Add MAC+Text</a></td>';
        echo '<tr>'.$tr.'</tr>';
        $unknown += 1;
      }
    }
    echo '</table>';
  }
  echo "<h2>Total in log - ".count($fin)." (new - ".$unknown.")</h2>";

  echo "<hr />";

  echo "<h1>Clients list</h1>";

  echo "<table border=1>";
  echo '<tr><th>Mac</th><th>Notes</th><th>Creation Date</th><th>Created By</th><th>Action</th></tr>';
  $lst=get_macs($mysqli);
  foreach ($lst as $usr) {
    $ln = '<td>'.$usr['username'].'</td>';
    $ln .= '<td><pre>'.$usr['notes'].'</pre></td>';
    $ln .= '<td>'.$usr['creationdate'].'</td>';
    $ln .= '<td>'.$usr['creationby'].'</td>';
    $ln .= '<td><a href=?remove='.$usr['username'].'>Remove</a></td>';
    echo '<tr>'.$ln.'</tr>';
  };
  echo "</table>";

  echo "<h2>Total in DB - ".count($lst)."</h2>";
  $mysqli->close();
