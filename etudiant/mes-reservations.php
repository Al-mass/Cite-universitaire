<?php
require_once '../includes/functions.php';

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

// Récupération des réservations avec calcul correct du montant
$stmt = $db->prepare("SELECT r.*, c.numero_chambre, c.type_chambre, c.prix_mensuel, 
                      ct.nom as cite_nom, ct.ville, ct.adresse,
                      (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
                      FROM reservations r 
                      JOIN chambres c ON r.chambre_id = c.id 
                      JOIN cites ct ON c.cite_id = ct.id 
                      WHERE r.utilisateur_id = ? 
                      ORDER BY r.date_reservation DESC");
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-calendar-check"></i> Mes Réservations
                </h2>
                <a href="../chambres/" class="btn btn-success">
                    <i class="bi bi-search"></i> Nouvelle réservation
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($reservations)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-inbox display-1"></i>
            <h4>Aucune réservation</h4>
            <p>Vous n'avez pas encore de réservation.</p>
            <a href="../chambres/" class="btn btn-success">Chercher une chambre</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($reservations as $reservation): 
                // === CALCUL CORRIGÉ DU MONTANT TOTAL ===
                $date_debut = new DateTime($reservation['date_debut']);
                $date_fin = new DateTime($reservation['date_fin']);
                $interval = $date_debut->diff($date_fin);
                $jours = max(1, $interval->days); // Au moins 1 jour
                $mois = $jours / 30;
                $montant_total_calcule = round($reservation['prix_mensuel'] * $mois);
                
                // Utiliser le montant calculé ou celui en base
                $montant_total = $montant_total_calcule;
                
                // Reste à payer
                $total_paye = $reservation['total_paye'] ?? 0;
                $reste_a_payer = $montant_total - $total_paye;
            ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-<?php 
                            echo $reservation['statut'] == 'confirmee' ? 'success' : 
                                ($reservation['statut'] == 'en_attente' ? 'warning' : 
                                ($reservation['statut'] == 'annulee' ? 'danger' : 'secondary')); 
                        ?> <?php echo $reservation['statut'] == 'en_attente' ? '' : 'text-white'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Réservation #<?php echo $reservation['id']; ?></h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo ucfirst($reservation['statut']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Chambre</h6>
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars($reservation['numero_chambre']); ?></strong><br>
                                        <small class="text-muted"><?php echo ucfirst($reservation['type_chambre']); ?></small><br>
                                        <?php echo htmlspecialchars($reservation['cite_nom']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($reservation['ville']); ?></small>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Séjour</h6>
                                    <p class="mb-1">
                                        <i class="bi bi-calendar3"></i> Arrivée : <strong><?php echo date('d/m/Y', strtotime($reservation['date_debut'])); ?></strong><br>
                                        <i class="bi bi-calendar3"></i> Départ : <strong><?php echo date('d/m/Y', strtotime($reservation['date_fin'])); ?></strong><br>
                                        <i class="bi bi-hourglass"></i> Durée : <strong><?php echo $jours; ?> jour(s)</strong><br>
                                        <i class="bi bi-cash"></i> Prix : <strong><?php echo formatFCFA($reservation['prix_mensuel']); ?>/mois</strong>
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Détail du paiement -->
                            <div class="row mb-2">
                                <div class="col-6">
                                    <span class="text-muted">Montant total :</span><br>
                                    <strong class="fs-5"><?php echo formatFCFA($montant_total); ?></strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Déjà payé :</span><br>
                                    <strong class="text-success"><?php echo formatFCFA($total_paye); ?></strong>
                                </div>
                            </div>
                            
                            <?php if ($reste_a_payer > 0 && $reservation['statut'] != 'annulee'): ?>
                                <div class="alert alert-warning py-2 mb-2">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Reste à payer : <strong><?php echo formatFCFA($reste_a_payer); ?></strong>
                                </div>
                            <?php elseif ($reste_a_payer <= 0 && $total_paye > 0): ?>
                                <div class="alert alert-success py-2 mb-2">
                                    <i class="bi bi-check-circle"></i> Entièrement payé
                                </div>
                            <?php endif; ?>
                            
                            <!-- Barre de progression -->
                            <?php if ($montant_total > 0): ?>
                                <div class="progress mb-3" style="height: 8px;">
                                    <?php $pourcentage = round(($total_paye / $montant_total) * 100); ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $pourcentage; ?>%"></div>
                                </div>
                                <small class="text-muted">Paiement : <?php echo $pourcentage; ?>%</small>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if ($reservation['statut'] == 'en_attente'): ?>
                                        <span class="text-warning"><i class="bi bi-clock"></i> En attente</span>
                                    <?php elseif ($reservation['statut'] == 'confirmee'): ?>
                                        <span class="text-success"><i class="bi bi-check-circle"></i> Confirmée</span>
                                    <?php elseif ($reservation['statut'] == 'annulee'): ?>
                                        <span class="text-danger"><i class="bi bi-x-circle"></i> Annulée</span>
                                    <?php elseif ($reservation['statut'] == 'terminee'): ?>
                                        <span class="text-secondary"><i class="bi bi-flag"></i> Terminée</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="btn-group">
                                    <?php if ($reste_a_payer > 0 && in_array($reservation['statut'], ['en_attente', 'confirmee'])): ?>
                                        <a href="paiements.php?reservation_id=<?php echo $reservation['id']; ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-credit-card"></i> Payer
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($reservation['statut'] == 'terminee'): ?>
                                        <a href="evaluations.php?chambre_id=<?php echo $reservation['chambre_id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-star"></i> Évaluer
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="../chambres/details.php?id=<?php echo $reservation['chambre_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Voir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.card { border: none; border-radius: 12px; transition: all 0.3s; }
.card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
.progress { border-radius: 10px; background-color: #e9ecef; }
</style>

<?php include '../includes/footer.php'; ?>