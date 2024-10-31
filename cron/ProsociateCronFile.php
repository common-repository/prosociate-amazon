<?php

$path = preg_replace('/wp-content.*$/','',__DIR__);
require_once($path.'/wp-load.php');
//var_dump(dirname(__FILE__));
require_once(dirname(__FILE__).'/ProsociateCron.php');
