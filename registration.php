<?php require_once('header.php'); ?>

<?php
// Obtener banner de registro
$statement = $pdo->prepare("SELECT banner_registration FROM tbl_settings WHERE id=1");
$statement->execute();
$row = $statement->fetch(PDO::FETCH_ASSOC);
$banner_registration = $row['banner_registration'];
?>

<?php
if (isset($_POST['form1'])) {

    $valid = 1;
    $error_message = '';
    $success_message = '';

    // Validaciones
    if(empty($_POST['cust_name'])) { $valid = 0; $error_message .= LANG_VALUE_123."<br>"; }
    if(empty($_POST['cust_email'])) { $valid = 0; $error_message .= LANG_VALUE_131."<br>"; }
    elseif(!filter_var($_POST['cust_email'], FILTER_VALIDATE_EMAIL)) {
        $valid = 0; $error_message .= LANG_VALUE_134."<br>";
    } else {
        // Validar email único
        $statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email=?");
        $statement->execute([$_POST['cust_email']]);
        if($statement->rowCount()) {
            $valid = 0; 
            $error_message .= LANG_VALUE_147."<br>";
        }
    }

    if(empty($_POST['cust_phone'])) { $valid = 0; $error_message .= LANG_VALUE_124."<br>"; }
    if(empty($_POST['cust_address'])) { $valid = 0; $error_message .= LANG_VALUE_125."<br>"; }
    if(empty($_POST['cust_country'])) { $valid = 0; $error_message .= LANG_VALUE_126."<br>"; }
    if(empty($_POST['cust_city'])) { $valid = 0; $error_message .= LANG_VALUE_127."<br>"; }
    if(empty($_POST['cust_state'])) { $valid = 0; $error_message .= LANG_VALUE_128."<br>"; }
    if(empty($_POST['cust_zip'])) { $valid = 0; $error_message .= LANG_VALUE_129."<br>"; }

    if(empty($_POST['cust_password']) || empty($_POST['cust_re_password'])) {
        $valid = 0; $error_message .= LANG_VALUE_138."<br>";
    } elseif($_POST['cust_password'] != $_POST['cust_re_password']) {
        $valid = 0; $error_message .= LANG_VALUE_139."<br>";
    }

    // Si todo está OK, registrar cliente
    if($valid == 1) {

        $cust_datetime = date('Y-m-d H:i:s');
        $cust_timestamp = time();
        $token = md5(time()); // se queda por compatibilidad, pero NO se usa

        $statement = $pdo->prepare("
            INSERT INTO tbl_customer (
                cust_name, cust_cname, cust_email, cust_phone, cust_country,
                cust_address, cust_city, cust_state, cust_zip,
                cust_password, cust_token, cust_datetime, cust_timestamp, cust_status
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $statement->execute([
            strip_tags($_POST['cust_name']),
            strip_tags($_POST['cust_cname']),
            strip_tags($_POST['cust_email']),
            strip_tags($_POST['cust_phone']),
            strip_tags($_POST['cust_country']),
            strip_tags($_POST['cust_address']),
            strip_tags($_POST['cust_city']),
            strip_tags($_POST['cust_state']),
            strip_tags($_POST['cust_zip']),
            md5($_POST['cust_password']),
            $token,
            $cust_datetime,
            $cust_timestamp,
            1 // ACTIVAR CUENTA DIRECTO
        ]);

        // Mensaje sin verificación
        $success_message = "Registration successful! You can now log in.";

        // Limpiar POST
        foreach(['cust_name','cust_cname','cust_email','cust_phone','cust_address','cust_city','cust_state','cust_zip'] as $field) {
            unset($_POST[$field]);
        }
    }
}
?>

<div class="page-banner" style="background-color:#444;background-image: url(assets/uploads/<?php echo $banner_registration; ?>);">
    <div class="inner"><h1><?php echo LANG_VALUE_16; ?></h1></div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="user-content">
                    <form action="" method="post">
                        <?php $csrf->echoInputField(); ?>
                        <div class="row">
                            <div class="col-md-2"></div>
                            <div class="col-md-8">

                                <!-- Mensajes -->
                                <?php if(!empty($error_message)): ?>
                                    <div class="error" style="padding:10px;background:#f1f1f1;margin-bottom:20px;">
                                        <?php echo $error_message; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if(!empty($success_message)): ?>
                                    <div class="success" style="padding:10px;background:#f1f1f1;margin-bottom:20px;">
                                        <?php echo $success_message; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Formulario -->
                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_102; ?> *</label>
                                    <input type="text" class="form-control" name="cust_name" value="<?php echo $_POST['cust_name'] ?? ''; ?>">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_103; ?></label>
                                    <input type="text" class="form-control" name="cust_cname" value="<?php echo $_POST['cust_cname'] ?? ''; ?>">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_94; ?> *</label>
                                    <input type="email" class="form-control" name="cust_email" value="<?php echo $_POST['cust_email'] ?? ''; ?>">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_104; ?> *</label>
                                    <input type="text" class="form-control" name="cust_phone" value="<?php echo $_POST['cust_phone'] ?? ''; ?>">
                                </div>

                                <div class="col-md-12 form-group">
                                    <label><?php echo LANG_VALUE_105; ?> *</label>
                                    <textarea name="cust_address" class="form-control" style="height:70px;"><?php echo $_POST['cust_address'] ?? ''; ?></textarea>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_106; ?> *</label>
                                    <select name="cust_country" class="form-control select2">
                                        <option value="">Select country</option>
                                        <?php
                                        $statement = $pdo->prepare("SELECT * FROM tbl_country ORDER BY country_name ASC");
                                        $statement->execute();
                                        $countries = $statement->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($countries as $row) {
                                            $selected = ($_POST['cust_country'] ?? '') == $row['country_id'] ? 'selected' : '';
                                            echo "<option value='{$row['country_id']}' $selected>{$row['country_name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_107; ?> *</label>
                                    <input type="text" class="form-control" name="cust_city" value="<?php echo $_POST['cust_city'] ?? ''; ?>">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_108; ?> *</label>
                                    <input type="text" class="form-control" name="cust_state" value="<?php echo $_POST['cust_state'] ?? ''; ?>">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_109; ?> *</label>
                                    <input type="text" class="form-control" name="cust_zip" value="<?php echo $_POST['cust_zip'] ?? ''; ?>">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_96; ?> *</label>
                                    <input type="password" class="form-control" name="cust_password">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label><?php echo LANG_VALUE_98; ?> *</label>
                                    <input type="password" class="form-control" name="cust_re_password">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label></label>
                                    <input type="submit" class="btn btn-danger" value="<?php echo LANG_VALUE_15; ?>" name="form1">
                                </div>

                            </div>
                        </div>
                    </form>
                </div>                
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
