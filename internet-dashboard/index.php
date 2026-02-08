<?php
require __DIR__ . '/db.php';

$today = new DateTime('now');
$rangeStart = (new DateTime('now'))->modify('-30 days')->format('Y-m-d');
$dbNotice = null;

if ($pdo) {
    $stats = [
        'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'active_customers' => (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn(),
        'active_devices' => (int) $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'online'")->fetchColumn(),
        'open_tickets' => (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','pending')")->fetchColumn(),
        'monthly_revenue' => (float) $pdo->query("SELECT SUM(plans.monthly_price) FROM customers JOIN plans ON customers.plan_id = plans.id WHERE customers.status = 'active'")->fetchColumn(),
    ];

    $avgUsageStmt = $pdo->prepare("SELECT AVG(download_gb + upload_gb) FROM usage_logs WHERE usage_date >= :rangeStart");
    $avgUsageStmt->execute(['rangeStart' => $rangeStart]);
    $stats['avg_usage'] = (float) $avgUsageStmt->fetchColumn();

    $planUsageStmt = $pdo->prepare(
        "SELECT plans.name, SUM(usage_logs.download_gb + usage_logs.upload_gb) AS total_gb
         FROM usage_logs
         JOIN customers ON usage_logs.customer_id = customers.id
         JOIN plans ON customers.plan_id = plans.id
         WHERE usage_logs.usage_date >= :rangeStart
         GROUP BY plans.id
         ORDER BY total_gb DESC"
    );
    $planUsageStmt->execute(['rangeStart' => $rangeStart]);
    $planUsage = $planUsageStmt->fetchAll();

    $ticketStmt = $pdo->query(
        "SELECT tickets.subject, tickets.priority, tickets.status, tickets.opened_at, customers.full_name
         FROM tickets
         JOIN customers ON tickets.customer_id = customers.id
         ORDER BY tickets.opened_at DESC
         LIMIT 5"
    );
    $recentTickets = $ticketStmt->fetchAll();

    $outageStmt = $pdo->query(
        "SELECT region, started_at, eta_restore, status
         FROM outages
         ORDER BY started_at DESC
         LIMIT 3"
    );
    $outages = $outageStmt->fetchAll();

    $topCustomersStmt = $pdo->prepare(
        "SELECT customers.full_name, plans.name AS plan_name, SUM(usage_logs.download_gb + usage_logs.upload_gb) AS total_gb
         FROM usage_logs
         JOIN customers ON usage_logs.customer_id = customers.id
         JOIN plans ON customers.plan_id = plans.id
         WHERE usage_logs.usage_date >= :rangeStart
         GROUP BY customers.id
         ORDER BY total_gb DESC
         LIMIT 5"
    );
    $topCustomersStmt->execute(['rangeStart' => $rangeStart]);
    $topCustomers = $topCustomersStmt->fetchAll();
} else {
    $dbNotice = 'تعذر الاتصال بقاعدة البيانات، تم عرض بيانات تجريبية مؤقتاً.';

    $stats = [
        'customers' => 120,
        'active_customers' => 112,
        'active_devices' => 98,
        'open_tickets' => 7,
        'monthly_revenue' => 7420.50,
        'avg_usage' => 53.4,
    ];

    $planUsage = [
        ['name' => 'Starter 50', 'total_gb' => 430.2],
        ['name' => 'Home 150', 'total_gb' => 620.8],
        ['name' => 'Pro 300', 'total_gb' => 510.6],
        ['name' => 'Business 600', 'total_gb' => 720.9],
    ];

    $recentTickets = [
        ['full_name' => 'ليلى حمزة', 'subject' => 'تذبذب سرعة التحميل', 'priority' => 'high', 'status' => 'open'],
        ['full_name' => 'رامي حداد', 'subject' => 'طلب نقل راوتر', 'priority' => 'medium', 'status' => 'pending'],
        ['full_name' => 'سارة العمري', 'subject' => 'تحديث بيانات الفاتورة', 'priority' => 'low', 'status' => 'open'],
    ];

    $outages = [
        ['region' => 'عمّان - الشميساني', 'started_at' => '2024-03-30 06:20', 'eta_restore' => '2024-03-30 12:00', 'status' => 'monitoring'],
        ['region' => 'إربد - الجامعة', 'started_at' => '2024-03-29 21:15', 'eta_restore' => '2024-03-30 02:00', 'status' => 'resolved'],
    ];

    $topCustomers = [
        ['full_name' => 'منى خليل', 'plan_name' => 'Business 600', 'total_gb' => 312.8],
        ['full_name' => 'عمر الحسن', 'plan_name' => 'Pro 300', 'total_gb' => 286.4],
        ['full_name' => 'لينا منصور', 'plan_name' => 'Home 150', 'total_gb' => 260.7],
    ];
}

$maxPlanUsage = 0;
foreach ($planUsage as $plan) {
    $maxPlanUsage = max($maxPlanUsage, (float) $plan['total_gb']);
}

function formatCurrency(float $value): string
{
    return number_format($value, 2) . ' د.أ';
}

function formatNumber(float $value): string
{
    return number_format($value, 1);
}

function badgeClass(string $status): string
{
    return match ($status) {
        'resolved', 'online' => 'success',
        'pending', 'maintenance', 'monitoring', 'identified' => 'warning',
        default => 'danger',
    };
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم شبكة الانترنت</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="brand">مزود الخدمة | لوحة التحكم</div>
            <nav class="nav">
                <a class="active" href="#">الرئيسية</a>
                <a href="#">المشتركين</a>
                <a href="#">الأجهزة</a>
                <a href="#">الباقات</a>
                <a href="#">الدعم الفني</a>
                <a href="#">التقارير</a>
            </nav>
            <div class="support-card">
                <strong>حالة الشبكة</strong>
                <p>تم رصد <?php echo count($outages); ?> بلاغات عن انقطاع خلال 24 ساعة.</p>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <div>
                    <h1>لوحة إدارة شبكة الانترنت</h1>
                    <div class="meta">تاريخ اليوم: <?php echo $today->format('Y/m/d'); ?></div>
                </div>
                <div class="meta">آخر تحديث: <?php echo $today->format('H:i'); ?></div>
            </div>

            <?php if ($dbNotice) : ?>
                <div class="notice"><?php echo htmlspecialchars($dbNotice); ?></div>
            <?php endif; ?>

            <section class="cards">
                <div class="card">
                    <h3>عدد المشتركين</h3>
                    <div class="value"><?php echo $stats['customers']; ?></div>
                    <div class="trend">مُفعّل منهم <?php echo $stats['active_customers']; ?> مشترك</div>
                </div>
                <div class="card">
                    <h3>الأجهزة المتصلة</h3>
                    <div class="value"><?php echo $stats['active_devices']; ?></div>
                    <div class="trend">أجهزة نشطة الآن</div>
                </div>
                <div class="card">
                    <h3>الدخل الشهري المتوقع</h3>
                    <div class="value"><?php echo formatCurrency($stats['monthly_revenue']); ?></div>
                    <div class="trend">حسب الباقات الفعّالة</div>
                </div>
                <div class="card">
                    <h3>متوسط الاستهلاك الشهري</h3>
                    <div class="value"><?php echo formatNumber($stats['avg_usage']); ?> GB</div>
                    <div class="trend">آخر 30 يوم</div>
                </div>
                <div class="card">
                    <h3>تذاكر الدعم المفتوحة</h3>
                    <div class="value"><?php echo $stats['open_tickets']; ?></div>
                    <div class="trend">تذاكر تحتاج متابعة</div>
                </div>
            </section>

            <section class="grid">
                <div class="panel">
                    <h2>استهلاك الباقات (آخر 30 يوم)</h2>
                    <?php if (!$planUsage) : ?>
                        <p>لا توجد بيانات للاستهلاك.</p>
                    <?php else : ?>
                        <?php foreach ($planUsage as $plan) :
                            $percentage = $maxPlanUsage > 0 ? ($plan['total_gb'] / $maxPlanUsage) * 100 : 0;
                        ?>
                            <div class="bar">
                                <div class="label"><?php echo htmlspecialchars($plan['name']); ?></div>
                                <div class="track">
                                    <div class="fill" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <div class="value"><?php echo formatNumber((float) $plan['total_gb']); ?> GB</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>أكثر المشتركين استهلاكاً</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>المشترك</th>
                                <th>الباقة</th>
                                <th>الاستهلاك</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCustomers as $customer) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['plan_name']); ?></td>
                                    <td><?php echo formatNumber((float) $customer['total_gb']); ?> GB</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid">
                <div class="panel">
                    <h2>تذاكر الدعم الأخيرة</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>المشترك</th>
                                <th>الموضوع</th>
                                <th>الأولوية</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $ticket) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['priority']); ?></td>
                                    <td><span class="badge <?php echo badgeClass($ticket['status']); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="panel">
                    <h2>بلاغات الانقطاع</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>المنطقة</th>
                                <th>بداية البلاغ</th>
                                <th>العودة المتوقعة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outages as $outage) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($outage['region']); ?></td>
                                    <td><?php echo htmlspecialchars($outage['started_at']); ?></td>
                                    <td><?php echo htmlspecialchars($outage['eta_restore']); ?></td>
                                    <td><span class="badge <?php echo badgeClass($outage['status']); ?>"><?php echo htmlspecialchars($outage['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
