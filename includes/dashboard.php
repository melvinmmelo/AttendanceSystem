<?php
// pages/dashboard.php

// Handle AJAX request for chart data
if (isset($_GET['action']) && $_GET['action'] === 'get_checkin_trend') {
    $filter_event_id = (int)($_GET['event_id'] ?? 0);

    $sql = "SELECT HOUR(scanned_at) as hour, COUNT(*) as checkins
            FROM scan_logs
            WHERE scan_result IN ('checked_in', 'manual_lookup_checked_in')
              AND scanned_at >= NOW() - INTERVAL 24 HOUR";
    $params = [];

    if ($filter_event_id) {
        $sql .= " AND event_id = ?";
        $params[] = $filter_event_id;
    }

    $sql .= " GROUP BY HOUR(scanned_at) ORDER BY HOUR(scanned_at) ASC";
    $results = db_query($sql, $params);

    $data = array_fill(0, 24, 0); // Initialize 24 hours with 0 check-ins
    foreach ($results as $row) {
        $data[(int)$row['hour']] = (int)$row['checkins'];
    }

    send_json_response(['data' => $data]);
}

// Get event_id from URL to filter the stats
$filter_event_id = (int)($_GET['event_id'] ?? 0);
$events = db_query("SELECT id, name FROM events ORDER BY event_date DESC");

// --- Fetch Statistics ---

// Base queries
$total_events_sql = "SELECT COUNT(*) FROM events";
$total_attendees_sql = "SELECT COUNT(*) FROM attendees";
$unique_checkins_sql = "SELECT COUNT(*) FROM attendees WHERE checkin_status = 'checked_in'";
$duplicate_scans_sql = "SELECT COUNT(*) FROM scan_logs WHERE scan_result IN ('already_checked_in', 'manual_lookup_duplicate')";

$params = [];
$filter_active = false;
if ($filter_event_id) {
    $params[] = $filter_event_id;
    $filter_active = true;
    $total_attendees_sql .= " WHERE event_id = ?";
    $unique_checkins_sql .= " AND event_id = ?";
    $duplicate_scans_sql .= " AND event_id = ?";
}

// Execute queries
$total_events = db_count($total_events_sql);
$total_attendees = db_count($total_attendees_sql, $filter_active ? $params : []);
$unique_checkins = db_count($unique_checkins_sql, $filter_active ? $params : []);
$duplicate_scans = db_count($duplicate_scans_sql, $filter_active ? $params : []);

$checkin_percentage = ($total_attendees > 0) ? round(($unique_checkins / $total_attendees) * 100) : 0;

?>

<div class="page-hero-row">
    <div class="page-hero">
        <h1>Dashboard</h1>
        <p>Overview of event statistics and attendance.</p>
    </div>
    <div class="filter-bar">
        <form method="GET" action="index.php">
            <input type="hidden" name="page" value="dashboard">
            <select name="event_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Events</option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?= $ev['id'] ?>" <?= $filter_event_id == $ev['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ev['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filter_event_id): ?>
                <a href="index.php?page=dashboard" class="btn btn-ghost btn-sm" title="Clear Filter"><i class="bi bi-x"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="grid-4">
    <div class="stat-card purple"><span class="stat-icon"><i class="bi bi-calendar-event"></i></span><div class="stat-value"><?= $total_events ?></div><div class="stat-label">Total Events</div></div>
    <div class="stat-card pink"><span class="stat-icon"><i class="bi bi-people"></i></span><div class="stat-value"><?= $total_attendees ?></div><div class="stat-label">Total Attendees<?= $filter_event_id ? ' (Filtered)' : '' ?></div></div>
    <div class="stat-card green"><span class="stat-icon"><i class="bi bi-check2-circle"></i></span><div class="stat-value"><?= $unique_checkins ?></div><div class="stat-label">Unique Check-ins<?= $filter_event_id ? ' (Filtered)' : '' ?></div><div class="stat-change up"><?= $checkin_percentage ?>%</div></div>
    <div class="stat-card orange"><span class="stat-icon"><i class="bi bi-exclamation-triangle"></i></span><div class="stat-value"><?= $duplicate_scans ?></div><div class="stat-label">Duplicate Scans<?= $filter_event_id ? ' (Filtered)' : '' ?></div></div>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="bi bi-graph-up"></i></span>
        <div class="card-title">Check-in Trend</div>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="checkin-chart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('checkin-chart')?.getContext('2d');
    if (!ctx) return;

    // Function to generate labels for the last 24 hours
    function generateTimeLabels() {
        const labels = [];
        const now = new Date();
        for (let i = 23; i >= 0; i--) {
            const date = new Date(now.getTime() - i * 60 * 60 * 1000);
            labels.push(date.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true }).toLowerCase());
        }
        return labels;
    }

    // Fetch data and render chart
    async function renderCheckinChart() {
        try {
            const eventId = <?= $filter_event_id ?>;
            const response = await fetch(`index.php?page=dashboard&action=get_checkin_trend&event_id=${eventId}`);
            const chartData = await response.json();

            // The backend returns data indexed by hour of the day. We need to reorder it to match our labels.
            const orderedData = [];
            const currentHour = new Date().getHours();
            for (let i = 23; i >= 0; i--) {
                const hour = (currentHour - i + 24) % 24;
                orderedData.push(chartData.data[hour] || 0);
            }

            const isLightMode = document.body.classList.contains('light-mode');
            const chartConfig = chartOptions(false); // from app.js
            chartConfig.data = {
                labels: generateTimeLabels(),
                datasets: [{ label: 'Check-ins', data: orderedData, borderColor: isLightMode ? '#6c63ff' : '#ffc107', backgroundColor: isLightMode ? 'rgba(108,99,255,.12)' : 'rgba(255, 193, 7, 0.1)', tension: .4, fill: true, pointBackgroundColor: isLightMode ? '#6c63ff' : '#ffc107', pointRadius: 4 }]
            };
            new Chart(ctx, chartConfig);
        } catch (e) {
            console.error("Failed to load chart data:", e);
            ctx.canvas.parentElement.innerHTML = '<p class="text-center text-muted">Could not load chart data.</p>';
        }
    }

    renderCheckinChart();
});
</script>