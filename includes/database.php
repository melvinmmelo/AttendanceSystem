<?php
// ============================================================
//  AttendQR — Database (PDO Singleton)
// ============================================================

class Database {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT .
                   ';dbname=' . DB_NAME . ';charset=utf8mb4';
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Be AJAX-aware. Return JSON for AJAX requests, and an HTML error page for normal page loads.
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    http_response_code(500);
                    die(json_encode(['success' => false, 'message' => 'Database Connection Error: ' . $e->getMessage()]));
                } else {
                    die('<div style="font-family:sans-serif;padding:40px;color:#ff6584;">
                        <h2>Database Connection Error</h2>
                        <p>' . htmlspecialchars($e->getMessage()) . '</p>
                        <p>Please check your database configuration in <code>includes/config.php</code></p>
                    </div>');
                }
            }
        }
        return self::$instance;
    }
}

// ============================================================
//  Helper: run a query and return results
// ============================================================
function db_query($sql, $params = []) {
    $stmt = Database::getInstance()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_row($sql, $params = []) {
    $stmt = Database::getInstance()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_execute($sql, $params = []) {
    $stmt = Database::getInstance()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_count($sql, $params = []) {
    $stmt = Database::getInstance()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function db_last_insert_id() {
    return (int) Database::getInstance()->lastInsertId();
}
