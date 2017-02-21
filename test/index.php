<?php
include __DIR__ . '/../src/avadim/hjson/Hjson.php';
include __DIR__ . '/../src/avadim/hjson/HjsonException.php';

use avadim\hjson\Hjson;

$str = file_get_contents(__DIR__ . '/test.hjson');

$arr = Hjson::decode($str, true);

var_dump($arr);

// EOF