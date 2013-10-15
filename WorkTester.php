<?php
require __DIR__ . '/Coolie.php';
spl_autoload_register(array('\\Coolie\\Coolie', 'autoload'));

$data = array();

$test = new \Coolie\Worker\Stat();
$test->applyJob($data);