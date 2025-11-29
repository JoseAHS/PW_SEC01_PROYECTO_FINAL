<?php require_once('header.php'); ?>

<?php
// 🚫 Evitar acceso directo
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// ✅ Verificar que el ID exista
	$statement = $pdo->prepare("SELECT * FROM tbl_top_category WHERE tcat_id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if($total == 0) {
		header('location: logout.php');
		exit;
	}
}
?>

<?php
// ✅ Inicializar arrays vacíos
$ecat_ids = array();
$p_ids = array();

// 🔹 Obtener todas las end categories asociadas al top category
$statement = $pdo->prepare("
	SELECT t3.ecat_id 
	FROM tbl_top_category t1
	JOIN tbl_mid_category t2 ON t1.tcat_id = t2.tcat_id
	JOIN tbl_end_category t3 ON t2.mcat_id = t3.mcat_id
	WHERE t1.tcat_id=?
");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);

foreach ($result as $row) {
	$ecat_ids[] = $row['ecat_id'];
}

// 🔹 Si hay end categories, buscar productos relacionados
if(!empty($ecat_ids)) {

	foreach($ecat_ids as $ecat_id) {
		$statement = $pdo->prepare("SELECT p_id, p_featured_photo FROM tbl_product WHERE ecat_id=?");
		$statement->execute(array($ecat_id));
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);

		foreach ($result as $row) {
			$p_ids[] = $row['p_id'];

			// 1️⃣ Eliminar foto principal si existe
			if(!empty($row['p_featured_photo']) && file_exists('../assets/uploads/'.$row['p_featured_photo'])) {
				unlink('../assets/uploads/'.$row['p_featured_photo']);
			}
		}
	}

	// 🔹 Si hay productos, eliminar todo lo relacionado
	if(!empty($p_ids)) {
		foreach($p_ids as $p_id) {

			// Eliminar fotos adicionales
			$statement = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=?");
			$statement->execute(array($p_id));
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach ($result as $row) {
				if(!empty($row['photo']) && file_exists('../assets/uploads/product_photos/'.$row['photo'])) {
					unlink('../assets/uploads/product_photos/'.$row['photo']);
				}
			}

			// Eliminar registros relacionados
			$tables = ['tbl_product_photo','tbl_product_size','tbl_product_color','tbl_rating','tbl_product'];
			foreach($tables as $table){
				$statement = $pdo->prepare("DELETE FROM $table WHERE p_id=?");
				$statement->execute(array($p_id));
			}

			// Eliminar pagos y órdenes
			$statement = $pdo->prepare("SELECT payment_id FROM tbl_order WHERE product_id=?");
			$statement->execute(array($p_id));
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach ($result as $row) {
				$statement1 = $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id=?");
				$statement1->execute(array($row['payment_id']));
			}

			$statement = $pdo->prepare("DELETE FROM tbl_order WHERE product_id=?");
			$statement->execute(array($p_id));
		}
	}

	// Eliminar todas las end categories
	foreach($ecat_ids as $ecat_id) {
		$statement = $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id=?");
		$statement->execute(array($ecat_id));
	}
}

// 🔹 Eliminar mid categories asociadas al top category
$statement = $pdo->prepare("DELETE FROM tbl_mid_category WHERE tcat_id=?");
$statement->execute(array($_REQUEST['id']));

// 🔹 Eliminar el top category
$statement = $pdo->prepare("DELETE FROM tbl_top_category WHERE tcat_id=?");
$statement->execute(array($_REQUEST['id']));

// ✅ Redirigir al listado
header('location: top-category.php');
exit;
?>
