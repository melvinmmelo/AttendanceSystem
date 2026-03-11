<?php
// --- Step 1: Fetch all necessary data for the report cards and modals ---
$stats = db_row("SELECT
  COUNT(*) as total_reg,
  SUM(checkin_status='checked_in') as total_checkin,
  SUM(checkin_status='pending')    as total_pending,
  SUM(email_sent=1)                as emails_sent
  FROM attendees");

$rate = $stats['total_reg'] > 0 ? round($stats['total_checkin']/$stats['total_reg']*100,1) : 0;

$event_stats = db_query("SELECT e.name,
  COUNT(a.id) as registrations,
  SUM(a.checkin_status='checked_in') as checked_in,
  SUM(a.checkin_status='pending')    as pending
  FROM events e LEFT JOIN attendees a ON a.event_id=e.id
  GROUP BY e.id,e.name ORDER BY e.event_date DESC");

$scan_stats = db_row("SELECT COUNT(*) as total,
  SUM(scan_result IN ('checked_in', 'manual_lookup_checked_in')) as ok,
  SUM(scan_result IN ('already_checked_in', 'manual_lookup_duplicate')) as dup,
  SUM(scan_result IN ('invalid', 'not_registered', 'manual_lookup_not_found')) as invalid
  FROM scan_logs");

// New query to fetch details for the duplicate scans modal
$duplicate_scans = db_query("
    SELECT sl.scanned_at, sl.scan_result, a.full_name, a.email, e.name as event_name
    FROM scan_logs sl
    JOIN attendees a ON sl.attendee_id = a.id
    JOIN events e ON sl.event_id = e.id
    WHERE sl.scan_result IN ('already_checked_in', 'manual_lookup_duplicate')
    ORDER BY sl.scanned_at DESC
    LIMIT 50
");

// JS chart data
$chart_labels = json_encode(array_column($event_stats,'name'));
$chart_regs   = json_encode(array_map(function($e){ return (int)$e['registrations']; },$event_stats));
$chart_checks = json_encode(array_map(function($e){ return (int)$e['checked_in']; },$event_stats));
?>
<div class="page-hero"><h1>Analytics &amp; Reports</h1><p>Detailed insights across all events.</p></div>

<div class="grid-4">
  <div class="stat-card purple">
    <span class="stat-icon"><i class="bi bi-pie-chart"></i></span>
    <div class="stat-value"><?= $rate ?>%</div>
    <div class="stat-label">Overall Attendance Rate</div>
  </div>
  <div class="stat-card green">
    <span class="stat-icon"><i class="bi bi-envelope"></i></span>
    <div class="stat-value"><?= number_format($stats['emails_sent']) ?></div>
    <div class="stat-label">Emails Sent</div>
  </div>
  <div class="stat-card orange">
    <span class="stat-icon"><i class="bi bi-qr-code"></i></span>
    <div class="stat-value"><?= number_format($stats['total_reg']) ?></div>
    <div class="stat-label">QR Codes Generated</div>
  </div>
  <!-- Step 2: Make the card clickable to open the modal -->
  <a href="javascript:void(0)" onclick="openModal('duplicate-scans-modal')" class="stat-card pink" style="text-decoration: none; color: inherit; cursor: pointer;">
    <span class="stat-icon"><i class="bi bi-exclamation-triangle"></i></span>
    <div class="stat-value"><?= (int)($scan_stats['dup']??0) ?></div>
    <div class="stat-label">Duplicate Scan Attempts</div>
    <div class="stat-change neutral" style="margin-top: 8px;">Click to view details</div>
  </a>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><span><i class="bi bi-graph-up"></i></span><div class="card-title">Registrations vs Attendance by Event</div></div>
    <div class="card-body">
      <div class="chart-container"><canvas id="event-compare-chart"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span><i class="bi bi-pie-chart"></i></span><div class="card-title">Attendance Distribution</div></div>
    <div class="card-body">
      <div class="chart-container"><canvas id="attendance-donut-chart"></canvas></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span><i class="bi bi-calendar3"></i></span>
    <div class="card-title">Event Performance Summary</div>
    <a href="index.php?page=reports&export=csv" class="btn btn-ghost btn-sm"><i class="bi bi-download"></i> Export</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Event</th><th>Registrations</th><th>Checked In</th><th>Pending</th><th>Rate</th></tr></thead>
      <tbody>
        <?php foreach ($event_stats as $e):
          $r = $e['registrations'] > 0 ? round($e['checked_in']/$e['registrations']*100) : 0;
        ?>
        <tr>
          <td style="font-weight:500;color:var(--text);"><?= htmlspecialchars($e['name']) ?></td>
          <td><?= $e['registrations'] ?></td>
          <td><?= $e['checked_in'] ?></td>
          <td><?= $e['pending'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="progress-bar" style="width:80px;">
                <div class="progress-fill <?= $r>=60?'success':'accent' ?>" style="width:<?= $r ?>%"></div>
              </div>
              <span style="font-size:.82rem;font-weight:700;"><?= $r ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
window.chartEventLabels = <?= $chart_labels ?>;
window.chartRegs        = <?= $chart_regs ?>;
window.chartChecks      = <?= $chart_checks ?>;
window.chartTotalCheckin = <?= (int)$stats['total_checkin'] ?>;
window.chartTotalPending = <?= (int)$stats['total_pending'] ?>;
</script>

<!-- Step 3: Add the modal HTML at the end of the file -->
<div class="modal-overlay" id="duplicate-scans-modal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Recent Duplicate Scans</h3>
      <button class="modal-close" onclick="closeModal('duplicate-scans-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Attendee</th>
                        <th>Event</th>
                        <th>Time of Scan</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($duplicate_scans)): ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:32px;">No duplicate scans recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($duplicate_scans as $scan): ?>
                            <tr>
                                <td data-label="Attendee">
                                    <div style="font-weight:500;color:var(--text);"><?= htmlspecialchars($scan['full_name']) ?></div>
                                    <div style="font-size:.73rem;color:var(--text3);"><?= htmlspecialchars($scan['email']) ?></div>
                                </td>
                                <td data-label="Event"><?= htmlspecialchars($scan['event_name']) ?></td>
                                <td data-label="Time of Scan"><?= date('M j, Y g:i A', strtotime($scan['scanned_at'])) ?></td>
                                <td data-label="Type">
                                    <?php if ($scan['scan_result'] === 'manual_lookup_duplicate'): ?>
                                        <span class="badge badge-info">Lookup</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Scan</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('duplicate-scans-modal')">Close</button>
    </div>
  </div>
</div>
