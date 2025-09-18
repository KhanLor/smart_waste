<?php
require_once '../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'collector') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Collector';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Routes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body class="role-collector">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar text-dark p-0">
                <div class="p-3">
                    <h4><?php echo APP_NAME; ?></h4>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link text-dark" href="index.php"><i class="fas fa-truck me-2"></i>Dashboard</a>
                        <a class="nav-link text-dark active" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
                        <a class="nav-link text-dark" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
                        <a class="nav-link text-dark" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
                        <a class="nav-link text-dark" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        <a class="nav-link text-dark" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>My Routes</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
                    </div>
                    <form id="filters" class="row g-2 align-items-end mb-3">
                        <div class="col-sm-4">
                            <label for="day" class="form-label">Day</label>
                            <select id="day" class="form-select">
                                <option value="today">Today</option>
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" class="form-select">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <button id="apply" type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button>
                        </div>
                    </form>
                    <div class="card">
                        <div class="card-body">
                            <div id="summary" class="mb-2 small text-muted"></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Area / Street</th>
                                            <th>Waste</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function loadRoutes() {
        const day = document.getElementById('day').value;
        const status = document.getElementById('status').value;
        const url = new URL('../../api/get_collector_tasks.php', window.location.origin);
        url.searchParams.set('day', day);
        if (status) url.searchParams.set('status', status);
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('rows');
        const summary = document.getElementById('summary');
        tbody.innerHTML = '';
        if (data.success && data.tasks.length) {
            summary.textContent = `${data.count} tasks for ${data.day}`;
            for (const t of data.tasks) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${t.collection_time}</strong></td>
                    <td>${t.area || ''} ${t.street_name ? '- ' + t.street_name : ''}</td>
                    <td><span class="badge bg-info text-dark">${t.waste_type}</span></td>
                    <td><span class="badge bg-secondary text-uppercase">${(t.status || '').replace('_',' ')}</span></td>
                `;
                tbody.appendChild(tr);
            }
        } else {
            summary.textContent = 'No tasks found.';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'text-center text-muted';
            td.textContent = 'No tasks.';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    document.getElementById('filters').addEventListener('submit', (e) => { e.preventDefault(); loadRoutes(); });
    document.addEventListener('DOMContentLoaded', loadRoutes);
    </script>
</body>
</html>


