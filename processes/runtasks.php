#!/usr/bin/php
<?php
  date_default_timezone_set('America/La_Paz');
            $fecha = date("Y-m-d");
             $hora = date("H:i:s");
         $horadiff = 4*60*60;
         $fechabol = gmdate("Y-m-d", time()-$horadiff);
          $horabol = gmdate("H:i:s", time()-$horadiff);

   $pathinc    = "/home/upbroot/public_html/v1/inc";
   $pathlogops = "/home/upbroot/logs/operations";
   $pathlogsql = "/home/upbroot/logs/sql";
  $lockfilepid = "/home/upbroot/processes/lock.pid";

  // This include uses the full path to the file, as its loated outside of the web directory
  include_once("/home/upbroot/conf/conf.inc.php");
  include_once("$pathinc/langs/$surp_lang.inc.php");
  include_once("$pathinc/func.inc.php");
  include_once("$pathinc/dbopen.inc.php");
  echo "[".date("Y-m-i H:i:s")."] $surp_sitename v. $surp_sitever\n";
  echo "[".date("Y-m-i H:i:s")."] Process starting ...\n";
  echo "[".date("Y-m-i H:i:s")."] ----------------------------------------------------\n";
  $lock_file = fopen($lockfilepid, 'c');
  $got_lock = flock($lock_file, LOCK_EX | LOCK_NB, $wouldblock);
  if ($lock_file === false || (!$got_lock && !$wouldblock)) {
    throw new Exception(
        "[".date("Y-m-i H:i:s")."] Unexpected error opening or locking lock file. Perhaps you " .
        "don't  have permission to write to the lock file or its " .
        "containing directory?"
    );
  } else if (!$got_lock && $wouldblock) {
    exit("[".date("Y-m-i H:i:s")."] Another instance is already running; terminating.\n");
  }
  // Lock acquired; let's write our PID to the lock file for the convenience
  // of humans who may wish to terminate the script.
  ftruncate($lock_file, 0);
  fwrite($lock_file, getmypid() . "\n");
  echo "[".date("Y-m-i H:i:s")."] Searching for scheduled operations ...\n";
  $so  = "select * from OPERATIONS op, OPERATIONTYPES ot where ";
  $so .= "op.ot_id=ot.ot_id and op.os_id='1' order by op.op_id asc;";
  $qo = db_query($so);
  echo "[".date("Y-m-i H:i:s")."] Found ".$qo->num_rows." pending operations\n";
  echo "[".date("Y-m-i H:i:s")."] ----------------------------------------------------\n";
  while ($po = $qo->fetch_object()) {
    echo "[".date("Y-m-i H:i:s")."] ID: $po->op_id Type: $po->ot_short\n";
    echo "[".date("Y-m-i H:i:s")."] -----------------------------------\n";
    echo "[".date("Y-m-i H:i:s")."]   Marking operation as been processed\n";
    $os = "update OPERATIONS set os_id='2' where op_id='$po->op_id' limit 1;";
    // *CAE* $doit = db_query($os);
    $return_var = "";
    if ($po->ot_id==9) {
      // We Need to add a new user
      // First we try to create it
      $command  = "/usr/sbin/adduser ";
      $rt_login = "";
      $rt_info = "";
      $rt_home = "";
      $rt_shell = "";
      $rt_quota = "";
      list($rt_login, $rt_password, $rt_info, $rt_home, $rt_shell, $rt_quota)=explode("|", $po->ot_d_flags);
      $command  = "/usr/sbin/adduser ";
      if ($rt_home != "") { $command .= "-m -d $rt_home "; }
      if ($rt_info != "") { $command .= "-c \"$rt_info\" "; }
      if ($rt_shell != "") { $command .= "-s $rt_shell "; }
      $command .= "$rt_login";
      exec($command, $output, $return_var);
      $counter = 0;
      $outputstring = "";
      while ($counter < count($output)) {
        $outputstring .= $output[$counter];
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
      $s_log .= "'$outputstring', '$return_var');";
      $w_log = db_query($s_log);
      $return_var = 0; // *CAE*
      if ($return_var == 0) {
        // Only if this was successfull we set the user password and quota
        $command = "/usr/bin/echo \"$rt_password\" | /usr/bin/passwd --stdin $rt_login";
        exec($command, $output, $return_var);
        $counter = 0;
        $outputstring = "";
        while ($counter < count($output)) {
          $outputstring .= $output[$counter];
          $counter++;
        }
        $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
        $s_log .= "tl_command, tl_output, tl_returnvar) values(";
        $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
        $s_log .= "'$outputstring', '$return_var');";
        $w_log = db_query($s_log);
        $command = "/usr/bin/passwd -e $rt_login";
        exec($command, $output, $return_var);
        $counter = 0;
        $outputstring = "";
        while ($counter < count($output)) {
          $outputstring .= $output[$counter];
          $counter++;
        }
        $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
        $s_log .= "tl_command, tl_output, tl_returnvar) values(";
        $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
        $s_log .= "'$outputstring', '$return_var');";
        $w_log = db_query($s_log);
        $command = "/usr/sbin/xfs_quota -x -c \"limit bsoft=000 bhard=$rt_quota isoft=000 ihard=000 $rt_login\" /";
        exec($command, $output, $return_var);
        $counter = 0;
        $outputstring = "";
        while ($counter < count($output)) {
          $outputstring .= $output[$counter];
          $counter++;
        }
        $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
        $s_log .= "tl_command, tl_output, tl_returnvar) values(";
        $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
        $s_log .= "'$outputstring', '$return_var');";
        $w_log = db_query($s_log);
      }
      unset($output);
      unset($return_var);
    } elseif ($po->ot_id==11) {
      // This is an info change!
      $command = "/usr/sbin/usermod --comment \"$po->ot_d_flags\" $po->ot_d_username";
      exec($command, $output, $return_var);
      $counter = 0;
      $outputstring = "";
      while ($counter < count($output)) {
        $outputstring .= $output[$counter];
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
      $s_log .= "'$outputstring', '$return_var');";
      $w_log = db_query($s_log);
    } elseif ($po->ot_id==12) {
      // This is a shell change!
      $command = "/usr/sbin/usermod --shell \"$po->ot_d_flags\" $po->ot_d_username";
      exec($command, $output, $return_var);
      $counter = 0;
      $outputstring = "";
      while ($counter < count($output)) {
        $outputstring .= $output[$counter];
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
      $s_log .= "'$outputstring', '$return_var');";
      $w_log = db_query($s_log);
    } elseif ($po->ot_id==13) {
      // This is a user groups change!
      // First we will list all groups in the system, and will remove if the user is no longer listed
      // Then I will re add
      // Lets see
      $precmd = "/usr/bin/cat /etc/group | /usr/bin/cut -d\":\" -f1,3";
      $counter = 0;
      $commands = "";
      $outputs = "";
      $return_vars = "";
      exec($precmd, $results);
      while ($counter < count($results)) {
        $line = $results[$counter];
        list($au_group,$au_gid)=explode(":",$line);
        if ($au_gid>=$surp_ugmin and $au_gid<$surp_ugmax) {
          if ($au_group != $po->ot_d_username) {
            $groupdel = "/usr/bin/gpasswd -d $po->ot_d_username $au_group";
            exec($groupdel, $output, $return_var);
            $commands .= $groupdel."\n";
            $counter2 = 0;
            while ($counter < count($output)) {
              $outputs .= $output[$counter]."\n";
              $counter2++;
            }
            $return_vars .= $return_var."\n"; 
          }
        }
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$commands', ";
      $s_log .= "'$outputs', '$return_vars');";
      $w_log = db_query($s_log);
      $return_var .= $return_vars;
      // Now we add the user to all the given groups
      $procgroups = explode("|", $po->ot_d_flags);
      $counter = 0;
      $commands = "";
      $outputs = "";
      $return_vars = "";
      while ($counter < count($procgroups)) {
        if ($procgroups[$counter]!="") {
          $groupadd = "/usr/bin/gpasswd -a $po->ot_d_username ".$procgroups[$counter];
          exec($groupadd, $output, $return_var);
          $commands .= $groupadd."\n";
          $counter2 = 0;
          while ($counter < count($output)) {
            $outputs .= $output[$counter]."\n";
            $counter2++;
          }
          $return_vars .= $return_var."\n";
        }
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$commands', ";
      $s_log .= "'$outputs', '$return_vars');";
      $w_log = db_query($s_log);
      $return_var .= $return_vars;
    } elseif ($po->ot_id==14) {
      // This is a user quota change!
      list($rt_bsq, $rt_bhq, $rt_isq, $rt_ihq)=explode("|", $po->ot_d_flags);
      $command = "/usr/sbin/xfs_quota -x -c \"limit bsoft=$rt_bsq bhard=$rt_bhq isoft=$rt_isq ihard=$rt_ihq $po->ot_d_username\" /";
      exec($command, $output, $return_var);
      $counter = 0;
      $outputstring = "";
      while ($counter < count($output)) {
        $outputstring .= $output[$counter];
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
      $s_log .= "'$outputstring', '$return_var');";
      $w_log = db_query($s_log);
    } elseif ($po->ot_id==15) {
      // This is a user removal!
      $command = "/usr/sbin/userdel -r $po->ot_d_username";
      exec($command, $output, $return_var);
      $counter = 0;
      $outputstring = "";
      while ($counter < count($output)) {
        $outputstring .= $output[$counter];
        $counter++;
      }
      $s_log  = "insert into TRANSACTIONLOG (tl_date, tl_time, op_id, ";
      $s_log .= "tl_command, tl_output, tl_returnvar) values(";
      $s_log .= "'$fecha', '$hora', '$po->op_id', '$command', ";
      $s_log .= "'$outputstring', '$return_var');";
      $w_log = db_query($s_log);
    }
    echo "[".date("Y-m-i H:i:s")."]   Marking operation as finished\n";
    $return_var = str_replace("\n", "", $return_var);
    $os  = "update OPERATIONS set os_id='3' where op_id='$po->op_id' ";
    $os .= "ot_d_result='$return_var' limit 1;";
    // *CAE* $doit = db_query($os);
    echo "[".date("Y-m-i H:i:s")."] -----------------------------------\n";
  }
  // Lock acquired; let's write our PID to the lock file for the convenience
  // of humans who may wish to terminate the script.
  ftruncate($lock_file, 0);
  fwrite($lock_file, getmypid() . "\n");

  echo "[".date("Y-m-i H:i:s")."] ----------------------------------------------------\n";
  echo "[".date("Y-m-i H:i:s")."] Process terminating ...\n";
  // All done; we blank the PID file and explicitly release the lock 
  // (although this should be unnecessary) before terminating.
  ftruncate($lock_file, 0);
  flock($lock_file, LOCK_UN);
  include_once("$pathinc/dbclose.inc.php");
?>