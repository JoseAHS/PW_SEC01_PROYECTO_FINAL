<?php 
ob_start();
session_start();
include 'admin/inc/config.php';

// --- LIMPIAR CARRITO ---
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'cart_') === 0) { 
        unset($_SESSION[$key]); 
    }
}

// --- LIMPIAR DATOS DEL CLIENTE ---
unset($_SESSION['customer']);
unset($_SESSION['customer_id']);
unset($_SESSION['customer_email']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_phone']);
unset($_SESSION['customer_address']);

// --- OPCIONAL: si quieres borrar todo completamente ---
// session_destroy();

header("Location: ".BASE_URL."login.php");
exit;
?>
