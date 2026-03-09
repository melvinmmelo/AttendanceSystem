// ============================================================
//  AttendQR — app.js
// ============================================================

// ===== SIDEBAR =====
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ===== MODALS =====
function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}

// Close on backdrop click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});

// ===== TOAST =====
let toastTimer;
function toast(msg, type = 'info') {
  const t = document.getElementById('toast');
  const icons = { info: 'ℹ️', success: '✅', error: '❌', warning: '⚠️' };
  t.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${msg}</span>`;
  t.className = 'show';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.className = ''; }, 3500);
}

// ===== CONFIRM DIALOG =====
function confirm(title, message, onConfirm, danger = true) {
  document.getElementById('confirm-title').textContent   = title;
  document.getElementById('confirm-message').textContent = message;
  const btn = document.getElementById('confirm-ok-btn');
  btn.className = danger ? 'btn btn-danger' : 'btn btn-primary';
  btn.onclick = () => { closeModal('confirm-modal'); onConfirm(); };
  openModal('confirm-modal');
}

// ===== AJAX HELPER =====
async function apiPost(url, data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  // Add CSRF token from the meta tag in the layout's <head>
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (csrfMeta) {
    fd.append('csrf_token', csrfMeta.content);
  }

  const res = await fetch(url, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });

  // If session has expired, the server will return a 401 Unauthorized.
  // We catch this here to provide a better user experience than a generic error.
  if (res.status === 401) {
    toast('Session expired. Redirecting to login...', 'error');
    setTimeout(() => window.location.href = 'index.php?page=login', 2000);
    // Return a promise that never resolves to prevent further .then() or .catch()
    return new Promise(() => {});
  }

  const responseData = await res.json().catch(() => {
      throw new Error('The server returned an invalid response. Please check server logs.');
  });

  if (!res.ok) {
    throw new Error(responseData.message || 'An unknown server error occurred.');
  }
  return responseData;
}

// ===== SOUND FEEDBACK =====
function playSound(type = 'success') {
    // Use a try-catch block to prevent errors if the AudioContext is blocked or not supported.
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        if (!audioContext) return;

        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        gainNode.gain.setValueAtTime(0, audioContext.currentTime);
        gainNode.gain.linearRampToValueAtTime(0.4, audioContext.currentTime + 0.01); // Volume

        if (type === 'success') {
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(900, audioContext.currentTime + 0.05);
        } else { // 'error' or 'warning'
            oscillator.type = 'square';
            oscillator.frequency.setValueAtTime(220, audioContext.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(110, audioContext.currentTime + 0.1);
        }

        oscillator.start(audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.00001, audioContext.currentTime + 0.25);
        oscillator.stop(audioContext.currentTime + 0.25);
    } catch (e) {
        // Silently fail if audio playback is not possible.
        console.warn("Could not play sound:", e);
    }
}

// ===== QR SCANNER =====
let scannerActive = false;
let html5QrCodeScanner;
let isProcessingScan = false; // Flag to prevent processing multiple scans at once

function startScanner() {
    const viewportId = 'scanner-viewport';
    const area = document.getElementById('scanner-area');
    const startBtn = document.getElementById('scanner-start-btn');
    const stopBtn = document.getElementById('scanner-stop-btn');
    if (!area) return;

    const onScanSuccess = (decodedText, decodedResult) => {
        // Prevent re-entry if a scan is already being processed or if the scanner is stopped.
        if (!scannerActive || isProcessingScan) return;

        isProcessingScan = true; // Set a lock
        html5QrCodeScanner.pause();

        apiPost('index.php?page=scanner&action=process_scan', {
            qr_data: decodedText,
            event_id: document.getElementById('scanner-event-id')?.value || ''
        })
        .then(scan => {
            playSound(scan.type);
            displayScanResult(scan);
            addScanToLog(scan);
        })
        .catch((err) => {
            const errorScan = { type: 'error', status: '❌ Server Error', name: 'Unknown', event: '—', qr: 'ERROR' };
            toast(err.message || 'A server error occurred during scan.', 'error');
            playSound('error');
            displayScanResult(errorScan);
            addScanToLog(errorScan);
        })
        .finally(() => {
            setTimeout(() => {
                if (scannerActive && html5QrCodeScanner) {
                    area.classList.remove('success', 'error', 'warning');
                    area.classList.add('scanning');
                    const resultEl = document.getElementById('scanner-result');
                    if (resultEl) resultEl.innerHTML = '';
                    html5QrCodeScanner.resume();
                    isProcessingScan = false; // Release the lock
                } else {
                    isProcessingScan = false; // Ensure lock is always released
                }
            }, 1200); // Reduced delay from 2.5s to 1.2s for faster scanning
        });
    };

    const onScanFailure = (error) => { /* This is called frequently, so we intentionally leave it blank. */ };

    try {
        html5QrCodeScanner = new Html5QrcodeScanner(
            viewportId,
            {
                fps: 10
                // By removing the 'qrbox' option, the scanner will use the entire viewport.
                // This maximizes the camera view and removes the shaded background area.
            },
            /* verbose= */ false
        );
        html5QrCodeScanner.render(onScanSuccess, onScanFailure);

        scannerActive = true;
        area.classList.add('scanning');
        if (startBtn) startBtn.style.display = 'none';
        if (stopBtn) stopBtn.style.display = 'flex';
        toast('Scanner active. Point camera at QR code.', 'info');

        const resultEl = document.getElementById('scanner-result');
        if (resultEl) resultEl.innerHTML = '';

    } catch (e) {
        console.error(e);
        toast('Failed to start scanner. Check camera permissions.', 'error');
    }
}

function stopScanner() {
    scannerActive = false;
    if (!html5QrCodeScanner) return;

    html5QrCodeScanner.clear()
        .then(() => {
            const area = document.getElementById('scanner-area');
            const startBtn = document.getElementById('scanner-start-btn');
            const stopBtn = document.getElementById('scanner-stop-btn');
            if (!area) return;

            area.className = 'scanner-area';
            if (startBtn) startBtn.style.display = 'flex';
            if (stopBtn) stopBtn.style.display = 'none';
            const resultEl = document.getElementById('scanner-result');
            if (resultEl) resultEl.innerHTML = '<p class="text-muted text-sm" style="text-align:center;padding:10px;">Scanner stopped.</p>';
        })
        .catch(err => { console.error("Failed to clear scanner.", err); })
        .finally(() => { html5QrCodeScanner = null; });
}

function displayScanResult(scan) {
  const area     = document.getElementById('scanner-area');
  const resultEl = document.getElementById('scanner-result');
  if (!area || !resultEl) return;

  area.classList.remove('scanning', 'success', 'error', 'warning');
  area.classList.add(scan.type === 'success' ? 'success' : scan.type === 'warning' ? 'warning' : 'error');

  let rawDataHtml = '';
  if (scan.raw_data) {
      // Sanitize to prevent HTML injection from QR code content
      const sanitizedData = scan.raw_data.replace(/</g, "&lt;").replace(/>/g, "&gt;");
      rawDataHtml = `<div style="margin-top:8px;font-size:.7rem;color:var(--text3);">
                       <strong>Scanned Data:</strong>
                       <code style="white-space:pre-wrap;word-break:break-all;display:block;margin-top:4px;padding:8px;font-size:.75rem;">${sanitizedData}</code>
                     </div>`;
  }

  resultEl.innerHTML = `
    <div class="validation-result ${scan.type === 'warning' ? 'warning' : scan.type}">
      <div class="result-icon">${scan.type === 'success' ? '✅' : scan.type === 'warning' ? '⚠️' : '❌'}</div>
      <div>
        <div class="result-status">${scan.status}</div>
        <div class="result-detail">${scan.name} &bull; ${scan.event}</div>
        <div style="font-size:.73rem;color:var(--text3);margin-top:3px;">${scan.qr}</div>
        ${rawDataHtml}
      </div>
    </div>`;
}

function addScanToLog(scan) {
  const log = document.getElementById('scan-log');
  if (!log) return;

  const empty = log.querySelector('.empty-state');
  if (empty) empty.remove();

  const now = new Date().toLocaleTimeString();
  const row = document.createElement('div');
  row.className = 'scan-log-item';
  row.style.animation = 'fadeIn .3s ease';

  let iconHtml, badgeClass, badgeText;

  // Use the reliable 'scan_result' from the backend instead of parsing the display 'status'
  switch (scan.scan_result) {
    case 'checked_in':
    case 'manual_lookup_checked_in':
      iconHtml = '<i class="bi bi-check-circle-fill text-success"></i>';
      badgeClass = 'badge-success';
      badgeText = (scan.scan_result === 'manual_lookup_checked_in') ? 'LOOKUP' : 'OK';
      break;
    case 'already_checked_in':
    case 'manual_lookup_duplicate':
      iconHtml = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
      badgeClass = 'badge-warning';
      badgeText = 'DUP';
      break;
    case 'wrong_event':
    case 'manual_lookup_wrong_event':
      iconHtml = '<i class="bi bi-exclamation-circle-fill text-error"></i>';
      badgeClass = 'badge-error';
      badgeText = 'WRONG';
      break;
    default: // 'invalid', 'not_registered', 'manual_lookup_not_found', 'error'
      iconHtml = '<i class="bi bi-x-circle-fill text-error"></i>';
      badgeClass = 'badge-error';
      badgeText = 'FAIL';
      break;
  }

  row.innerHTML = `
    <span style="font-size:1.4rem;">${iconHtml}</span>
    <div style="flex:1;">
      <div style="font-size:.85rem;font-weight:500;color:var(--text);">${scan.name}</div>
      <div style="font-size:.73rem;color:var(--text3);">${scan.qr} &bull; ${now}</div>
    </div>
    <span class="badge ${badgeClass}">${badgeText}</span>`;
  log.prepend(row);
}

function manualLookup() {
  const q = document.getElementById('manual-lookup-input')?.value.trim();
  if (!q) return;
  const eventId = document.getElementById('scanner-event-id')?.value || '';

  apiPost('index.php?page=scanner&action=lookup', { query: q, event_id: eventId })
    .then(scan => {
        displayScanResult(scan);
        addScanToLog(scan); // Add the result to the live log
        toast(scan.status.substring(2).trim(), scan.type); // Show toast with status text
    })
    .catch((err) => {
        toast(err.message || 'Server error. Please try again.', 'error');
        displayScanResult({ type:'error', status:'❌ Server Error', name:'Please check logs', event:'—', qr: q });
    });
}

// ===== EVENT FILTERING (QR CODES PAGE) =====
function filterByEvent(eventId) {
    const url = new URL(window.location);
    url.searchParams.set('page', 'qrcodes'); // Ensure page is correct
    if (eventId) {
        url.searchParams.set('event_id', eventId);
    } else {
        url.searchParams.delete('event_id');
    }
    // Remove prefill_id to avoid confusion when filtering
    url.searchParams.delete('prefill_id');
    window.location.href = url.toString();
}

// ===== QR GENERATOR =====
function generateQR() {
  const name  = document.getElementById('qr-name')?.value.trim();
  const email = document.getElementById('qr-email')?.value.trim();
  const eventSelect = document.getElementById('qr-event');
  const event = eventSelect.options[eventSelect.selectedIndex].text;
  const rid   = document.getElementById('qr-rid')?.value;

  if (!name || !email) { toast('Please fill in Name and Email.', 'error'); return; }
  if (eventSelect.value === "") { toast('Please select an event.', 'error'); return; }

  const output = document.getElementById('qr-output');
  if (!output) return;
  output.innerHTML = '';

  const qrData = JSON.stringify({ rid, name, email, event, ts: new Date().toISOString() });

  new QRCode(output, {
    text:          qrData,
    width:         200, height: 200,
    colorDark:     '#000000', colorLight: '#ffffff',
    correctLevel:  QRCode.CorrectLevel.H
  });

  const previewName = document.getElementById('qr-preview-name');
  const previewMeta = document.getElementById('qr-preview-meta');
  const downloadBtn = document.getElementById('qr-download-btn');

  if (previewName) previewName.textContent = name;
  if (previewMeta) previewMeta.textContent = `${event} • ${rid}`;
  if (downloadBtn) downloadBtn.disabled = false;

  toast(`QR Code generated for ${name}`, 'success');
}

function downloadQR() {
  const canvas = document.querySelector('#qr-output canvas');
  if (!canvas) return;
  const name = document.getElementById('qr-preview-name')?.textContent || 'qr-code';
  const link  = document.createElement('a');
  link.download = `${name.replace(/\s+/g,'-').toLowerCase()}-qr.png`;
  link.href = canvas.toDataURL();
  link.click();
  toast('QR Code downloaded!', 'success');
}

function sendQREmail() {
  const name = document.getElementById('qr-name')?.value;
  const email = document.getElementById('qr-email')?.value;
  const eventSelect = document.getElementById('qr-event');
  const eventId = eventSelect.value;
  const rid   = document.getElementById('qr-rid')?.value;

  if (!name || !email || !eventId) {
    toast('Please fill Name, Email, and select an Event.', 'warning');
    return;
  }

  // The server will now generate the QR code, so we don't need to check for a canvas or send its data.

  toast(`Sending email to ${email}...`, 'info');

  apiPost('index.php?page=qrcodes&action=send_email', {
    respondent_id: rid,
    name: name,
    email: email,
    event_id: eventId
  }).then(d => {
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success && d.attendee_id) {
        const row = document.querySelector(`#qr-log-row-${d.attendee_id}`);
        if (row) {
            // If the attendee is already visible in the log, just update the status badge.
            const statusCell = row.querySelector('.email-status-cell');
            if (statusCell) {
                statusCell.innerHTML = '<span class="badge badge-success">✅ Sent</span>';
            }
        } else {
            // If the row is not visible (new attendee, or different filter/page),
            // reload the page after a short delay to show the updated state.
            setTimeout(() => window.location.reload(), 2000);
        }
    }
  }).catch((err) => {
    toast(err.message || 'An error occurred while sending the email.', 'error');
  });
}

/**
 * Opens a confirmation dialog and then resends a QR code email to an attendee.
 * @param {object} attendee An object containing attendee details.
 * @param {number} attendee.id The attendee's database ID.
 * @param {string} attendee.name The attendee's full name.
 * @param {string} attendee.email The attendee's email address.
 * @param {string} attendee.rid The attendee's respondent ID.
 */
async function resendEmail(attendee) {
  if (!attendee || !attendee.id || !attendee.email) {
      toast('Missing attendee information.', 'error');
      return;
  }

  // Use the global confirm dialog
  confirm(
      'Resend QR Code Email?',
      `This will send a new QR code to ${attendee.name} at ${attendee.email}.`,
      async () => {
          toast(`Sending email to ${attendee.name}...`, 'info');

          // Call the API with only the respondentId.
          // The server will look up the attendee and generate the QR code.
          try {
              const data = await apiPost('index.php?page=qrcodes&action=send_email', {
                  respondent_id: attendee.rid
              });

              toast(data.message, data.success ? 'success' : 'error');

              // Update UI if successful
              if (data.success) {
                  const row = document.querySelector(`#qr-log-row-${attendee.id}`) || document.querySelector(`#attendee-row-${attendee.id}`);
                  if (row) {
                      const statusCell = row.querySelector('.email-status-cell');
                      if (statusCell) {
                          statusCell.innerHTML = '<span class="badge badge-success">✅ Sent</span>';
                      }
                  }
              }
          } catch (e) {
              toast(e.message || 'An error occurred while sending the email.', 'error');
          }
      },
      false // This is not a destructive action, so use primary button style
  );
}

// ===== BULK EMAIL SEND =====
function sendToAllPending(eventId) {
    if (!eventId) return;

    confirm(
        'Send Emails to All Pending?',
        'This will send QR code emails to all attendees for this event who have not yet received one. This may take some time. Are you sure?',
        () => {
            toast('Sending emails to all pending attendees... This may take a moment.', 'info');

            apiPost('index.php?page=qrcodes&action=send_to_all_pending', { event_id: eventId })
                .then(data => {
                    toast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => window.location.reload(), 2500);
                    }
                })
                .catch(err => {
                    toast(err.message || 'An error occurred during the bulk email send.', 'error');
                });
        },
        false // Not a destructive action
    );
}

// ===== FORM PREVIEW =====
function previewGoogleForm() {
  const url    = document.getElementById('form-url-input')?.value.trim() || '';
  const title  = document.getElementById('embed-form-title')?.value.trim() || 'Google Form';
  const area   = document.getElementById('form-preview-area');
  const badge  = document.getElementById('form-status-badge');
  if (!area) return;

  if (!url || !url.includes('google.com/forms')) {
    toast('Please enter a valid Google Forms URL.', 'error'); return;
  }

  area.innerHTML = `
    <div style="background:var(--surface3);border-radius:var(--radius-sm);overflow:hidden;">
      <div style="padding:14px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);">
        <span>📝</span>
        <span style="font-size:.87rem;font-weight:500;">${title}</span>
        <span class="badge badge-success" style="margin-left:auto;">✅ Valid URL</span>
      </div>
      <div style="padding:24px;text-align:center;color:var(--text2);font-size:.87rem;">
        <div style="font-size:2rem;margin-bottom:12px;">📋</div>
        <p>Form will be embedded as an iframe in the registration page.</p>
        <p style="font-size:.75rem;color:var(--text3);margin-top:8px;word-break:break-all;">${url}</p>
        <div style="margin-top:16px;">
          <a href="${url}" target="_blank" class="btn btn-ghost btn-sm">🔗 Open Form</a>
        </div>
      </div>
    </div>`;

  if (badge) { badge.textContent = 'Ready'; badge.className = 'badge badge-success'; }
  toast('Form URL validated!', 'success');
}

function openSyncModal(formId, sheetUrl = '') {
  document.getElementById('sync-form-id').value = formId;
  const urlInput = document.getElementById('sync-csv-url');
  urlInput.value = sheetUrl;

  const syncBtn = document.getElementById('sync-start-btn');
  if (sheetUrl) {
    syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Sync Now & Update URL';
  } else {
    syncBtn.innerHTML = '<i class="bi bi-save"></i> Save URL & Sync';
  }

  openModal('sync-modal');
}

// ===== CHARTS =====
let chartInstances = []; // To hold chart objects for re-initialization

function initCharts() {
  // Destroy any existing chart instances to prevent memory leaks
  chartInstances.forEach(chart => chart.destroy());
  chartInstances = [];

  const isLightMode = document.body.classList.contains('light-mode');

  // Check-in trend (Dashboard)
  const ctx1 = document.getElementById('checkin-chart')?.getContext('2d');
  if (ctx1) {
    const chart1 = new Chart(ctx1, {
      type: 'line',
      data: {
        labels:   ['8AM','9AM','10AM','11AM','12PM','1PM','2PM','3PM'],
        datasets: [{ label:'Check-ins', data:[5,18,32,27,15,38,28,26],
          borderColor: isLightMode ? '#6c63ff' : '#ffc107',
          backgroundColor: isLightMode ? 'rgba(108,99,255,.12)' : 'rgba(255, 193, 7, 0.1)',
          tension:.4, fill:true,
          pointBackgroundColor: isLightMode ? '#6c63ff' : '#ffc107',
          pointRadius:4 }]
      },
      options: chartOptions(false)
    });
    chartInstances.push(chart1);
  }

  // Event Compare (Reports)
  const ctx2 = document.getElementById('event-compare-chart')?.getContext('2d');
  if (ctx2) {
    const labels = window.chartEventLabels || ['Tech Summit','DevFest','AI Workshop','Leadership'];
    const regs   = window.chartRegs        || [89, 74, 45, 39];
    const checks = window.chartChecks      || [67, 52, 0,  0];
    const chart2 = new Chart(ctx2, {
      type:'bar',
      data:{
        labels,
        datasets:[
          { label:'Registrations', data:regs,   backgroundColor: isLightMode ? 'rgba(108,99,255,.75)' : 'rgba(108,99,255,.75)', borderRadius:6 },
          { label:'Checked In',    data:checks, backgroundColor:'rgba(67,233,123,.75)', borderRadius:6 }
        ]
      },
      options: chartOptions(true)
    });
    chartInstances.push(chart2);
  }

  // Donut (Reports)
  const ctx3 = document.getElementById('attendance-donut-chart')?.getContext('2d');
  if (ctx3) {
    const legendColor = isLightMode ? '#495057' : 'rgba(255,255,255,.5)';
    const ci  = window.chartTotalCheckin  || 119;
    const pen = window.chartTotalPending  || 128;
    const chart3 = new Chart(ctx3, {
      type:'doughnut',
      data:{
        labels:['Checked In','Pending'],
        datasets:[{ data:[ci, pen],
          backgroundColor:['rgba(67,233,123,.8)','rgba(247,151,30,.8)'],
          borderWidth:0, hoverOffset:8 }]
      },
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'bottom', labels:{ color: legendColor, padding:16, font:{size:11} } } }
      }
    });
    chartInstances.push(chart3);
  }
}

function chartOptions(showLegend) {
  const isLightMode = document.body.classList.contains('light-mode');
  const gridColor = isLightMode ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.04)';
  const tickColor = isLightMode ? '#6c757d' : 'rgba(255,255,255,.4)';
  const legendColor = isLightMode ? '#495057' : 'rgba(255,255,255,.5)';
  return {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ display:showLegend, labels:{ color: legendColor, font:{size:11} } } },
    scales:{
      x:{ grid:{ color: gridColor }, ticks:{ color: tickColor, font:{size:11} } },
      y:{ grid:{ color: gridColor }, ticks:{ color: tickColor, font:{size:11} } }
    }
  };
}


// ===== THEME TOGGLE =====
function applyTheme(theme) {
    const toggleBtn = document.getElementById('theme-toggle-btn');
    if (!toggleBtn) return;
    const icon = toggleBtn.querySelector('i');

    if (theme === 'light') {
        document.body.classList.add('light-mode');
        if (icon) icon.className = 'bi bi-moon';
    } else {
        document.body.classList.remove('light-mode');
        if (icon) icon.className = 'bi bi-sun';
    }
}

function toggleTheme() {
    const currentTheme = document.body.classList.contains('light-mode') ? 'light' : 'dark';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    localStorage.setItem('theme', newTheme);
    applyTheme(newTheme);
    initCharts(); // Re-initialize charts with new theme colors
    toast(`Switched to ${newTheme} mode`, 'info');
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  // Apply saved theme on page load
  const savedTheme = localStorage.getItem('theme') || 'dark'; // Default to dark
  applyTheme(savedTheme);

  // Theme toggle button
  const themeToggleBtn = document.getElementById('theme-toggle-btn');
  if (themeToggleBtn) {
      themeToggleBtn.addEventListener('click', toggleTheme);
  }

  initCharts();

  // Auto-dismiss alerts
  document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => a.style.display = 'none', 5000);
  });

  // Handle closing pop-overs (sidebar, notifications) when clicking outside
  document.addEventListener('click', e => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle  = document.getElementById('sidebar-toggle');
    const notifPanel = document.getElementById('notif-panel'); // This will be null, but keep for safety
    const notifBtn = document.getElementById('notif-btn'); // This will be null

    if (sidebar && sidebarToggle && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
      sidebar.classList.remove('open');
    }

  });
});
