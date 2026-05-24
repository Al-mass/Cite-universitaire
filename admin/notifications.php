<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error'] = "Accès réservé aux administrateurs";
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

// ==================== VÉRIFIER ET CRÉER LA TABLE SI NÉCESSAIRE ====================
try {
    $db->query("SELECT 1 FROM notifications LIMIT 1");
} catch (PDOException $e) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        utilisateur_id INT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'systeme',
        message TEXT NOT NULL,
        lu TINYINT(1) DEFAULT 0,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
}

try {
    $db->query("SELECT lu FROM notifications LIMIT 1");
} catch (PDOException $e) {
    $db->exec("ALTER TABLE notifications ADD COLUMN lu TINYINT(1) DEFAULT 0");
}

// ==================== GESTION DES ACTIONS ====================
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'all';

// Marquer comme lu
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $stmt = $db->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([intval($_GET['read']), $admin_id]);
    $message = "✓ Notification marquée comme lue";
}

// Marquer tout comme lu
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET lu = 1 WHERE utilisateur_id = ? AND lu = 0");
    $stmt->execute([$admin_id]);
    $count = $stmt->rowCount();
    $message = "✓ $count notification(s) marquée(s) comme lue(s)";
}

// Supprimer une notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([intval($_GET['delete']), $admin_id]);
    $message = "✓ Notification supprimée";
}

// Supprimer toutes les notifications lues
if (isset($_POST['clear_read'])) {
    $stmt = $db->prepare("DELETE FROM notifications WHERE utilisateur_id = ? AND lu = 1");
    $stmt->execute([$admin_id]);
    $count = $stmt->rowCount();
    $message = "✓ $count notification(s) supprimée(s)";
}

// ==================== ENVOI DE NOTIFICATION ====================
if (isset($_POST['send_notification'])) {
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $notif_type = $_POST['notif_type'] ?? 'system';
    $notif_message = trim($_POST['notif_message'] ?? '');
    
    if (!empty($notif_message)) {
        $count = 0;
        if ($recipient_type == 'all') {
            $stmt = $db->query("SELECT id FROM utilisateurs WHERE role = 'etudiant' AND statut = 1");
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($students as $sid) {
                $stmt2 = $db->prepare("INSERT INTO notifications (utilisateur_id, type, message) VALUES (?, ?, ?)");
                $stmt2->execute([$sid, $notif_type, $notif_message]);
                $count++;
            }
        } elseif ($recipient_type == 'single' && $recipient_id > 0) {
            $stmt = $db->prepare("INSERT INTO notifications (utilisateur_id, type, message) VALUES (?, ?, ?)");
            $stmt->execute([$recipient_id, $notif_type, $notif_message]);
            $count = 1;
        }
        $message = "✓ Notification envoyée à $count étudiant(s)";
    } else {
        $error = "Le message ne peut pas être vide";
    }
}

// ==================== STATISTIQUES ====================
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN lu = 1 THEN 1 ELSE 0 END) as read_count,
    SUM(CASE WHEN type = 'reservation' THEN 1 ELSE 0 END) as reservations,
    SUM(CASE WHEN type = 'paiement' THEN 1 ELSE 0 END) as payments,
    SUM(CASE WHEN type = 'system' THEN 1 ELSE 0 END) as system,
    SUM(CASE WHEN type = 'alert' THEN 1 ELSE 0 END) as alerts
    FROM notifications WHERE utilisateur_id = ?");
$stmt->execute([$admin_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ==================== FILTRES ET PAGINATION ====================
$where = ["utilisateur_id = ?"];
$params = [$admin_id];

if ($activeTab == 'unread') {
    $where[] = "lu = 0";
} elseif ($activeTab == 'read') {
    $where[] = "lu = 1";
}

$typeFilter = $_GET['type'] ?? '';
if (!empty($typeFilter)) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}

$whereClause = implode(" AND ", $where);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

$query = "SELECT * FROM notifications WHERE $whereClause ORDER BY date_creation DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== LISTE DES ÉTUDIANTS POUR L'ENVOI ====================
$stmt = $db->query("SELECT id, nom, prenom, email FROM utilisateurs WHERE role = 'etudiant' AND statut = 1 ORDER BY nom");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== TYPES DE NOTIFICATIONS ====================
$notificationTypes = [
    'system' => ['icon' => 'gear', 'color' => 'secondary', 'label' => 'Système'],
    'reservation' => ['icon' => 'calendar-check', 'color' => 'primary', 'label' => 'Réservation'],
    'paiement' => ['icon' => 'credit-card', 'color' => 'success', 'label' => 'Paiement'],
    'alert' => ['icon' => 'exclamation-triangle', 'color' => 'danger', 'label' => 'Alerte'],
    'info' => ['icon' => 'info-circle', 'color' => 'info', 'label' => 'Information']
];

include '../includes/header.php';
?>

<div class="notifications-page">
    <!-- En-tête avec dégradé -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="page-title">
                        <i class="bi bi-bell-fill"></i>
                        Centre de Notifications
                        <?php if ($stats['unread'] > 0): ?>
                            <span class="unread-badge"><?php echo $stats['unread']; ?> non lues</span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">Gérez vos notifications et restez informé en temps réel</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <button class="btn btn-light btn-lg me-2" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                        <i class="bi bi-send-fill"></i> Envoyer
                    </button>
                    <button class="btn btn-outline-light btn-lg" onclick="window.location.href='dashboard.php'">
                        <i class="bi bi-arrow-left"></i> Retour
                    </button>
                </div>
            </div>
        </div>
        <div class="header-wave">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100">
                <path fill="#f8fafc" d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,58.7L1440,64L1440,100L1360,100C1280,100,1120,100,960,100C800,100,640,100,480,100C320,100,160,100,80,100L0,100Z"></path>
            </svg>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show modern-alert" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show modern-alert" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon"><i class="bi bi-bell-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total notifications</span>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['unread']; ?></span>
                    <span class="stat-label">Non lues</span>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['read_count']; ?></span>
                    <span class="stat-label">Lues</span>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['reservations']; ?></span>
                    <span class="stat-label">Réservations</span>
                </div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="bi bi-credit-card-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['payments']; ?></span>
                    <span class="stat-label">Paiements</span>
                </div>
            </div>
            <div class="stat-card pink">
                <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['alerts']; ?></span>
                    <span class="stat-label">Alertes</span>
                </div>
            </div>
        </div>

        <!-- Barre d'outils -->
        <div class="toolbar">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <ul class="nav nav-pills modern-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab == 'all' ? 'active' : ''; ?>" href="?tab=all">
                                <i class="bi bi-inbox"></i> Toutes
                                <span class="badge"><?php echo $stats['total']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab == 'unread' ? 'active' : ''; ?>" href="?tab=unread">
                                <i class="bi bi-envelope"></i> Non lues
                                <span class="badge bg-warning"><?php echo $stats['unread']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab == 'read' ? 'active' : ''; ?>" href="?tab=read">
                                <i class="bi bi-envelope-open"></i> Lues
                                <span class="badge bg-secondary"><?php echo $stats['read_count']; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="action-buttons">
                        <?php if ($stats['unread'] > 0): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="mark_all_read" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-check-all"></i> Tout marquer comme lu
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($stats['read_count'] > 0): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer toutes les notifications lues ?');">
                                <button type="submit" name="clear_read" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash"></i> Nettoyer
                                </button>
                            </form>
                        <?php endif; ?>
                        <div class="dropdown d-inline">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-funnel"></i> Filtrer
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item <?php echo empty($typeFilter) ? 'active' : ''; ?>" href="?tab=<?php echo $activeTab; ?>">Tous les types</a>
                                <div class="dropdown-divider"></div>
                                <?php foreach ($notificationTypes as $key => $type): ?>
                                    <a class="dropdown-item <?php echo $typeFilter == $key ? 'active' : ''; ?>" href="?tab=<?php echo $activeTab; ?>&type=<?php echo $key; ?>">
                                        <i class="bi bi-<?php echo $type['icon']; ?>"></i> <?php echo $type['label']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des notifications -->
        <div class="notifications-feed">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h3>Aucune notification</h3>
                    <p>Vous n'avez pas de notifications pour le moment.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                        <i class="bi bi-send"></i> Envoyer une notification
                    </button>
                </div>
            <?php else: ?>
                <?php 
                $currentDate = null;
                foreach ($notifications as $index => $notif): 
                    $notifDate = date('Y-m-d', strtotime($notif['date_creation']));
                    $showDate = $currentDate != $notifDate;
                    $currentDate = $notifDate;
                    
                    $typeConfig = $notificationTypes[$notif['type']] ?? ['icon' => 'bell', 'color' => 'secondary', 'label' => 'Système'];
                    $isUnread = $notif['lu'] == 0;
                    
                    // Animation delay
                    $animationDelay = $index * 0.05;
                ?>
                    <?php if ($showDate): ?>
                        <div class="date-divider">
                            <span>
                                <?php 
                                if ($notifDate == date('Y-m-d')) echo "Aujourd'hui";
                                elseif ($notifDate == date('Y-m-d', strtotime('-1 day'))) echo "Hier";
                                else echo date('d/m/Y', strtotime($notifDate));
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="notification-card <?php echo $isUnread ? 'unread' : ''; ?>" style="animation-delay: <?php echo $animationDelay; ?>s">
                        <div class="notification-badge bg-<?php echo $typeConfig['color']; ?>">
                            <i class="bi bi-<?php echo $typeConfig['icon']; ?>"></i>
                        </div>
                        <div class="notification-body">
                            <div class="notification-header">
                                <span class="notification-type">
                                    <i class="bi bi-<?php echo $typeConfig['icon']; ?>"></i>
                                    <?php echo $typeConfig['label']; ?>
                                </span>
                                <span class="notification-time">
                                    <i class="bi bi-clock"></i>
                                    <?php echo date('H:i', strtotime($notif['date_creation'])); ?>
                                </span>
                                <?php if ($isUnread): ?>
                                    <span class="unread-indicator">
                                        <span class="pulse"></span> Nouveau
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="notification-message">
                                <?php 
                                $msg = str_replace(['€', 'EUR', 'euros'], 'FCFA', $notif['message']);
                                $msg = preg_replace_callback('/(\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*FCFA/', function($m) {
                                    return number_format(floatval(str_replace(',', '', $m[1])), 0, ',', ' ') . ' FCFA';
                                }, $msg);
                                echo nl2br(htmlspecialchars($msg));
                                ?>
                            </p>
                        </div>
                        <div class="notification-actions">
                            <?php if ($isUnread): ?>
                                <a href="?tab=<?php echo $activeTab; ?>&type=<?php echo $typeFilter; ?>&read=<?php echo $notif['id']; ?>" 
                                   class="action-btn mark-read" data-bs-toggle="tooltip" title="Marquer comme lu">
                                    <i class="bi bi-check-lg"></i>
                                </a>
                            <?php endif; ?>
                            <a href="?tab=<?php echo $activeTab; ?>&type=<?php echo $typeFilter; ?>&delete=<?php echo $notif['id']; ?>" 
                               class="action-btn delete" data-bs-toggle="tooltip" title="Supprimer"
                               onclick="return confirm('Supprimer cette notification ?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <ul class="modern-pagination">
                            <?php
                            $urlParams = $_GET;
                            unset($urlParams['page']);
                            $baseUrl = 'notifications.php?' . http_build_query($urlParams);
                            if ($baseUrl != 'notifications.php?') $baseUrl .= '&';
                            ?>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a href="<?php echo $baseUrl; ?>page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a href="<?php echo $baseUrl; ?>page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Envoyer Notification -->
<div class="modal fade modern-modal" id="sendNotificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-send-fill"></i> Envoyer une notification
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Destinataire</label>
                            <select class="form-select" name="recipient_type" id="recipientType">
                                <option value="all">Tous les étudiants</option>
                                <option value="single">Un étudiant spécifique</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="singleStudentDiv" style="display: none;">
                            <label class="form-label">Sélectionner l'étudiant</label>
                            <select class="form-select" name="recipient_id">
                                <option value="">Choisir...</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['prenom'] . ' ' . $s['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type de notification</label>
                        <div class="type-selector">
                            <?php foreach ($notificationTypes as $key => $type): ?>
                                <div class="type-option">
                                    <input type="radio" name="notif_type" value="<?php echo $key; ?>" id="type_<?php echo $key; ?>" 
                                           <?php echo $key == 'system' ? 'checked' : ''; ?>>
                                    <label for="type_<?php echo $key; ?>" class="type-label bg-<?php echo $type['color']; ?>">
                                        <i class="bi bi-<?php echo $type['icon']; ?>"></i>
                                        <span><?php echo $type['label']; ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="notif_message" rows="4" 
                                  placeholder="Écrivez votre message ici..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="send_notification" class="btn btn-primary">
                        <i class="bi bi-send"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px 0 80px;
    position: relative;
    margin-top: -24px;
}
.page-title {
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 0;
}
.unread-badge {
    background: #FCD116;
    color: #000;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.9rem;
    margin-left: 15px;
}
.header-wave {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    line-height: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    align-items: center;
    gap: 15px;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}
.stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-card.red .stat-icon { background: linear-gradient(135deg, #f093fb, #f5576c); }
.stat-card.green .stat-icon { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #43e97b, #38f9d7); }
.stat-card.orange .stat-icon { background: linear-gradient(135deg, #fa709a, #fee140); }
.stat-card.pink .stat-icon { background: linear-gradient(135deg, #a18cd1, #fbc2eb); }
.stat-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
    color: #2d3748;
}
.stat-label {
    font-size: 13px;
    color: #718096;
}

/* Toolbar */
.toolbar {
    background: white;
    border-radius: 16px;
    padding: 15px 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.modern-tabs .nav-link {
    color: #718096;
    border-radius: 30px;
    padding: 10px 20px;
    margin-right: 10px;
    font-weight: 500;
    transition: all 0.3s;
}
.modern-tabs .nav-link:hover {
    background: #f7fafc;
    color: #667eea;
}
.modern-tabs .nav-link.active {
    background: #667eea;
    color: white;
}
.modern-tabs .nav-link .badge {
    margin-left: 8px;
    background: rgba(255,255,255,0.2);
}
.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Notifications Feed */
.notifications-feed {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.date-divider {
    text-align: center;
    margin: 25px 0 15px;
    position: relative;
}
.date-divider:first-child {
    margin-top: 0;
}
.date-divider span {
    background: #edf2f7;
    padding: 6px 20px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
}
.notification-card {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    padding: 20px;
    border-radius: 16px;
    margin-bottom: 10px;
    transition: all 0.3s;
    animation: slideIn 0.4s ease-out forwards;
    opacity: 0;
    border: 1px solid transparent;
}
.notification-card:hover {
    background: #f7fafc;
    border-color: #e2e8f0;
}
.notification-card.unread {
    background: linear-gradient(90deg, #fef5e7 0%, #fff 100%);
    border-left: 4px solid #FCD116;
}
.notification-badge {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    flex-shrink: 0;
}
.notification-body {
    flex: 1;
}
.notification-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 8px;
}
.notification-type {
    font-weight: 600;
    color: #2d3748;
}
.notification-time {
    font-size: 13px;
    color: #a0aec0;
}
.unread-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #ecc94b;
    font-weight: 600;
}
.pulse {
    width: 8px;
    height: 8px;
    background: #ecc94b;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}
.notification-message {
    margin: 0;
    color: #4a5568;
    line-height: 1.6;
}
.notification-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7fafc;
    color: #718096;
    transition: all 0.2s;
    text-decoration: none;
}
.action-btn:hover {
    transform: scale(1.05);
}
.action-btn.mark-read:hover {
    background: #48bb78;
    color: white;
}
.action-btn.delete:hover {
    background: #f56565;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    color: white;
    font-size: 45px;
}
.empty-state h3 {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
}
.empty-state p {
    color: #718096;
    margin-bottom: 20px;
}

/* Modal */
.modern-modal .modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
}
.modern-modal .modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 20px 25px;
}
.modern-modal .modal-body {
    padding: 25px;
}
.type-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.type-option input {
    display: none;
}
.type-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 20px;
    border-radius: 12px;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
    opacity: 0.6;
}
.type-option input:checked + .type-label {
    opacity: 1;
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.type-label i {
    font-size: 24px;
    margin-bottom: 5px;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}
.modern-pagination {
    display: flex;
    list-style: none;
    padding: 0;
    gap: 8px;
}
.modern-pagination .page-item a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #f7fafc;
    color: #4a5568;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}
.modern-pagination .page-item.active a {
    background: #667eea;
    color: white;
}
.modern-pagination .page-item a:hover {
    background: #667eea;
    color: white;
}
.modern-pagination .page-item.disabled a {
    opacity: 0.5;
    pointer-events: none;
}

/* Animations */
@keyframes slideIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .page-title { font-size: 1.8rem; }
    .notification-card { flex-wrap: wrap; }
    .notification-actions { width: 100%; justify-content: flex-end; }
    .toolbar .row > div { margin-bottom: 10px; }
    .action-buttons { justify-content: flex-start; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>

<script>
document.getElementById('recipientType')?.addEventListener('change', function() {
    document.getElementById('singleStudentDiv').style.display = this.value === 'single' ? 'block' : 'none';
});

// Activer les tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(el) { return new bootstrap.Tooltip(el); });
});
</script>

<?php include '../includes/footer.php'; ?>