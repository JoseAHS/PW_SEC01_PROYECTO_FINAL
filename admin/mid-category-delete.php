<?php require_once('header.php'); ?>

<?php
// 🚫 Evitar acceso directo a esta página
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// ✅ Verificar que el ID exista en la base de datos
	$statement = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE mcat_id=?");
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

// 🔹 Obtener todos los end categories asociados al mid category
$statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);

foreach ($result as $row) {
	$ecat_ids[] = $row['ecat_id'];
}

// 🔹 Si hay end categories, buscar productos relacionados
if(!empty($ecat_ids)) {

	// Obtener los IDs de productos vinculados a esas categorías finales
	foreach($ecat_ids as $ecat_id) {
		$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE ecat_id=?");
		$statement->execute(array($ecat_id));
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($result as $row) {
			$p_ids[] = $row['p_id'];
		}
	}

	// 🔹 Si existen productos, eliminar todo lo relacionado
	if(!empty($p_ids)) {

		foreach($p_ids as $p_id) {

			// 1️⃣ Eliminar foto principal del producto
			$statement = $pdo->prepare("SELECT p_featured_photo FROM tbl_product WHERE p_id=?");
			$statement->execute(array($p_id));
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach ($result as $row) {
				if(!empty($row['p_featured_photo']) && file_exists('../assets/uploads/'.$row['p_featured_photo'])) {
					unlink('../assets/uploads/'.$row['p_featured_photo']);
				}
			}

			// 2️⃣ Eliminar fotos adicionales
			$statement = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=?");
			$statement->execute(array($p_id));
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach ($result as $row) {
				if(!empty($row['photo']) && file_exists('../assets/uploads/product_photos/'.$row['photo'])) {
					unlink('../assets/uploads/product_photos/'.$row['photo']);
				}
			}

			// 3️⃣ Eliminar registros de tablas relacionadas
			$statement = $pdo->prepare("DELETE FROM tbl_product_photo WHERE p_id=?");
			$statement->execute(array($p_id));

			$statement = $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?");
			$statement->execute(array($p_id));

			$statement = $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?");
			$statement->execute(array($p_id));

			$statement = $pdo->prepare("DELETE FROM tbl_rating WHERE p_id=?");
			$statement->execute(array($p_id));

			// 4️⃣ Eliminar pagos asociados al pedido del producto
			$statement = $pdo->prepare("SELECT payment_id FROM tbl_order WHERE product_id=?");
			$statement->execute(array($p_id));
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach ($result as $row) {
				$statement1 = $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id=?");
				$statement1->execute(array($row['payment_id']));
			}

			// 5️⃣ Eliminar pedidos
			$statement = $pdo->prepare("DELETE FROM tbl_order WHERE product_id=?");
			$statement->execute(array($p_id));

			// 6️⃣ Finalmente, eliminar el producto
			$statement = $pdo->prepare("DELETE FROM tbl_product WHERE p_id=?");
			$statement->execute(array($p_id));
		}
	}

	// 🔹 Eliminar todas las categorías finales asociadas
	foreach($ecat_ids as $ecat_id) {
		$statement = $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id=?");
		$statement->execute(array($ecat_id));
	}
}

// 🔹 Eliminar la categoría intermedia (mid-category)
$statement = $pdo->prepare("DELETE FROM tbl_mid_category WHERE mcat_id=?");
$statement->execute(array($_REQUEST['id']));

// ✅ Redirigir al listado
header('location: mid-category.php');
exit;
?>
