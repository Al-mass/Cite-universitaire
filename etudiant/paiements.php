<?php
require_once '../includes/functions.php';

// Vérifier connexion
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}
if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

// Initialisation variables
$error = '';
$success = '';
$etape = isset($_GET['etape']) ? intval($_GET['etape']) : 1;
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$montant_param = isset($_GET['montant']) ? floatval($_GET['montant']) : 0;

if ($etape < 1) $etape = 1;
if ($etape > 4) $etape = 1;

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "✅ Paiement par Carte de Crédit effectué avec succès !";
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// TRAITEMENT POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Étape 1 : choix réservation
    if ($action == 'initier') {
        $rid = intval($_POST['reservation_id'] ?? 0);
        if ($rid > 0) {
            header("Location: paiements.php?etape=2&reservation_id=$rid");
            exit;
        } else {
            $error = "Veuillez sélectionner une réservation";
        }
    }
    
    // Étape 2 : validation montant
    if ($action == 'montant') {
        $rid = intval($_POST['reservation_id'] ?? 0);
        $montant = floatval($_POST['montant'] ?? 0);
        
        $s = $db->prepare("SELECT montant_total - COALESCE((SELECT SUM(montant) FROM paiements WHERE reservation_id = r.id AND statut = 'complete'), 0) as reste FROM reservations r WHERE id = ? AND utilisateur_id = ?");
        $s->execute([$rid, $user_id]);
        $r = $s->fetch();
        
        if ($r && $montant > 0 && $montant <= $r['reste']) {
            header("Location: paiements.php?etape=3&reservation_id=$rid&montant=$montant");
            exit;
        } else {
            $error = "Montant invalide. Maximum: " . formatFCFA($r['reste'] ?? 0);
        }
    }
    
    // Étape 3 : enregistrer paiement par CARTE DE CRÉDIT
    if ($action == 'payer') {
        $rid = intval($_POST['reservation_id'] ?? 0);
        $montant = floatval($_POST['montant'] ?? 0);
        $ref = trim($_POST['reference'] ?? '');
        
        $s = $db->prepare("SELECT * FROM reservations WHERE id = ? AND utilisateur_id = ?");
        $s->execute([$rid, $user_id]);
        $res = $s->fetch();
        
        if (!$res) {
            $error = "Réservation non trouvée";
        } else {
            $deja = $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = $rid AND statut = 'complete'")->fetchColumn();
            $max = $res['montant_total'] - $deja;
            
            if ($montant <= 0 || $montant > $max) {
                $error = "Montant invalide. Maximum: " . formatFCFA($max);
            } else {
                if (empty($ref)) {
                    $ref = 'CC-' . strtoupper(uniqid());
                }
                
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) VALUES (?, ?, 'carte_credit', 'complete', ?)");
                    $result = $stmt->execute([$rid, $montant, $ref]);
                    
                    if (!$result) {
                        $errorInfo = $stmt->errorInfo();
                        throw new Exception("Erreur SQL: " . $errorInfo[2]);
                    }
                    
                    $pid = $db->lastInsertId();
                    
                    if ($res['statut'] == 'en_attente' && ($deja + $montant) >= $res['montant_total']) {
                        $db->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = ?")->execute([$rid]);
                    }
                    
                    if (function_exists('envoyerNotification')) {
                        envoyerNotification($user_id, 'paiement', "Paiement de " . formatFCFA($montant) . " reçu par Carte de Crédit. Transaction: $ref");
                    }
                    
                    $db->commit();
                    header("Location: paiements.php?etape=4&success=1&paiement_id=$pid&montant=$montant");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Erreur: " . $e->getMessage();
                }
            }
        }
    }
}

// Récupérer réservations à payer
$reservations_a_payer = $db->query("SELECT r.*, c.numero_chambre, ct.nom as cite_nom, r.montant_total - COALESCE((SELECT SUM(montant) FROM paiements WHERE reservation_id = r.id AND statut = 'complete'), 0) as reste FROM reservations r JOIN chambres c ON r.chambre_id = c.id JOIN cites ct ON c.cite_id = ct.id WHERE r.utilisateur_id = $user_id AND r.statut IN ('confirmee', 'en_attente') HAVING reste > 0")->fetchAll();

// Détails réservation
$details = null;
$reste = 0;
if ($reservation_id > 0) {
    $s = $db->prepare("SELECT r.*, c.numero_chambre, c.type_chambre, ct.nom as cite_nom, r.montant_total - COALESCE((SELECT SUM(montant) FROM paiements WHERE reservation_id = r.id AND statut = 'complete'), 0) as reste FROM reservations r JOIN chambres c ON r.chambre_id = c.id JOIN cites ct ON c.cite_id = ct.id WHERE r.id = ? AND r.utilisateur_id = ?");
    $s->execute([$reservation_id, $user_id]);
    $details = $s->fetch();
    if ($details) $reste = $details['reste'];
}

// Historique
$historique = $db->query("SELECT p.*, r.id as reservation_id, c.numero_chambre, ct.nom as cite_nom FROM paiements p JOIN reservations r ON p.reservation_id = r.id JOIN chambres c ON r.chambre_id = c.id JOIN cites ct ON c.cite_id = ct.id WHERE r.utilisateur_id = $user_id ORDER BY p.date_paiement DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-credit-card-2-front"></i> Paiement par Carte de Crédit</h2>
    
    <!-- Étapes -->
    <div class="d-flex justify-content-center mb-4">
        <?php for($i=1; $i<=4; $i++): ?>
            <div class="text-center mx-3">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center <?php echo $etape >= $i ? 'bg-success text-white' : 'bg-light text-muted'; ?>" style="width:40px;height:40px;font-weight:bold;"><?php echo $etape > $i ? '✓' : $i; ?></div>
                <div class="small mt-1"><?php echo ['','Réservation','Montant','Carte','Confirmation'][$i]; ?></div>
            </div>
        <?php endfor; ?>
    </div>
    
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <!-- ÉTAPE 1 -->
    <?php if ($etape == 1): ?>
        <?php if (empty($reservations_a_payer)): ?>
            <div class="alert alert-info">Aucune réservation en attente de paiement. <a href="../chambres/" class="btn btn-success btn-sm ms-2">Réserver</a></div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="initier">
                <div class="card mb-3">
                    <div class="card-header bg-success text-white"><strong>Choisissez une réservation</strong></div>
                    <div class="card-body">
                        <?php foreach ($reservations_a_payer as $r): ?>
                            <label class="d-block border rounded p-3 mb-2" style="cursor:pointer;">
                                <input type="radio" name="reservation_id" value="<?php echo $r['id']; ?>" required>
                                <strong>#<?php echo $r['id']; ?></strong> - Chambre <?php echo htmlspecialchars($r['numero_chambre']); ?> (<?php echo htmlspecialchars($r['cite_nom']); ?>) 
                                | Total: <?php echo formatFCFA($r['montant_total']); ?> 
                                | <span class="text-danger"><strong>Reste: <?php echo formatFCFA($r['reste']); ?></strong></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100">Continuer <i class="bi bi-arrow-right"></i></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ÉTAPE 2 -->
    <?php if ($etape == 2 && $details): ?>
        <div class="card">
            <div class="card-header bg-success text-white"><strong>Saisissez le montant</strong></div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Réservation #<?php echo $details['id']; ?></strong><br>
                    Chambre <?php echo htmlspecialchars($details['numero_chambre']); ?> (<?php echo htmlspecialchars($details['cite_nom']); ?>)<br>
                    Total: <?php echo formatFCFA($details['montant_total']); ?><br>
                    <span class="text-danger"><strong>Reste à payer: <?php echo formatFCFA($reste); ?></strong></span>
                </div>
                <p><i class="bi bi-credit-card-2-front"></i> <strong>Méthode : Carte de Crédit</strong></p>
                
                <?php if ($reste <= 0): ?>
                    <div class="alert alert-warning">Cette réservation est déjà payée.</div>
                    <a href="paiements.php" class="btn btn-primary">Retour</a>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="montant">
                        <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Montant à payer (FCFA)</label>
                            <input type="number" name="montant" class="form-control form-control-lg" value="<?php echo $reste; ?>" min="1" max="<?php echo $reste; ?>" required>
                            <small class="text-muted">Minimum: 1 FCFA | Maximum: <?php echo formatFCFA($reste); ?></small>
                        </div>
                        <button type="submit" class="btn btn-success">Continuer <i class="bi bi-arrow-right"></i></button>
                        <a href="paiements.php" class="btn btn-secondary">Retour</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ÉTAPE 3 : Carte de Crédit -->
    <?php if ($etape == 3 && $details): ?>
        <div class="card">
            <div class="card-header bg-success text-white"><strong>Informations de votre Carte de Crédit</strong></div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Montant à payer:</strong> <?php echo formatFCFA($montant_param); ?><br>
                    <strong>Méthode:</strong> <i class="bi bi-credit-card-2-front"></i> Carte de Crédit
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="payer">
                    <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                    <input type="hidden" name="montant" value="<?php echo $montant_param; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Titulaire de la carte</label><input type="text" class="form-control" placeholder="Nom et prénom"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Numéro de carte</label><input type="text" class="form-control" placeholder="1234 5678 9012 3456"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Date d'expiration</label><input type="text" class="form-control" placeholder="MM/AA"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Code de sécurité (CVV)</label><input type="text" class="form-control" placeholder="123"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Référence (optionnel)</label><input type="text" name="reference" class="form-control" placeholder="Auto-généré si vide"></div>
                    </div>
                    
                    <div class="alert alert-warning small"><i class="bi bi-shield-lock"></i> Paiement sécurisé. Vos données sont cryptées.</div>
                    
                    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-lock-fill"></i> Payer par Carte de Crédit</button>
                    <a href="paiements.php?etape=2&reservation_id=<?php echo $reservation_id; ?>" class="btn btn-secondary">Retour</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- ÉTAPE 4 -->
    <?php if ($etape == 4): ?>
        <div class="text-center py-5">
            <div style="font-size: 80px;" class="text-success mb-3">✓</div>
            <h2>Paiement par Carte de Crédit Réussi !</h2>
            <p class="lead">Votre paiement de <strong><?php echo formatFCFA(floatval($_GET['montant'] ?? 0)); ?></strong> a été enregistré.</p>
            <p class="text-muted">Transaction #<?php echo $_GET['paiement_id'] ?? ''; ?></p>
            <hr>
            <a href="mes-reservations.php" class="btn btn-success btn-lg"><i class="bi bi-calendar-check"></i> Mes réservations</a>
            <a href="paiements.php" class="btn btn-outline-primary btn-lg"><i class="bi bi-credit-card"></i> Mes paiements</a>
            <a href="../index.php" class="btn btn-outline-secondary btn-lg"><i class="bi bi-house"></i> Accueil</a>
        </div>
    <?php endif; ?>

    <!-- Historique -->
    <?php if ($etape == 1 && !empty($historique)): ?>
        <h5 class="mt-5"><i class="bi bi-clock-history"></i> Historique des paiements</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr><th>ID</th><th>Réservation</th><th>Chambre</th><th>Montant</th><th>Méthode</th><th>Date</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $p): ?>
                    <tr>
                        <td><strong>#<?php echo $p['id']; ?></strong></td>
                        <td>#<?php echo $p['reservation_id']; ?></td>
                        <td><?php echo htmlspecialchars($p['numero_chambre']); ?><br><small class="text-muted"><?php echo htmlspecialchars($p['cite_nom']); ?></small></td>
                        <td><strong class="text-success"><?php echo formatFCFA($p['montant']); ?></strong></td>
                        <td><i class="bi bi-credit-card-2-front"></i> <?php echo $p['methode_paiement'] == 'carte_credit' ? 'Carte de Crédit' : $p['methode_paiement']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($p['date_paiement'])); ?></td>
                        <td><span class="badge bg-success">Payé</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>