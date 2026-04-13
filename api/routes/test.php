<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'status' => 'ok',
    'php' => PHP_VERSION,
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'script' => $_SERVER['SCRIPT_NAME'],
    'doc_root' => $_SERVER['DOCUMENT_ROOT'],
]);
