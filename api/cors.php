<?php
// CORS Preflight Handler
// This file handles OPTIONS requests before any routing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
http_response_code(200);
exit;
