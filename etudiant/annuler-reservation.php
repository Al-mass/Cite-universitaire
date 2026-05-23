<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Vérifier si l'ID de réservation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('mes-reservations.php');
}

$reservation_id = $_GET['id'];

// Récupérer les informations de la réservation
$stmt = $db->prepare("SELECT r.*, c.numero_chambre, c.type_chambre, c.prix_mensuel,
                      ct.nom as cite_nom, ct.ville,
                      u.nom, u.prenom, u.email
                      FROM reservations r
                      JOIN chambres c ON r.chambre_id = c.id
                      JOIN cites ct ON c.cite_id = ct.id
                      JOIN utilisateurs u ON r.utilisateur_id = u.id
                      WHERE r.id = ? AND r.utilisateur_id = ?");
$stmt->execute([$reservation_id, $user_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si la réservation existe et appartient à l'utilisateur
if (!$reservation) {
    $_SESSION['error'] = "Réservation non trouvée ou accès non autorisé.";
    redirect('mes-reservations.php');
}

// Vérifier si la réservation peut être annulée
$date_debut = new DateTime($reservation['date_debut']);
$aujourdhui = new DateTime();
$interval = $aujourdhui->diff($date_debut);
$jours_avant_debut = $interval->days;

$peut_etre_annulee = true;
$message_annulation = "";

if ($reservation['statut'] == 'annulee') {
    $peut_etre_annulee = false;
    $message_annulation = "Cette réservation a déjà été annulée.";
} elseif ($reservation['statut'] == 'terminee') {
    $peut_etre_annulee = false;
    $message_annulation = "Cette réservation est déjà terminée et ne peut plus être annulée.";
} elseif ($date_debut < $aujourdhui) {
    $peut_etre_annulee = false;
    $message_annulation = "Cette réservation a déjà commencé et ne peut plus être annulée.";
}

// Calculer les frais d'annulation
$frais_annulation = 0;
$montant_remboursable = 0;
$politique_annulation = "";

if ($peut_etre_annulee) {
    // Récupérer les paiements effectués
    $stmt = $db->prepare("SELECT SUM(montant) as total_paye FROM paiements 
                         WHERE reservation_id = ? AND statut = 'complete'");
    $stmt->execute([$reservation_id]);
    $total_paye = $stmt->fetch(PDO::FETCH_ASSOC)['total_paye'] ?? 0;
    
    if ($jours_avant_debut > 30) {
        $frais_annulation = 0;
        $politique_annulation = "Annulation plus de 30 jours avant l'arrivée : aucun frais.";
    } elseif ($jours_avant_debut > 14) {
        $frais_annulation = $reservation['montant_total'] * 0.25;
        $politique_annulation = "Annulation entre 14 et 30 jours avant l'arrivée : 25% de frais.";
    } elseif ($jours_avant_debut > 7) {
        $frais_annulation = $reservation['montant_total'] * 0.50;
        $politique_annulation = "Annulation entre 7 et 14 jours avant l'arrivée : 50% de frais.";
    } else {
        $frais_annulation = $reservation['montant_total'] * 0.75;
        $politique_annulation = "Annulation moins de 7 jours avant l'arrivée : 75% de frais.";
    }
    
    $montant_remboursable = $total_paye - $frais_annulation;
    if ($montant_remboursable < 0) $montant_remboursable = 0;
}

// Traitement de l'annulation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmer_annulation'])) {
    if ($peut_etre_annulee) {
        try {
            $db->beginTransaction();
            
            // Mettre à jour le statut de la réservation
            $stmt = $db->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?");
            $stmt->execute([$reservation_id]);
            
            // Mettre à jour la disponibilité de la chambre
            $stmt = $db->prepare("UPDATE chambres SET disponible = 1 WHERE id = ?");
            $stmt->execute([$reservation['chambre_id']]);
            
            // Enregistrer le remboursement si nécessaire
            if ($montant_remboursable > 0) {
                $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) 
                                     VALUES (?, ?, 'virement', 'complete', ?)");
                $transaction_id = 'REFUND-' . strtoupper(uniqid());
                $stmt->execute([$reservation_id, -$montant_remboursable, $transaction_id]);
            }
            
            // Enregistrer les frais d'annulation
            if ($frais_annulation > 0) {
                $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) 
                                     VALUES (?, ?, 'virement', 'complete', ?)");
                $transaction_id = 'CANCEL-FEE-' . strtoupper(uniqid());
                $stmt->execute([$reservation_id, $frais_annulation, $transaction_id]);
            }
            
            // Créer une notification pour l'étudiant
            $message = "Votre réservation #" . $reservation_id . " a été annulée. ";
            if ($montant_remboursable > 0) {
                $message .= "Un remboursement de " . number_format($montant_remboursable, 2) . " € sera effectué.";
            }
            if ($frais_annulation > 0) {
                $message .= " Des frais d'annulation de " . number_format($frais_annulation, 2) . " € ont été appliqués.";
            }
            
            $stmt = $db->prepare("INSERT INTO notifications (utilisateur_id, type, message) VALUES (?, 'reservation', ?)");
            $stmt->execute([$user_id, $message]);
            
            // Notifier l'administrateur
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            $admin_id = $stmt->fetchColumn();
            
            if ($admin_id) {
                $message_admin = "Réservation #" . $reservation_id . " annulée par l'étudiant " . 
                                $reservation['prenom'] . " " . $reservation['nom'];
                $stmt = $db->prepare("INSERT INTO notifications (utilisateur_id, type, message) VALUES (?, 'reservation', ?)");
                $stmt->execute([$admin_id, $message_admin]);
            }
            
            $db->commit();
            
            $_SESSION['success'] = "Votre réservation a été annulée avec succès. " . $message;
            redirect('historique-reservations.php');
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Une erreur est survenue lors de l'annulation : " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-exclamation-triangle"></i> Annuler la réservation
                    </h4>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!$peut_etre_annulee): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-circle"></i> <?php echo $message_annulation; ?>
                        </div>
                        <div class="text-center">
                            <a href="mes-reservations.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Retour aux réservations
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Détails de la réservation -->
                        <div class="alert alert-info">
                            <h5>Détails de la réservation à annuler</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Réservation #<?php echo $reservation['id']; ?></strong><br>
                                        <strong>Chambre :</strong> <?php echo htmlspecialchars($reservation['numero_chambre']); ?> 
                                        (<?php echo ucfirst($reservation['type_chambre']); ?>)<br>
                                        <strong>Cité :</strong> <?php echo htmlspecialchars($reservation['cite_nom']); ?> - 
                                        <?php echo htmlspecialchars($reservation['ville']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Dates :</strong><br>
                                        Du <?php echo date('d/m/Y', strtotime($reservation['date_debut'])); ?><br>
                                        Au <?php echo date('d/m/Y', strtotime($reservation['date_fin'])); ?><br>
                                        <strong>Montant total :</strong> <?php echo number_format($reservation['montant_total'], 2); ?> €
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Politique d'annulation -->
                        <div class="card mb-3">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0 text-dark">Politique d'annulation</h5>
                            </div>
                            <div class="card-body">
                                <p><strong><?php echo $politique_annulation; ?></strong></p>
                                
                                <div class="alert alert-secondary">
                                    <h6>Détail des frais :</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td>Montant total de la réservation :</td>
                                            <td class="text-end"><?php echo number_format($reservation['montant_total'], 2); ?> €</td>
                                        </tr>
                                        <?php
                                        $stmt = $db->prepare("SELECT SUM(montant) as total_paye FROM paiements 
                                                             WHERE reservation_id = ? AND statut = 'complete'");
                                        $stmt->execute([$reservation_id]);
                                        $total_paye = $stmt->fetch(PDO::FETCH_ASSOC)['total_paye'] ?? 0;
                                        ?>
                                        <tr>
                                            <td>Montant déjà payé :</td>
                                            <td class="text-end"><?php echo number_format($total_paye, 2); ?> €</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td>Frais d'annulation :</td>
                                            <td class="text-end">- <?php echo number_format($frais_annulation, 2); ?> €</td>
                                        </tr>
                                        <tr class="table-success">
                                            <td><strong>Montant remboursable :</strong></td>
                                            <td class="text-end"><strong><?php echo number_format($montant_remboursable, 2); ?> €</strong></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <p class="mb-0">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Le remboursement sera effectué sous 5 à 10 jours ouvrés sur votre moyen de paiement initial.
                                    </small>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Raison de l'annulation -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="raison_annulation" class="form-label">
                                    Raison de l'annulation (optionnel)
                                </label>
                                <select class="form-select" id="raison_annulation" name="raison_annulation">
                                    <option value="">Sélectionnez une raison</option>
                                    <option value="changement_avis">Changement d'avis</option>
                                    <option value="trouve_mieux">J'ai trouvé mieux ailleurs</option>
                                    <option value="probleme_financier">Problème financier</option>
                                    <option value="changement_programme">Changement de programme d'études</option>
                                    <option value="autre">Autre raison</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="autre_raison_div" style="display: none;">
                                <label for="autre_raison" class="form-label">Précisez votre raison</label>
                                <textarea class="form-control" id="autre_raison" name="autre_raison" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmation" required>
                                    <label class="form-check-label" for="confirmation">
                                        Je comprends que cette action est définitive et j'accepte les conditions d'annulation.
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="mes-reservations.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                                <button type="submit" name="confirmer_annulation" class="btn btn-danger">
                                    <i class="bi bi-x-circle"></i> Confirmer l'annulation
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Aide -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6><i class="bi bi-question-circle"></i> Besoin d'aide ?</h6>
                    <p class="mb-0">
                        Si vous avez des questions concernant l'annulation, n'hésitez pas à 
                        <a href="../contact.php">contacter notre service client</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('raison_annulation')?.addEventListener('change', function() {
    const autreDiv = document.getElementById('autre_raison_div');
    if (this.value === 'autre') {
        autreDiv.style.display = 'block';
        document.getElementById('autre_raison').required = true;
    } else {
        autreDiv.style.display = 'none';
        document.getElementById('autre_raison').required = false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>