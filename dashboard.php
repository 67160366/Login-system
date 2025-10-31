<?php
// dashboard.php - Protected Sales Dashboard
require __DIR__ . '/config_mysqli.php';

// ตรวจสอบการ login
if (!isset($_SESSION['user_id'])) {
  $_SESSION['flash'] = 'Please login to access the dashboard.';
  header('Location: login.php');
  exit;
}

// ฟังก์ชันช่วยดึงข้อมูล
function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) { return []; }
  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}

// เตรียมข้อมูลสำหรับกราฟต่าง ๆ
try {
  $monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
  $category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
  $region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
  $topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
  $payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
  $hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
  $newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
  $kpis = fetch_all($mysqli, "
    SELECT
      (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
      (SELECT SUM(quantity)   FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
      (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
  ");
  $kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];
} catch (Throwable $e) {
  http_response_code(500);
  die('Error loading dashboard data: ' . htmlspecialchars($e->getMessage()));
}

// Helper for number format
function nf($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sales Dashboard - Retail DW</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    body { 
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      color: #e2e8f0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .navbar {
      background: rgba(17, 24, 39, 0.95) !important;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .navbar-brand {
      font-weight: 700;
      font-size: 1.5rem;
      background: linear-gradient(45deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .user-info {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
    }
    .card { 
      background: rgba(17, 24, 39, 0.8);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 1rem;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
    }
    .card h5 { 
      color: #e5e7eb;
      font-weight: 600;
      margin-bottom: 1rem;
    }
    .kpi-card {
      position: relative;
      overflow: hidden;
    }
    .kpi-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }
    .kpi { 
      font-size: 2rem;
      font-weight: 700;
      background: linear-gradient(45deg, #93c5fd, #c7d2fe);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .sub { 
      color: #93c5fd;
      font-size: .85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .grid { 
      display: grid;
      gap: 1.5rem;
      grid-template-columns: repeat(12, 1fr);
    }
    .col-12 { grid-column: span 12; }
    .col-6 { grid-column: span 6; }
    .col-4 { grid-column: span 4; }
    .col-8 { grid-column: span 8; }
    @media (max-width: 991px) {
      .col-6, .col-4, .col-8 { grid-column: span 12; }
    }
    canvas { max-height: 360px; }
    .btn-logout {
      background: rgba(239, 68, 68, 0.2);
      border: 1px solid rgba(239, 68, 68, 0.4);
      color: #fca5a5;
      transition: all 0.3s ease;
    }
    .btn-logout:hover {
      background: rgba(239, 68, 68, 0.3);
      border-color: rgba(239, 68, 68, 0.6);
      color: #fef2f2;
    }
    .page-header {
      background: rgba(17, 24, 39, 0.6);
      backdrop-filter: blur(10px);
      border-radius: 1rem;
      padding: 1.5rem;
      margin-bottom: 2rem;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .page-title {
      font-size: 2rem;
      font-weight: 700;
      margin: 0;
      background: linear-gradient(45deg, #93c5fd, #c7d2fe);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-dark sticky-top">
    <div class="container-fluid px-3 px-md-4">
      <span class="navbar-brand">📊 Retail Analytics</span>
      <div class="user-info">
        <div class="user-avatar">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="d-none d-md-block">
          <div style="font-size: 0.9rem; font-weight: 600;">
            <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
          </div>
          <div style="font-size: 0.75rem; color: #93c5fd;">
            <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
          </div>
        </div>
        <a href="logout.php" class="btn btn-logout btn-sm ms-2">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container-fluid px-3 px-md-4 py-4">
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h1 class="page-title">Sales Dashboard</h1>
          <p class="mb-0 text-muted">Real-time analytics and insights</p>
        </div>
        <div class="text-end">
          <div class="sub mb-1">Data Source</div>
          <div style="color: #e5e7eb;">MySQL Database (retail_dw)</div>
        </div>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid mb-4">
      <div class="card kpi-card p-4 col-4">
        <div class="sub">ยอดขาย 30 วัน</div>
        <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
        <div class="text-muted small mt-2">Total Revenue</div>
      </div>
      <div class="card kpi-card p-4 col-4">
        <div class="sub">จำนวนชิ้นขาย 30 วัน</div>
        <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?></div>
        <div class="text-muted small mt-2">Units Sold</div>
      </div>
      <div class="card kpi-card p-4 col-4">
        <div class="sub">จำนวนผู้ซื้อ 30 วัน</div>
        <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?></div>
        <div class="text-muted small mt-2">Unique Customers</div>
      </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid">
      <div class="card p-4 col-8">
        <h5>📈 ยอดขายรายเดือน (2 ปี)</h5>
        <canvas id="chartMonthly"></canvas>
      </div>

      <div class="card p-4 col-4">
        <h5>🍰 สัดส่วนยอดขายตามหมวด</h5>
        <canvas id="chartCategory"></canvas>
      </div>

      <div class="card p-4 col-6">
        <h5>🏆 Top 10 สินค้าขายดี</h5>
        <canvas id="chartTopProducts"></canvas>
      </div>

      <div class="card p-4 col-6">
        <h5>🗺️ ยอดขายตามภูมิภาค</h5>
        <canvas id="chartRegion"></canvas>
      </div>

      <div class="card p-4 col-6">
        <h5>💳 วิธีการชำระเงิน</h5>
        <canvas id="chartPayment"></canvas>
      </div>

      <div class="card p-4 col-6">
        <h5>⏰ ยอดขายรายชั่วโมง</h5>
        <canvas id="chartHourly"></canvas>
      </div>

      <div class="card p-4 col-12">
        <h5>👥 ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5>
        <canvas id="chartNewReturning"></canvas>
      </div>
    </div>
  </div>

<script>
// เตรียมข้อมูลจาก PHP -> JS
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// Default chart options
Chart.defaults.color = '#e5e7eb';
Chart.defaults.borderColor = 'rgba(255,255,255,0.1)';
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

// Utility: pick labels & values
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

// Color palettes
const gradientColors = [
  'rgba(102, 126, 234, 0.8)',
  'rgba(118, 75, 162, 0.8)',
  'rgba(147, 197, 253, 0.8)',
  'rgba(199, 210, 254, 0.8)',
  'rgba(167, 139, 250, 0.8)',
  'rgba(196, 181, 253, 0.8)'
];

// Monthly Sales Chart
(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  const ctx = document.getElementById('chartMonthly');
  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(102, 126, 234, 0.6)');
  gradient.addColorStop(1, 'rgba(102, 126, 234, 0.05)');
  
  new Chart(ctx, {
    type: 'line',
    data: { 
      labels, 
      datasets: [{ 
        label: 'ยอดขาย (฿)', 
        data: values, 
        tension: 0.4,
        fill: true,
        backgroundColor: gradient,
        borderColor: 'rgba(102, 126, 234, 1)',
        borderWidth: 3,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(102, 126, 234, 1)',
        pointHoverBorderWidth: 2
      }] 
    },
    options: { 
      responsive: true,
      maintainAspectRatio: true,
      plugins: { 
        legend: { 
          labels: { color: '#e5e7eb', font: { size: 13, weight: '600' } } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(102, 126, 234, 0.5)',
          borderWidth: 1
        }
      }, 
      scales: {
        x: { 
          ticks: { color: '#c7d2fe', maxTicksLimit: 12 },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        },
        y: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        }
      }
    }
  });
})();

// Category Doughnut Chart
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: { 
      labels, 
      datasets: [{ 
        data: values,
        backgroundColor: gradientColors,
        borderWidth: 2,
        borderColor: 'rgba(17, 24, 39, 0.8)'
      }] 
    },
    options: { 
      responsive: true,
      plugins: { 
        legend: { 
          position: 'bottom',
          labels: { 
            color: '#e5e7eb',
            padding: 15,
            font: { size: 12 }
          } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(102, 126, 234, 0.5)',
          borderWidth: 1
        }
      }
    }
  });
})();

// Top Products Bar Chart
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: [{ 
        label: 'ชิ้นที่ขาย', 
        data: qty,
        backgroundColor: 'rgba(147, 197, 253, 0.8)',
        borderColor: 'rgba(147, 197, 253, 1)',
        borderWidth: 1,
        borderRadius: 6
      }] 
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { 
        legend: { 
          labels: { color: '#e5e7eb', font: { size: 13, weight: '600' } } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(147, 197, 253, 0.5)',
          borderWidth: 1
        }
      },
      scales: {
        x: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        },
        y: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        }
      }
    }
  });
})();

// Region Bar Chart
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: [{ 
        label: 'ยอดขาย (฿)', 
        data: values,
        backgroundColor: 'rgba(199, 210, 254, 0.8)',
        borderColor: 'rgba(199, 210, 254, 1)',
        borderWidth: 1,
        borderRadius: 6
      }] 
    },
    options: { 
      responsive: true,
      plugins: { 
        legend: { 
          labels: { color: '#e5e7eb', font: { size: 13, weight: '600' } } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(199, 210, 254, 0.5)',
          borderWidth: 1
        }
      }, 
      scales: {
        x: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        },
        y: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        }
      }
    }
  });
})();

// Payment Pie Chart
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(document.getElementById('chartPayment'), {
    type: 'pie',
    data: { 
      labels, 
      datasets: [{ 
        data: values,
        backgroundColor: gradientColors,
        borderWidth: 2,
        borderColor: 'rgba(17, 24, 39, 0.8)'
      }] 
    },
    options: { 
      responsive: true,
      plugins: { 
        legend: { 
          position: 'bottom',
          labels: { 
            color: '#e5e7eb',
            padding: 15,
            font: { size: 12 }
          } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(102, 126, 234, 0.5)',
          borderWidth: 1
        }
      }
    }
  });
})();

// Hourly Sales Bar Chart
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(document.getElementById('chartHourly'), {
    type: 'bar',
    data: { 
      labels: labels.map(h => h + ':00'), 
      datasets: [{ 
        label: 'ยอดขาย (฿)', 
        data: values,
        backgroundColor: 'rgba(167, 139, 250, 0.8)',
        borderColor: 'rgba(167, 139, 250, 1)',
        borderWidth: 1,
        borderRadius: 6
      }] 
    },
    options: { 
      responsive: true,
      plugins: { 
        legend: { 
          labels: { color: '#e5e7eb', font: { size: 13, weight: '600' } } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(167, 139, 250, 0.5)',
          borderWidth: 1
        }
      }, 
      scales: {
        x: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        },
        y: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        }
      }
    }
  });
})();

// New vs Returning Customers Line Chart
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
  const retC = newReturning.map(o => parseFloat(o.returning_sales));
  new Chart(document.getElementById('chartNewReturning'), {
    type: 'line',
    data: { 
      labels,
      datasets: [
        { 
          label: 'ลูกค้าใหม่ (฿)', 
          data: newC, 
          tension: 0.4,
          fill: false,
          borderColor: 'rgba(167, 139, 250, 1)',
          backgroundColor: 'rgba(167, 139, 250, 0.1)',
          borderWidth: 3,
          pointRadius: 0,
          pointHoverRadius: 5
        },
        { 
          label: 'ลูกค้าเดิม (฿)', 
          data: retC, 
          tension: 0.4,
          fill: false,
          borderColor: 'rgba(147, 197, 253, 1)',
          backgroundColor: 'rgba(147, 197, 253, 0.1)',
          borderWidth: 3,
          pointRadius: 0,
          pointHoverRadius: 5
        }
      ]
    },
    options: { 
      responsive: true,
      plugins: { 
        legend: { 
          labels: { color: '#e5e7eb', font: { size: 13, weight: '600' } } 
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          padding: 12,
          borderColor: 'rgba(102, 126, 234, 0.5)',
          borderWidth: 1
        }
      }, 
      scales: {
        x: { 
          ticks: { color: '#c7d2fe', maxTicksLimit: 15 },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        },
        y: { 
          ticks: { color: '#c7d2fe' },
          grid: { color: 'rgba(255,255,255,0.05)' } 
        }
      }
    }
  });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>