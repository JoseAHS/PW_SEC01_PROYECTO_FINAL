<?php require_once('header.php'); ?>

<?php
// Preventing direct access
if(!isset($_REQUEST['id'])) {
    header('location: logout.php');
    exit;
} else {
    // Validate category exists
    $statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE ecat_id=?");
    $statement->execute([$_REQUEST['id']]);
    if($statement->rowCount() == 0) {
        header('location: logout.php');
        exit;
    }
}
?>

<?php

// ==============================================
// GET ALL PRODUCTS LINKED TO THIS end-category
// ==============================================
$statement = $pdo->prepare("SELECT p_id FROM tbl_product WHERE ecat_id=?");
$statement->execute([$_REQUEST['id']]);
$result = $statement->fetchAll(PDO::FETCH_ASSOC);

// Always initialize the array
$p_ids = [];

foreach ($result as $row) {
    $p_ids[] = $row['p_id'];
}

// ==============================================
// IF THERE ARE PRODUCTS → DELETE EVERYTHING
// ==============================================
if (count($p_ids) > 0) {

    foreach ($p_ids as $pid) {

        // --- 1. Delete featured photo ---
        $statement = $pdo->prepare("SELECT p_featured_photo FROM tbl_product WHERE p_id=?");
        $statement->execute([$pid]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['p_featured_photo'])) {
            @unlink('../assets/uploads/' . $row['p_featured_photo']);
        }

        // --- 2. Delete additional photos ---
        $statement = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=?");
        $statement->execute([$pid]);
        $photos = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($photos as $ph) {
            if (!empty($ph['photo'])) {
                @unlink('../assets/uploads/product_photos/' . $ph['photo']);
            }
        }

        // --- 3. Delete sizes ---
        $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?")->execute([$pid]);

        // --- 4. Delete colors ---
        $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?")->execute([$pid]);

        // --- 5. Delete ratings ---
        $pdo->prepare("DELETE FROM tbl_rating WHERE p_id=?")->execute([$pid]);

        // --- 6. Delete related orders + payments ---
        $statement = $pdo->prepare("SELECT payment_id FROM tbl_order WHERE product_id=?");
        $statement->execute([$pid]);
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as $order) {
            $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id=?")
                ->execute([$order['payment_id']]);
        }

        // Delete orders
        $pdo->prepare("DELETE FROM tbl_order WHERE product_id=?")->execute([$pid]);

        // --- 7. Delete product ---
        $pdo->prepare("DELETE FROM tbl_product WHERE p_id=?")->execute([$pid]);

        // --- 8. Delete product photos records ---
        $pdo->prepare("DELETE FROM tbl_product_photo WHERE p_id=?")->execute([$pid]);
    }
}

// ==============================================
// DELETE CATEGORY ITSELF
// ==============================================
$statement = $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id=?");
$statement->execute([$_REQUEST['id']]);

// Redirect
header('location: end-category.php');
exit;

?>
