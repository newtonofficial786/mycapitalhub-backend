<?php
// This file routes requests to the backend index.php
// The api/.htaccess routes all requests here

// Change to parent directory for backend includes
chdir(dirname(__DIR__));

// Include the main backend router
require_once __DIR__ . '/../index.php';
