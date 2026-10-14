<?php
// backend/master_data/manage_routes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $action = sanitize($_POST['action'] ?? '');
    $routeId = (int)($_POST['route_id'] ?? 0);
    $routeName = '';

    try {
        $db = Database::getConnection();

        if ($action === 'create' || $action === 'update') {
            $routeName = trim((string)($_POST['route_name'] ?? ''));
            $rate = (float)($_POST['rate'] ?? 0.00);
            $toll = (float)($_POST['toll'] ?? 0.00);
            $effectiveDate = sanitize($_POST['effective_date'] ?? date('Y-m-d'));
            $status = sanitize($_POST['status'] ?? 'ACTIVE');

            if (empty($routeName)) {
                $_SESSION['error'] = "Route name is required.";
                header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php?tab=routes");
                exit;
            }

            if (strlen($routeName) > 100) {
                throw new InvalidArgumentException('Route names must be 100 characters or fewer.');
            }

            $duplicateStmt = $db->prepare('SELECT route_name FROM routes
                                           WHERE lower(trim(route_name)) = lower(trim(:name))
                                           AND id <> :id
                                           LIMIT 1');
            $duplicateStmt->execute([
                ':name' => $routeName,
                ':id' => $action === 'update' ? $routeId : 0
            ]);
            $duplicateName = $duplicateStmt->fetchColumn();
            if ($duplicateName !== false) {
                throw new InvalidArgumentException("A route named '{$duplicateName}' already exists. Use a unique route name.");
            }

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO routes (route_name, rate, toll, effective_date, status) VALUES (:name, :rate, :toll, :effective_date, :status)");
                $stmt->execute([
                    ':name'           => $routeName,
                    ':rate'           => $rate,
                    ':toll'           => $toll,
                    ':effective_date' => $effectiveDate,
                    ':status'         => $status
                ]);
                log_activity($userId, "Create Route", null, "Created route: {$routeName}");
                $_SESSION['success'] = "Route '{$routeName}' created successfully.";
            } else {
                $stmt = $db->prepare("UPDATE routes SET route_name = :name, rate = :rate, toll = :toll, effective_date = :effective_date, status = :status WHERE id = :id");
                $stmt->execute([
                    ':name'           => $routeName,
                    ':rate'           => $rate,
                    ':toll'           => $toll,
                    ':effective_date' => $effectiveDate,
                    ':status'         => $status,
                    ':id'             => $routeId
                ]);
                log_activity($userId, "Update Route", null, "Updated route ID: {$routeId}");
                $_SESSION['success'] = "Route details updated successfully.";
            }

        } elseif ($action === 'toggle_status') {
            $status = sanitize($_POST['status'] ?? 'INACTIVE');
            $stmt = $db->prepare("UPDATE routes SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $routeId]);

            log_activity($userId, "Toggle Route Status", null, "Route ID {$routeId} set to {$status}");
            $_SESSION['success'] = "Route status updated to {$status}.";
        }

        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php?tab=routes");
        exit;

    } catch (InvalidArgumentException $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php?tab=routes");
        exit;
    } catch (PDOException $e) {
        error_log("Manage Routes Error: " . $e->getMessage());
        $sqliteError = (string)($e->errorInfo[2] ?? '');
        if (stripos($sqliteError, 'UNIQUE constraint failed: routes.route_name') !== false) {
            $_SESSION['error'] = "A route named '{$routeName}' already exists. Enter a different route name.";
        } elseif (stripos($sqliteError, 'CHECK constraint failed') !== false) {
            $_SESSION['error'] = 'One of the route values is not allowed. Check the submitted status and amounts.';
        } else {
            $_SESSION['error'] = 'Unable to save route. Database error: ' . ($sqliteError !== '' ? $sqliteError : 'unknown SQLite error');
        }
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php?tab=routes");
        exit;
    } catch (Exception $e) {
        error_log("Manage Routes Error: " . $e->getMessage());
        $_SESSION['error'] = 'Unable to process the route operation. Please try again.';
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php?tab=routes");
        exit;
    }
}