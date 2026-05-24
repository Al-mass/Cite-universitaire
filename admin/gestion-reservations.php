<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Filtres
$filtre = $_GET['filtre'] ?? 'toutes';
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
} elseif ($filtre == 'prochaines') {
    $where[] = "r.date_debut >= CURDATE()";
    $where[] = "r.statut IN ('confirmee', 'en_attente')";
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

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="bi bi-calendar-check-fill text-primary"></i> 
                        Gestion des Réservations
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active">Réservations</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="confirmation-reservation.php" class="btn btn-warning">
                        <i class="bi bi-check-circle"></i> À confirmer
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages flash -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-2 col-sm-4 mb-3">
            <a href="?filtre=toutes" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-primary mx-auto">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </a>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <a href="?filtre=en_attente" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-warning mx-auto">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['en_attente']; ?></div>
                    <div class="stat-label">En attente</div>
                </div>
            </a>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <a href="?filtre=confirmees" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-success mx-auto">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['confirmees']; ?></div>
                    <div class="stat-label">Confirmées</div>
                </div>
            </a>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <a href="?filtre=terminees" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-info mx-auto">
                        <i class="bi bi-flag"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['terminees']; ?></div>
                    <div class="stat-label">Terminées</div>
                </div>
            </a>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <a href="?filtre=annulees" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-danger mx-auto">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['annulees']; ?></div>
                    <div class="stat-label">Annulées</div>
                </div>
            </a>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <a href="?filtre=prochaines" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-secondary mx-auto">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value">À venir</div>
                    <div class="stat-label">Prochaines</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="btn-group flex-wrap">
                <a href="?filtre=toutes" class="btn btn-outline-primary <?php echo $filtre == 'toutes' ? 'active' : ''; ?>">
                    Toutes
                </a>
                <a href="?filtre=en_attente" class="btn btn-outline-warning <?php echo $filtre == 'en_attente' ? 'active' : ''; ?>">
                    <i class="bi bi-hourglass-split"></i> En attente
                </a>
                <a href="?filtre=confirmees" class="btn btn-outline-success <?php echo $filtre == 'confirmees' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle"></i> Confirmées
                </a>
                <a href="?filtre=terminees" class="btn btn-outline-info <?php echo $filtre == 'terminees' ? 'active' : ''; ?>">
                    <i class="bi bi-flag"></i> Terminées
                </a>
                <a href="?filtre=annulees" class="btn btn-outline-danger <?php echo $filtre == 'annulees' ? 'active' : ''; ?>">
                    <i class="bi bi-x-circle"></i> Annulées
                </a>
                <a href="?filtre=prochaines" class="btn btn-outline-secondary <?php echo $filtre == 'prochaines' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event"></i> À venir
                </a>
            </div>
        </div>
        <div class="col-md-4">
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
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($reservations)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h5 class="mt-3">Aucune réservation trouvée</h5>
                    <p class="text-muted">Aucune réservation ne correspond à vos critères.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Étudiant</th>
                                <th>Chambre</th>
                                <th>Dates</th>
                                <th>Montant</th>
                                <th>Payé</th>
                                <th>Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): 
                                $reste = $res['montant_total'] - ($res['total_paye'] ?? 0);
                            ?>
                            <tr>
                                <td><strong>#<?php echo $res['id']; ?></strong></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($res['prenom'] . ' ' . $res['nom']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($res['email']); ?></small>
                                 </div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($res['numero_chambre']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($res['cite_nom']); ?></small>
                                 </div>
                                </td>
                                <td>
                                    <small>
                                        <i class="bi bi-calendar3"></i> Arrivée: <?php echo date('d/m/Y', strtotime($res['date_debut'])); ?><br>
                                        <i class="bi bi-calendar-x"></i> Départ: <?php echo date('d/m/Y', strtotime($res['date_fin'])); ?>
                                    </small>
                                 </div>
                                </td>
                                <td><?php echo formatFCFA($res['montant_total']); ?></td>
                                <td>
                                    <span class="<?php echo ($res['total_paye'] ?? 0) > 0 ? 'text-success' : 'text-muted'; ?>">
                                        <?php echo formatFCFA($res['total_paye'] ?? 0); ?>
                                    </span>
                                    <?php if ($reste > 0): ?>
                                        <br><small class="text-danger">Reste: <?php echo formatFCFA($reste); ?></small>
                                    <?php endif; ?>
                                 </div>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch($res['statut']) {
                                        case 'en_attente':
                                            $statusClass = 'warning';
                                            $statusIcon = 'hourglass-split';
                                            break;
                                        case 'confirmee':
                                            $statusClass = 'success';
                                            $statusIcon = 'check-circle';
                                            break;
                                        case 'annulee':
                                            $statusClass = 'danger';
                                            $statusIcon = 'x-circle';
                                            break;
                                        case 'terminee':
                                            $statusClass = 'secondary';
                                            $statusIcon = 'flag';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <i class="bi bi-<?php echo $statusIcon; ?>"></i>
                                        <?php echo ucfirst($res['statut']); ?>
                                    </span>
                                 </div>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="details-reservation.php?id=<?php echo $res['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($res['statut'] == 'en_attente'): ?>
                                            <a href="confirmation-reservation.php?action=confirmer&id=<?php echo $res['id']; ?>&filtre=<?php echo $filtre; ?>" 
                                               class="btn btn-sm btn-outline-success" title="Confirmer"
                                               onclick="return confirm('Confirmer cette réservation ? La chambre sera marquée occupée pour cette période.')">
                                                <i class="bi bi-check-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($res['statut'] != 'annulee' && $res['statut'] != 'terminee'): ?>
                                            <a href="annuler-reservation.php?id=<?php echo $res['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" title="Annuler"
                                               onclick="return confirm('Annuler cette réservation ?')">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin-top: 10px;
}
.stat-label {
    font-size: 13px;
    color: #718096;
}
.btn-group {
    flex-wrap: wrap;
    gap: 5px;
}
@media (max-width: 768px) {
    .stat-card { margin-bottom: 15px; }
    .table-responsive { font-size: 13px; }
    .btn-group .btn { font-size: 12px; padding: 5px 10px; }
}
</style>

<?php include '../includes/footer.php'; ?>