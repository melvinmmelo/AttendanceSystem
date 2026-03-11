<?php
// pages/api.php

// This file acts as a central router for all AJAX API calls.
// It ensures that all API logic is separate from the page rendering logic.

verify_csrf(); // Protect all API endpoints

$endpoint = $_REQUEST['endpoint'] ?? '';

try {
    switch ($endpoint) {
        case 'process_scan':
            $qr_data_string = $_POST['qr_data'] ?? '';
            $qr_data = json_decode($qr_data_string, true);
            $selected_event_id = (int)($_POST['event_id'] ?? 0);
            $rid = $qr_data['rid'] ?? null;

            if (!$rid || !is_array($qr_data)) {
                db_execute("INSERT INTO scan_logs (qr_code_id, event_id, scanned_by, scan_result, ip_address, user_agent) VALUES (?,?,?,?,?,?)", ['INVALID_QR_DATA', $selected_event_id ?: null, $_SESSION['admin_id'] ?? null, 'invalid', $_SERVER['REMOTE_ADDR'], substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                send_json_response(['type' => 'error', 'status' => '❌ Invalid QR Code', 'name' => 'Unknown', 'event' => '—', 'qr' => 'INVALID_DATA', 'raw_data' => $qr_data_string, 'scan_result' => 'invalid']);
            }

            $attendee = db_row("SELECT a.*, e.name as event_name FROM attendees a JOIN events e ON e.id=a.event_id WHERE a.respondent_id = ?", [$rid]);

            if ($attendee) {
                if ($selected_event_id && $attendee['event_id'] != $selected_event_id) {
                    db_execute("INSERT INTO scan_logs (attendee_id, qr_code_id, event_id, scanned_by, scan_result, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)", [$attendee['id'], $attendee['qr_code_id'], $selected_event_id, $_SESSION['admin_id'] ?? null, 'wrong_event', $_SERVER['REMOTE_ADDR'], substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                    $selected_event = db_row("SELECT name FROM events WHERE id=?", [$selected_event_id]);
                    send_json_response(['type' => 'error', 'status' => '❌ Wrong Event', 'name' => $attendee['full_name'], 'event' => 'Expected: ' . ($selected_event['name'] ?? 'N/A') . ' | Scanned: ' . $attendee['event_name'], 'qr' => $attendee['qr_code_id'], 'scan_result' => 'wrong_event']);
                } else {
                    $scan_result = ($attendee['checkin_status'] === 'checked_in') ? 'already_checked_in' : 'checked_in';
                    if ($scan_result === 'checked_in') {
                        db_execute("UPDATE attendees SET checkin_status='checked_in', checkin_at=NOW() WHERE id=?", [$attendee['id']]);
                    }
                    db_execute("INSERT INTO scan_logs (attendee_id, qr_code_id, event_id, scanned_by, scan_result, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)", [$attendee['id'], $attendee['qr_code_id'], $attendee['event_id'], $_SESSION['admin_id'] ?? null, $scan_result, $_SERVER['REMOTE_ADDR'], substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                    $type = ($scan_result === 'checked_in') ? 'success' : 'warning';
                    $status = ($scan_result === 'checked_in') ? '✅ Checked In' : '⚠️ Already Checked-in';
                    send_json_response(['type' => $type, 'status' => $status, 'name' => $attendee['full_name'], 'event' => $attendee['event_name'], 'qr' => $attendee['qr_code_id'], 'scan_result' => $scan_result]);
                }
            } else {
                $qr_code_id_from_scan = 'UNKNOWN-' . substr(hash('sha256', $qr_data_string), 0, 10);
                db_execute("INSERT INTO scan_logs (attendee_id, qr_code_id, event_id, scanned_by, scan_result, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)", [null, $qr_code_id_from_scan, $selected_event_id ?: null, $_SESSION['admin_id'] ?? null, 'not_registered', $_SERVER['REMOTE_ADDR'], substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                send_json_response(['type' => 'error', 'status' => '❌ Not Registered', 'name' => $qr_data['name'] ?? 'Unknown', 'event' => $qr_data['event'] ?? '—', 'qr' => $qr_code_id_from_scan, 'scan_result' => 'not_registered']);
            }
            break;

        case 'manual_lookup':
            $q = trim($_POST['query'] ?? '');
            $selected_event_id = (int)($_POST['event_id'] ?? 0);

            if (empty($q)) {
                send_json_response(['type' => 'error', 'status' => '❌ Invalid Query', 'name' => 'Query cannot be empty', 'event' => '—', 'qr' => '', 'scan_result' => 'error']);
            }

            $att = db_row("SELECT a.*,e.name as event_name FROM attendees a JOIN events e ON e.id=a.event_id WHERE a.email=? OR a.qr_code_id=? OR a.respondent_id=?", [$q, $q, $q]);
            if ($att) {
                $event_text = $att['event_name'];
                if ($selected_event_id && $att['event_id'] != $selected_event_id) {
                    $scan_result = 'manual_lookup_wrong_event';
                    $type = 'error';
                    $status_text = '❌ Wrong Event';
                    $selected_event = db_row("SELECT name FROM events WHERE id=?", [$selected_event_id]);
                    $event_text = 'Expected: ' . ($selected_event['name'] ?? 'N/A') . ' | Found: ' . $att['event_name'];
                } elseif ($att['checkin_status'] === 'checked_in') {
                    $scan_result = 'manual_lookup_duplicate';
                    $type = 'warning';
                    $status_text = '⚠️ Already Checked-in';
                } else {
                    db_execute("UPDATE attendees SET checkin_status='checked_in', checkin_at=NOW() WHERE id=?", [$att['id']]);
                    $scan_result = 'manual_lookup_checked_in';
                    $type = 'success';
                    $status_text = '✅ Checked In';
                }
                db_execute("INSERT INTO scan_logs (attendee_id, qr_code_id, event_id, scanned_by, scan_result, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)", [$att['id'], $att['qr_code_id'], $att['event_id'], $_SESSION['admin_id'] ?? null, $scan_result, $_SERVER['REMOTE_ADDR'], substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                send_json_response(['type' => $type, 'status' => $status_text, 'name' => $att['full_name'], 'event' => $event_text, 'qr' => $att['qr_code_id'], 'scan_result' => $scan_result]);
            } else {
                $qr_code_id_from_lookup = 'LOOKUP-' . substr(hash('sha256', $q), 0, 10);
                db_execute("INSERT INTO scan_logs (attendee_id, qr_code_id, event_id, scanned_by, scan_result, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)", [null, $qr_code_id_from_lookup, $selected_event_id ?: null, $_SESSION['admin_id'] ?? null, 'manual_lookup_not_found', $_SERVER['REMOTE_ADDR'], substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                send_json_response(['type' => 'error', 'status' => '❌ Not Registered', 'name' => 'Unknown', 'event' => '—', 'qr' => $q, 'scan_result' => 'manual_lookup_not_found']);
            }
            break;

        default:
            send_json_response(['success' => false, 'message' => 'API endpoint not found.'], 404);
            break;
    }
} catch (Exception $e) {
    // Catch any unexpected errors and return a generic server error.
    send_json_response(['success' => false, 'message' => 'An internal server error occurred: ' . $e->getMessage()], 500);
}