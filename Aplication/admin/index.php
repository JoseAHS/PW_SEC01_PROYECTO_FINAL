<?php
// dashboard.php limpio y optimizado – estilo WooCommerce con gráficos
require_once('header.php');

/* ---------------------------------------------------------
   SANITIZAR FECHAS
--------------------------------------------------------- */
function sanitize_date($d) {
    $d = trim($d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
}

$default_from = date('Y-m-d', strtotime('-11 months'));
$default_to   = date('Y-m-d');

$filter_from = sanitize_date($_GET['from'] ?? '') ?: $default_from;
$filter_to   = sanitize_date($_GET['to'] ?? '') ?: $default_to;


/* ---------------------------------------------------------
   CONSULTAS KPI PRINCIPALES
--------------------------------------------------------- */

$kpi = [];

// Productos
$kpi['products'] = $pdo->query("SELECT COUNT(*) FROM tbl_product")->fetchColumn();

// Total de órdenes
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tbl_payment
    WHERE STR_TO_DATE(payment_date,'%Y-%m-%d') BETWEEN ? AND ?
");
$stmt->execute([$filter_from, $filter_to]);
$kpi['orders_total'] = $stmt->fetchColumn();

// Revenue (solo completadas)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(paid_amount),0) FROM tbl_payment
    WHERE payment_status='Completed'
      AND STR_TO_DATE(payment_date,'%Y-%m-%d') BETWEEN ? AND ?
");
$stmt->execute([$filter_from, $filter_to]);
$kpi['revenue'] = $stmt->fetchColumn();

// Total customers
$kpi['customers_total'] = $pdo->query("SELECT COUNT(*) FROM tbl_customer")->fetchColumn();

// Órdenes pendientes
$kpi['orders_pending'] = $pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE payment_status='Pending'")->fetchColumn();

// Órdenes completadas
$kpi['orders_completed'] = $pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE payment_status='Completed'")->fetchColumn();


/* ---------------------------------------------------------
   RANGO DE MESES PARA GRÁFICAS
--------------------------------------------------------- */
function build_month_range($from, $to) {
    $start = new DateTime($from); $start->modify('first day of this month');
    $end   = new DateTime($to);   $end->modify('first day of this month');

    $period = new DatePeriod($start, new DateInterval('P1M'), (clone $end)->modify('+1 month'));

    $months = [];
    $labels = [];

    foreach ($period as $dt) {
        $months[] = $dt->format('Y-m');
        $labels[] = $dt->format('M Y');
    }
    return [$months, $labels];
}

list($months_list, $months_labels) = build_month_range($filter_from, $filter_to);


/* ---------------------------------------------------------
   ÓRDENES MENSUALES
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(STR_TO_DATE(payment_date,'%Y-%m-%d'),'%Y-%m') AS ym,
           SUM(CASE WHEN payment_status='Completed' THEN 1 END) AS completed_count,
           SUM(CASE WHEN payment_status='Pending' THEN 1 END) AS pending_count,
           SUM(CASE WHEN payment_status='Completed' THEN paid_amount END) AS revenue
    FROM tbl_payment
    WHERE STR_TO_DATE(payment_date,'%Y-%m-%d') BETWEEN ? AND ?
    GROUP BY ym
    ORDER BY ym
");
$stmt->execute([$filter_from, $filter_to]);

$monthly_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthly_map = [];
foreach ($monthly_raw as $r) $monthly_map[$r['ym']] = $r;

$series_completed = [];
$series_pending   = [];
$series_revenue   = [];

foreach ($months_list as $m) {
    $series_completed[] = (int)($monthly_map[$m]['completed_count'] ?? 0);
    $series_pending[]   = (int)($monthly_map[$m]['pending_count'] ?? 0);
    $series_revenue[]   = (float)($monthly_map[$m]['revenue'] ?? 0);
}


/* ---------------------------------------------------------
   TOP PRODUCTS
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT o.product_name,
           SUM(o.quantity) AS qty_sold
    FROM tbl_order o
    JOIN tbl_payment p ON o.payment_id=p.payment_id
    WHERE p.payment_status='Completed'
      AND STR_TO_DATE(p.payment_date,'%Y-%m-%d') BETWEEN ? AND ?
    GROUP BY o.product_name
    ORDER BY qty_sold DESC
    LIMIT 10
");
$stmt->execute([$filter_from, $filter_to]);
$top = $stmt->fetchAll(PDO::FETCH_ASSOC);

$top_labels = array_column($top, 'product_name');
$top_qtys   = array_map('intval', array_column($top, 'qty_sold'));


/* ---------------------------------------------------------
   NEW CUSTOMERS MES A MES
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(STR_TO_DATE(cust_datetime,'%Y-%m-%d'),'%Y-%m') AS ym,
           COUNT(*) AS cnt
    FROM tbl_customer
    WHERE STR_TO_DATE(cust_datetime,'%Y-%m-%d') BETWEEN ? AND ?
    GROUP BY ym
    ORDER BY ym
");
$stmt->execute([$filter_from, $filter_to]);

$nc_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$nc_map = [];
foreach ($nc_raw as $r) $nc_map[$r['ym']] = $r['cnt'];

$newcust_counts = [];
foreach ($months_list as $m) {
    $newcust_counts[] = (int)($nc_map[$m] ?? 0);
}


/* ---------------------------------------------------------
   RECENT ORDERS
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM tbl_payment
    WHERE STR_TO_DATE(payment_date,'%Y-%m-%d') BETWEEN ? AND ?
    ORDER BY STR_TO_DATE(payment_date,'%Y-%m-%d') DESC
    LIMIT 50
");
$stmt->execute([$filter_from, $filter_to]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_orders = [];
$itemStmt = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");

foreach ($recent_payments as $p) {
    $itemStmt->execute([$p['payment_id']]);
    $recent_orders[] = [
        'payment' => $p,
        'items'   => $itemStmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}


/* ---------------------------------------------------------
   HTML UI
--------------------------------------------------------- */
?>

<section class="content-header">
    <h1>Dashboard</h1>
</section>

<section class="content">

<!-- KPI -->
<div class="row">

    <div class="col-lg-3 col-md-6">
        <div class="info-box bg-maroon">
            <span class="info-box-icon"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Products</span>
                <span class="info-box-number"><?= $kpi['products'] ?></span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="info-box bg-olive">
            <span class="info-box-icon"><i class="fa fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Orders (range)</span>
                <span class="info-box-number"><?= $kpi['orders_total'] ?></span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="info-box bg-blue">
            <span class="info-box-icon"><i class="fa fa-dollar"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Revenue</span>
                <span class="info-box-number">$<?= number_format($kpi['revenue'],2) ?></span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="info-box bg-purple">
            <span class="info-box-icon"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Customers</span>
                <span class="info-box-number"><?= $kpi['customers_total'] ?></span>
            </div>
        </div>
    </div>

</div>

<!-- CHARTS -->
<div class="row">

    <div class="col-lg-6">
        <div class="box box-solid bg-black">
            <div class="box-header with-border">
                <h3 class="box-title text-light">📦 Orders: Completed vs Pending</h3>
            </div>
            <div class="box-body">
                <canvas id="ordersCmpChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="box box-solid bg-black">
            <div class="box-header with-border">
                <h3 class="box-title text-light">💰 Revenue Trend</h3>
            </div>
            <div class="box-body">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>

</div>


<div class="row">

    <div class="col-lg-6">
        <div class="box box-solid bg-black">
            <div class="box-header with-border">
                <h3 class="box-title text-light">🏆 Top Products</h3>
            </div>
            <div class="box-body">
                <canvas id="topProducts"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="box box-solid bg-black">
            <div class="box-header with-border">
                <h3 class="box-title text-light">👥 New Customers</h3>
            </div>
            <div class="box-body">
                <canvas id="newCustomers"></canvas>
            </div>
        </div>
    </div>

</div>


<!-- Recent Orders -->
<div class="box box-solid bg-black">
    <div class="box-header with-border">
        <h3 class="box-title text-light">🧾 Recent Orders</h3>
    </div>

    <div class="box-body table-responsive">
        
        <style>
            /* Encabezados */
            #ordersTable th {
                background: #222 !important;
                color: #fff !important;
                font-weight: bold;
                text-transform: uppercase;
                padding: 12px;
            }

            /* Filas */
            #ordersTable td {
                background: #2d2d2d !important;
                color: #eaeaea !important;
                padding: 10px 15px !important;
                vertical-align: middle !important;
            }

            /* Hover */
            #ordersTable tbody tr:hover td {
                background: #3a3a3a !important;
            }

            /* Celda de items */
            .items-list {
                display: block;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .items-list li {
                padding: 3px 0;
                border-bottom: 1px solid #444;
            }

            .items-list li:last-child {
                border-bottom: none;
            }
        </style>

        <table id="ordersTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Quantity</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($recent_orders as $ro): ?>
                    <?php $p = $ro['payment']; ?>
                    <tr>
                        <td><?= htmlspecialchars($p['payment_id']) ?></td>
                        <td><?= htmlspecialchars($p['customer_name']) ?></td>
                        <td><?= htmlspecialchars($p['payment_date']) ?></td>
                        <td>$<?= number_format($p['paid_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($p['payment_status']) ?></td>

                        <td>
                            <ul class="items-list">
                                <?php foreach ($ro['items'] as $item): ?>
                                    <li>
                                        <?= htmlspecialchars($item['product_name']) ?>  
                                        <td><?= intval($item['quantity']) ?></td>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>


</section>

<?php require_once('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const monthsLabels = <?= json_encode($months_labels) ?>;
    const completed    = <?= json_encode($series_completed) ?>;
    const pending      = <?= json_encode($series_pending) ?>;
    const revenue      = <?= json_encode($series_revenue) ?>;
    const topLabels    = <?= json_encode($top_labels) ?>;
    const topQtys      = <?= json_encode($top_qtys) ?>;
    const newCust      = <?= json_encode($newcust_counts) ?>;

    /* Orders Comparison */
    new Chart(document.getElementById('ordersCmpChart'), {
        type: 'bar',
        data: {
            labels: monthsLabels,
            datasets: [
                { label: 'Completed', data: completed, backgroundColor: '#28a745' },
                { label: 'Pending', data: pending, backgroundColor: '#ffc107' }
            ]
        }
    });

    /* Revenue Trend */
    new Chart(document.getElementById('revenueTrendChart'), {
        type: 'line',
        data: {
            labels: monthsLabels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: '#36a2eb',
                    backgroundColor: 'rgba(54,162,235,0.2)',
                    fill: true
                }
            ]
        }
    });

    /* Top Products */
    new Chart(document.getElementById('topProducts'), {
        type: 'bar',
        data: {
            labels: topLabels,
            datasets: [
                { label: 'Units Sold', data: topQtys, backgroundColor: '#ff9f40' }
            ]
        },
        options: { indexAxis: 'y' }
    });

    /* New Customers */
    new Chart(document.getElementById('newCustomers'), {
        type: 'line',
        data: {
            labels: monthsLabels,
            datasets: [
                {
                    label: 'New Customers',
                    data: newCust,
                    borderColor: '#9966ff',
                    backgroundColor: 'rgba(153,102,255,0.2)',
                    fill: true
                }
            ]
        }
    });

});
</script>
