<?php

function create_new_pdo($drv, $host, $port, $dbname, $user_name, $password, $options) {
  return new PDO($drv.':host='.$host.';port='.$port.';dbname='.$dbname, $user_name, $password, $options);
}

?>
