<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est étudiant
if (!isLoggedIn()) {
    $_SESSION['error'] = "Veuillez vous connecter pour accéder à cette page";
    redirect('../auth/login.php');
}

if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// ==================== GESTION DES ACTIONS ====================
$message = '';
$error = '';

// Marquer une notification comme lue
if (isset($_GET['marquer_lu']) && is_numeric($_GET['marquer_lu'])) {
    $notif_id = intval($_GET['marquer_lu']);
    $stmt = $db->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?");
    if ($stmt->execute([$notif_id, $user_id])) {
        $message = "Notification marquée comme lue";
    }
}

// Marquer toutes les notifications comme lues
if (isset($_POST['marquer_tout_lu'])) {
    $stmt = $db->prepare("UPDATE notifications SET lu = 1 WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $message = "Toutes les notifications ont été marquées comme lues";
}

// Supprimer une notification
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    $notif_id = intval($_GET['supprimer']);
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND utilisateur_id = ?");
    if ($stmt->execute([$notif_id, $user_id])) {
        $message = "Notification supprimée";
    }
}

// Supprimer toutes les notifications lues
if (isset($_POST['supprimer_lues'])) {
    $stmt = $db->prepare("DELETE FROM notifications WHERE utilisateur_id = ? AND lu = 1");
    $stmt->execute([$user_id]);
    $message = "Toutes les notifications lues ont été supprimées";
}

// Supprimer toutes les notifications
if (isset($_POST['supprimer_tout'])) {
    $stmt = $db->prepare("DELETE FROM notifications WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $message = "Toutes les notifications ont été supprimées";
}

// ==================== FILTRES ====================
$filtre = $_GET['filtre'] ?? 'toutes';
$type_filtre = $_GET['type'] ?? '';

$where = ["utilisateur_id = ?"];
$params = [$user_id];

if ($filtre == 'non_lues') {
    $where[] = "lu = 0";
} elseif ($filtre == 'lues') {
    $where[] = "lu = 1";
}

if (!empty($type_filtre)) {
    $where[] = "type = ?";
    $params[] = $type_filtre;
}

$whereClause = implode(" AND ", $where);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Compter le total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $limit);

// Récupérer les notifications
$query = "SELECT * FROM notifications WHERE $whereClause ORDER BY date_creation DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) as non_lues,
    SUM(CASE WHEN lu = 1 THEN 1 ELSE 0 END) as lues,
    SUM(CASE WHEN type = 'reservation' THEN 1 ELSE 0 END) as reservations,
    SUM(CASE WHEN type = 'paiement' THEN 1 ELSE 0 END) as paiements,
    SUM(CASE WHEN type = 'systeme' THEN 1 ELSE 0 END) as systeme,
    SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as messages
    FROM notifications WHERE utilisateur_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Types de notifications disponibles
$stmt = $db->prepare("SELECT DISTINCT type FROM notifications WHERE utilisateur_id = ?");
$stmt->execute([$user_id]);
$types_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="bi bi-bell-fill text-warning"></i> 
                        Mes Notifications
                        <?php if ($stats['non_lues'] > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $stats['non_lues']; ?> non lue(s)</span>
                        <?php endif; ?>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active">Notifications</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stats-container">
                <div class="stat-card <?php echo $filtre == 'toutes' && !$type_filtre ? 'active' : ''; ?>">
                    <a href="notifications.php" class="text-decoration-none">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-bell"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?php echo $stats['total']; ?></span>
                            <span class="stat-label">Toutes</span>
                        </div>
                    </a>
                </div>
                <div class="stat-card <?php echo $filtre == 'non_lues' ? 'active' : ''; ?>">
                    <a href="notifications.php?filtre=non_lues" class="text-decoration-none">
                        <div class="stat-icon bg-danger">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?php echo $stats['non_lues']; ?></span>
                            <span class="stat-label">Non lues</span>
                        </div>
                    </a>
                </div>
                <div class="stat-card <?php echo $filtre == 'lues' ? 'active' : ''; ?>">
                    <a href="notifications.php?filtre=lues" class="text-decoration-none">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?php echo $stats['lues']; ?></span>
                            <span class="stat-label">Lues</span>
                        </div>
                    </a>
                </div>
                <div class="stat-card <?php echo $type_filtre == 'reservation' ? 'active' : ''; ?>">
                    <a href="notifications.php?type=reservation" class="text-decoration-none">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?php echo $stats['reservations']; ?></span>
                            <span class="stat-label">Réservations</span>
                        </div>
                    </a>
                </div>
                <div class="stat-card <?php echo $type_filtre == 'paiement' ? 'active' : ''; ?>">
                    <a href="notifications.php?type=paiement" class="text-decoration-none">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?php echo $stats['paiements']; ?></span>
                            <span class="stat-label">Paiements</span>
                        </div>
                    </a>
                </div>
                <div class="stat-card <?php echo $type_filtre == 'message' ? 'active' : ''; ?>">
                    <a href="notifications.php?type=message" class="text-decoration-none">
                        <div class="stat-icon bg-secondary">
                            <i class="bi bi-chat"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?php echo $stats['messages']; ?></span>
                            <span class="stat-label">Messages</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et actions -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="filtre" value="<?php echo $filtre; ?>">
                <select class="form-select" name="type" style="width: auto;" onchange="this.form.submit()">
                    <option value="">Tous les types</option>
                    <?php foreach ($types_disponibles as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $type_filtre == $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($type_filtre): ?>
                    <a href="notifications.php?filtre=<?php echo $filtre; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-6">
            <div class="d-flex justify-content-end gap-2">
                <?php if ($stats['non_lues'] > 0): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="marquer_tout_lu" class="btn btn-outline-success">
                            <i class="bi bi-check-all"></i> Tout marquer comme lu
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($stats['lues'] > 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer toutes les notifications lues ?');">
                        <button type="submit" name="supprimer_lues" class="btn btn-outline-danger">
                            <i class="bi bi-trash"></i> Supprimer les lues
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($stats['total'] > 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer TOUTES les notifications ? Cette action est irréversible.');">
                        <button type="submit" name="supprimer_tout" class="btn btn-outline-danger">
                            <i class="bi bi-trash-fill"></i> Tout supprimer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Liste des notifications -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash"></i>
                    <h3>Aucune notification</h3>
                    <p class="text-muted">Vous n'avez pas de notifications pour le moment.</p>
                    <a href="dashboard.php" class="btn btn-primary mt-2">
                        <i class="bi bi-arrow-left"></i> Retour au tableau de bord
                    </a>
                </div>
            <?php else: ?>
                <div class="notifications-timeline">
                    <?php 
                    $current_date = null;
                    foreach ($notifications as $notif): 
                        $notif_date = date('Y-m-d', strtotime($notif['date_creation']));
                        $afficher_date = false;
                        
                        if ($current_date != $notif_date) {
                            $current_date = $notif_date;
                            $afficher_date = true;
                        }
                        
                        // Déterminer l'icône et la couleur selon le type
                        $icon = 'bell';
                        $color = 'primary';
                        switch ($notif['type']) {
                            case 'reservation':
                                $icon = 'calendar-check';
                                $color = 'info';
                                break;
                            case 'paiement':
                                $icon = 'credit-card';
                                $color = 'success';
                                break;
                            case 'systeme':
                                $icon = 'gear';
                                $color = 'secondary';
                                break;
                            case 'message':
                                $icon = 'chat';
                                $color = 'warning';
                                break;
                        }
                    ?>
                    
                    <?php if ($afficher_date): ?>
                        <div class="timeline-date">
                            <span>
                                <?php 
                                if ($notif_date == date('Y-m-d')) {
                                    echo "Aujourd'hui";
                                } elseif ($notif_date == date('Y-m-d', strtotime('-1 day'))) {
                                    echo "Hier";
                                } else {
                                    echo date('d/m/Y', strtotime($notif_date));
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="notification-item <?php echo !$notif['lu'] ? 'unread' : ''; ?>" 
                         data-notif-id="<?php echo $notif['id']; ?>">
                        <div class="notification-icon bg-<?php echo $color; ?>">
                            <i class="bi bi-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-header">
                                <span class="notification-type">
                                    <?php echo ucfirst($notif['type']); ?>
                                </span>
                                <span class="notification-time">
                                    <i class="bi bi-clock"></i>
                                    <?php echo date('H:i', strtotime($notif['date_creation'])); ?>
                                </span>
                            </div>
                            <p class="notification-message">
                                <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                            </p>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notif['lu']): ?>
                                <span class="badge bg-warning unread-badge">
                                    <i class="bi bi-circle-fill"></i> Nouveau
                                </span>
                            <?php endif; ?>
                            <div class="btn-group">
                                <?php if (!$notif['lu']): ?>
                                    <a href="?marquer_lu=<?php echo $notif['id']; ?>&filtre=<?php echo $filtre; ?>&type=<?php echo $type_filtre; ?>" 
                                       class="btn btn-sm btn-outline-success" 
                                       title="Marquer comme lu"
                                       data-bs-toggle="tooltip">
                                        <i class="bi bi-check-lg"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?supprimer=<?php echo $notif['id']; ?>&filtre=<?php echo $filtre; ?>&type=<?php echo $type_filtre; ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   title="Supprimer"
                                   data-bs-toggle="tooltip"
                                   onclick="return confirm('Supprimer cette notification ?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php
                            $url_params = $_GET;
                            unset($url_params['page']);
                            $base_url = 'notifications.php?' . http_build_query($url_params);
                            if ($base_url != 'notifications.php?') $base_url .= '&';
                            ?>
                            
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Statistiques */
.stats-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.stat-card {
    flex: 1;
    min-width: 100px;
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.stat-card.active {
    border-color: #007A5E;
    background-color: #f0fff4;
}

.stat-card a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: inherit;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 22px;
    font-weight: bold;
    line-height: 1.2;
}

.stat-label {
    font-size: 13px;
    color: #6c757d;
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 80px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #495057;
    margin-bottom: 10px;
}

/* Timeline des notifications */
.notifications-timeline {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    padding: 20px;
}

.timeline-date {
    text-align: center;
    margin: 20px 0 15px;
    position: relative;
}

.timeline-date:first-child {
    margin-top: 0;
}

.timeline-date span {
    background: #e9ecef;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    color: #495057;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    border-radius: 12px;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    margin-bottom: 5px;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #fff8e1;
    border-left-color: #FCD116;
}

.notification-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 5px;
}

.notification-type {
    font-weight: 600;
    font-size: 14px;
    color: #495057;
}

.notification-time {
    font-size: 12px;
    color: #6c757d;
}

.notification-time i {
    margin-right: 4px;
}

.notification-message {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
    line-height: 1.5;
}

.notification-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

.unread-badge {
    font-size: 11px;
    padding: 4px 8px;
}

.unread-badge i {
    font-size: 8px;
    margin-right: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-container {
        flex-wrap: wrap;
    }
    
    .stat-card {
        min-width: calc(33.333% - 10px);
    }
    
    .notification-item {
        flex-wrap: wrap;
    }
    
    .notification-actions {
        flex-direction: row;
        width: 100%;
        justify-content: flex-end;
        margin-top: 10px;
    }
    
    .timeline-date span {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .stat-card {
        min-width: calc(50% - 10px);
    }
    
    .stat-icon {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }
    
    .stat-value {
        font-size: 18px;
    }
}

/* Pagination */
.pagination .page-item.active .page-link {
    background-color: #007A5E;
    border-color: #007A5E;
}

.pagination .page-link {
    color: #007A5E;
}

.pagination .page-link:hover {
    color: #005a45;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.notification-item {
    animation: slideIn 0.3s ease-out;
}

/* Tooltips */
[data-bs-toggle="tooltip"] {
    cursor: pointer;
}
</style>

<script>
// Activer les tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.notification-item');
    items.forEach((item, index) => {
        item.style.animationDelay = (index * 0.05) + 's';
    });
});

// Mise à jour du titre avec le nombre de notifications non lues
function updateTitle() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    if (unreadCount > 0) {
        document.title = '(' + unreadCount + ') Notifications - Cité U Ngaoundéré';
    } else {
        document.title = 'Notifications - Cité U Ngaoundéré';
    }
}

updateTitle();

// Marquer comme lu au clic (optionnel)
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function(e) {
        // Ne pas déclencher si on clique sur un bouton
        if (e.target.closest('a') || e.target.closest('button')) return;
        
        const notifId = this.dataset.notifId;
        if (this.classList.contains('unread')) {
            // Rediriger pour marquer comme lu
            window.location.href = '?marquer_lu=' + notifId;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>