<?php

error_reporting(E_ALL &~ E_USER_DEPRECATED);
ini_set('display_errors', '1');

require 'vendor/autoload.php';
require 'includes/utilities.php';

require 'includes/theme-setup.php';
require 'includes/filters.php';
require 'includes/search.php';
require 'includes/templating.php';
require 'includes/admin/dashboard.php';
require 'includes/admin/users.php';


