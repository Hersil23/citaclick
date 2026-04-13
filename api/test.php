<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode(['status' => 'ok', 'message' => 'API is working', 'php' => PHP_VERSION]);
