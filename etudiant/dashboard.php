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
$user_name = $_SESSION['user_name'] ?? 'Étudiant';

// ==================== STATISTIQUES DE L'ÉTUDIANT ====================
$stats = [];

// Réservations actives
$stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations 
                     WHERE utilisateur_id = ? AND statut IN ('en_attente', 'confirmee')");
$stmt->execute([$user_id]);
$stats['reservations_actives'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Réservations terminées
$stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations 
                     WHERE utilisateur_id = ? AND statut = 'terminee'");
$stmt->execute([$user_id]);
$stats['reservations_terminees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Réservations en attente
$stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations 
                     WHERE utilisateur_id = ? AND statut = 'en_attente'");
$stmt->execute([$user_id]);
$stats['reservations_attente'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total dépensé
$stmt = $db->prepare("SELECT COALESCE(SUM(p.montant), 0) as total FROM paiements p
                     JOIN reservations r ON p.reservation_id = r.id
                     WHERE r.utilisateur_id = ? AND p.statut = 'complete'");
$stmt->execute([$user_id]);
$stats['total_depense'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Notifications non lues
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications 
                     WHERE utilisateur_id = ? AND lu = 0");
$stmt->execute([$user_id]);
$stats['notifications'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Note moyenne donnée
$stmt = $db->prepare("SELECT AVG(note) as moyenne FROM evaluations WHERE utilisateur_id = ?");
$stmt->execute([$user_id]);
$stats['note_moyenne'] = round($stmt->fetch(PDO::FETCH_ASSOC)['moyenne'] ?? 0, 1);

// ==================== PROCHAINE RÉSERVATION ====================
// CORRECTION : Suppression de ct.quartier
$stmt = $db->prepare("SELECT r.*, c.numero_chambre, c.type_chambre, c.prix_mensuel, c.image as chambre_image,
                      ct.nom as cite_nom, ct.ville
                      FROM reservations r 
                      JOIN chambres c ON r.chambre_id = c.id 
                      JOIN cites ct ON c.cite_id = ct.id 
                      WHERE r.utilisateur_id = ? AND r.statut = 'confirmee' 
                      AND r.date_debut >= CURDATE() 
                      ORDER BY r.date_debut ASC LIMIT 1");
$stmt->execute([$user_id]);
$prochaine_reservation = $stmt->fetch(PDO::FETCH_ASSOC);

// ==================== RÉSERVATIONS RÉCENTES ====================
// CORRECTION : Suppression de ct.quartier
$stmt = $db->prepare("SELECT r.*, c.numero_chambre, c.type_chambre, c.prix_mensuel,
                      ct.nom as cite_nom, ct.ville,
                      (SELECT note FROM evaluations WHERE utilisateur_id = ? AND chambre_id = r.chambre_id) as evaluation_note
                      FROM reservations r 
                      JOIN chambres c ON r.chambre_id = c.id 
                      JOIN cites ct ON c.cite_id = ct.id 
                      WHERE r.utilisateur_id = ? 
                      ORDER BY r.date_reservation DESC LIMIT 3");
$stmt->execute([$user_id, $user_id]);
$reservations_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== DERNIÈRES NOTIFICATIONS ====================
$stmt = $db->prepare("SELECT * FROM notifications 
                     WHERE utilisateur_id = ? 
                     ORDER BY date_creation DESC LIMIT 5");
$stmt->execute([$user_id]);
$dernieres_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== CHAMBRES SUGGÉRÉES ====================
$query = "SELECT c.*, ct.nom as cite_nom, ct.ville,
          (SELECT AVG(note) FROM evaluations WHERE chambre_id = c.id) as note_moyenne
          FROM chambres c 
          JOIN cites ct ON c.cite_id = ct.id 
          WHERE c.disponible = 1 
          ORDER BY RAND() LIMIT 3";
$stmt = $db->query($query);
$chambres_suggerees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== STATISTIQUES MENSUELLES ====================
$stmt = $db->prepare("SELECT 
    COALESCE(SUM(montant_total), 0) as total_reservations,
    COUNT(*) as nb_reservations
    FROM reservations 
    WHERE utilisateur_id = ? AND MONTH(date_reservation) = MONTH(CURRENT_DATE())");
$stmt->execute([$user_id]);
$stats_mois = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculer le nombre de jours avant la prochaine réservation
$jours_avant = 0;
if ($prochaine_reservation) {
    $aujourdhui = new DateTime();
    $arrivee = new DateTime($prochaine_reservation['date_debut']);
    $interval = $aujourdhui->diff($arrivee);
    $jours_avant = $interval->days;
}

include '../includes/header.php';
?>

<div class="student-dashboard">
    <!-- En-tête avec dégradé -->
    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="welcome-section">
                        <span class="welcome-badge">
                            <i class="bi bi-mortarboard-fill"></i> Étudiant
                        </span>
                        <h1 class="welcome-title">
                            Bonjour, <span class="highlight"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span> 
                        </h1>
                        <p class="welcome-subtitle">
                            <?php
                            $heure = date('H');
                            if ($heure < 12) echo "Bonne matinée ! Prêt pour une nouvelle journée ?";
                            elseif ($heure < 18) echo "Bon après-midi ! Besoin d'un logement ?";
                            else echo "Bonsoir ! Trouvez votre chez-vous pour demain.";
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="header-actions">
                        <a href="notifications.php" class="header-btn <?php echo $stats['notifications'] > 0 ? 'has-notif' : ''; ?>">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($stats['notifications'] > 0): ?>
                                <span class="notification-badge"><?php echo $stats['notifications']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="profil.php" class="header-btn">
                            <i class="bi bi-person-gear"></i>
                        </a>
                        <a href="../chambres/" class="btn btn-light btn-lg">
                            <i class="bi bi-search"></i> Chercher une chambre
                        </a>
                    </div>
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
            <div class="stat-card gradient-1">
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['reservations_actives']; ?></span>
                    <span class="stat-label">Réservations actives</span>
                </div>
            </div>
            <div class="stat-card gradient-2">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['reservations_terminees']; ?></span>
                    <span class="stat-label">Séjours terminés</span>
                </div>
            </div>
            <div class="stat-card gradient-3">
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo formatFCFA($stats['total_depense']); ?></span>
                    <span class="stat-label">Total dépensé</span>
                </div>
            </div>
            <div class="stat-card gradient-4">
                <div class="stat-icon"><i class="bi bi-star-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['note_moyenne']; ?>/5</span>
                    <span class="stat-label">Note moyenne donnée</span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-lg-8">
                <!-- Prochaine réservation -->
                <?php if ($prochaine_reservation): ?>
                <div class="upcoming-card">
                    <div class="upcoming-header">
                        <div class="upcoming-title">
                            <i class="bi bi-calendar-event"></i>
                            <span>Votre prochain séjour</span>
                        </div>
                        <span class="countdown-badge">
                            <i class="bi bi-clock"></i> Dans <?php echo $jours_avant; ?> jour<?php echo $jours_avant > 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="upcoming-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="room-preview">
                                    <?php if (!empty($prochaine_reservation['chambre_image'])): ?>
                                        <img src="../<?php echo $prochaine_reservation['chambre_image']; ?>" alt="Chambre">
                                    <?php else: ?>
                                        <div class="room-placeholder">
                                            <i class="bi bi-door-open"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h4 class="room-title">
                                    Chambre <?php echo htmlspecialchars($prochaine_reservation['numero_chambre']); ?>
                                    <span class="room-type-badge"><?php echo ucfirst($prochaine_reservation['type_chambre']); ?></span>
                                </h4>
                                <p class="room-location">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <?php echo htmlspecialchars($prochaine_reservation['cite_nom']); ?> - 
                                    <?php echo htmlspecialchars($prochaine_reservation['ville']); ?>
                                </p>
                                <div class="stay-dates">
                                    <div class="date-item">
                                        <i class="bi bi-calendar3"></i>
                                        <span>Arrivée : <strong><?php echo date('d/m/Y', strtotime($prochaine_reservation['date_debut'])); ?></strong></span>
                                    </div>
                                    <div class="date-item">
                                        <i class="bi bi-calendar3"></i>
                                        <span>Départ : <strong><?php echo date('d/m/Y', strtotime($prochaine_reservation['date_fin'])); ?></strong></span>
                                    </div>
                                </div>
                                <div class="upcoming-actions">
                                    <a href="mes-reservations.php" class="btn btn-primary">
                                        <i class="bi bi-eye"></i> Voir détails
                                    </a>
                                    <a href="paiements.php" class="btn btn-outline-success">
                                        <i class="bi bi-credit-card"></i> Effectuer un paiement
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-upcoming-card">
                    <div class="no-upcoming-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h4>Aucune réservation à venir</h4>
                    <p>Vous n'avez pas encore de réservation active.</p>
                    <a href="../chambres/" class="btn btn-primary btn-lg">
                        <i class="bi bi-search"></i> Trouver une chambre
                    </a>
                </div>
                <?php endif; ?>

                <!-- Actions rapides -->
                <div class="quick-actions">
                    <h5 class="section-title"><i class="bi bi-lightning-charge"></i> Actions rapides</h5>
                    <div class="actions-grid">
                        <a href="../chambres/" class="action-item">
                            <div class="action-icon bg-primary">
                                <i class="bi bi-search"></i>
                            </div>
                            <span>Chercher</span>
                        </a>
                        <a href="mes-reservations.php" class="action-item">
                            <div class="action-icon bg-success">
                                <i class="bi bi-calendar"></i>
                            </div>
                            <span>Mes réservations</span>
                        </a>
                        <a href="paiements.php" class="action-item">
                            <div class="action-icon bg-warning">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <span>Paiements</span>
                        </a>
                        <a href="historique-reservations.php" class="action-item">
                            <div class="action-icon bg-info">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <span>Historique</span>
                        </a>
                        <a href="evaluations.php" class="action-item">
                            <div class="action-icon bg-danger">
                                <i class="bi bi-star"></i>
                            </div>
                            <span>Évaluer</span>
                        </a>
                        <a href="profil.php" class="action-item">
                            <div class="action-icon bg-secondary">
                                <i class="bi bi-person"></i>
                            </div>
                            <span>Profil</span>
                        </a>
                    </div>
                </div>

                <!-- Réservations récentes -->
                <?php if (!empty($reservations_recentes)): ?>
                <div class="recent-reservations">
                    <div class="section-header">
                        <h5 class="section-title"><i class="bi bi-clock-history"></i> Réservations récentes</h5>
                        <a href="mes-reservations.php" class="view-all">Voir tout <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="reservations-list">
                        <?php foreach ($reservations_recentes as $res): ?>
                        <div class="reservation-item">
                            <div class="reservation-info">
                                <span class="reservation-id">#<?php echo $res['id']; ?></span>
                                <span class="reservation-room">
                                    Chambre <?php echo htmlspecialchars($res['numero_chambre']); ?> - 
                                    <?php echo htmlspecialchars($res['cite_nom']); ?>
                                </span>
                                <span class="reservation-dates">
                                    <?php echo date('d/m/Y', strtotime($res['date_debut'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($res['date_fin'])); ?>
                                </span>
                            </div>
                            <div class="reservation-status">
                                <span class="status-badge status-<?php echo $res['statut']; ?>">
                                    <?php echo ucfirst($res['statut']); ?>
                                </span>
                                <?php if ($res['statut'] == 'terminee' && !$res['evaluation_note']): ?>
                                    <a href="evaluations.php" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-star"></i> Évaluer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Colonne latérale -->
            <div class="col-lg-4">
                <!-- Notifications récentes -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h5><i class="bi bi-bell"></i> Notifications récentes</h5>
                        <a href="notifications.php" class="view-all">Voir tout</a>
                    </div>
                    <div class="notifications-mini">
                        <?php if (empty($dernieres_notifications)): ?>
                            <p class="text-muted text-center py-3">Aucune notification</p>
                        <?php else: ?>
                            <?php foreach ($dernieres_notifications as $notif): 
                                $icon = 'bell';
                                $color = 'primary';
                                if ($notif['type'] == 'reservation') { $icon = 'calendar-check'; $color = 'info'; }
                                elseif ($notif['type'] == 'paiement') { $icon = 'credit-card'; $color = 'success'; }
                                elseif ($notif['type'] == 'alerte') { $icon = 'exclamation-triangle'; $color = 'danger'; }
                                $msg = str_replace(['€', 'EUR', 'euros'], 'FCFA', $notif['message']);
                            ?>
                                <div class="mini-notif <?php echo $notif['lu'] == 0 ? 'unread' : ''; ?>">
                                    <div class="mini-notif-icon bg-<?php echo $color; ?>">
                                        <i class="bi bi-<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="mini-notif-content">
                                        <p><?php echo htmlspecialchars(substr($msg, 0, 50)) . '...'; ?></p>
                                        <small><?php echo date('d/m/Y H:i', strtotime($notif['date_creation'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chambres suggérées -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h5><i class="bi bi-lightbulb"></i> Suggestions pour vous</h5>
                        <a href="../chambres/" class="view-all">Voir plus</a>
                    </div>
                    <div class="suggested-rooms">
                        <?php foreach ($chambres_suggerees as $chambre): ?>
                        <a href="../chambres/details.php?id=<?php echo $chambre['id']; ?>" class="suggested-room">
                            <div class="suggested-room-image">
                                <?php if (!empty($chambre['image'])): ?>
                                    <img src="../<?php echo $chambre['image']; ?>" alt="Chambre">
                                <?php else: ?>
                                    <div class="placeholder"><i class="bi bi-door-open"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="suggested-room-info">
                                <h6>Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?></h6>
                                <p><?php echo htmlspecialchars($chambre['cite_nom']); ?> - <?php echo htmlspecialchars($chambre['ville']); ?></p>
                                <span class="price"><?php echo formatFCFA($chambre['prix_mensuel']); ?>/mois</span>
                                <?php if ($chambre['note_moyenne']): ?>
                                    <span class="rating">
                                        <i class="bi bi-star-fill"></i> <?php echo round($chambre['note_moyenne'], 1); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Résumé du mois -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h5><i class="bi bi-calendar-month"></i> Ce mois-ci</h5>
                    </div>
                    <div class="month-summary">
                        <div class="summary-item">
                            <span class="summary-label">Réservations</span>
                            <span class="summary-value"><?php echo $stats_mois['nb_reservations']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Total dépensé</span>
                            <span class="summary-value"><?php echo formatFCFA($stats_mois['total_reservations']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Réservations actives</span>
                            <span class="summary-value"><?php echo $stats['reservations_actives']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, #007A5E 0%, #006400 100%);
    padding: 40px 0 80px;
    position: relative;
    margin-top: -24px;
}
.welcome-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 14px;
    margin-bottom: 15px;
    backdrop-filter: blur(10px);
}
.welcome-title {
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.welcome-title .highlight {
    color: #FCD116;
}
.welcome-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 0;
}
.header-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 15px;
}
.header-btn {
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    position: relative;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
    text-decoration: none;
}
.header-btn:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    transform: translateY(-2px);
}
.header-btn.has-notif::after {
    content: '';
    position: absolute;
    top: 10px;
    right: 10px;
    width: 10px;
    height: 10px;
    background: #FCD116;
    border-radius: 50%;
    border: 2px solid white;
}
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #CE1126;
    color: white;
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 30px;
    min-width: 20px;
    text-align: center;
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
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 20px;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
}
.gradient-1 .stat-icon { background: linear-gradient(135deg, #007A5E, #006400); }
.gradient-2 .stat-icon { background: linear-gradient(135deg, #CE1126, #a00); }
.gradient-3 .stat-icon { background: linear-gradient(135deg, #FCD116, #e6b800); color: #000; }
.gradient-4 .stat-icon { background: linear-gradient(135deg, #4facfe, #00f2fe); }
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

/* Upcoming Card */
.upcoming-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}
.upcoming-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    background: linear-gradient(90deg, #f7fafc 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
}
.upcoming-title {
    font-weight: 600;
    color: #2d3748;
}
.upcoming-title i {
    color: #007A5E;
    margin-right: 8px;
}
.countdown-badge {
    background: #FCD116;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    color: #000;
}
.upcoming-body {
    padding: 25px;
}
.room-preview {
    border-radius: 15px;
    overflow: hidden;
    height: 150px;
}
.room-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.room-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #007A5E, #006400);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 40px;
}
.room-title {
    font-weight: 700;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.room-type-badge {
    background: #e2e8f0;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.room-location {
    color: #718096;
    margin-bottom: 15px;
}
.stay-dates {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}
.date-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #4a5568;
}
.upcoming-actions {
    display: flex;
    gap: 10px;
}

/* No Upcoming */
.no-upcoming-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}
.no-upcoming-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #007A5E, #006400);
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 35px;
}
.no-upcoming-card h4 {
    font-weight: 700;
    margin-bottom: 10px;
}
.no-upcoming-card p {
    color: #718096;
    margin-bottom: 20px;
}

/* Quick Actions */
.quick-actions {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}
.section-title {
    font-weight: 600;
    margin-bottom: 20px;
    color: #2d3748;
}
.actions-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
}
.action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #4a5568;
    transition: all 0.3s;
}
.action-item:hover {
    transform: translateY(-3px);
    color: #007A5E;
}
.action-icon {
    width: 55px;
    height: 55px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin-bottom: 8px;
}
.action-item span {
    font-size: 13px;
    font-weight: 500;
}

/* Recent Reservations */
.recent-reservations {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.view-all {
    color: #007A5E;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}
.reservation-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #e2e8f0;
}
.reservation-item:last-child {
    border-bottom: none;
}
.reservation-id {
    font-weight: 600;
    color: #007A5E;
    margin-right: 15px;
}
.reservation-room {
    color: #2d3748;
    margin-right: 15px;
}
.reservation-dates {
    color: #718096;
    font-size: 14px;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-en_attente { background: #FCD116; color: #000; }
.status-confirmee { background: #48bb78; color: white; }
.status-terminee { background: #a0aec0; color: white; }
.status-annulee { background: #f56565; color: white; }

/* Sidebar Cards */
.sidebar-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}
.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.sidebar-header h5 {
    font-weight: 600;
    margin-bottom: 0;
    color: #2d3748;
}
.mini-notif {
    display: flex;
    gap: 12px;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 8px;
    transition: all 0.3s;
}
.mini-notif:hover { background: #f7fafc; }
.mini-notif.unread { background: #fef5e7; }
.mini-notif-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
}
.mini-notif-content p {
    margin: 0 0 5px;
    font-size: 13px;
    color: #4a5568;
}
.mini-notif-content small {
    font-size: 11px;
    color: #a0aec0;
}

/* Suggested Rooms */
.suggested-room {
    display: flex;
    gap: 15px;
    padding: 12px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s;
    margin-bottom: 10px;
    color: inherit;
}
.suggested-room:hover {
    background: #f7fafc;
    transform: translateX(5px);
}
.suggested-room-image {
    width: 70px;
    height: 70px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
}
.suggested-room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.suggested-room-image .placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #007A5E, #006400);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}
.suggested-room-info h6 {
    margin: 0 0 5px;
    font-weight: 600;
    color: #2d3748;
}
.suggested-room-info p {
    margin: 0 0 5px;
    font-size: 13px;
    color: #718096;
}
.suggested-room-info .price {
    font-weight: 700;
    color: #007A5E;
    font-size: 14px;
}
.suggested-room-info .rating {
    margin-left: 10px;
    color: #FCD116;
    font-size: 13px;
}

/* Month Summary */
.month-summary {
    padding: 10px 0;
}
.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}
.summary-item:last-child { border-bottom: none; }
.summary-label { color: #718096; }
.summary-value { font-weight: 600; color: #2d3748; }

/* Responsive */
@media (max-width: 992px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .actions-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .welcome-title { font-size: 1.8rem; }
    .header-actions { margin-top: 20px; justify-content: flex-start; }
    .upcoming-body .row > div { margin-bottom: 15px; }
    .stay-dates { flex-direction: column; gap: 10px; }
    .reservation-item { flex-direction: column; align-items: flex-start; gap: 10px; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .actions-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php include '../includes/footer.php'; ?>