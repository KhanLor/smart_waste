<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Check if user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$full_name = $username;

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's waste reports
$stmt = $conn->prepare("SELECT * FROM waste_reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports = $stmt->get_result();

// Get user's collection schedule
$stmt = $conn->prepare("
    SELECT cs.* FROM collection_schedules cs 
    WHERE cs.street_name LIKE ? OR cs.area LIKE ?
    ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')
");
$address_search = '%' . $user['address'] . '%';
$stmt->bind_param("ss", $address_search, $address_search);
$stmt->execute();
$schedules = $stmt->get_result();

// Get next collection
$next_collection = null;
if ($schedules->num_rows > 0) {
    $schedules->data_seek(0);
    while ($schedule = $schedules->fetch_assoc()) {
        $today = strtolower(date('l'));
        if ($schedule['collection_day'] === $today) {
            $next_collection = $schedule;
            break;
        }
    }
}

// Get unread notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        .nav-link {
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
        }
        .schedule-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }
        .report-item {
            border-left: 4px solid #007bff;
        }
        .report-item.high {
            border-left-color: #dc3545;
        }
        .report-item.medium {
            border-left-color: #ffc107;
        }
        .report-item.low {
            border-left-color: #28a745;
        }
    </style>
</head>
<body class="role-resident">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-recycle me-2"></i><?php echo APP_NAME; ?></h4>
                    <hr class="bg-white">
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active" href="index.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white" href="reports.php">
                            <i class="fas fa-exclamation-circle me-2"></i>My Reports
                        </a>
                        <a class="nav-link text-white" href="submit_report.php">
                            <i class="fas fa-plus-circle me-2"></i>Submit Report
                        </a>
                        <a class="nav-link text-white" href="schedule.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedule
                        </a>
                        <a class="nav-link text-white" href="points.php">
                            <i class="fas fa-leaf me-2"></i>Eco Points
                        </a>
                        <a class="nav-link text-white" href="feedback.php">
                            <i class="fas fa-comment me-2"></i>Feedback
                        </a>
                        <a class="nav-link text-white" href="chat.php">
                            <i class="fas fa-comments me-2"></i>Chat
                            <?php if ($notifications->num_rows > 0): ?>
                                <span class="notification-badge"><?php echo $notifications->num_rows; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link text-white" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profile
                        </a>
                        <hr class="bg-white">
                        <a class="nav-link text-white" href="../../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Welcome back, <?php echo e($user['first_name']); ?>!</h2>
                            <p class="text-muted mb-0">Here's what's happening with your waste management</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Next Collection</h6>
                                            <?php if ($next_collection): ?>
                                                <h4 class="mb-0"><?php echo ucfirst($next_collection['collection_day']); ?></h4>
                                                <small><?php echo format_ph_date($next_collection['collection_time'], 'g:i A'); ?></small>
                                            <?php else: ?>
                                                <h4 class="mb-0">No Schedule</h4>
                                                <small>Check your area</small>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fas fa-truck fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card stat-card warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Active Reports</h6>
                                            <h4 class="mb-0"><?php echo $reports->num_rows; ?></h4>
                                            <small>Pending resolution</small>
                                        </div>
                                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card stat-card success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Eco Points</h6>
                                            <h4 class="mb-0"><?php echo $user['eco_points']; ?></h4>
                                            <small>Keep earning!</small>
                                        </div>
                                        <i class="fas fa-leaf fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <a href="submit_report.php" class="btn btn-primary w-100">
                                                <i class="fas fa-exclamation-circle me-2"></i>Report Issue
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="schedule.php" class="btn btn-success w-100">
                                                <i class="fas fa-calendar me-2"></i>View Schedule
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="feedback.php" class="btn btn-info w-100">
                                                <i class="fas fa-comment me-2"></i>Give Feedback
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="chat.php" class="btn btn-warning w-100">
                                                <i class="fas fa-comments me-2"></i>Chat Support
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reports and Schedule -->
                    <div class="row">
                        <!-- Recent Reports -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Recent Reports</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($reports->num_rows > 0): ?>
                                        <?php while ($report = $reports->fetch_assoc()): ?>
                                            <div class="report-item <?php echo $report['priority']; ?> p-3 mb-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($report['title']); ?></h6>
                                                        <small class="text-muted"><?php echo e($report['location']); ?></small>
                                                        <br>
                                                        <span class="badge bg-<?php echo $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                                            <?php echo ucfirst($report['priority']); ?>
                                                        </span>
                                                        <span class="badge bg-secondary ms-1"><?php echo ucfirst($report['status']); ?></span>
                                                    </div>
                                                    <small class="text-muted"><?php echo format_ph_date($report['created_at'], 'M j'); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No reports yet. Submit your first report!</p>
                                    <?php endif; ?>
                                    <div class="text-center mt-3">
                                        <a href="reports.php" class="btn btn-outline-primary btn-sm">View All Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collection Schedule -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>This Week's Schedule</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($schedules->num_rows > 0): ?>
                                        <?php 
                                        $schedules->data_seek(0);
                                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                        foreach ($days as $day):
                                            $schedules->data_seek(0);
                                            $daySchedule = null;
                                            while ($schedule = $schedules->fetch_assoc()) {
                                                if ($schedule['collection_day'] === $day) {
                                                    $daySchedule = $schedule;
                                                    break;
                                                }
                                            }
                                        ?>
                                            <div class="schedule-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo ucfirst($day); ?></h6>
                                                        <?php if ($daySchedule): ?>
                                                            <small class="text-muted">
                                                                <?php echo e($daySchedule['waste_type']); ?> - 
                                                                <?php echo format_ph_date($daySchedule['collection_time'], 'g:i A'); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No collection scheduled</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($daySchedule): ?>
                                                        <span class="badge bg-success"><?php echo ucfirst($daySchedule['waste_type']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No schedule found for your area.</p>
                                    <?php endif; ?>
                                    <div class="text-center mt-3">
                                        <a href="schedule.php" class="btn btn-outline-success btn-sm">View Full Schedule</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <?php if ($notifications->num_rows > 0): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Recent Notifications</h5>
                                </div>
                                <div class="card-body">
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <div class="alert alert-<?php echo $notification['type'] === 'success' ? 'success' : ($notification['type'] === 'warning' ? 'warning' : ($notification['type'] === 'error' ? 'danger' : 'info')); ?> alert-dismissible fade show" role="alert">
                                            <strong><?php echo e($notification['title']); ?></strong>
                                            <br>
                                            <?php echo e($notification['message']); ?>
                                            <small class="d-block text-muted mt-1">
                                                <?php echo format_ph_date($notification['created_at']); ?>
                                            </small>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/notifications.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/register_sw.js"></script>
</body>
</html>
