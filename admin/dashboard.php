<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Définir formatFCFA si non définie


$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

// ==================== VÉRIFIER ET CRÉER LA TABLE NOTIFICATIONS SI NÉCESSAIRE ====================
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

// Vérifier si la colonne 'lu' existe
try {
    $db->query("SELECT lu FROM notifications LIMIT 1");
} catch (PDOException $e) {
    $db->exec("ALTER TABLE notifications ADD COLUMN lu TINYINT(1) DEFAULT 0");
}

// ==================== STATISTIQUES ====================
$stats = [];

// Nombre total de chambres
$stmt = $db->query("SELECT COUNT(*) as total FROM chambres");
$stats['total_chambres'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Chambres disponibles
$stmt = $db->query("SELECT COUNT(*) as total FROM chambres WHERE disponible = 1");
$stats['chambres_disponibles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Chambres occupées
$stats['chambres_occupees'] = $stats['total_chambres'] - $stats['chambres_disponibles'];

// Nombre d'étudiants
$stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'etudiant'");
$stats['total_etudiants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Réservations en attente
$stmt = $db->query("SELECT COUNT(*) as total FROM reservations WHERE statut = 'en_attente'");
$stats['reservations_attente'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Réservations confirmées
$stmt = $db->query("SELECT COUNT(*) as total FROM reservations WHERE statut = 'confirmee'");
$stats['reservations_confirmees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Réservations du mois
$stmt = $db->query("SELECT COUNT(*) as total FROM reservations WHERE MONTH(date_reservation) = MONTH(CURRENT_DATE()) AND YEAR(date_reservation) = YEAR(CURRENT_DATE())");
$stats['reservations_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Revenus du mois
$stmt = $db->query("SELECT COALESCE(SUM(montant), 0) as total FROM paiements WHERE statut = 'complete' AND MONTH(date_paiement) = MONTH(CURRENT_DATE()) AND YEAR(date_paiement) = YEAR(CURRENT_DATE())");
$stats['revenus_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Revenus totaux
$stmt = $db->query("SELECT COALESCE(SUM(montant), 0) as total FROM paiements WHERE statut = 'complete'");
$stats['revenus_totaux'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Nombre de cités
$stmt = $db->query("SELECT COUNT(*) as total FROM cites");
$stats['total_cites'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Taux d'occupation
$stats['taux_occupation'] = $stats['total_chambres'] > 0 ? round(($stats['chambres_occupees'] / $stats['total_chambres']) * 100) : 0;

// ==================== NOTIFICATIONS ====================
// CORRECTION : Compter correctement les notifications non lues pour l'admin
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE utilisateur_id = ? AND lu = 0");
$stmt->execute([$admin_id]);
$notifications_non_lues = $stmt->fetchColumn();

// Récupérer les 5 dernières notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_creation DESC LIMIT 5");
$stmt->execute([$admin_id]);
$dernieres_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== DERNIÈRES RÉSERVATIONS ====================
$query = "SELECT r.*, u.nom, u.prenom, u.email, c.numero_chambre, c.type_chambre, ct.nom as cite_nom 
          FROM reservations r 
          JOIN utilisateurs u ON r.utilisateur_id = u.id 
          JOIN chambres c ON r.chambre_id = c.id 
          JOIN cites ct ON c.cite_id = ct.id 
          ORDER BY r.date_reservation DESC LIMIT 5";
$stmt = $db->query($query);
$dernieres_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== DERNIERS PAIEMENTS ====================
$query = "SELECT p.*, u.nom, u.prenom, r.id as reservation_id, c.numero_chambre
          FROM paiements p
          JOIN reservations r ON p.reservation_id = r.id
          JOIN utilisateurs u ON r.utilisateur_id = u.id
          JOIN chambres c ON r.chambre_id = c.id
          ORDER BY p.date_paiement DESC LIMIT 5";
$stmt = $db->query($query);
$derniers_paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== STATISTIQUES PAR CITÉ ====================
$query = "SELECT ct.nom, ct.ville, 
          COUNT(c.id) as nb_chambres,
          SUM(CASE WHEN c.disponible = 1 THEN 1 ELSE 0 END) as nb_disponibles
          FROM cites ct
          LEFT JOIN chambres c ON ct.id = c.cite_id
          GROUP BY ct.id
          ORDER BY ct.nom";
$stmt = $db->query($query);
$stats_cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="bi bi-speedometer2"></i> Tableau de Bord Administrateur
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active">Tableau de bord</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notification Badge avec dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($notifications_non_lues > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $notifications_non_lues; ?>
                                    <span class="visually-hidden">notifications non lues</span>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 350px; max-height: 400px; overflow-y: auto;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="bi bi-bell"></i> Notifications récentes</h6>
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                            </div>
                            <?php if (empty($dernieres_notifications)): ?>
                                <p class="text-muted text-center py-3 mb-0">Aucune notification</p>
                            <?php else: ?>
                                <?php foreach ($dernieres_notifications as $notif): 
                                    $icon = 'bell';
                                    $color = 'primary';
                                    if ($notif['type'] == 'reservation') { $icon = 'calendar-check'; $color = 'info'; }
                                    elseif ($notif['type'] == 'paiement') { $icon = 'credit-card'; $color = 'success'; }
                                    elseif ($notif['type'] == 'alerte') { $icon = 'exclamation-triangle'; $color = 'danger'; }
                                    $msg = str_replace(['€', 'EUR', 'euros'], 'FCFA', $notif['message']);
                                ?>
                                    <div class="dropdown-item <?php echo $notif['lu'] == 0 ? 'bg-light' : ''; ?> rounded mb-1 p-2">
                                        <div class="d-flex">
                                            <div class="me-2">
                                                <span class="badge bg-<?php echo $color; ?> p-2">
                                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($notif['date_creation'])); ?></small>
                                                <p class="mb-0 small"><?php echo htmlspecialchars(substr($msg, 0, 60)) . '...'; ?></p>
                                            </div>
                                            <?php if ($notif['lu'] == 0): ?>
                                                <span class="badge bg-warning ms-1" style="height: 10px; width: 10px; padding: 0;"></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="notifications.php" class="dropdown-item text-center text-primary">
                                <i class="bi bi-arrow-right"></i> Voir toutes les notifications
                            </a>
                        </div>
                    </div>
                    
                    <!-- Date du jour -->
                    <span class="text-muted">
                        <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="row mb-4">
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-white-50">Chambres</h6>
                            <h3 class="mb-0"><?php echo $stats['total_chambres']; ?></h3>
                            <small>Total</small>
                        </div>
                        <i class="bi bi-door-open display-6 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card bg-success text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-white-50">Disponibles</h6>
                            <h3 class="mb-0"><?php echo $stats['chambres_disponibles']; ?></h3>
                            <small>Chambres</small>
                        </div>
                        <i class="bi bi-check-circle display-6 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card bg-info text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-white-50">Étudiants</h6>
                            <h3 class="mb-0"><?php echo $stats['total_etudiants']; ?></h3>
                            <small>Inscrits</small>
                        </div>
                        <i class="bi bi-people display-6 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card bg-warning text-dark h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">En attente</h6>
                            <h3 class="mb-0"><?php echo $stats['reservations_attente']; ?></h3>
                            <small>Réservations</small>
                        </div>
                        <i class="bi bi-clock display-6 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card bg-secondary text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-white-50">Revenus</h6>
                            <h3 class="mb-0"><?php echo formatFCFA($stats['revenus_mois']); ?></h3>
                            <small>Ce mois</small>
                        </div>
                        <i class="bi bi-cash-stack display-6 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge-fill text-warning"></i> Actions Rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="gestion-chambres.php" class="btn btn-outline-primary">
                            <i class="bi bi-door-open"></i> Gérer les Chambres
                        </a>
                        <a href="gestion-reservations.php" class="btn btn-outline-success">
                            <i class="bi bi-calendar-check"></i> Gérer les Réservations
                        </a>
                        <a href="gestion-utilisateurs.php" class="btn btn-outline-info">
                            <i class="bi bi-people"></i> Gérer les Utilisateurs
                        </a>
                        <a href="gestion-cites.php" class="btn btn-outline-warning">
                            <i class="bi bi-building"></i> Gérer les Cités
                        </a>
                        <a href="gestion-paiements.php" class="btn btn-outline-secondary">
                            <i class="bi bi-credit-card"></i> Gérer les Paiements
                        </a>
                        <a href="rapports.php" class="btn btn-outline-danger">
                            <i class="bi bi-file-text"></i> Rapports
                        </a>
                        <a href="ajouter-chambre.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Ajouter une Chambre
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche -->
        <div class="col-lg-8">
            <!-- Dernières réservations -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Dernières Réservations</h5>
                    <a href="gestion-reservations.php" class="btn btn-sm btn-light">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Étudiant</th>
                                    <th>Chambre</th>
                                    <th>Dates</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dernieres_reservations)): ?>
                                    <tr><td colspan="7" class="text-center py-3 text-muted">Aucune réservation</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dernieres_reservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo $res['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($res['prenom'] . ' ' . substr($res['nom'], 0, 1) . '.'); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($res['email']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($res['numero_chambre']); ?>
                                            <br><small><?php echo htmlspecialchars($res['cite_nom']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($res['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($res['date_fin'])); ?></small>
                                        </td>
                                        <td><?php echo formatFCFA($res['montant_total']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $res['statut'] == 'confirmee' ? 'success' : 
                                                    ($res['statut'] == 'en_attente' ? 'warning' : 
                                                    ($res['statut'] == 'annulee' ? 'danger' : 'secondary')); 
                                            ?>"><?php echo ucfirst($res['statut']); ?></span>
                                        </td>
                                        <td>
                                            <a href="details-reservation.php?id=<?php echo $res['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Derniers paiements -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-credit-card"></i> Derniers Paiements</h5>
                    <a href="gestion-paiements.php" class="btn btn-sm btn-light">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Étudiant</th>
                                    <th>Réservation</th>
                                    <th>Montant</th>
                                    <th>Méthode</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($derniers_paiements)): ?>
                                    <tr><td colspan="6" class="text-center py-3 text-muted">Aucun paiement</td></tr>
                                <?php else: ?>
                                    <?php foreach ($derniers_paiements as $p): ?>
                                    <tr>
                                        <td>#<?php echo $p['id']; ?></td>
                                        <td><?php echo htmlspecialchars($p['prenom'] . ' ' . substr($p['nom'], 0, 1) . '.'); ?></td>
                                        <td>#<?php echo $p['reservation_id']; ?></td>
                                        <td class="<?php echo $p['montant'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo formatFCFA($p['montant']); ?></td>
                                        <td>
                                            <?php 
                                            $methodes = ['carte_credit' => 'Carte', 'orange_money' => 'Orange Money', 'mobile_money' => 'MTN', 'virement' => 'Virement', 'especes' => 'Espèces'];
                                            echo $methodes[$p['methode_paiement']] ?? $p['methode_paiement'];
                                            ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($p['date_paiement'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne droite -->
        <div class="col-lg-4">
            <!-- Occupations par cité -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-building"></i> Occupations par Cité</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($stats_cites as $cite): 
                        $taux = $cite['nb_chambres'] > 0 ? round((($cite['nb_chambres'] - $cite['nb_disponibles']) / $cite['nb_chambres']) * 100) : 0;
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><strong><?php echo htmlspecialchars($cite['nom']); ?></strong></span>
                                <span class="text-muted small"><?php echo ($cite['nb_chambres'] - $cite['nb_disponibles']); ?>/<?php echo $cite['nb_chambres']; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-<?php echo $taux > 80 ? 'danger' : ($taux > 50 ? 'warning' : 'success'); ?>" 
                                     role="progressbar" style="width: <?php echo $taux; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $taux; ?>% d'occupation</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Résumé financier -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Résumé Financier</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><td>Revenus ce mois</td><td class="text-end"><strong><?php echo formatFCFA($stats['revenus_mois']); ?></strong></td></tr>
                        <tr><td>Revenus totaux</td><td class="text-end"><strong><?php echo formatFCFA($stats['revenus_totaux']); ?></strong></td></tr>
                        <tr><td>Réservations ce mois</td><td class="text-end"><strong><?php echo $stats['reservations_mois']; ?></strong></td></tr>
                        <tr><td>Réservations confirmées</td><td class="text-end"><strong><?php echo $stats['reservations_confirmees']; ?></strong></td></tr>
                        <tr><td>Cités universitaires</td><td class="text-end"><strong><?php echo $stats['total_cites']; ?></strong></td></tr>
                    </table>
                </div>
            </div>

            <!-- Raccourcis -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-link"></i> Raccourcis</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="notifications.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-bell"></i> Notifications</span>
                            <?php if ($notifications_non_lues > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $notifications_non_lues; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="profil.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-gear"></i> Mon Profil
                        </a>
                        <a href="../auth/logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="bi bi-box-arrow-right"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    border-radius: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
}
.progress {
    border-radius: 10px;
    background-color: #e9ecef;
}
.table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge {
    font-weight: 500;
    padding: 5px 10px;
}
.dropdown-menu {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    border-radius: 12px;
}
.dropdown-item:hover {
    background-color: #f8f9fa;
}
.btn-outline-primary, .btn-outline-success, .btn-outline-info, 
.btn-outline-warning, .btn-outline-secondary, .btn-outline-danger {
    border-width: 2px;
    font-weight: 500;
    border-radius: 8px;
}
@media (max-width: 768px) {
    .card-body { padding: 15px; }
    h3 { font-size: 1.5rem; }
}
</style>

<?php include '../includes/footer.php'; ?>