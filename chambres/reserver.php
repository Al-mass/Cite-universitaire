<?php
require_once '../includes/functions.php';

// Vérifier connexion
if (!isLoggedIn() || isAdmin()) {
    $_SESSION['error'] = "Veuillez vous connecter en tant qu'étudiant";
    redirect('../auth/login.php');
}

// Vérifier ID chambre
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$chambre_id = intval($_GET['id']);
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer la chambre
$stmt = $db->prepare("SELECT c.*, ct.nom as cite_nom, ct.ville FROM chambres c JOIN cites ct ON c.cite_id = ct.id WHERE c.id = ?");
$stmt->execute([$chambre_id]);
$chambre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chambre) {
    $_SESSION['error'] = "Chambre non trouvée";
    redirect('index.php');
}

// Vérifier si la chambre est globalement marquée disponible (mais on vérifiera aussi les dates)
// Ne pas bloquer ici car une chambre peut être réservée sur des dates non chevauchantes

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    
    // Validation des dates
    $today = date('Y-m-d');
    $date_debut_obj = new DateTime($date_debut);
    $date_fin_obj = new DateTime($date_fin);
    $today_obj = new DateTime($today);
    
    if (empty($date_debut) || empty($date_fin)) {
        $error = "Veuillez sélectionner les deux dates";
    } elseif ($date_debut_obj < $today_obj) {
        $error = "La date d'arrivée ne peut pas être dans le passé";
    } elseif ($date_fin_obj <= $date_debut_obj) {
        $error = "La date de départ doit être après la date d'arrivée";
    } else {
        // Vérification des conflits de dates pour cette chambre
        // Une chambre est considérée occupée pour les dates où une réservation confirmée ou en attente existe
        $stmt = $db->prepare("SELECT COUNT(*) as total, 
                              GROUP_CONCAT(CONCAT(date_debut, ' - ', date_fin) SEPARATOR ', ') as dates_conflict
                              FROM reservations 
                              WHERE chambre_id = ? 
                              AND statut IN ('confirmee', 'en_attente')
                              AND (
                                  (date_debut <= ? AND date_fin >= ?)  -- Chevauchement avec la nouvelle réservation
                                  OR (date_debut <= ? AND date_fin >= ?)
                                  OR (date_debut >= ? AND date_fin <= ?)
                              )");
        $stmt->execute([
            $chambre_id, 
            $date_fin, $date_debut,     // Condition 1: réservation existante qui couvre la période
            $date_fin, $date_debut,     // Condition 2: réservation existante qui chevauche
            $date_debut, $date_fin      // Condition 3: nouvelle réservation qui englobe une existante
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            $error = "Cette chambre est déjà occupée pour les dates sélectionnées.";
            $error_detail = "Périodes déjà réservées : " . $result['dates_conflict'];
        } else {
            // Calculer le prix basé sur la durée
            $interval = $date_debut_obj->diff($date_fin_obj);
            $jours = max(1, $interval->days);
            // Prix au prorata du prix mensuel (base 30 jours)
            $montant = round($chambre['prix_mensuel'] * ($jours / 30));
            
            // Enregistrer la réservation
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO reservations (utilisateur_id, chambre_id, date_debut, date_fin, montant_total, statut) 
                                     VALUES (?, ?, ?, ?, ?, 'en_attente')");
                $stmt->execute([$user_id, $chambre_id, $date_debut, $date_fin, $montant]);
                $reservation_id = $db->lastInsertId();
                
                // Ne pas modifier la disponibilité globale de la chambre
                // La disponibilité sera vérifiée dynamiquement par les dates
                
                // Notification admin
                $stmt = $db->query("SELECT id FROM utilisateurs WHERE role = 'admin' LIMIT 1");
                $admin_id = $stmt->fetchColumn();
                if ($admin_id && function_exists('envoyerNotification')) {
                    $message = "Nouvelle réservation #$reservation_id pour la chambre " . $chambre['numero_chambre'];
                    envoyerNotification($admin_id, 'reservation', $message);
                }
                
                // Notification à l'étudiant
                if (function_exists('envoyerNotification')) {
                    $message = "Votre réservation #$reservation_id a été enregistrée. En attente de confirmation.";
                    envoyerNotification($user_id, 'reservation', $message);
                }
                
                $db->commit();
                
                $_SESSION['success'] = "Réservation effectuée avec succès !";
                header("Location: reservation-confirmation.php?id=$reservation_id");
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
}

// Récupérer les réservations existantes pour affichage (périodes occupées)
$stmt = $db->prepare("SELECT date_debut, date_fin, statut 
                      FROM reservations 
                      WHERE chambre_id = ? 
                      AND statut IN ('confirmee', 'en_attente')
                      AND date_fin >= CURDATE()
                      ORDER BY date_debut ASC");
$stmt->execute([$chambre_id]);
$periodes_occupees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Accueil</a></li>
            <li class="breadcrumb-item"><a href="index.php">Chambres</a></li>
            <li class="breadcrumb-item"><a href="details.php?id=<?php echo $chambre_id; ?>">Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?></a></li>
            <li class="breadcrumb-item active">Réserver</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-gradient-success text-white py-3">
                    <h4 class="mb-0"><i class="bi bi-calendar-plus-fill"></i> Réserver cette chambre</h4>
                </div>
                <div class="card-body p-4">
                    <!-- Message d'erreur -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-x-circle-fill fs-4 me-3"></i>
                                <div>
                                    <strong>Réservation impossible</strong><br>
                                    <?php echo htmlspecialchars($error); ?>
                                    <?php if (isset($error_detail)): ?>
                                        <small class="d-block mt-1"><?php echo htmlspecialchars($error_detail); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Message de succès -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Informations de la chambre -->
                    <div class="room-info-card bg-light p-3 rounded-3 mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <?php if (!empty($chambre['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($chambre['image']); ?>" 
                                         class="img-fluid rounded-3" alt="Chambre">
                                <?php else: ?>
                                    <div class="bg-secondary bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" 
                                         style="height: 100px;">
                                        <i class="bi bi-door-open fs-1 text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?></h5>
                                        <p class="text-muted mb-2">
                                            <i class="bi bi-building"></i> <?php echo htmlspecialchars($chambre['cite_nom']); ?> - 
                                            <?php echo htmlspecialchars($chambre['ville']); ?>
                                        </p>
                                        <div class="d-flex gap-3">
                                            <span class="badge bg-info"><?php echo ucfirst($chambre['type_chambre']); ?></span>
                                            <span><i class="bi bi-people"></i> <?php echo $chambre['capacite']; ?> personne(s)</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="price-display">
                                            <span class="price-label">Prix mensuel</span>
                                            <span class="price-value"><?php echo formatFCFA($chambre['prix_mensuel']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire de réservation -->
                    <form method="POST" id="reservationForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-calendar-check text-success"></i> Date d'arrivée *
                                </label>
                                <input type="date" 
                                       name="date_debut" 
                                       id="date_debut"
                                       class="form-control form-control-lg date-input" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       required>
                                <small class="text-muted">Arrivée à partir de 14h00</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-calendar-x text-danger"></i> Date de départ *
                                </label>
                                <input type="date" 
                                       name="date_fin" 
                                       id="date_fin"
                                       class="form-control form-control-lg date-input" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                       required>
                                <small class="text-muted">Départ avant 12h00</small>
                            </div>
                        </div>

                        <!-- Aperçu du prix -->
                        <div class="price-preview mt-4 p-3 bg-light rounded-3" id="pricePreview" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-calculator-fill text-success fs-5"></i>
                                    <span class="ms-2">Estimation du prix</span>
                                </div>
                                <div>
                                    <span class="fw-bold fs-5" id="estimatedPrice">0 FCFA</span>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar bg-success" id="priceProgress" style="width: 0%"></div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Prix calculé au prorata du prix mensuel (base 30 jours)
                            </small>
                        </div>

                        <!-- Périodes déjà réservées -->
                        <?php if (!empty($periodes_occupees)): ?>
                        <div class="alert alert-warning mt-4">
                            <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Périodes déjà réservées</h6>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($periodes_occupees as $periode): ?>
                                    <li>
                                        Du <?php echo date('d/m/Y', strtotime($periode['date_debut'])); ?> 
                                        au <?php echo date('d/m/Y', strtotime($periode['date_fin'])); ?>
                                        (<?php echo $periode['statut'] == 'confirmee' ? 'Confirmée' : 'En attente'; ?>)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                            <a href="details.php?id=<?php echo $chambre_id; ?>" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                                <i class="bi bi-check-circle"></i> Confirmer la réservation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Informations importantes -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-info-circle-fill"></i> Informations</h5>
                </div>
                <div class="card-body">
                    <div class="info-item mb-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <strong>Réservation gratuite</strong>
                        <p class="text-muted mt-1 mb-0">Aucun frais de réservation n'est facturé.</p>
                    </div>
                    <div class="info-item mb-3">
                        <i class="bi bi-credit-card-fill text-warning me-2"></i>
                        <strong>Paiement après confirmation</strong>
                        <p class="text-muted mt-1 mb-0">Le paiement s'effectue après validation par l'administration.</p>
                    </div>
                    <div class="info-item mb-3">
                        <i class="bi bi-shield-check-fill text-info me-2"></i>
                        <strong>Annulation gratuite</strong>
                        <p class="text-muted mt-1 mb-0">Annulation sans frais jusqu'à 7 jours avant l'arrivée.</p>
                    </div>
                    <hr>
                    <div class="info-item">
                        <i class="bi bi-clock-history text-secondary me-2"></i>
                        <strong>Délai de confirmation</strong>
                        <p class="text-muted mt-1 mb-0">Sous 24-48h ouvrées.</p>
                    </div>
                </div>
            </div>

            <!-- Aide -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-secondary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-headset"></i> Besoin d'aide ?</h5>
                </div>
                <div class="card-body text-center">
                    <i class="bi bi-telephone-fill fs-1 text-secondary"></i>
                    <p class="mt-2 mb-0">Contactez notre service client</p>
                    <p class="fw-bold">+237 699 999 999</p>
                    <hr>
                    <i class="bi bi-envelope-fill"></i>
                    <p class="mb-0">contact@univ-ndere.cm</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-success {
    background: linear-gradient(135deg, #007A5E 0%, #006400 100%);
}

.date-input {
    border: 2px solid #e2e8f0;
    transition: all 0.3s;
}

.date-input:focus {
    border-color: #007A5E;
    box-shadow: 0 0 0 0.2rem rgba(0, 122, 94, 0.25);
}

.price-display {
    background: white;
    padding: 10px 15px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.price-label {
    display: block;
    font-size: 12px;
    color: #718096;
}

.price-value {
    font-size: 22px;
    font-weight: 700;
    color: #007A5E;
}

.price-preview {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.room-info-card {
    border-left: 4px solid #007A5E;
}

@media (max-width: 768px) {
    .btn-lg {
        font-size: 14px;
        padding: 10px 20px;
    }
}
</style>

<script>
// Calcul du prix en temps réel
const prixMensuel = <?php echo $chambre['prix_mensuel']; ?>;

function calculerPrix() {
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = document.getElementById('date_fin').value;
    const pricePreview = document.getElementById('pricePreview');
    const estimatedPriceSpan = document.getElementById('estimatedPrice');
    const priceProgress = document.getElementById('priceProgress');
    
    if (dateDebut && dateFin) {
        const debut = new Date(dateDebut);
        const fin = new Date(dateFin);
        
        if (fin > debut) {
            const diffTime = Math.abs(fin - debut);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // Prix au prorata (base 30 jours)
            const prixEstime = Math.round(prixMensuel * (diffDays / 30));
            const pourcentage = Math.min(100, Math.round((diffDays / 30) * 100));
            
            estimatedPriceSpan.textContent = new Intl.NumberFormat('fr-FR').format(prixEstime) + ' FCFA';
            priceProgress.style.width = pourcentage + '%';
            pricePreview.style.display = 'block';
        } else {
            pricePreview.style.display = 'none';
        }
    } else {
        pricePreview.style.display = 'none';
    }
}

// Validation des dates
document.getElementById('date_debut').addEventListener('change', function() {
    const dateFin = document.getElementById('date_fin');
    const minDate = new Date(this.value);
    minDate.setDate(minDate.getDate() + 1);
    dateFin.min = minDate.toISOString().split('T')[0];
    
    if (dateFin.value && new Date(dateFin.value) <= new Date(this.value)) {
        dateFin.value = '';
    }
    calculerPrix();
});

document.getElementById('date_fin').addEventListener('change', calculerPrix);

// Validation du formulaire avant soumission
document.getElementById('reservationForm').addEventListener('submit', function(e) {
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = document.getElementById('date_fin').value;
    
    if (!dateDebut || !dateFin) {
        e.preventDefault();
        alert('Veuillez sélectionner les dates d\'arrivée et de départ.');
        return false;
    }
    
    const debut = new Date(dateDebut);
    const fin = new Date(dateFin);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (debut < today) {
        e.preventDefault();
        alert('La date d\'arrivée ne peut pas être dans le passé.');
        return false;
    }
    
    if (fin <= debut) {
        e.preventDefault();
        alert('La date de départ doit être après la date d\'arrivée.');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>