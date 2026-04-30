<?php

function authenticateAdmin() {
    $user = authenticate();
    
    if (empty($user['is_admin'])) {
        error('Admin access required', 403);
    }
    
    return $user;
}
