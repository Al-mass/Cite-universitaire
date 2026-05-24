<?php
require_once '../includes/functions.php';

// Activer l'affichage des erreurs pour le débogage (à commenter en production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Vérifier si l'utilisateur est admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Définir les fonctions si elles n'existent pas
if (!function_exists('formatFCFA')) {
    function formatFCFA($montant, $avec_symbole = true) {
        if ($montant === null || $montant === '') {
            $montant = 0;
        }
        $formatted = number_format(floatval($montant), 0, ',', ' ');
        return $avec_symbole ? $formatted . ' FCFA' : $formatted;
    }
}

if (!function_exists('getNomMethodePaiement')) {
    function getNomMethodePaiement($code) {
        $methodes = [
            'carte_credit' => 'Carte Bancaire',
            'mobile_money' => 'MTN Mobile Money',
            'orange_money' => 'Orange Money',
            'om' => 'Orange Money',
            'virement' => 'Virement Bancaire',
            'especes' => 'Espèces'
        ];
        return $methodes[$code] ?? ucfirst(str_replace('_', ' ', $code));
    }
}

if (!function_exists('getIconeMethodePaiement')) {
    function getIconeMethodePaiement($code) {
        $icones = [
            'carte_credit' => 'credit-card',
            'mobile_money' => 'phone',
            'orange_money' => 'wifi',
            'om' => 'wifi',
            'virement' => 'bank',
            'especes' => 'cash'
        ];
        return $icones[$code] ?? 'credit-card';
    }
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de réservation invalide";
    redirect('gestion-reservations.php');
}

$reservation_id = intval($_GET['id']);
$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

// ==================== RÉCUPÉRATION DES DONNÉES ====================

// Récupérer les détails de la réservation (SANS colonnes problématiques)
$query = "SELECT r.*, 
          u.id as user_id, u.nom, u.prenom, u.email, u.telephone, u.date_inscription as user_inscription,
          c.id as chambre_id, c.numero_chambre, c.type_chambre, c.prix_mensuel, c.equipements, 
          c.description as chambre_description, c.image as chambre_image,
          ct.id as cite_id, ct.nom as cite_nom, ct.adresse, ct.ville, ct.code_postal
          FROM reservations r
          JOIN utilisateurs u ON r.utilisateur_id = u.id
          JOIN chambres c ON r.chambre_id = c.id
          JOIN cites ct ON c.cite_id = ct.id
          WHERE r.id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    $_SESSION['error'] = "Réservation non trouvée";
    redirect('gestion-reservations.php');
}

// Récupérer les paiements liés
$stmt = $db->prepare("SELECT * FROM paiements WHERE reservation_id = ? ORDER BY date_paiement DESC");
$stmt->execute([$reservation_id]);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer le total payé
$total_paye = 0;
foreach ($paiements as $p) {
    if ($p['statut'] == 'complete') {
        $total_paye += $p['montant'];
    }
}
// Initialiser $reste_a_payer avec une valeur par défaut
$reste_a_payer = $reservation['montant_total'] - $total_paye;

// Récupérer l'évaluation si elle existe
$stmt = $db->prepare("SELECT * FROM evaluations WHERE utilisateur_id = ? AND chambre_id = ?");
$stmt->execute([$reservation['user_id'], $reservation['chambre_id']]);
$evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

// ==================== TRAITEMENT DES ACTIONS ====================
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'changer_statut') {
        $nouveau_statut = $_POST['statut'];
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveau_statut, $reservation_id]);
            
            if ($nouveau_statut == 'confirmee') {
                $stmt = $db->prepare("UPDATE chambres SET disponible = 0 WHERE id = ?");
                $stmt->execute([$reservation['chambre_id']]);
            }
            
            if (in_array($nouveau_statut, ['terminee', 'annulee'])) {
                $stmt = $db->prepare("UPDATE chambres SET disponible = 1 WHERE id = ?");
                $stmt->execute([$reservation['chambre_id']]);
            }
            
            if (function_exists('envoyerNotification')) {
                $message = "Le statut de votre réservation #$reservation_id a été changé en : " . ucfirst($nouveau_statut);
                envoyerNotification($reservation['user_id'], 'reservation', $message);
            }
            
            $db->commit();
            $success = "Statut de la réservation mis à jour avec succès";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur lors du changement de statut : " . $e->getMessage();
        }
        
        // Rafraîchir les données
        redirect("details-reservation.php?id=$reservation_id");
    }
    
    if ($action == 'ajouter_paiement') {
        $montant = floatval($_POST['montant'] ?? 0);
        $methode = $_POST['methode_paiement'] ?? '';
        $transaction_id = trim($_POST['transaction_id'] ?? '');
        $statut = $_POST['statut_paiement'] ?? 'complete';
        
        if (empty($transaction_id)) {
            $prefix = match($methode) {
                'orange_money' => 'OM',
                'carte_credit' => 'CB',
                'virement' => 'VIR',
                'especes' => 'CASH',
                default => 'PAY'
            };
            $transaction_id = $prefix . '-' . strtoupper(uniqid());
        }
        
        if ($montant > 0 && $montant <= $reste_a_payer) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) 
                                     VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$reservation_id, $montant, $methode, $statut, $transaction_id]);
                
                if ($statut == 'complete' && ($total_paye + $montant) >= $reservation['montant_total']) {
                    if ($reservation['statut'] == 'en_attente') {
                        $stmt = $db->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = ?");
                        $stmt->execute([$reservation_id]);
                    }
                }
                
                if (function_exists('envoyerNotification')) {
                    $message = "Un paiement de " . formatFCFA($montant) . " a été enregistré pour votre réservation #$reservation_id";
                    envoyerNotification($reservation['user_id'], 'paiement', $message);
                }
                
                $db->commit();
                $success = "Paiement de " . formatFCFA($montant) . " enregistré avec succès";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        } else {
            $error = "Montant invalide. Maximum : " . formatFCFA($reste_a_payer);
        }
        
        redirect("details-reservation.php?id=$reservation_id");
    }
    
    if ($action == 'envoyer_message') {
        $message = trim($_POST['message_personnel'] ?? '');
        
        if (!empty($message) && function_exists('envoyerNotification')) {
            envoyerNotification($reservation['user_id'], 'message', $message);
            $success = "Message envoyé avec succès";
        }
        
        redirect("details-reservation.php?id=$reservation_id");
    }
}

// Récupérer les messages de session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// S'assurer que $reste_a_payer est défini pour le JavaScript
$reste_a_payer_js = $reste_a_payer;

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>
                        <i class="bi bi-info-circle"></i> Détails de la Réservation #<?php echo $reservation_id; ?>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item"><a href="gestion-reservations.php">Réservations</a></li>
                            <li class="breadcrumb-item active">Réservation #<?php echo $reservation_id; ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="gestion-reservations.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                    <?php if ($reste_a_payer > 0): ?>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ajouterPaiementModal">
                            <i class="bi bi-plus-circle"></i> Ajouter un paiement
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Colonne gauche -->
        <div class="col-lg-8">
            <!-- Statut -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center" 
                     style="background-color: <?php 
                        echo $reservation['statut'] == 'confirmee' ? '#28a745' : 
                            ($reservation['statut'] == 'en_attente' ? '#FCD116' : 
                            ($reservation['statut'] == 'annulee' ? '#CE1126' : '#6c757d')); 
                     ?>; color: <?php echo $reservation['statut'] == 'en_attente' ? '#000' : '#fff'; ?>;">
                    <h5 class="mb-0">
                        <i class="bi bi-flag"></i> Statut : <?php echo ucfirst($reservation['statut']); ?>
                    </h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#changerStatutModal">
                        <i class="bi bi-pencil"></i> Changer le statut
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Date de réservation :</strong> <?php echo date('d/m/Y H:i', strtotime($reservation['date_reservation'])); ?></p>
                            <p><strong>Date d'arrivée :</strong> <?php echo date('d/m/Y', strtotime($reservation['date_debut'])); ?></p>
                            <p><strong>Date de départ :</strong> <?php echo date('d/m/Y', strtotime($reservation['date_fin'])); ?></p>
                            <p>
                                <strong>Durée :</strong> 
                                <?php 
                                $debut = new DateTime($reservation['date_debut']);
                                $fin = new DateTime($reservation['date_fin']);
                                $interval = $debut->diff($fin);
                                echo $interval->days . ' jours';
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Montant total :</strong> <span class="fs-5"><?php echo formatFCFA($reservation['montant_total']); ?></span></p>
                            <p><strong>Total payé :</strong> <span class="text-success"><?php echo formatFCFA($total_paye); ?></span></p>
                            <p><strong>Reste à payer :</strong> 
                                <span class="<?php echo $reste_a_payer > 0 ? 'text-danger' : 'text-success'; ?> fw-bold">
                                    <?php echo formatFCFA($reste_a_payer); ?>
                                </span>
                            </p>
                            <div class="progress mb-2" style="height: 20px;">
                                <?php 
                                $pourcentage = $reservation['montant_total'] > 0 ? 
                                    round(($total_paye / $reservation['montant_total']) * 100) : 0;
                                ?>
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $pourcentage; ?>%">
                                    <?php echo $pourcentage; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations de la chambre -->
            <div class="card mb-4">
                <div class="card-header" style="background-color: #007A5E; color: white;">
                    <h5 class="mb-0"><i class="bi bi-door-open"></i> Informations de la chambre</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <?php if (!empty($reservation['chambre_image'])): ?>
                                <img src="../<?php echo $reservation['chambre_image']; ?>" 
                                     class="img-fluid rounded" alt="Chambre">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                     style="height: 200px;">
                                    <i class="bi bi-door-open display-1 text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h6>Chambre <?php echo htmlspecialchars($reservation['numero_chambre']); ?></h6>
                            <p class="mb-1">
                                <strong>Type :</strong> <?php echo ucfirst($reservation['type_chambre']); ?><br>
                                <strong>Cité :</strong> <?php echo htmlspecialchars($reservation['cite_nom']); ?><br>
                                <strong>Adresse :</strong> <?php echo htmlspecialchars($reservation['adresse']); ?><br>
                                <strong>Ville :</strong> <?php echo htmlspecialchars($reservation['ville']); ?><br>
                                <strong>Prix mensuel :</strong> <?php echo formatFCFA($reservation['prix_mensuel']); ?>
                            </p>
                            <?php if (!empty($reservation['equipements'])): ?>
                                <p class="mb-1"><strong>Équipements :</strong> <?php echo htmlspecialchars($reservation['equipements']); ?></p>
                            <?php endif; ?>
                            <a href="../chambres/details.php?id=<?php echo $reservation['chambre_id']; ?>" 
                               class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                                <i class="bi bi-eye"></i> Voir la fiche de la chambre
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paiements -->
            <div class="card mb-4">
                <div class="card-header" style="background-color: #FCD116; color: #000;">
                    <h5 class="mb-0"><i class="bi bi-credit-card"></i> Historique des paiements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($paiements)): ?>
                        <p class="text-muted">Aucun paiement enregistré</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Transaction</th>
                                        <th>Montant</th>
                                        <th>Méthode</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paiements as $p): ?>
                                    <tr>
                                        <td>#<?php echo $p['id']; ?></td>
                                        <td><small><?php echo htmlspecialchars($p['transaction_id'] ?: 'N/A'); ?></small></td>
                                        <td class="<?php echo $p['montant'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo formatFCFA($p['montant']); ?>
                                        </td>
                                        <td>
                                            <i class="bi bi-<?php echo getIconeMethodePaiement($p['methode_paiement']); ?>"></i>
                                            <?php echo getNomMethodePaiement($p['methode_paiement']); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($p['date_paiement'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $p['statut'] == 'complete' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($p['statut']); ?>
                                            </span>
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

        <!-- Colonne droite -->
        <div class="col-lg-4">
            <!-- Informations de l'étudiant -->
            <div class="card mb-4">
                <div class="card-header" style="background-color: #006400; color: white;">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Informations de l'étudiant</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-person-circle display-1 text-muted"></i>
                    </div>
                    <h6 class="text-center"><?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?></h6>
                    <hr>
                    <p class="mb-1"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($reservation['email']); ?></p>
                    <p class="mb-1"><i class="bi bi-telephone"></i> <?php echo $reservation['telephone'] ? htmlspecialchars($reservation['telephone']) : 'Non renseigné'; ?></p>
                    <p class="mb-1"><i class="bi bi-calendar"></i> Inscrit le <?php echo date('d/m/Y', strtotime($reservation['user_inscription'])); ?></p>
                    <a href="gestion-utilisateurs.php?action=voir&id=<?php echo $reservation['user_id']; ?>" class="btn btn-outline-primary btn-sm w-100 mt-2">
                        <i class="bi bi-person"></i> Voir profil complet
                    </a>
                </div>
            </div>

            <!-- Évaluation -->
            <?php if ($evaluation): ?>
            <div class="card mb-4">
                <div class="card-header" style="background-color: #FCD116; color: #000;">
                    <h5 class="mb-0"><i class="bi bi-star"></i> Évaluation</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?php echo $i <= $evaluation['note'] ? '-fill' : ''; ?> text-warning"></i>
                        <?php endfor; ?>
                        <span class="ms-2">(<?php echo $evaluation['note']; ?>/5)</span>
                    </div>
                    <p class="mb-0"><em>"<?php echo nl2br(htmlspecialchars($evaluation['commentaire'])); ?>"</em></p>
                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Envoyer un message -->
            <div class="card mb-4">
                <div class="card-header" style="background-color: #17a2b8; color: white;">
                    <h5 class="mb-0"><i class="bi bi-chat"></i> Envoyer un message</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="envoyer_message">
                        <div class="mb-3">
                            <textarea class="form-control" name="message_personnel" rows="3" 
                                      placeholder="Votre message à l'étudiant..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-info w-100">
                            <i class="bi bi-send"></i> Envoyer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Changer Statut -->
<div class="modal fade" id="changerStatutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="changer_statut">
                <div class="modal-header">
                    <h5 class="modal-title">Changer le statut</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <select class="form-select" name="statut" required>
                        <option value="en_attente" <?php echo $reservation['statut'] == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="confirmee" <?php echo $reservation['statut'] == 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                        <option value="annulee" <?php echo $reservation['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                        <option value="terminee" <?php echo $reservation['statut'] == 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                    </select>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> L'étudiant sera notifié du changement.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajouter Paiement -->
<?php if ($reste_a_payer > 0): ?>
<div class="modal fade" id="ajouterPaiementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="ajouterPaiementForm">
                <input type="hidden" name="action" value="ajouter_paiement">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Ajouter un paiement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Montant à payer</label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   name="montant" 
                                   id="modal_montant"
                                   step="<?php echo ($reste_a_payer < 500) ? '1' : '500'; ?>" 
                                   min="<?php echo min(1, $reste_a_payer); ?>" 
                                   max="<?php echo $reste_a_payer; ?>" 
                                   value="<?php echo $reste_a_payer; ?>" 
                                   required>
                            <span class="input-group-text">FCFA</span>
                        </div>
                        <small class="text-muted">
                            <?php if ($reste_a_payer < 500): ?>
                                <span class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Le montant restant est de <?php echo formatFCFA($reste_a_payer); ?>. 
                                    Vous devez payer le montant exact.
                                </span>
                            <?php else: ?>
                                Minimum : 500 FCFA | Maximum : <?php echo formatFCFA($reste_a_payer); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <?php if ($reste_a_payer >= 500): ?>
                    <div class="mb-3">
                        <label class="form-label">Paiement rapide</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm montant-rapide" 
                                    data-montant="<?php echo $reste_a_payer; ?>">
                                Tout payer
                            </button>
                            <?php 
                            $montants = [10000, 20000, 50000, 100000];
                            foreach ($montants as $m): 
                                if ($m <= $reste_a_payer): 
                            ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm montant-rapide" 
                                        data-montant="<?php echo $m; ?>">
                                    <?php echo formatFCFA($m); ?>
                                </button>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Méthode de paiement</label>
                        <select class="form-select" name="methode_paiement" required>
                            <option value="especes">Espèces</option>
                            <option value="orange_money">Orange Money</option>
                            <option value="carte_credit">Carte Bancaire</option>
                            <option value="virement">Virement Bancaire</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Statut du paiement</label>
                        <select class="form-select" name="statut_paiement">
                            <option value="complete">Complété</option>
                            <option value="en_attente">En attente</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Référence de transaction</label>
                        <input type="text" class="form-control" name="transaction_id" 
                               placeholder="Laissez vide pour générer automatiquement">
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Récapitulatif :</strong><br>
                        Montant total : <?php echo formatFCFA($reservation['montant_total']); ?><br>
                        Déjà payé : <?php echo formatFCFA($total_paye); ?><br>
                        <span class="<?php echo $reste_a_payer > 0 ? 'text-danger' : 'text-success'; ?>">
                            Reste à payer : <strong><?php echo formatFCFA($reste_a_payer); ?></strong>
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Enregistrer le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
}
.progress {
    border-radius: 10px;
    background-color: #e9ecef;
}
.table th {
    font-weight: 600;
    font-size: 0.85rem;
}
.badge {
    font-weight: 500;
    padding: 5px 10px;
}
.form-control.is-invalid {
    border-color: #CE1126;
}
.montant-rapide {
    min-width: 90px;
}
</style>

<script>
// Passer les variables PHP à JavaScript
const resteAPayer = <?php echo $reste_a_payer_js; ?>;

// Gestion des boutons de montant rapide
document.querySelectorAll('.montant-rapide').forEach(btn => {
    btn.addEventListener('click', function() {
        const montant = parseInt(this.dataset.montant);
        const inputMontant = document.getElementById('modal_montant');
        const max = parseInt(inputMontant.max);
        
        let montantFinal = montant;
        if (montantFinal > max) {
            montantFinal = max;
        }
        if (montantFinal < parseInt(inputMontant.min)) {
            montantFinal = parseInt(inputMontant.min);
        }
        
        inputMontant.value = montantFinal;
    });
});

// Validation du formulaire
document.getElementById('ajouterPaiementForm')?.addEventListener('submit', function(e) {
    const inputMontant = document.getElementById('modal_montant');
    const montant = parseFloat(inputMontant.value);
    const min = parseFloat(inputMontant.min);
    const max = parseFloat(inputMontant.max);
    
    if (isNaN(montant) || montant < min) {
        e.preventDefault();
        alert('Le montant minimum est de ' + formatFCFA(min));
        inputMontant.value = min;
        return false;
    }
    
    if (montant > max) {
        e.preventDefault();
        alert('Le montant maximum est de ' + formatFCFA(max));
        inputMontant.value = max;
        return false;
    }
    
    if (resteAPayer < 500 && montant != resteAPayer) {
        e.preventDefault();
        alert('Le montant doit être exactement ' + formatFCFA(resteAPayer));
        inputMontant.value = resteAPayer;
        return false;
    }
    
    if (!confirm('Confirmer l\'enregistrement du paiement de ' + formatFCFA(montant) + ' ?')) {
        e.preventDefault();
        return false;
    }
});

// Fonction de formatage FCFA
function formatFCFA(montant) {
    return new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
}

// Validation en temps réel
document.getElementById('modal_montant')?.addEventListener('input', function() {
    const montant = parseFloat(this.value) || 0;
    const max = parseFloat(this.max);
    const min = parseFloat(this.min);
    
    if (montant < min || montant > max) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});
</script>

<?php include '../includes/footer.php'; ?>