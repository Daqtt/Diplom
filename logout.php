<?php
session_start();
require 'cookie_utils.php';

logoutUser();

header('Location: login.php');
exit;




