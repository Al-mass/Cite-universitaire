<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error'] = "Accès réservé aux administrateurs";
    redirect('../auth/login.php');
}

// Définir formatFCFA si non définie
if (!function_exists('formatFCFA')) {
    function formatFCFA($montant, $avec_symbole = true) {
        if ($montant === null || $montant === '') {
            $montant = 0;
        }
        $formatted = number_format(floatval($montant), 0, ',', ' ');
        return $avec_symbole ? $formatted . ' FCFA' : $formatted;
    }
}

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

$success = '';
$error = '';

// ==================== TRAITEMENT DES ACTIONS ====================

// Confirmer une réservation
if (isset($_GET['action']) && $_GET['action'] == 'confirmer' && isset($_GET['id'])) {
    $reservation_id = intval($_GET['id']);
    
    try {
        $db->beginTransaction();
        
        // Vérifier que la réservation existe
        $stmt = $db->prepare("SELECT r.*, c.id as chambre_id, c.numero_chambre, u.nom, u.prenom, u.email 
                              FROM reservations r
                              JOIN chambres c ON r.chambre_id = c.id
                              JOIN utilisateurs u ON r.utilisateur_id = u.id
                              WHERE r.id = ?");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            throw new Exception("Réservation non trouvée");
        }
        
        if ($reservation['statut'] != 'en_attente') {
            throw new Exception("Cette réservation n'est pas en attente");
        }
        
        // Vérifier que la chambre est toujours disponible
        $stmt = $db->prepare("SELECT disponible FROM chambres WHERE id = ?");
        $stmt->execute([$reservation['chambre_id']]);
        $disponible = $stmt->fetchColumn();
        
        if (!$disponible) {
            throw new Exception("La chambre n'est plus disponible");
        }
        
        // Mettre à jour le statut de la réservation
        $stmt = $db->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = ?");
        $stmt->execute([$reservation_id]);
        
        // Marquer la chambre comme occupée pour la période
        $stmt = $db->prepare("UPDATE chambres SET disponible = 0 WHERE id = ?");
        $stmt->execute([$reservation['chambre_id']]);
        
        // Envoyer une notification à l'étudiant
        if (function_exists('envoyerNotification')) {
            $message = "Votre réservation #$reservation_id pour la chambre " . $reservation['numero_chambre'] . 
                      " a été confirmée ! Vous pouvez maintenant procéder au paiement.";
            envoyerNotification($reservation['utilisateur_id'], 'reservation', $message);
        }
        
        // Envoyer un email de confirmation
        $to = $reservation['email'];
        $subject = "[Cité U Ngaoundéré] Réservation #$reservation_id confirmée";
        $body = "
        <html>
        <head><style>body{font-family:Arial;}.header{background:#007A5E;color:white;padding:20px;}</style></head>
        <body>
            <div class='header'><h2>Réservation Confirmée !</h2></div>
            <p>Bonjour " . $reservation['prenom'] . ",</p>
            <p>Votre réservation #$reservation_id pour la chambre " . $reservation['numero_chambre'] . " a été confirmée.</p>
            <p>Vous pouvez maintenant procéder au paiement depuis votre espace étudiant.</p>
            <p>Montant total : " . formatFCFA($reservation['montant_total']) . "</p>
            <p>Cordialement,<br>Cité Universitaire de Ngaoundéré</p>
        </body>
        </html>";
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: contact@univ-ndere.cm";
        @mail($to, $subject, $body, $headers);
        
        $db->commit();
        $success = "Réservation #$reservation_id confirmée avec succès !";
        
        // Journaliser
        if (function_exists('logAction')) {
            logAction('confirmation_reservation', $admin_id, "Réservation #$reservation_id confirmée");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Annuler une réservation
if (isset($_GET['action']) && $_GET['action'] == 'annuler' && isset($_GET['id'])) {
    $reservation_id = intval($_GET['id']);
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT r.*, u.nom, u.prenom FROM reservations r 
                              JOIN utilisateurs u ON r.utilisateur_id = u.id WHERE r.id = ?");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            throw new Exception("Réservation non trouvée");
        }
        
        // Mettre à jour le statut
        $stmt = $db->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?");
        $stmt->execute([$reservation_id]);
        
        // Libérer la chambre si elle était confirmée
        if ($reservation['statut'] == 'confirmee') {
            $stmt = $db->prepare("UPDATE chambres SET disponible = 1 WHERE id = ?");
            $stmt->execute([$reservation['chambre_id']]);
        }
        
        // Notifier l'étudiant
        if (function_exists('envoyerNotification')) {
            $message = "Votre réservation #$reservation_id a été annulée. Contactez l'administration pour plus d'informations.";
            envoyerNotification($reservation['utilisateur_id'], 'reservation', $message);
        }
        
        $db->commit();
        $success = "Réservation #$reservation_id annulée";
        
        logAction('annulation_reservation', $admin_id, "Réservation #$reservation_id annulée");
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// ==================== FILTRES ====================
$filtre = $_GET['filtre'] ?? 'en_attente';
$recherche = $_GET['recherche'] ?? '';

$where = [];
$params = [];

if ($filtre == 'en_attente') {
    $where[] = "r.statut = 'en_attente'";
} elseif ($filtre == 'confirmees') {
    $where[] = "r.statut = 'confirmee'";
} elseif ($filtre == 'annulees') {
    $where[] = "r.statut = 'annulee'";
} elseif ($filtre == 'terminees') {
    $where[] = "r.statut = 'terminee'";
}

if (!empty($recherche)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR c.numero_chambre LIKE ? OR r.id LIKE ?)";
    $search = '%' . $recherche . '%';
    $params = array_merge($params, [$search, $search, $search, $search, $search]);
}

$whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

// Récupérer les réservations
$query = "SELECT r.*, u.nom, u.prenom, u.email, u.telephone,
          c.numero_chambre, c.type_chambre, c.prix_mensuel,
          ct.nom as cite_nom, ct.ville,
          (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
          FROM reservations r
          JOIN utilisateurs u ON r.utilisateur_id = u.id
          JOIN chambres c ON r.chambre_id = c.id
          JOIN cites ct ON c.cite_id = ct.id
          $whereClause
          ORDER BY r.date_reservation DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
    SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) as confirmees,
    SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as annulees,
    SUM(CASE WHEN statut = 'terminee' THEN 1 ELSE 0 END) as terminees
    FROM reservations");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="bi bi-check-circle-fill text-success"></i> 
                        Confirmation des Réservations
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item"><a href="gestion-reservations.php">Réservations</a></li>
                            <li class="breadcrumb-item active">Confirmation</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="gestion-reservations.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Toutes les réservations
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <a href="?filtre=en_attente" class="text-decoration-none">
                    <div class="stat-icon bg-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['en_attente']; ?></span>
                        <span class="stat-label">En attente</span>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <a href="?filtre=confirmees" class="text-decoration-none">
                    <div class="stat-icon bg-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['confirmees']; ?></span>
                        <span class="stat-label">Confirmées</span>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <a href="?filtre=annulees" class="text-decoration-none">
                    <div class="stat-icon bg-danger">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['annulees']; ?></span>
                        <span class="stat-label">Annulées</span>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <a href="?filtre=terminees" class="text-decoration-none">
                    <div class="stat-icon bg-secondary">
                        <i class="bi bi-flag"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['terminees']; ?></span>
                        <span class="stat-label">Terminées</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="btn-group">
                <a href="?filtre=en_attente" class="btn btn-outline-warning <?php echo $filtre == 'en_attente' ? 'active' : ''; ?>">
                    <i class="bi bi-hourglass-split"></i> En attente
                </a>
                <a href="?filtre=confirmees" class="btn btn-outline-success <?php echo $filtre == 'confirmees' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle"></i> Confirmées
                </a>
                <a href="?filtre=annulees" class="btn btn-outline-danger <?php echo $filtre == 'annulees' ? 'active' : ''; ?>">
                    <i class="bi bi-x-circle"></i> Annulées
                </a>
                <a href="?filtre=terminees" class="btn btn-outline-secondary <?php echo $filtre == 'terminees' ? 'active' : ''; ?>">
                    <i class="bi bi-flag"></i> Terminées
                </a>
            </div>
        </div>
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <input type="hidden" name="filtre" value="<?php echo $filtre; ?>">
                <input type="text" class="form-control" name="recherche" 
                       placeholder="Rechercher par nom, email, chambre..." 
                       value="<?php echo htmlspecialchars($recherche); ?>">
                <button type="submit" class="btn btn-primary ms-2">
                    <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($recherche)): ?>
                    <a href="?filtre=<?php echo $filtre; ?>" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Liste des réservations -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>Aucune réservation</h3>
                    <p>Aucune réservation ne correspond à vos critères.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Étudiant</th>
                                <th>Chambre</th>
                                <th>Dates</th>
                                <th>Montant</th>
                                <th>Payé</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): 
                                $reste = $res['montant_total'] - $res['total_paye'];
                            ?>
                            <tr>
                                <td><strong>#<?php echo $res['id']; ?></strong></td>
                                <td>
                                    <div class="student-info">
                                        <span class="student-name"><?php echo htmlspecialchars($res['prenom'] . ' ' . $res['nom']); ?></span>
                                        <span class="student-email"><?php echo htmlspecialchars($res['email']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-number"><?php echo htmlspecialchars($res['numero_chambre']); ?></span>
                                    <span class="room-cite"><?php echo htmlspecialchars($res['cite_nom']); ?></span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo date('d/m/Y', strtotime($res['date_debut'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($res['date_fin'])); ?>
                                    </small>
                                </td>
                                <td><?php echo formatFCFA($res['montant_total']); ?></td>
                                <td>
                                    <div class="payment-progress">
                                        <span class="<?php echo $res['total_paye'] > 0 ? 'text-success' : 'text-muted'; ?>">
                                            <?php echo formatFCFA($res['total_paye']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $res['statut']; ?>">
                                        <?php 
                                        if ($res['statut'] == 'en_attente') echo '<i class="bi bi-hourglass-split"></i> En attente';
                                        elseif ($res['statut'] == 'confirmee') echo '<i class="bi bi-check-circle"></i> Confirmée';
                                        elseif ($res['statut'] == 'annulee') echo '<i class="bi bi-x-circle"></i> Annulée';
                                        elseif ($res['statut'] == 'terminee') echo '<i class="bi bi-flag"></i> Terminée';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="details-reservation.php?id=<?php echo $res['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           data-bs-toggle="tooltip" title="Voir détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if ($res['statut'] == 'en_attente'): ?>
                                            <a href="?action=confirmer&id=<?php echo $res['id']; ?>&filtre=<?php echo $filtre; ?>" 
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Confirmer cette réservation ? L\'étudiant sera notifié.');"
                                               data-bs-toggle="tooltip" title="Confirmer">
                                                <i class="bi bi-check-lg"></i>
                                            </a>
                                            <a href="?action=annuler&id=<?php echo $res['id']; ?>&filtre=<?php echo $filtre; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Annuler cette réservation ?');"
                                               data-bs-toggle="tooltip" title="Annuler">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        <?php elseif ($res['statut'] == 'confirmee'): ?>
                                            <a href="?action=annuler&id=<?php echo $res['id']; ?>&filtre=<?php echo $filtre; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Annuler cette réservation ? La chambre sera libérée.');"
                                               data-bs-toggle="tooltip" title="Annuler">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    transition: all 0.3s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}
.stat-card a {
    display: flex;
    align-items: center;
    gap: 15px;
    color: inherit;
}
.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.2;
    color: #2d3748;
}
.stat-label {
    font-size: 14px;
    color: #718096;
}

/* Table */
.modern-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.modern-table thead th {
    background: #f8fafc;
    font-weight: 600;
    color: #4a5568;
    border-bottom: 1px solid #e2e8f0;
    padding: 15px;
}
.modern-table tbody td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}
.modern-table tbody tr:hover {
    background: #f7fafc;
}

/* Student Info */
.student-info {
    display: flex;
    flex-direction: column;
}
.student-name {
    font-weight: 600;
    color: #2d3748;
}
.student-email {
    font-size: 12px;
    color: #718096;
}

/* Room Info */
.room-number {
    font-weight: 600;
    color: #2d3748;
}
.room-cite {
    display: block;
    font-size: 12px;
    color: #718096;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}
.status-en_attente {
    background: #fefcbf;
    color: #744210;
}
.status-confirmee {
    background: #c6f6d5;
    color: #22543d;
}
.status-annulee {
    background: #fed7d7;
    color: #9b2c2c;
}
.status-terminee {
    background: #e2e8f0;
    color: #4a5568;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.empty-state i {
    font-size: 60px;
    color: #cbd5e0;
    margin-bottom: 20px;
}
.empty-state h3 {
    color: #4a5568;
    margin-bottom: 10px;
}
.empty-state p {
    color: #a0aec0;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 15px;
    }
    .action-buttons {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Activer les tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(el) { return new bootstrap.Tooltip(el); });
});
</script>

<?php include '../includes/footer.php'; ?>