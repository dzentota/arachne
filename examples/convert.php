<?php

$in = fopen('barbora.rescan.csv', 'r') or die('cannot read');
$out = fopen('barbora.rescan.csv', 'w') or die('cannot write');

while ($data = fgetcsv($in, 512, ';', '"', '"')) {
    print_r($data);
    die();
}