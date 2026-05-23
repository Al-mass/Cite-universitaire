<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est étudiant
if (!isLoggedIn() || isAdmin()) {
    $_SESSION['error'] = "Veuillez vous connecter en tant qu'étudiant";
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

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Réservation non spécifiée";
    redirect('index.php');
}

$reservation_id = intval($_GET['id']);
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les détails de la réservation
$query = "SELECT r.*, 
          c.numero_chambre, c.type_chambre, c.prix_mensuel, c.image as chambre_image,
          ct.nom as cite_nom, ct.ville, ct.adresse,
          u.nom, u.prenom, u.email, u.telephone
          FROM reservations r
          JOIN chambres c ON r.chambre_id = c.id
          JOIN cites ct ON c.cite_id = ct.id
          JOIN utilisateurs u ON r.utilisateur_id = u.id
          WHERE r.id = ? AND r.utilisateur_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$reservation_id, $user_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    $_SESSION['error'] = "Réservation non trouvée ou accès non autorisé";
    redirect('index.php');
}

// Calculer la durée
$debut = new DateTime($reservation['date_debut']);
$fin = new DateTime($reservation['date_fin']);
$interval = $debut->diff($fin);
$duree = $interval->days;

// Récupérer le total déjà payé
$stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = ? AND statut = 'complete'");
$stmt->execute([$reservation_id]);
$total_paye = $stmt->fetchColumn();
$reste_a_payer = $reservation['montant_total'] - $total_paye;

// Générer un numéro de confirmation
$numero_confirmation = 'RES-' . date('Y') . '-' . str_pad($reservation_id, 6, '0', STR_PAD_LEFT);

include '../includes/header.php';
?>

<div class="confirmation-page">
    <!-- En-tête -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Accueil</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Chambres</a></li>
                            <li class="breadcrumb-item active">Confirmation</li>
                        </ol>
                    </nav>
                    <h1 class="page-title">
                        <i class="bi bi-check-circle-fill"></i>
                        Réservation Confirmée !
                    </h1>
                    <p class="page-subtitle">Votre demande de réservation a été enregistrée avec succès</p>
                </div>
            </div>
        </div>
        <div class="header-wave">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 80">
                <path fill="#f8fafc" d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,58.7L1440,64L1440,80L1360,80C1280,80,1120,80,960,80C800,80,640,80,480,80C320,80,160,80,80,80L0,80Z"></path>
            </svg>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Indicateur d'étapes -->
        <div class="steps-progress mb-5">
            <div class="step completed">
                <div class="step-icon"><i class="bi bi-check-lg"></i></div>
                <span class="step-label">Sélection</span>
            </div>
            <div class="step-line completed"></div>
            <div class="step completed">
                <div class="step-icon"><i class="bi bi-check-lg"></i></div>
                <span class="step-label">Réservation</span>
            </div>
            <div class="step-line"></div>
            <div class="step <?php echo $total_paye > 0 ? 'completed' : ''; ?>">
                <div class="step-icon"><?php echo $total_paye > 0 ? '<i class="bi bi-check-lg"></i>' : '3'; ?></div>
                <span class="step-label">Paiement</span>
            </div>
            <div class="step-line"></div>
            <div class="step <?php echo $total_paye >= $reservation['montant_total'] ? 'completed' : ''; ?>">
                <div class="step-icon">4</div>
                <span class="step-label">Confirmation</span>
            </div>
        </div>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-lg-8">
                <!-- Message de succès -->
                <div class="success-card">
                    <div class="success-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="success-content">
                        <h3>Votre réservation est en attente de confirmation</h3>
                        <p>Un email de confirmation a été envoyé à <strong><?php echo htmlspecialchars($reservation['email']); ?></strong></p>
                        <p class="text-muted">Vous recevrez une notification dès que votre réservation sera confirmée par l'administration.</p>
                    </div>
                </div>

                <!-- Détails de la réservation -->
                <div class="details-card">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle"></i> Détails de la réservation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label>Numéro de réservation</label>
                                    <div class="info-value highlight">#<?php echo $reservation_id; ?></div>
                                </div>
                                <div class="info-group">
                                    <label>Numéro de confirmation</label>
                                    <div class="info-value">
                                        <code><?php echo $numero_confirmation; ?></code>
                                    </div>
                                </div>
                                <div class="info-group">
                                    <label>Statut</label>
                                    <div class="info-value">
                                        <span class="status-badge status-<?php echo $reservation['statut']; ?>">
                                            <?php if ($reservation['statut'] == 'en_attente'): ?>
                                                <i class="bi bi-clock"></i> En attente de confirmation
                                            <?php elseif ($reservation['statut'] == 'confirmee'): ?>
                                                <i class="bi bi-check-circle"></i> Confirmée
                                            <?php elseif ($reservation['statut'] == 'annulee'): ?>
                                                <i class="bi bi-x-circle"></i> Annulée
                                            <?php else: ?>
                                                <?php echo ucfirst($reservation['statut']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-group">
                                    <label>Date de réservation</label>
                                    <div class="info-value">
                                        <?php echo date('d/m/Y à H:i', strtotime($reservation['date_reservation'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label>Client</label>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?>
                                    </div>
                                </div>
                                <div class="info-group">
                                    <label>Email</label>
                                    <div class="info-value"><?php echo htmlspecialchars($reservation['email']); ?></div>
                                </div>
                                <div class="info-group">
                                    <label>Téléphone</label>
                                    <div class="info-value"><?php echo htmlspecialchars($reservation['telephone'] ?? 'Non renseigné'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations de la chambre -->
                <div class="room-card">
                    <div class="card-header">
                        <h5><i class="bi bi-door-open"></i> Chambre réservée</h5>
                    </div>
                    <div class="card-body">
                        <div class="room-details">
                            <div class="room-image">
                                <?php if (!empty($reservation['chambre_image'])): ?>
                                    <img src="../<?php echo $reservation['chambre_image']; ?>" alt="Chambre">
                                <?php else: ?>
                                    <div class="room-placeholder">
                                        <i class="bi bi-door-open"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="room-info">
                                <h6>Chambre <?php echo htmlspecialchars($reservation['numero_chambre']); ?></h6>
                                <span class="room-type"><?php echo ucfirst($reservation['type_chambre']); ?></span>
                                <p class="room-location">
                                    <i class="bi bi-geo-alt"></i>
                                    <?php echo htmlspecialchars($reservation['cite_nom']); ?> - 
                                    <?php echo htmlspecialchars($reservation['ville']); ?>
                                </p>
                                <p class="room-address">
                                    <i class="bi bi-map"></i>
                                    <?php echo htmlspecialchars($reservation['adresse']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="stay-dates">
                            <div class="date-block">
                                <span class="date-label">Arrivée</span>
                                <span class="date-value">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo date('d/m/Y', strtotime($reservation['date_debut'])); ?>
                                </span>
                                <small>à partir de 14h00</small>
                            </div>
                            <div class="date-arrow">
                                <i class="bi bi-arrow-right"></i>
                                <span><?php echo $duree; ?> nuit<?php echo $duree > 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="date-block">
                                <span class="date-label">Départ</span>
                                <span class="date-value">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo date('d/m/Y', strtotime($reservation['date_fin'])); ?>
                                </span>
                                <small>avant 12h00</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne latérale -->
            <div class="col-lg-4">
                <!-- Résumé du paiement -->
                <div class="payment-summary-card">
                    <div class="card-header">
                        <h5><i class="bi bi-credit-card"></i> Résumé du paiement</h5>
                    </div>
                    <div class="card-body">
                        <div class="payment-item">
                            <span>Montant total</span>
                            <span class="amount"><?php echo formatFCFA($reservation['montant_total']); ?></span>
                        </div>
                        <?php if ($total_paye > 0): ?>
                            <div class="payment-item text-success">
                                <span>Déjà payé</span>
                                <span class="amount">- <?php echo formatFCFA($total_paye); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($reste_a_payer > 0): ?>
                            <div class="payment-item reste">
                                <span>Reste à payer</span>
                                <span class="amount"><?php echo formatFCFA($reste_a_payer); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="payment-item text-success">
                                <span>Statut</span>
                                <span class="amount"><i class="bi bi-check-circle"></i> Payé</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($reste_a_payer > 0): ?>
                            <hr>
                            <div class="payment-actions">
                                <a href="../etudiant/paiements.php?reservation_id=<?php echo $reservation_id; ?>" class="btn btn-success btn-lg w-100">
                                    <i class="bi bi-credit-card"></i> Effectuer un paiement
                                </a>
                                <p class="payment-note">
                                    <i class="bi bi-info-circle"></i>
                                    Le paiement peut être effectué par Orange Money, Carte Bancaire, Virement ou Espèces.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Prochaines étapes -->
                <div class="next-steps-card">
                    <div class="card-header">
                        <h6><i class="bi bi-list-check"></i> Prochaines étapes</h6>
                    </div>
                    <div class="card-body">
                        <ul class="steps-list">
                            <li class="<?php echo $reservation['statut'] != 'en_attente' ? 'completed' : ''; ?>">
                                <i class="bi bi-<?php echo $reservation['statut'] != 'en_attente' ? 'check-circle-fill text-success' : 'hourglass-split text-warning'; ?>"></i>
                                <span>Confirmation par l'administration</span>
                            </li>
                            <li class="<?php echo $total_paye > 0 ? 'completed' : ''; ?>">
                                <i class="bi bi-<?php echo $total_paye > 0 ? 'check-circle-fill text-success' : 'circle'; ?>"></i>
                                <span>Paiement de la réservation</span>
                            </li>
                            <li>
                                <i class="bi bi-circle"></i>
                                <span>Arrivée à la cité universitaire</span>
                            </li>
                            <li>
                                <i class="bi bi-circle"></i>
                                <span>Installation dans votre chambre</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Actions -->
                <div class="actions-card">
                    <div class="d-grid gap-2">
                        <a href="../etudiant/mes-reservations.php" class="btn btn-outline-primary">
                            <i class="bi bi-calendar-check"></i> Voir mes réservations
                        </a>
                        <a href="../etudiant/dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-speedometer2"></i> Tableau de bord
                        </a>
                        <a href="index.php" class="btn btn-outline-success">
                            <i class="bi bi-search"></i> Chercher d'autres chambres
                        </a>
                        <button class="btn btn-outline-info" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimer cette page
                        </button>
                    </div>
                </div>

                <!-- Contact -->
                <div class="contact-card">
                    <h6><i class="bi bi-headset"></i> Besoin d'aide ?</h6>
                    <p>Notre équipe est à votre disposition</p>
                    <p class="contact-phone">
                        <i class="bi bi-telephone-fill"></i> +237 699 999 999
                    </p>
                    <p class="contact-email">
                        <i class="bi bi-envelope-fill"></i> contact@univ-ndere.cm
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, #007A5E 0%, #006400 100%);
    padding: 40px 0 80px;
    position: relative;
    margin-top: -24px;
}
.breadcrumb {
    margin-bottom: 15px;
}
.breadcrumb-item a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
}
.breadcrumb-item.active {
    color: white;
}
.page-title {
    color: white;
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.page-subtitle {
    color: rgba(255,255,255,0.9);
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

/* Steps Progress */
.steps-progress {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
}
.step {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.step-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #718096;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-bottom: 8px;
}
.step.completed .step-icon {
    background: #48bb78;
    color: white;
}
.step-label {
    font-size: 13px;
    color: #718096;
    font-weight: 500;
}
.step.completed .step-label {
    color: #48bb78;
}
.step-line {
    width: 80px;
    height: 3px;
    background: #e2e8f0;
    margin: 0 15px 35px;
}
.step-line.completed {
    background: #48bb78;
}

/* Success Card */
.success-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    display: flex;
    gap: 20px;
    align-items: center;
    border-left: 5px solid #48bb78;
}
.success-icon {
    font-size: 50px;
    color: #48bb78;
}
.success-content h3 {
    margin: 0 0 10px;
    color: #2d3748;
    font-weight: 600;
}
.success-content p {
    margin: 0 0 5px;
    color: #4a5568;
}

/* Cards */
.details-card, .room-card, .payment-summary-card, .next-steps-card, .actions-card, .contact-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}
.card-header {
    padding: 20px 25px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.card-header h5, .card-header h6 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
}
.card-body {
    padding: 25px;
}

/* Info Groups */
.info-group {
    margin-bottom: 18px;
}
.info-group label {
    display: block;
    font-size: 12px;
    color: #a0aec0;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-value {
    font-size: 16px;
    color: #2d3748;
    font-weight: 500;
}
.info-value.highlight {
    font-size: 24px;
    font-weight: 700;
    color: #007A5E;
}
.info-value code {
    background: #f7fafc;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 14px;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 500;
}
.status-en_attente {
    background: #fefcbf;
    color: #744210;
}
.status-confirmee {
    background: #c6f6d5;
    color: #22543d;
}

/* Room Details */
.room-details {
    display: flex;
    gap: 20px;
}
.room-image {
    width: 120px;
    height: 120px;
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
    background: linear-gradient(135deg, #007A5E, #006400);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 40px;
}
.room-info h6 {
    margin: 0 0 5px;
    font-weight: 600;
}
.room-type {
    display: inline-block;
    background: #e2e8f0;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    margin-bottom: 8px;
}
.room-location, .room-address {
    margin: 5px 0;
    font-size: 14px;
    color: #718096;
}

/* Stay Dates */
.stay-dates {
    display: flex;
    align-items: center;
    justify-content: space-around;
    text-align: center;
}
.date-block {
    flex: 1;
}
.date-label {
    display: block;
    font-size: 12px;
    color: #a0aec0;
    margin-bottom: 5px;
}
.date-value {
    display: block;
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 5px;
}
.date-value i {
    color: #007A5E;
    margin-right: 5px;
}
.date-block small {
    color: #a0aec0;
    font-size: 12px;
}
.date-arrow {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #a0aec0;
}
.date-arrow i {
    font-size: 20px;
}
.date-arrow span {
    font-size: 12px;
    margin-top: 5px;
}

/* Payment Summary */
.payment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}
.payment-item.reste {
    border-top: 1px solid #e2e8f0;
    margin-top: 10px;
    padding-top: 15px;
    font-weight: 600;
}
.payment-item .amount {
    font-weight: 600;
    font-size: 16px;
}
.payment-item.reste .amount {
    font-size: 22px;
    color: #007A5E;
}
.payment-note {
    margin-top: 15px;
    font-size: 13px;
    color: #718096;
    text-align: center;
}

/* Steps List */
.steps-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.steps-list li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
    color: #a0aec0;
}
.steps-list li:last-child {
    border-bottom: none;
}
.steps-list li.completed {
    color: #2d3748;
}
.steps-list li i {
    font-size: 20px;
}

/* Contact Card */
.contact-card {
    padding: 20px;
    text-align: center;
}
.contact-card h6 {
    margin-bottom: 15px;
}
.contact-phone {
    font-size: 18px;
    font-weight: 600;
    color: #007A5E;
    margin: 10px 0 5px;
}
.contact-email {
    color: #718096;
}

/* Responsive */
@media (max-width: 768px) {
    .page-title { font-size: 1.8rem; }
    .step-line { width: 40px; }
    .step-icon { width: 35px; height: 35px; }
    .success-card { flex-direction: column; text-align: center; }
    .room-details { flex-direction: column; }
    .room-image { width: 100%; height: 180px; }
    .stay-dates { flex-direction: column; gap: 20px; }
    .date-arrow i { transform: rotate(90deg); }
}

/* Print Styles */
@media print {
    .navbar, .footer, .btn, .actions-card, .payment-actions, .header-wave,
    .steps-progress, .breadcrumb, .next-steps-card, .contact-card {
        display: none !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .page-header {
        background: none !important;
        color: black !important;
        padding: 20px 0 !important;
    }
    .page-title, .page-subtitle {
        color: black !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>