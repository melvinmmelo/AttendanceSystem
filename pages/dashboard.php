<?php
// pages/dashboard.php
$db = Database::getInstance();

// ---- Auto-update event statuses ----
// This logic runs on dashboard load to ensure all stats are based on current event statuses.
// 1. Set 'upcoming' events to 'active' if their start date and time have passed.
db_execute("UPDATE events SET status = 'active' WHERE status = 'upcoming' AND CONCAT(event_date, ' ', COALESCE(event_time, '00:00:00')) <= NOW()");
// 2. Set 'active' events to 'completed' if their date has passed (i.e., it's the next day).
db_execute("UPDATE events SET status = 'completed' WHERE status = 'active' AND event_date < CURDATE()");

// Stats
$total_reg     = db_count("SELECT COUNT(*) FROM attendees");
$total_checkin = db_count("SELECT COUNT(*) FROM attendees WHERE checkin_status='checked_in'");
$total_pending = db_count("SELECT COUNT(*) FROM attendees WHERE checkin_status='pending'");
$total_events  = db_count("SELECT COUNT(*) FROM events WHERE status IN('active','upcoming')");

$rate = $total_reg > 0 ? round($total_checkin / $total_reg * 100, 1) : 0;

// Active events
$active_events = db_query("SELECT e.*, 
    COUNT(a.id) as registrations,
    SUM(a.checkin_status='checked_in') as checked_in
  FROM events e
  LEFT JOIN attendees a ON a.event_id = e.id
  WHERE e.status IN('active','upcoming')
  GROUP BY e.id
  ORDER BY e.event_date ASC LIMIT 4");

// Recent check-ins
$recent = db_query("SELECT a.*, e.name as event_name FROM attendees a
  JOIN events e ON e.id = a.event_id
  WHERE a.checkin_status = 'checked_in'
  ORDER BY a.checkin_at DESC LIMIT 8");
?>

<div class="flex items-center gap-3" style="margin-bottom:12px;">
  <span class="live-dot"></span>
  <span class="text-sm text-muted">Live &bull; Auto-refreshes every 10s</span>
</div>

<div class="page-hero">
  <h1>Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?> <span style="font-size: 1.5rem;">👋</span></h1>
  <p>Here's what's happening with your events today &mdash; <?= date('l, F j, Y') ?></p>
</div>

<!-- Stat Cards -->
<div class="grid-4">
  <div class="stat-card purple">
    <span class="stat-icon"><i class="bi bi-people"></i></span>
    <div class="stat-value"><?= number_format($total_reg) ?></div>
    <div class="stat-label">Total Registrations</div>
    <div class="stat-change up">↑ Across all events</div>
  </div>
  <div class="stat-card green">
    <span class="stat-icon"><i class="bi bi-check2-all"></i></span>
    <div class="stat-value"><?= number_format($total_checkin) ?></div>
    <div class="stat-label">Checked In</div>
    <div class="stat-change up">↑ <?= $rate ?>% attendance rate</div>
  </div>
  <div class="stat-card orange">
    <span class="stat-icon"><i class="bi bi-hourglass"></i></span>
    <div class="stat-value"><?= number_format($total_pending) ?></div>
    <div class="stat-label">Pending Check-in</div>
    <div class="stat-change neutral"><?= $total_reg > 0 ? round($total_pending/$total_reg*100,1) : 0 ?>% of registrations</div>
  </div>
  <div class="stat-card pink">
    <span class="stat-icon"><i class="bi bi-calendar3"></i></span>
    <div class="stat-value"><?= $total_events ?></div>
    <div class="stat-label">Active Events</div>
    <div class="stat-change neutral">Upcoming &amp; active</div>
  </div>
</div>

<div class="grid-2">
  <!-- Check-in Chart -->
  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-graph-up"></i></span>
      <div class="card-title">Check-in Trend (Today)</div>
      <span class="badge badge-info">Live</span>
    </div>
    <div class="card-body">
      <div class="chart-container">
        <canvas id="checkin-chart"></canvas>
      </div>
    </div>
  </div>

  <!-- Active Events -->
  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-calendar-event"></i></span>
      <div class="card-title">Active Events</div>
      <a href="index.php?page=events" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div style="padding:8px;">
      <?php if (empty($active_events)): ?>
        <div class="empty-state" style="padding:30px 20px;">
          <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
          <div class="empty-text">No active events.</div>
        </div>
      <?php else: ?>
        <?php foreach ($active_events as $ev):
          $ev_rate = $ev['registrations'] > 0 ? round($ev['checked_in']/$ev['registrations']*100) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:var(--radius-sm);transition:background .2s;cursor:pointer;"
             onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'">
          <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;"><i class="bi bi-calendar-check"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.875rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ev['name']) ?></div>
            <div style="font-size:.73rem;color:var(--text3);"><?= date('M j, Y', strtotime($ev['event_date'])) ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <span class="badge <?= $ev['status']==='active'?'badge-success':'badge-info' ?>"><?= ucfirst($ev['status']) ?></span>
            <div style="font-size:.72rem;color:var(--text3);margin-top:4px;"><?= $ev['checked_in'] ?>/<?= $ev['registrations'] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Check-ins -->
<div class="card">
  <div class="card-header">
    <span><i class="bi bi-lightning-charge"></i></span>
    <div class="card-title">Recent Check-ins</div>
    <a href="index.php?page=attendees" class="btn btn-ghost btn-sm"><i class="bi bi-eye"></i> View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Attendee</th>
          <th>Event</th>
          <th>Check-in Time</th>
          <th>QR Code</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No check-ins yet.</td></tr>
        <?php else: ?>
          <?php foreach ($recent as $a):
            $initials = implode('', array_map(function($w){return strtoupper($w[0]);}, array_slice(explode(' ',$a['full_name']),0,2)));
          ?>
          <tr>
            <td data-label="Attendee">
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar" style="background:linear-gradient(135deg,var(--accent),var(--accent2));font-size:.7rem;"><?= $initials ?></div>
                <div>
                  <div style="font-weight:500;color:var(--text);font-size:.875rem;"><?= htmlspecialchars($a['full_name']) ?></div>
                  <div style="font-size:.73rem;color:var(--text3);"><?= htmlspecialchars($a['email']) ?></div>
                </div>
              </div>
            </td>
            <td data-label="Event"><?= htmlspecialchars($a['event_name']) ?></td>
            <td data-label="Check-in Time"><?= $a['checkin_at'] ? date('M j, g:i A', strtotime($a['checkin_at'])) : '—' ?></td>
            <td data-label="QR Code"><code><?= htmlspecialchars($a['qr_code_id']) ?></code></td>
            <td data-label="Status"><span class="badge badge-success"><i class="bi bi-check-circle"></i> Checked In</span></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Live dot pulse
setInterval(() => {
  document.querySelectorAll('.live-dot').forEach(d => {
    d.style.opacity = '0.3';
    setTimeout(() => d.style.opacity = '1', 400);
  });
}, 5000);

// Auto-refresh the page every 10 seconds to update stats.
setTimeout(() => {
    // Only reload if there are no modals open to prevent interrupting user actions.
    if (document.querySelectorAll('.modal-overlay.open').length === 0) {
        window.location.reload();
    }
}, 10000);
</script>
