<?php

$current = [
    'name'     => 'cc1',
    'parent'   => null,
    'children' => [],
];

var_dump($current);
$item = [
    'name'     => 'cc2',
    'parent'   => &$current,
    'children' => [],
];
var_dump($current);
