<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est étudiant
if (!isLoggedIn() || isAdmin()) {
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
$user_id = $_SESSION['user_id'];

// ==================== FILTRES ====================
$statut_filter = $_GET['statut'] ?? '';
$annee_filter = $_GET['annee'] ?? '';
$mois_filter = $_GET['mois'] ?? '';
$recherche = $_GET['recherche'] ?? '';
$tri = $_GET['tri'] ?? 'date_desc';

// Construction de la requête avec filtres
$where = ["r.utilisateur_id = ?"];
$params = [$user_id];

if (!empty($statut_filter)) {
    $where[] = "r.statut = ?";
    $params[] = $statut_filter;
}

if (!empty($annee_filter)) {
    $where[] = "YEAR(r.date_reservation) = ?";
    $params[] = $annee_filter;
}

if (!empty($mois_filter)) {
    $where[] = "MONTH(r.date_reservation) = ?";
    $params[] = $mois_filter;
}

if (!empty($recherche)) {
    $where[] = "(c.numero_chambre LIKE ? OR ct.nom LIKE ? OR r.id LIKE ?)";
    $search = '%' . $recherche . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$whereClause = implode(" AND ", $where);

// Ordre de tri
$orderBy = "r.date_reservation DESC";
switch ($tri) {
    case 'date_asc': $orderBy = "r.date_reservation ASC"; break;
    case 'montant_desc': $orderBy = "r.montant_total DESC"; break;
    case 'montant_asc': $orderBy = "r.montant_total ASC"; break;
    case 'date_desc': default: $orderBy = "r.date_reservation DESC";
}

// ==================== STATISTIQUES ====================
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) as confirmees,
    SUM(CASE WHEN statut = 'terminee' THEN 1 ELSE 0 END) as terminees,
    SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as annulees,
    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
    COALESCE(SUM(montant_total), 0) as total_depense,
    AVG(montant_total) as moyenne_depense
    FROM reservations WHERE utilisateur_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ==================== RÉCUPÉRATION DES RÉSERVATIONS ====================
$query = "SELECT r.*, c.numero_chambre, c.type_chambre, c.prix_mensuel, c.image as chambre_image,
          ct.nom as cite_nom, ct.ville,
          (SELECT note FROM evaluations WHERE utilisateur_id = ? AND chambre_id = r.chambre_id) as evaluation_note,
          (SELECT commentaire FROM evaluations WHERE utilisateur_id = ? AND chambre_id = r.chambre_id) as evaluation_commentaire,
          (SELECT COUNT(*) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as paiements_effectues,
          (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
          FROM reservations r
          JOIN chambres c ON r.chambre_id = c.id
          JOIN cites ct ON c.cite_id = ct.id
          WHERE $whereClause
          ORDER BY $orderBy";

$stmt = $db->prepare($query);
$params_full = array_merge([$user_id, $user_id], $params);
$stmt->execute($params_full);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== ANNÉES ET MOIS DISPONIBLES ====================
$stmt = $db->prepare("SELECT DISTINCT YEAR(date_reservation) as annee 
                      FROM reservations WHERE utilisateur_id = ? ORDER BY annee DESC");
$stmt->execute([$user_id]);
$annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

$mois_francais = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

// ==================== STATISTIQUES PAR MOIS (GRAPHIQUE) ====================
$stmt = $db->prepare("SELECT 
    DATE_FORMAT(date_reservation, '%Y-%m') as mois,
    COUNT(*) as nb_reservations,
    SUM(montant_total) as total_montant
    FROM reservations 
    WHERE utilisateur_id = ? AND date_reservation >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mois ORDER BY mois DESC LIMIT 12");
$stmt->execute([$user_id]);
$stats_mensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="history-page">
    <!-- En-tête -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="page-title">
                        <i class="bi bi-clock-history"></i>
                        Historique des Réservations
                    </h1>
                    <p class="page-subtitle">
                        Consultez l'historique complet de vos séjours et paiements
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="mes-reservations.php" class="btn btn-light btn-lg">
                        <i class="bi bi-calendar-check"></i> Réservations actuelles
                    </a>
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
        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total réservations</span>
                </div>
            </div>
            <div class="stat-card confirmed">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['confirmees']; ?></span>
                    <span class="stat-label">Confirmées</span>
                </div>
            </div>
            <div class="stat-card completed">
                <div class="stat-icon"><i class="bi bi-flag"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['terminees']; ?></span>
                    <span class="stat-label">Terminées</span>
                </div>
            </div>
            <div class="stat-card cancelled">
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['annulees']; ?></span>
                    <span class="stat-label">Annulées</span>
                </div>
            </div>
            <div class="stat-card spent">
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo formatFCFA($stats['total_depense']); ?></span>
                    <span class="stat-label">Total dépensé</span>
                </div>
            </div>
            <div class="stat-card average">
                <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo formatFCFA($stats['moyenne_depense']); ?></span>
                    <span class="stat-label">Moyenne / réservation</span>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filter-bar">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><i class="bi bi-funnel"></i> Statut</label>
                    <select class="form-select" name="statut" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <option value="confirmee" <?php echo $statut_filter == 'confirmee' ? 'selected' : ''; ?>>Confirmées</option>
                        <option value="terminee" <?php echo $statut_filter == 'terminee' ? 'selected' : ''; ?>>Terminées</option>
                        <option value="annulee" <?php echo $statut_filter == 'annulee' ? 'selected' : ''; ?>>Annulées</option>
                        <option value="en_attente" <?php echo $statut_filter == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><i class="bi bi-calendar-year"></i> Année</label>
                    <select class="form-select" name="annee" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        <?php foreach ($annees as $annee): ?>
                            <option value="<?php echo $annee; ?>" <?php echo $annee_filter == $annee ? 'selected' : ''; ?>>
                                <?php echo $annee; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><i class="bi bi-calendar-month"></i> Mois</label>
                    <select class="form-select" name="mois" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <?php foreach ($mois_francais as $num => $nom): ?>
                            <option value="<?php echo $num; ?>" <?php echo $mois_filter == $num ? 'selected' : ''; ?>>
                                <?php echo $nom; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><i class="bi bi-arrow-down-up"></i> Trier par</label>
                    <select class="form-select" name="tri" onchange="this.form.submit()">
                        <option value="date_desc" <?php echo $tri == 'date_desc' ? 'selected' : ''; ?>>Plus récent</option>
                        <option value="date_asc" <?php echo $tri == 'date_asc' ? 'selected' : ''; ?>>Plus ancien</option>
                        <option value="montant_desc" <?php echo $tri == 'montant_desc' ? 'selected' : ''; ?>>Montant ↓</option>
                        <option value="montant_asc" <?php echo $tri == 'montant_asc' ? 'selected' : ''; ?>>Montant ↑</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="bi bi-search"></i> Recherche</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="recherche" 
                               placeholder="N° chambre, cité..." value="<?php echo htmlspecialchars($recherche); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if (!empty($recherche) || !empty($statut_filter) || !empty($annee_filter) || !empty($mois_filter)): ?>
                            <a href="historique-reservations.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Graphique mensuel -->
        <?php if (!empty($stats_mensuelles)): ?>
        <div class="chart-card">
            <div class="chart-header">
                <h5><i class="bi bi-bar-chart"></i> Évolution des réservations (12 derniers mois)</h5>
            </div>
            <div class="chart-body">
                <canvas id="reservationsChart" height="80"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des réservations -->
        <div class="reservations-container">
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h3>Aucune réservation trouvée</h3>
                    <p>Aucune réservation ne correspond à vos critères de recherche.</p>
                    <a href="historique-reservations.php" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Réinitialiser les filtres
                    </a>
                </div>
            <?php else: ?>
                <?php 
                $currentMonth = null;
                foreach ($reservations as $index => $res): 
                    $resMonth = date('Y-m', strtotime($res['date_reservation']));
                    $showMonth = $currentMonth != $resMonth;
                    $currentMonth = $resMonth;
                    
                    // Calculs
                    $total_paye = $res['total_paye'] ?? 0;
                    $reste_a_payer = $res['montant_total'] - $total_paye;
                    $pourcentage_paye = $res['montant_total'] > 0 ? round(($total_paye / $res['montant_total']) * 100) : 0;
                    
                    $date1 = new DateTime($res['date_debut']);
                    $date2 = new DateTime($res['date_fin']);
                    $interval = $date1->diff($date2);
                    $duree = $interval->days;
                ?>
                    <?php if ($showMonth): ?>
                        <div class="month-divider">
                            <span>
                                <?php 
                                $dateObj = DateTime::createFromFormat('!m', substr($resMonth, 5, 2));
                                echo $mois_francais[intval(substr($resMonth, 5, 2))] . ' ' . substr($resMonth, 0, 4);
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="reservation-card <?php echo $res['statut']; ?>" style="animation-delay: <?php echo $index * 0.05; ?>s">
                        <div class="reservation-header">
                            <div class="reservation-id">
                                <span class="id-label">Réservation</span>
                                <span class="id-value">#<?php echo $res['id']; ?></span>
                            </div>
                            <div class="reservation-status">
                                <span class="status-badge status-<?php echo $res['statut']; ?>">
                                    <?php if ($res['statut'] == 'confirmee'): ?>
                                        <i class="bi bi-check-circle"></i> Confirmée
                                    <?php elseif ($res['statut'] == 'terminee'): ?>
                                        <i class="bi bi-flag"></i> Terminée
                                    <?php elseif ($res['statut'] == 'annulee'): ?>
                                        <i class="bi bi-x-circle"></i> Annulée
                                    <?php else: ?>
                                        <i class="bi bi-clock"></i> En attente
                                    <?php endif; ?>
                                </span>
                                <span class="reservation-date">
                                    <i class="bi bi-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($res['date_reservation'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="reservation-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="room-info">
                                        <div class="room-image">
                                            <?php if (!empty($res['chambre_image'])): ?>
                                                <img src="../<?php echo $res['chambre_image']; ?>" alt="Chambre">
                                            <?php else: ?>
                                                <div class="room-placeholder">
                                                    <i class="bi bi-door-open"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="room-details">
                                            <h6>Chambre <?php echo htmlspecialchars($res['numero_chambre']); ?></h6>
                                            <span class="room-type"><?php echo ucfirst($res['type_chambre']); ?></span>
                                            <p class="room-location">
                                                <i class="bi bi-geo-alt"></i>
                                                <?php echo htmlspecialchars($res['cite_nom']); ?> - 
                                                <?php echo htmlspecialchars($res['ville']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="stay-info">
                                        <div class="info-item">
                                            <i class="bi bi-calendar3"></i>
                                            <div>
                                                <label>Arrivée</label>
                                                <span><?php echo date('d/m/Y', strtotime($res['date_debut'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <i class="bi bi-calendar3"></i>
                                            <div>
                                                <label>Départ</label>
                                                <span><?php echo date('d/m/Y', strtotime($res['date_fin'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <i class="bi bi-hourglass"></i>
                                            <div>
                                                <label>Durée</label>
                                                <span><?php echo $duree; ?> jour<?php echo $duree > 1 ? 's' : ''; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="payment-info">
                                        <div class="payment-total">
                                            <label>Montant total</label>
                                            <span class="total-amount"><?php echo formatFCFA($res['montant_total']); ?></span>
                                        </div>
                                        <div class="payment-progress">
                                            <div class="progress-label">
                                                <span>Payé</span>
                                                <span><?php echo formatFCFA($total_paye); ?></span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: <?php echo $pourcentage_paye; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php if ($reste_a_payer > 0 && $res['statut'] != 'annulee'): ?>
                                            <span class="remaining-amount">Reste <?php echo formatFCFA($reste_a_payer); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <div class="reservation-actions">
                                        <a href="../chambres/details.php?id=<?php echo $res['chambre_id']; ?>" 
                                           class="action-btn" data-bs-toggle="tooltip" title="Voir la chambre">
                                            <i class="bi bi-door-open"></i>
                                        </a>
                                        
                                        <?php if ($res['statut'] == 'terminee' && !$res['evaluation_note']): ?>
                                            <a href="evaluations.php?chambre_id=<?php echo $res['chambre_id']; ?>" 
                                               class="action-btn highlight" data-bs-toggle="tooltip" title="Évaluer">
                                                <i class="bi bi-star"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($res['evaluation_note']): ?>
                                            <div class="evaluation-badge" data-bs-toggle="tooltip" 
                                                 title="Note: <?php echo $res['evaluation_note']; ?>/5">
                                                <i class="bi bi-star-fill"></i> <?php echo $res['evaluation_note']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($res['evaluation_commentaire']): ?>
                            <div class="evaluation-comment">
                                <i class="bi bi-chat-quote"></i>
                                "<?php echo htmlspecialchars($res['evaluation_commentaire']); ?>"
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
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
    color: rgba(255,255,255,0.8);
    font-size: 1.1rem;
    margin-bottom: 0;
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
    transition: all 0.3s;
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
.stat-card.total .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-card.confirmed .stat-icon { background: linear-gradient(135deg, #48bb78, #38a169); }
.stat-card.completed .stat-icon { background: linear-gradient(135deg, #4299e1, #3182ce); }
.stat-card.cancelled .stat-icon { background: linear-gradient(135deg, #f56565, #e53e3e); }
.stat-card.spent .stat-icon { background: linear-gradient(135deg, #ed8936, #dd6b20); }
.stat-card.average .stat-icon { background: linear-gradient(135deg, #9f7aea, #805ad5); }
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

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

/* Chart Card */
.chart-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}
.chart-header {
    margin-bottom: 15px;
}
.chart-header h5 {
    font-weight: 600;
    color: #2d3748;
    margin: 0;
}

/* Reservations Container */
.reservations-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

/* Month Divider */
.month-divider {
    text-align: center;
    margin: 25px 0 20px;
    position: relative;
}
.month-divider:first-child {
    margin-top: 0;
}
.month-divider span {
    background: #edf2f7;
    padding: 6px 20px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    color: #4a5568;
}

/* Reservation Card */
.reservation-card {
    background: #fafafa;
    border-radius: 16px;
    margin-bottom: 20px;
    overflow: hidden;
    border-left: 4px solid #cbd5e0;
    transition: all 0.3s;
    animation: slideIn 0.4s ease-out forwards;
    opacity: 0;
}
.reservation-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transform: translateX(3px);
}
.reservation-card.confirmee { border-left-color: #48bb78; }
.reservation-card.terminee { border-left-color: #4299e1; }
.reservation-card.annulee { border-left-color: #f56565; opacity: 0.7; }
.reservation-card.en_attente { border-left-color: #ecc94b; }

.reservation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
}
.reservation-id {
    display: flex;
    align-items: center;
    gap: 10px;
}
.id-label {
    font-size: 12px;
    text-transform: uppercase;
    color: #a0aec0;
}
.id-value {
    font-size: 18px;
    font-weight: 700;
    color: #2d3748;
}
.reservation-status {
    display: flex;
    align-items: center;
    gap: 15px;
}
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}
.status-confirmee { background: #c6f6d5; color: #22543d; }
.status-terminee { background: #bee3f8; color: #2b6cb0; }
.status-annulee { background: #fed7d7; color: #9b2c2c; }
.status-en_attente { background: #fefcbf; color: #744210; }
.reservation-date {
    color: #718096;
    font-size: 14px;
}

.reservation-body {
    padding: 20px;
}

/* Room Info */
.room-info {
    display: flex;
    gap: 15px;
}
.room-image {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
}
.room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.room-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 30px;
}
.room-details h6 {
    margin: 0 0 5px;
    font-weight: 600;
    color: #2d3748;
}
.room-type {
    display: inline-block;
    background: #e2e8f0;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    margin-bottom: 8px;
}
.room-location {
    margin: 0;
    font-size: 13px;
    color: #718096;
}

/* Stay Info */
.stay-info .info-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}
.stay-info .info-item i {
    color: #a0aec0;
    font-size: 18px;
    margin-top: 2px;
}
.stay-info .info-item label {
    display: block;
    font-size: 11px;
    color: #a0aec0;
    margin-bottom: 2px;
}
.stay-info .info-item span {
    font-weight: 500;
    color: #2d3748;
}

/* Payment Info */
.payment-info {
    padding-right: 10px;
}
.payment-total {
    margin-bottom: 15px;
}
.payment-total label {
    display: block;
    font-size: 11px;
    color: #a0aec0;
    margin-bottom: 2px;
}
.total-amount {
    font-size: 22px;
    font-weight: 700;
    color: #2d3748;
}
.payment-progress {
    margin-bottom: 10px;
}
.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 5px;
}
.progress {
    height: 8px;
    border-radius: 4px;
    background: #e2e8f0;
}
.remaining-amount {
    font-size: 13px;
    color: #e53e3e;
    font-weight: 500;
}

/* Actions */
.reservation-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}
.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #edf2f7;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.2s;
}
.action-btn:hover {
    background: #667eea;
    color: white;
    transform: scale(1.05);
}
.action-btn.highlight {
    background: #ecc94b;
    color: #744210;
}
.evaluation-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #fefcbf;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    color: #744210;
}

/* Evaluation Comment */
.evaluation-comment {
    margin-top: 15px;
    padding: 15px;
    background: #f7fafc;
    border-radius: 12px;
    font-style: italic;
    color: #4a5568;
    border-left: 3px solid #ecc94b;
}
.evaluation-comment i {
    color: #ecc94b;
    margin-right: 8px;
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

/* Animations */
@keyframes slideIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 992px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .page-title { font-size: 1.8rem; }
    .reservation-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .room-info { margin-bottom: 15px; }
    .reservation-actions { flex-direction: row; justify-content: flex-start; margin-top: 15px; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des réservations mensuelles
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reservationsChart')?.getContext('2d');
    if (ctx) {
        const data = <?php echo json_encode(array_reverse($stats_mensuelles)); ?>;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => {
                    const [year, month] = d.mois.split('-');
                    const mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                    return mois[parseInt(month) - 1] + ' ' + year.slice(2);
                }),
                datasets: [{
                    label: 'Nombre de réservations',
                    data: data.map(d => d.nb_reservations),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '# White',
                        titleColor: '#2d3748',
                        bodyColor: '#4a5568',
                        borderColor: '#e2e8f0',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e2e8f0' },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
    
    // Activer les tooltips
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(el) { return new bootstrap.Tooltip(el); });
});
</script>

<?php include '../includes/footer.php'; ?>