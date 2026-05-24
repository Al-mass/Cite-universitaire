<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
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

// Récupération des paramètres
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$paiement_id = isset($_GET['paiement_id']) ? intval($_GET['paiement_id']) : 0;
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';

// Récupérer les détails du paiement
$paiement_details = null;
$reservation_details = null;

if ($paiement_id > 0) {
    $stmt = $db->prepare("SELECT p.*, r.id as reservation_id, r.date_debut, r.date_fin, r.montant_total,
                          c.numero_chambre, c.type_chambre, ct.nom as cite_nom, ct.adresse, ct.ville
                          FROM paiements p
                          JOIN reservations r ON p.reservation_id = r.id
                          JOIN chambres c ON r.chambre_id = c.id
                          JOIN cites ct ON c.cite_id = ct.id
                          WHERE p.id = ? AND r.utilisateur_id = ?");
    $stmt->execute([$paiement_id, $user_id]);
    $paiement_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paiement_details) {
        $reservation_id = $paiement_details['reservation_id'];
        $transaction_id = $paiement_details['transaction_id'];
    }
} elseif ($reservation_id > 0) {
    // Récupérer le dernier paiement de la réservation
    $stmt = $db->prepare("SELECT p.*, r.id as reservation_id, r.date_debut, r.date_fin, r.montant_total,
                          c.numero_chambre, c.type_chambre, ct.nom as cite_nom, ct.adresse, ct.ville
                          FROM paiements p
                          JOIN reservations r ON p.reservation_id = r.id
                          JOIN chambres c ON r.chambre_id = c.id
                          JOIN cites ct ON c.cite_id = ct.id
                          WHERE r.id = ? AND r.utilisateur_id = ?
                          ORDER BY p.date_paiement DESC LIMIT 1");
    $stmt->execute([$reservation_id, $user_id]);
    $paiement_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paiement_details) {
        $paiement_id = $paiement_details['id'];
        $transaction_id = $paiement_details['transaction_id'];
    }
}

// Si aucun paiement trouvé mais qu'on a une réservation
if (!$paiement_details && $reservation_id > 0) {
    $stmt = $db->prepare("SELECT r.*, c.numero_chambre, c.type_chambre, ct.nom as cite_nom, ct.adresse, ct.ville
                          FROM reservations r
                          JOIN chambres c ON r.chambre_id = c.id
                          JOIN cites ct ON c.cite_id = ct.id
                          WHERE r.id = ? AND r.utilisateur_id = ?");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer le total payé pour cette réservation
$total_paye = 0;
$reste_a_payer = 0;
if ($reservation_id > 0) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM paiements 
                         WHERE reservation_id = ? AND statut = 'complete'");
    $stmt->execute([$reservation_id]);
    $total_paye = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
    
    if ($paiement_details) {
        $reste_a_payer = $paiement_details['montant_total'] - $total_paye;
    } elseif ($reservation_details) {
        $reste_a_payer = $reservation_details['montant_total'] - $total_paye;
    }
}

// Générer un numéro de facture
$numero_facture = 'FACT-' . date('Y') . '-' . str_pad($paiement_id ?: $reservation_id, 6, '0', STR_PAD_LEFT);
$date_emission = date('d/m/Y à H:i');

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Animation de succès -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="success-animation text-center mb-4">
                <div class="checkmark-container">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
                <h1 class="success-title mt-3">Paiement Réussi !</h1>
                <p class="success-message">Votre transaction a été traitée avec succès.</p>
            </div>
        </div>
    </div>

    <!-- Carte de confirmation -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-success text-white py-3 rounded-top-4">
                    <h4 class="mb-0 text-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Confirmation de Paiement
                    </h4>
                </div>
                
                <div class="card-body p-4">
                    <!-- Alerte de confirmation -->
                    <div class="alert alert-success border-start border-4 border-success d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-shield-check fs-3 me-3"></i>
                        <div>
                            <strong>Paiement sécurisé confirmé</strong><br>
                            <span class="small">Votre paiement a été traité en toute sécurité. Un email de confirmation vous a été envoyé.</span>
                        </div>
                    </div>

                    <!-- Détails de la transaction -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="info-card p-3 bg-light rounded-3 h-100">
                                <h6 class="text-muted mb-3">
                                    <i class="bi bi-receipt me-2"></i>Informations de la transaction
                                </h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted" width="130">N° Facture :</td>
                                        <td><strong><?php echo $numero_facture; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">N° Transaction :</td>
                                        <td>
                                            <code class="bg-white px-2 py-1 rounded">
                                                <?php echo $transaction_id ?: 'N/A'; ?>
                                            </code>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Date de paiement :</td>
                                        <td>
                                            <?php 
                                            if ($paiement_details) {
                                                echo date('d/m/Y', strtotime($paiement_details['date_paiement']));
                                                echo '<br><small class="text-muted">' . date('H:i', strtotime($paiement_details['date_paiement'])) . '</small>';
                                            } else {
                                                echo date('d/m/Y');
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Méthode de paiement :</td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php 
                                                if ($paiement_details) {
                                                    $methodes = [
                                                        'carte_credit' => 'Carte Bancaire',
                                                        'orange_money' => 'Orange Money',
                                                        'om' => 'Orange Money',
                                                        'mobile_money' => 'MTN Mobile Money',
                                                        'virement' => 'Virement Bancaire',
                                                        'especes' => 'Espèces'
                                                    ];
                                                    echo $methodes[$paiement_details['methode_paiement']] ?? ucfirst($paiement_details['methode_paiement']);
                                                } else {
                                                    echo 'Paiement en ligne';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Statut :</td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Complété
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-card p-3 bg-light rounded-3 h-100">
                                <h6 class="text-muted mb-3">
                                    <i class="bi bi-person me-2"></i>Informations client
                                </h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted" width="130">Nom :</td>
                                        <td><strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Client'); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email :</td>
                                        <td><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Date d'émission :</td>
                                        <td><?php echo $date_emission; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Détails de la réservation -->
                    <?php if ($paiement_details || $reservation_details): 
                        $details = $paiement_details ?: $reservation_details;
                    ?>
                    <div class="mt-4">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-calendar-check me-2"></i>Détails de la réservation
                        </h6>
                        <div class="reservation-details p-3 bg-white border rounded-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Réservation #<?php echo $reservation_id ?: $details['id']; ?></strong>
                                    </p>
                                    <p class="mb-1 text-muted">
                                        <i class="bi bi-door-open me-1"></i>
                                        Chambre <?php echo htmlspecialchars($details['numero_chambre']); ?> 
                                        (<?php echo ucfirst($details['type_chambre']); ?>)
                                    </p>
                                    <p class="mb-1 text-muted">
                                        <i class="bi bi-building me-1"></i>
                                        <?php echo htmlspecialchars($details['cite_nom']); ?>
                                    </p>
                                    <p class="mb-0 text-muted small">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?php echo htmlspecialchars($details['adresse'] ?? ''); ?>, 
                                        <?php echo htmlspecialchars($details['ville'] ?? 'Ngaoundéré'); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Dates du séjour</strong>
                                    </p>
                                    <p class="mb-1 text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Arrivée : <?php echo date('d/m/Y', strtotime($details['date_debut'])); ?>
                                    </p>
                                    <p class="mb-1 text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Départ : <?php echo date('d/m/Y', strtotime($details['date_fin'])); ?>
                                    </p>
                                    <p class="mb-0 text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        Durée : 
                                        <?php 
                                        $debut = new DateTime($details['date_debut']);
                                        $fin = new DateTime($details['date_fin']);
                                        $interval = $debut->diff($fin);
                                        echo $interval->days . ' jours';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Récapitulatif des montants -->
                    <div class="mt-4">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-cash-stack me-2"></i>Récapitulatif des paiements
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-end">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Montant total de la réservation</td>
                                        <td class="text-end">
                                            <?php 
                                            $montant_total = $paiement_details['montant_total'] ?? $reservation_details['montant_total'] ?? 0;
                                            echo formatFCFA($montant_total); 
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Total déjà payé</td>
                                        <td class="text-end text-success">
                                            - <?php echo formatFCFA($total_paye - ($paiement_details['montant'] ?? 0)); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong>Ce paiement</strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php 
                                                if ($paiement_details) {
                                                    $methodes = [
                                                        'carte_credit' => 'Carte Bancaire',
                                                        'orange_money' => 'Orange Money',
                                                        'om' => 'Orange Money',
                                                        'mobile_money' => 'MTN Mobile Money',
                                                        'virement' => 'Virement Bancaire',
                                                        'especes' => 'Espèces'
                                                    ];
                                                    echo $methodes[$paiement_details['methode_paiement']] ?? 'Paiement';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success">
                                                <?php echo formatFCFA($paiement_details['montant'] ?? 0); ?>
                                            </strong>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Total payé à ce jour</th>
                                        <th class="text-end text-success"><?php echo formatFCFA($total_paye); ?></th>
                                    </tr>
                                    <?php if ($reste_a_payer > 0): ?>
                                    <tr>
                                        <th>Reste à payer</th>
                                        <th class="text-end text-warning"><?php echo formatFCFA($reste_a_payer); ?></th>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <th>Statut du paiement</th>
                                        <th class="text-end">
                                            <span class="badge bg-success">Entièrement payé</span>
                                        </th>
                                    </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap justify-content-center gap-3">
                                <a href="../etudiant/mes-reservations.php" class="btn btn-success btn-lg px-4">
                                    <i class="bi bi-calendar-check me-2"></i>Voir mes réservations
                                </a>
                                <a href="../etudiant/paiements.php" class="btn btn-outline-primary btn-lg px-4">
                                    <i class="bi bi-credit-card me-2"></i>Voir mes paiements
                                </a>
                                <button class="btn btn-outline-secondary btn-lg px-4" onclick="window.print()">
                                    <i class="bi bi-printer me-2"></i>Imprimer le reçu
                                </button>
                                <a href="../index.php" class="btn btn-outline-success btn-lg px-4">
                                    <i class="bi bi-house me-2"></i>Accueil
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Message d'information -->
                    <div class="alert alert-info mt-4 mb-0">
                        <div class="d-flex">
                            <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                            <div>
                                <strong>Information importante</strong><br>
                                <p class="mb-0 small">
                                    Un email de confirmation a été envoyé à votre adresse email. 
                                    Veuillez le conserver pour vos archives. 
                                    Pour toute question, contactez-nous au <strong>+237 699 999 999</strong> 
                                    ou par email à <strong>contact@univ-ndere.cm</strong>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-light text-center py-3 rounded-bottom-4">
                    <small class="text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Transaction sécurisée • 
                        Facture N° <?php echo $numero_facture; ?> • 
                        Générée le <?php echo $date_emission; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Suggestions -->
    <div class="row mt-5">
        <div class="col-lg-8 mx-auto">
            <h5 class="mb-3 text-center">Vous pourriez aussi être intéressé par</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-building fs-1 text-success"></i>
                            <h6 class="mt-2">Autres chambres</h6>
                            <p class="small text-muted">Découvrez nos autres chambres disponibles</p>
                            <a href="../chambres/" class="btn btn-sm btn-outline-success">Explorer</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-star fs-1 text-warning"></i>
                            <h6 class="mt-2">Donner votre avis</h6>
                            <p class="small text-muted">Partagez votre expérience avec nous</p>
                            <a href="../etudiant/evaluations.php" class="btn btn-sm btn-outline-warning">Évaluer</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-headset fs-1 text-info"></i>
                            <h6 class="mt-2">Support</h6>
                            <p class="small text-muted">Besoin d'aide ? Contactez-nous</p>
                            <a href="../contact.php" class="btn btn-sm btn-outline-info">Contacter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Animation de succès */
.success-animation {
    animation: fadeInUp 0.8s ease-out;
}

.checkmark-container {
    width: 100px;
    height: 100px;
    margin: 0 auto;
}

.checkmark {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #28a745;
    stroke-miterlimit: 10;
    box-shadow: inset 0px 0px 0px #28a745;
    animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
}

.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #28a745;
    fill: none;
    animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}

@keyframes stroke {
    100% { stroke-dashoffset: 0; }
}

@keyframes scale {
    0%, 100% { transform: none; }
    50% { transform: scale3d(1.1, 1.1, 1); }
}

@keyframes fill {
    100% { box-shadow: inset 0px 0px 0px 50px #28a745; }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.success-title {
    color: #28a745;
    font-weight: bold;
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

.success-message {
    color: #6c757d;
    font-size: 1.1rem;
    animation: fadeInUp 0.6s ease-out 0.4s both;
}

/* Cartes */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
}

.info-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.info-card:hover {
    border-color: #28a745;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.1);
}

.reservation-details {
    transition: all 0.3s ease;
}

.reservation-details:hover {
    border-color: #28a745 !important;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.1);
}

/* Badges */
.badge {
    font-weight: 500;
    padding: 0.4em 0.8em;
}

/* Code */
code {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .checkmark-container {
        width: 80px;
        height: 80px;
    }
    
    .checkmark {
        width: 80px;
        height: 80px;
    }
    
    .success-title {
        font-size: 1.5rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}

/* Style d'impression */
@media print {
    .navbar, .footer, .btn, .alert-info, .suggestions {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
        background-color: transparent !important;
        color: #000 !important;
    }
}
</style>

<script>
// Animation supplémentaire au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter une classe d'animation aux cartes
    const cards = document.querySelectorAll('.info-card, .reservation-details');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200 + (index * 100));
    });
});

// Confirmation avant impression
function imprimerRecu() {
    if (confirm('Voulez-vous imprimer ce reçu de paiement ?')) {
        window.print();
    }
}
</script>

<?php include '../includes/footer.php'; ?>