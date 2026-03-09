<?php
// pages/events.php
$action = $_GET['action'] ?? 'list';

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_REQUEST['action'] ?? '';
    if ($a === 'create') {
        verify_csrf();
        $name   = trim($_POST['name']   ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $venue  = trim($_POST['venue']  ?? '');
        $date   = $_POST['event_date']  ?? '';
        $time   = $_POST['event_time']  ?? '';
        $status = $_POST['status']      ?? 'upcoming';
        $cap    = (int)($_POST['max_capacity'] ?? 0);

        if ($name && $date) {
            db_execute("INSERT INTO events (name,description,venue,event_date,event_time,status,max_capacity,created_by)
                        VALUES (?,?,?,?,?,?,?,?)",
                [$name,$desc,$venue,$date,$time,$status,$cap,$_SESSION['admin_id']]);
            flash('success','Event created successfully!');
            header('Location: index.php?page=events'); exit;
        }
    }
    if ($a === 'update') {
        verify_csrf();
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name']   ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $venue  = trim($_POST['venue']  ?? '');
        $date   = $_POST['event_date']  ?? '';
        $time   = $_POST['event_time']  ?? '';
        $status = $_POST['status']      ?? 'upcoming';
        $cap    = (int)($_POST['max_capacity'] ?? 0);

        if ($id && $name && $date) {
            db_execute("UPDATE events SET name=?, description=?, venue=?, event_date=?, event_time=?, status=?, max_capacity=? WHERE id=?",
                [$name, $desc, $venue, $date, $time, $status, $cap, $id]);
            flash('success','Event updated successfully!');
            header('Location: index.php?page=events'); exit;
        } else {
            flash('error', 'Missing required fields for update.');
            header('Location: index.php?page=events&action=edit&id=' . $id); exit;
        }
    }
    if ($a === 'delete') {
        verify_csrf();
        $id = (int)$_POST['id'];
        db_execute("DELETE FROM events WHERE id = ?", [$id]);
        flash('success','Event deleted.');
        header('Location: index.php?page=events'); exit;
    }
}

// ---- Auto-update statuses ----
// This logic runs whenever the events list is loaded to keep statuses current.
// 1. Set 'upcoming' events to 'active' if their start date and time have passed.
db_execute("UPDATE events SET status = 'active' WHERE status = 'upcoming' AND CONCAT(event_date, ' ', COALESCE(event_time, '00:00:00')) <= NOW()");
// 2. Set 'active' events to 'completed' if their date has passed (i.e., it's the next day).
db_execute("UPDATE events SET status = 'completed' WHERE status = 'active' AND event_date < CURDATE()");

// ---- List ----
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';

$where  = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND e.name LIKE ?"; $params[] = "%$search%"; }
if ($filter) { $where .= " AND e.status = ?";  $params[] = $filter; }

$page   = max(1,(int)($_GET['p']  ?? 1));
$per    = 15; // Events per page
$offset = ($page - 1) * $per;

$total_events = db_count("SELECT COUNT(*) FROM events e $where", $params);
$total_pages = (int)ceil($total_events / $per);

$limit_sql = " LIMIT $per OFFSET $offset";

$events = db_query("SELECT
    e.*,
    COALESCE(stats.total_reg, 0) as total_reg,
    COALESCE(stats.total_checkin, 0) as total_checkin,
    (SELECT form_status FROM event_forms WHERE event_id = e.id ORDER BY created_at DESC LIMIT 1) as form_status
  FROM events e
  LEFT JOIN (
      SELECT
          event_id,
          COUNT(id) as total_reg,
          SUM(checkin_status = 'checked_in') as total_checkin
      FROM attendees
      GROUP BY event_id
  ) as stats ON stats.event_id = e.id
  $where
  ORDER BY e.event_date DESC" . $limit_sql, $params);

// Show Create form
if ($action === 'create'): ?>
<div class="page-hero-row">
  <div class="page-hero"><h1>Create Event</h1><p>Set up a new event registration.</p></div>
  <a href="index.php?page=events" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Back to Events</a>
</div>
<div class="card" style="max-width:680px;">
  <div class="card-header"><span><i class="bi bi-calendar-plus"></i></span><div class="card-title">Event Details</div></div>
  <form method="POST" action="index.php?page=events">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Event Name *</label>
        <input type="text" name="name" class="form-input" placeholder="e.g. Tech Summit 2025" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Event Date *</label>
          <input type="date" name="event_date" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Event Time</label>
          <input type="time" name="event_time" class="form-input">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Venue</label>
        <input type="text" name="venue" class="form-input" placeholder="e.g. SMX Convention Center">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="upcoming">Upcoming</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Max Capacity</label>
          <input type="number" name="max_capacity" class="form-input" placeholder="0 = unlimited" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-textarea" placeholder="Event description..."></textarea>
      </div>
    </div>
    <div class="card-footer">
      <a href="index.php?page=events" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">Create Event</button>
    </div>
  </form>
</div>

<?php elseif ($action === 'edit'):
    $id = (int)($_GET['id'] ?? 0);
    $event = db_row("SELECT * FROM events WHERE id = ?", [$id]);
    if (!$event) {
        flash('error', 'Event not found.');
        header('Location: index.php?page=events'); exit;
    }
?>
<div class="page-hero-row">
  <div class="page-hero"><h1>Edit Event</h1><p>Update the details for "<?= htmlspecialchars($event['name']) ?>".</p></div>
  <a href="index.php?page=events" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Back to Events</a>
</div>
<div class="card" style="max-width:680px;">
  <div class="card-header"><span><i class="bi bi-pencil-square"></i></span><div class="card-title">Event Details</div></div>
  <form method="POST" action="index.php?page=events">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= $event['id'] ?>">
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Event Name *</label>
        <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($event['name']) ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Event Date *</label>
          <input type="date" name="event_date" class="form-input" value="<?= htmlspecialchars($event['event_date']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Event Time</label>
          <input type="time" name="event_time" class="form-input" value="<?= htmlspecialchars($event['event_time']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Venue</label>
        <input type="text" name="venue" class="form-input" value="<?= htmlspecialchars($event['venue']) ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="upcoming"  <?= $event['status']==='upcoming' ?'selected':'' ?>>Upcoming</option>
            <option value="active"    <?= $event['status']==='active'   ?'selected':'' ?>>Active</option>
            <option value="inactive"  <?= $event['status']==='inactive' ?'selected':'' ?>>Inactive</option>
            <option value="completed" <?= $event['status']==='completed'?'selected':'' ?>>Completed</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Max Capacity</label>
          <input type="number" name="max_capacity" class="form-input" value="<?= htmlspecialchars($event['max_capacity']) ?>" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-textarea"><?= htmlspecialchars($event['description']) ?></textarea>
      </div>
    </div>
    <div class="card-footer">
      <a href="index.php?page=events" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
    </div>
  </form>
</div>

<?php else: // List view ?>

<div class="page-hero-row">
  <div class="page-hero"><h1>Events</h1><p>Manage events and Google Form integrations.</p></div>
  <a href="index.php?page=events&action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> <span>Create Event</span></a>
</div>

<div class="flex items-center gap-3" style="margin-bottom:12px; margin-left: 4px;">
  <span class="live-dot"></span>
  <span class="text-sm text-muted">Live &bull; Auto-refreshes every 10 seconds to update stats.</span>
</div>

<div class="card">
  <div class="card-header">
    <form method="GET" action="index.php" class="filter-bar">
      <input type="hidden" name="page" value="events">
      <div class="search-bar">
        <span class="search-icon"><i class="bi bi-search"></i></span>
        <input type="text" name="q" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="filter" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active"   <?= $filter==='active'   ?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $filter==='inactive' ?'selected':'' ?>>Inactive</option>
        <option value="upcoming" <?= $filter==='upcoming' ?'selected':'' ?>>Upcoming</option>
        <option value="completed"<?= $filter==='completed'?'selected':'' ?>>Completed</option>
      </select>
      <button type="submit" class="btn btn-ghost btn-sm"><i class="bi bi-search"></i> Search</button>
    </form>
    <div style="margin-left:auto;font-size:.8rem;color:var(--text3);"><?= $total_events ?> events found</div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Event</th>
          <th>Date</th>
          <th>Venue</th>
          <th>Status</th>
          <th>Form</th>
          <th>Registrations</th>
          <th>Attendance</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($events)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px;">No events found. <a href="index.php?page=events&action=create" style="color:var(--accent);">Create one</a>.</td></tr>
        <?php else: ?>
          <?php foreach ($events as $ev):
            $rate = $ev['total_reg'] > 0 ? round($ev['total_checkin']/$ev['total_reg']*100) : 0;
            $status_badge = match($ev['status']) {
              'active'    => 'badge-success',
              'inactive'  => 'badge-neutral',
              'upcoming'  => 'badge-info',
              'completed' => 'badge-warning',
              default     => 'badge-neutral'
            };
          ?>
          <tr>
            <td data-label="Event">
              <div style="font-weight:500;color:var(--text);"><?= htmlspecialchars($ev['name']) ?></div>
              <div style="font-size:.73rem;color:var(--text3);">ID: <?= $ev['id'] ?></div>
            </td>
            <td data-label="Date"><?= date('M j, Y', strtotime($ev['event_date'])) ?></td>
            <td data-label="Venue"><?= htmlspecialchars($ev['venue'] ?: '—') ?></td>
            <td data-label="Status">
              <span class="badge <?= $status_badge ?>"><?= ucfirst($ev['status']) ?></span>
            </td>
            <td data-label="Form">
              <?php if ($ev['form_status']): ?>
                <span class="badge <?= $ev['form_status'] === 'active' ? 'badge-success' : 'badge-neutral' ?>">
                  <i class="bi <?= $ev['form_status'] === 'active' ? 'bi-check-circle-fill' : 'bi-slash-circle-fill' ?>"></i> <?= $ev['form_status'] === 'active' ? 'Open' : 'Closed' ?>
                </span>
              <?php else: ?>
                <span class="badge badge-warning"><i class="bi bi-exclamation-triangle"></i> No Form</span>
              <?php endif; ?>
            </td>
            <td data-label="Registrations">
              <div style="font-weight:600;color:var(--text);"><?= (int)$ev['total_reg'] ?></div>
              <?php if ($ev['max_capacity'] > 0): ?>
                <div class="progress-bar" style="width:80px;margin-top:5px;">
                  <div class="progress-fill accent" style="width:<?= min(100,round(((int)$ev['total_reg'])/$ev['max_capacity']*100)) ?>%"></div>
                </div>
              <?php endif; ?>
            </td>
            <td data-label="Attendance">
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="progress-bar" style="width:60px;">
                  <div class="progress-fill success" style="width:<?= $rate ?>%"></div>
                </div>
                <span style="font-size:.82rem;font-weight:600;"><?= $rate ?>%</span>
              </div>
            </td>
            <td data-label="Actions">
              <a href="index.php?page=attendees&event_id=<?= $ev['id'] ?>" class="action-btn" title="View Attendees"><i class="bi bi-people"></i></a>
              <a href="index.php?page=forms&event_id=<?= $ev['id'] ?>" class="action-btn" title="Manage Form"><i class="bi bi-file-earmark-ruled"></i></a>
              <a href="index.php?page=events&action=edit&id=<?= $ev['id'] ?>" class="action-btn" title="Edit Event"><i class="bi bi-pencil"></i></a>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                <button type="button" class="action-btn" title="Delete" onclick="const form = this.closest('form'); confirm('Delete Event?', 'Are you sure you want to delete this event and all its data? This action cannot be undone.', () => { form.submit(); })"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total_pages > 1): ?>
    <?php
      $base_url = "index.php?page=events" . ($search ? "&q=".urlencode($search) : '') . ($filter ? "&filter=".urlencode($filter) : '');
      echo render_pagination($page, $total_pages, $base_url);
    ?>
  <?php endif; ?>
</div>
<script>
// Auto-refresh the page every 10 seconds to update stats.
setTimeout(() => {
    // Only reload if there are no modals open to prevent interrupting user actions (like a delete confirmation).
    if (document.querySelectorAll('.modal-overlay.open').length === 0) {
        window.location.reload();
    }
}, 10000);
</script>
<?php endif; ?>
