<?php
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();
chdir($paths->systemDirectory . '/../');
define('FCPATH', __DIR__ . '/public/');
require __DIR__ . '/preload.php' ?? null;
