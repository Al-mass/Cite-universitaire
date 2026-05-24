<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est admin
if (!isLoggedIn() || !isAdmin()) {
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

if (!function_exists('getNomMethodePaiement')) {
    function getNomMethodePaiement($code) {
        $methodes = [
            'carte_credit' => 'Carte Bancaire',
            'mobile_money' => 'MTN Mobile Money',
            'orange_money' => 'Orange Money',
            'om' => 'Orange Money',
            'virement' => 'Virement Bancaire',
            'especes' => 'Espèces',
            'cheque' => 'Chèque'
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
            'especes' => 'cash',
            'cheque' => 'file-text'
        ];
        return $icones[$code] ?? 'credit-card';
    }
}

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

$success = '';
$error = '';

// ==================== TRAITEMENT DES ACTIONS ====================

// Ajouter un paiement manuel
if (isset($_POST['action']) && $_POST['action'] == 'ajouter_paiement') {
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $montant = floatval($_POST['montant'] ?? 0);
    $methode = $_POST['methode_paiement'];
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $statut = $_POST['statut_paiement'] ?? 'complete';
    $commentaire = trim($_POST['commentaire'] ?? '');
    
    if ($reservation_id > 0 && $montant > 0 && !empty($methode)) {
        try {
            $db->beginTransaction();
            
            // Vérifier le montant maximum autorisé
            $stmt = $db->prepare("SELECT r.montant_total, 
                                  (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
                                  FROM reservations r WHERE r.id = ?");
            $stmt->execute([$reservation_id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($res) {
                $max_autorise = $res['montant_total'] - $res['total_paye'];
                if ($montant > $max_autorise) {
                    throw new Exception("Le montant dépasse le reste à payer (" . formatFCFA($max_autorise) . ")");
                }
            }
            
            if (empty($transaction_id)) {
                $prefix = match($methode) {
                    'orange_money' => 'OM',
                    'carte_credit' => 'CB',
                    'virement' => 'VIR',
                    'especes' => 'CASH',
                    'mobile_money' => 'MM',
                    default => 'PAY'
                };
                $transaction_id = $prefix . '-' . strtoupper(uniqid());
            }
            
            $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$reservation_id, $montant, $methode, $statut, $transaction_id]);
            
            // Mettre à jour le statut de la réservation si paiement complet
            if ($statut == 'complete') {
                $stmt = $db->prepare("SELECT r.montant_total, r.statut,
                                      (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
                                      FROM reservations r WHERE r.id = ?");
                $stmt->execute([$reservation_id]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($res && $res['statut'] == 'en_attente' && $res['total_paye'] >= $res['montant_total']) {
                    $stmt = $db->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = ?");
                    $stmt->execute([$reservation_id]);
                }
            }
            
            // Notifier l'étudiant
            if (function_exists('envoyerNotification')) {
                $stmt = $db->prepare("SELECT utilisateur_id FROM reservations WHERE id = ?");
                $stmt->execute([$reservation_id]);
                $user_id = $stmt->fetchColumn();
                if ($user_id) {
                    $message = "Un paiement de " . formatFCFA($montant) . " a été enregistré pour votre réservation #$reservation_id.";
                    if (!empty($commentaire)) {
                        $message .= " Commentaire : $commentaire";
                    }
                    envoyerNotification($user_id, 'paiement', $message);
                }
            }
            
            $db->commit();
            $success = "Paiement de " . formatFCFA($montant) . " enregistré avec succès";
            
            logAction('ajout_paiement', $admin_id, "Paiement de " . formatFCFA($montant) . " pour réservation #$reservation_id");
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires";
    }
}

// Modifier un paiement
if (isset($_POST['action']) && $_POST['action'] == 'modifier_paiement') {
    $paiement_id = intval($_POST['paiement_id'] ?? 0);
    $montant = floatval($_POST['montant'] ?? 0);
    $methode = $_POST['methode_paiement'] ?? '';
    $statut = $_POST['statut_paiement'] ?? 'complete';
    
    if ($paiement_id > 0 && $montant > 0) {
        $stmt = $db->prepare("UPDATE paiements SET montant = ?, methode_paiement = ?, statut = ? WHERE id = ?");
        $stmt->execute([$montant, $methode, $statut, $paiement_id]);
        $success = "Paiement modifié avec succès";
    }
}

// Supprimer un paiement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $paiement_id = intval($_GET['delete']);
    
    // Récupérer les infos avant suppression
    $stmt = $db->prepare("SELECT p.*, r.id as reservation_id FROM paiements p 
                          JOIN reservations r ON p.reservation_id = r.id WHERE p.id = ?");
    $stmt->execute([$paiement_id]);
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paiement) {
        $stmt = $db->prepare("DELETE FROM paiements WHERE id = ?");
        $stmt->execute([$paiement_id]);
        $success = "Paiement #$paiement_id supprimé";
        logAction('suppression_paiement', $admin_id, "Paiement #$paiement_id de " . formatFCFA($paiement['montant']) . " supprimé");
    }
}

// Rembourser un paiement
if (isset($_POST['action']) && $_POST['action'] == 'rembourser') {
    $paiement_id = intval($_POST['paiement_id'] ?? 0);
    $montant = floatval($_POST['montant_remboursement'] ?? 0);
    $raison = trim($_POST['raison_remboursement'] ?? '');
    
    if ($paiement_id > 0 && $montant > 0) {
        try {
            $db->beginTransaction();
            
            // Récupérer les infos du paiement original
            $stmt = $db->prepare("SELECT * FROM paiements WHERE id = ?");
            $stmt->execute([$paiement_id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($original && $original['montant'] > 0) {
                $max_remboursement = $original['montant'];
                if ($montant > $max_remboursement) {
                    throw new Exception("Le montant du remboursement ne peut pas dépasser le montant original");
                }
                
                // Créer un paiement négatif (remboursement)
                $transaction_id = 'REFUND-' . strtoupper(uniqid());
                $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) 
                                     VALUES (?, ?, 'virement', 'complete', ?)");
                $stmt->execute([$original['reservation_id'], -$montant, $transaction_id]);
                
                // Notifier l'étudiant
                if (function_exists('envoyerNotification')) {
                    $stmt = $db->prepare("SELECT utilisateur_id FROM reservations WHERE id = ?");
                    $stmt->execute([$original['reservation_id']]);
                    $user_id = $stmt->fetchColumn();
                    if ($user_id) {
                        $message = "Un remboursement de " . formatFCFA($montant) . " a été effectué pour votre réservation.";
                        if (!empty($raison)) {
                            $message .= " Raison : $raison";
                        }
                        envoyerNotification($user_id, 'paiement', $message);
                    }
                }
                
                $db->commit();
                $success = "Remboursement de " . formatFCFA($montant) . " effectué avec succès";
                logAction('remboursement', $admin_id, "Remboursement de " . formatFCFA($montant) . " pour paiement #$paiement_id");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// ==================== FILTRES ====================
$statut_filter = $_GET['statut'] ?? '';
$methode_filter = $_GET['methode'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$recherche = $_GET['recherche'] ?? '';
$montant_min = $_GET['montant_min'] ?? '';
$montant_max = $_GET['montant_max'] ?? '';

$where = [];
$params = [];

if (!empty($statut_filter)) {
    $where[] = "p.statut = ?";
    $params[] = $statut_filter;
}

if (!empty($methode_filter)) {
    $where[] = "p.methode_paiement = ?";
    $params[] = $methode_filter;
}

if (!empty($date_debut)) {
    $where[] = "DATE(p.date_paiement) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $where[] = "DATE(p.date_paiement) <= ?";
    $params[] = $date_fin;
}

if (!empty($montant_min) && is_numeric($montant_min)) {
    $where[] = "ABS(p.montant) >= ?";
    $params[] = $montant_min;
}

if (!empty($montant_max) && is_numeric($montant_max)) {
    $where[] = "ABS(p.montant) <= ?";
    $params[] = $montant_max;
}

if (!empty($recherche)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ? OR c.numero_chambre LIKE ?)";
    $search = '%' . $recherche . '%';
    $params = array_merge($params, [$search, $search, $search, $search, $search]);
}

$whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Compter le total
$countQuery = "SELECT COUNT(*) FROM paiements p 
               JOIN reservations r ON p.reservation_id = r.id 
               JOIN utilisateurs u ON r.utilisateur_id = u.id 
               JOIN chambres c ON r.chambre_id = c.id 
               $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Récupérer les paiements
$query = "SELECT p.*, r.id as reservation_id, r.date_debut, r.date_fin, r.montant_total,
          u.nom, u.prenom, u.email,
          c.numero_chambre, c.type_chambre, ct.nom as cite_nom
          FROM paiements p
          JOIN reservations r ON p.reservation_id = r.id
          JOIN utilisateurs u ON r.utilisateur_id = u.id
          JOIN chambres c ON r.chambre_id = c.id
          JOIN cites ct ON c.cite_id = ct.id
          $whereClause
          ORDER BY p.date_paiement DESC
          LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== STATISTIQUES ====================
$stmt = $db->query("SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN statut = 'complete' AND montant > 0 THEN montant ELSE 0 END) as total_encaisse,
    SUM(CASE WHEN statut = 'complete' AND montant < 0 THEN ABS(montant) ELSE 0 END) as total_rembourse,
    SUM(CASE WHEN statut = 'en_attente' THEN montant ELSE 0 END) as total_attente,
    SUM(CASE WHEN statut = 'echoue' THEN montant ELSE 0 END) as total_echoue,
    SUM(CASE WHEN methode_paiement = 'orange_money' THEN 1 ELSE 0 END) as orange_money,
    SUM(CASE WHEN methode_paiement = 'carte_credit' THEN 1 ELSE 0 END) as carte_credit,
    SUM(CASE WHEN methode_paiement = 'virement' THEN 1 ELSE 0 END) as virement,
    SUM(CASE WHEN methode_paiement = 'especes' THEN 1 ELSE 0 END) as especes,
    SUM(CASE WHEN methode_paiement = 'mobile_money' THEN 1 ELSE 0 END) as mobile_money
    FROM paiements");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques par mois
$stmt = $db->query("SELECT 
    DATE_FORMAT(date_paiement, '%Y-%m') as mois,
    SUM(CASE WHEN montant > 0 THEN montant ELSE 0 END) as encaisse,
    SUM(CASE WHEN montant < 0 THEN ABS(montant) ELSE 0 END) as rembourse,
    COUNT(*) as nb_transactions
    FROM paiements 
    WHERE date_paiement >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mois ORDER BY mois DESC");
$stats_mensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les réservations pour le formulaire d'ajout
$stmt = $db->query("SELECT r.id, r.montant_total, u.nom, u.prenom, c.numero_chambre, ct.nom as cite_nom,
                    (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
                    FROM reservations r
                    JOIN utilisateurs u ON r.utilisateur_id = u.id
                    JOIN chambres c ON r.chambre_id = c.id
                    JOIN cites ct ON c.cite_id = ct.id
                    WHERE r.statut IN ('en_attente', 'confirmee')
                    ORDER BY r.date_reservation DESC");
$reservations_actives = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Méthodes de paiement disponibles
$methodes_paiement = [
    'especes' => 'Espèces',
    'orange_money' => 'Orange Money',
    'mobile_money' => 'MTN Mobile Money',
    'carte_credit' => 'Carte Bancaire',
    'virement' => 'Virement Bancaire',
    'cheque' => 'Chèque'
];

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="bi bi-credit-card-fill text-success"></i> 
                        Gestion des Paiements
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active">Paiements</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#ajouterPaiementModal">
                        <i class="bi bi-plus-circle"></i> Nouveau Paiement
                    </button>
                    <button class="btn btn-outline-primary btn-lg ms-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="bi bi-download"></i> Exporter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show modern-alert" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show modern-alert" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques globales -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="bi bi-arrow-left-right"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['total_transactions']; ?></span>
                    <span class="stat-label">Transactions</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card success">
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo formatFCFA($stats['total_encaisse']); ?></span>
                    <span class="stat-label">Encaissé</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo formatFCFA($stats['total_rembourse']); ?></span>
                    <span class="stat-label">Remboursé</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo formatFCFA($stats['total_attente']); ?></span>
                    <span class="stat-label">En attente</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card info">
                <div class="stat-icon"><i class="bi bi-wifi"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['orange_money']; ?></span>
                    <span class="stat-label">Orange Money</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card secondary">
                <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['carte_credit']; ?></span>
                    <span class="stat-label">Carte Bancaire</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-funnel"></i> Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous</option>
                    <option value="complete" <?php echo $statut_filter == 'complete' ? 'selected' : ''; ?>>Complété</option>
                    <option value="en_attente" <?php echo $statut_filter == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="echoue" <?php echo $statut_filter == 'echoue' ? 'selected' : ''; ?>>Échoué</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-credit-card"></i> Méthode</label>
                <select class="form-select" name="methode">
                    <option value="">Toutes</option>
                    <?php foreach ($methodes_paiement as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $methode_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-calendar"></i> Date début</label>
                <input type="date" class="form-control" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-calendar"></i> Date fin</label>
                <input type="date" class="form-control" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-cash"></i> Montant min</label>
                <input type="number" class="form-control" name="montant_min" placeholder="FCFA" value="<?php echo $montant_min; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-cash"></i> Montant max</label>
                <input type="number" class="form-control" name="montant_max" placeholder="FCFA" value="<?php echo $montant_max; ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label"><i class="bi bi-search"></i> Recherche</label>
                <input type="text" class="form-control" name="recherche" 
                       placeholder="Nom, email, transaction, chambre..." 
                       value="<?php echo htmlspecialchars($recherche); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <a href="gestion-paiements.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Graphique mensuel -->
    <?php if (!empty($stats_mensuelles)): ?>
    <div class="chart-card">
        <div class="chart-header">
            <h5><i class="bi bi-bar-chart"></i> Évolution des paiements (12 derniers mois)</h5>
        </div>
        <div class="chart-body">
            <canvas id="paiementsChart" height="80"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Liste des paiements -->
    <div class="table-card">
        <?php if (empty($paiements)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Aucun paiement</h3>
                <p>Aucun paiement ne correspond à vos critères.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Transaction</th>
                            <th>Étudiant</th>
                            <th>Réservation</th>
                            <th>Chambre</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $p): 
                            $isRemboursement = $p['montant'] < 0;
                        ?>
                        <tr class="<?php echo $isRemboursement ? 'refund-row' : ''; ?>">
                            <td><strong>#<?php echo $p['id']; ?></strong></td>
                            <td>
                                <code><?php echo htmlspecialchars($p['transaction_id'] ?: 'N/A'); ?></code>
                            </td>
                            <td>
                                <div class="student-info">
                                    <span class="student-name"><?php echo htmlspecialchars($p['prenom'] . ' ' . $p['nom']); ?></span>
                                    <span class="student-email"><?php echo htmlspecialchars($p['email']); ?></span>
                                </div>
                            </td>
                            <td><strong>#<?php echo $p['reservation_id']; ?></strong></td>
                            <td>
                                <span class="room-number"><?php echo htmlspecialchars($p['numero_chambre']); ?></span>
                                <span class="room-cite"><?php echo htmlspecialchars($p['cite_nom']); ?></span>
                            </td>
                            <td>
                                <span class="amount <?php echo $isRemboursement ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $isRemboursement ? '-' : ''; ?><?php echo formatFCFA(abs($p['montant'])); ?>
                                </span>
                                <?php if ($isRemboursement): ?>
                                    <span class="badge bg-danger ms-1">Remboursement</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="method-badge method-<?php echo $p['methode_paiement']; ?>">
                                    <i class="bi bi-<?php echo getIconeMethodePaiement($p['methode_paiement']); ?>"></i>
                                    <?php echo getNomMethodePaiement($p['methode_paiement']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($p['date_paiement'])); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $p['statut']; ?>">
                                    <?php 
                                    if ($p['statut'] == 'complete') echo '<i class="bi bi-check-circle"></i> Complété';
                                    elseif ($p['statut'] == 'en_attente') echo '<i class="bi bi-hourglass-split"></i> En attente';
                                    elseif ($p['statut'] == 'echoue') echo '<i class="bi bi-x-circle"></i> Échoué';
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="editPaiement(<?php echo htmlspecialchars(json_encode($p)); ?>)"
                                            data-bs-toggle="modal" data-bs-target="#modifierPaiementModal"
                                            data-bs-toggle="tooltip" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($p['statut'] == 'complete' && $p['montant'] > 0): ?>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="rembourserPaiement(<?php echo $p['id']; ?>, <?php echo $p['montant']; ?>)"
                                                data-bs-toggle="modal" data-bs-target="#rembourserModal"
                                                data-bs-toggle="tooltip" title="Rembourser">
                                            <i class="bi bi-arrow-return-left"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Supprimer ce paiement ? Cette action est irréversible.');"
                                       data-bs-toggle="tooltip" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <ul class="modern-pagination">
                        <?php
                        $urlParams = $_GET;
                        unset($urlParams['page']);
                        $baseUrl = 'gestion-paiements.php?' . http_build_query($urlParams);
                        if ($baseUrl != 'gestion-paiements.php?') $baseUrl .= '&';
                        ?>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Paiement -->
<div class="modal fade modern-modal" id="ajouterPaiementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="ajouter_paiement">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nouveau Paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Réservation *</label>
                            <select class="form-select" name="reservation_id" id="modal_reservation" required>
                                <option value="">Sélectionner une réservation</option>
                                <?php foreach ($reservations_actives as $res): 
                                    $reste = $res['montant_total'] - $res['total_paye'];
                                ?>
                                    <option value="<?php echo $res['id']; ?>" 
                                            data-montant-total="<?php echo $res['montant_total']; ?>"
                                            data-total-paye="<?php echo $res['total_paye']; ?>"
                                            data-reste="<?php echo $reste; ?>">
                                        #<?php echo $res['id']; ?> - <?php echo htmlspecialchars($res['prenom'] . ' ' . $res['nom']); ?> - 
                                        Chambre <?php echo htmlspecialchars($res['numero_chambre']); ?> - 
                                        Reste: <?php echo formatFCFA($reste); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Montant (FCFA) *</label>
                            <input type="number" class="form-control" name="montant" id="modal_montant" 
                                   step="500" min="500" required>
                            <small class="text-muted">Reste à payer : <span id="reste_affiche">-</span></small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Méthode de paiement *</label>
                            <select class="form-select" name="methode_paiement" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($methodes_paiement as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut_paiement">
                                <option value="complete">Complété</option>
                                <option value="en_attente">En attente</option>
                                <option value="echoue">Échoué</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Référence de transaction</label>
                        <input type="text" class="form-control" name="transaction_id" 
                               placeholder="Laissez vide pour générer automatiquement">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Commentaire</label>
                        <textarea class="form-control" name="commentaire" rows="2" 
                                  placeholder="Commentaire sur le paiement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer le paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Paiement -->
<div class="modal fade modern-modal" id="modifierPaiementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="modifier_paiement">
                <input type="hidden" name="paiement_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier Paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Montant (FCFA) *</label>
                        <input type="number" class="form-control" name="montant" id="edit_montant" 
                               step="500" min="500" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Méthode de paiement</label>
                        <select class="form-select" name="methode_paiement" id="edit_methode">
                            <?php foreach ($methodes_paiement as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Statut</label>
                        <select class="form-select" name="statut_paiement" id="edit_statut">
                            <option value="complete">Complété</option>
                            <option value="en_attente">En attente</option>
                            <option value="echoue">Échoué</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Remboursement -->
<div class="modal fade modern-modal" id="rembourserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="rembourser">
                <input type="hidden" name="paiement_id" id="remboursement_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-arrow-return-left"></i> Remboursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Montant à rembourser (FCFA)</label>
                        <input type="number" class="form-control" name="montant_remboursement" 
                               id="remboursement_montant" step="500" min="500" required>
                        <small class="text-muted">Maximum : <span id="remboursement_max">-</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Raison du remboursement</label>
                        <textarea class="form-control" name="raison_remboursement" rows="2" 
                                  placeholder="Raison du remboursement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Effectuer le remboursement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Export -->
<div class="modal fade modern-modal" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download"></i> Exporter les paiements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="export-paiements.php?format=csv<?php echo !empty($whereClause) ? '&' . http_build_query($_GET) : ''; ?>" 
                       class="list-group-item list-group-item-action">
                        <i class="bi bi-file-spreadsheet text-success"></i> Exporter en CSV
                    </a>
                    <a href="export-paiements.php?format=pdf<?php echo !empty($whereClause) ? '&' . http_build_query($_GET) : ''; ?>" 
                       class="list-group-item list-group-item-action">
                        <i class="bi bi-file-pdf text-danger"></i> Exporter en PDF
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="window.print()">
                        <i class="bi bi-printer text-primary"></i> Imprimer
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
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
.stat-card.primary .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-card.success .stat-icon { background: linear-gradient(135deg, #48bb78, #38a169); }
.stat-card.danger .stat-icon { background: linear-gradient(135deg, #f56565, #e53e3e); }
.stat-card.warning .stat-icon { background: linear-gradient(135deg, #ed8936, #dd6b20); }
.stat-card.info .stat-icon { background: linear-gradient(135deg, #4299e1, #3182ce); }
.stat-card.secondary .stat-icon { background: linear-gradient(135deg, #718096, #4a5568); }
.stat-value {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: #2d3748;
}
.stat-label {
    font-size: 13px;
    color: #718096;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

/* Chart Card */
.chart-card {
    background: white;
    border-radius: 20px;
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

/* Table Card */
.table-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

/* Modern Table */
.modern-table {
    margin-bottom: 0;
}
.modern-table thead th {
    background: #f8fafc;
    font-weight: 600;
    color: #4a5568;
    border-bottom: 1px solid #e2e8f0;
    padding: 15px;
    white-space: nowrap;
}
.modern-table tbody td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}
.modern-table tbody tr:hover {
    background: #f7fafc;
}
.modern-table tbody tr.refund-row {
    background: #fef5f5;
}

/* Student Info */
.student-info {
    display: flex;
    flex-direction: column;
}
.student-name {
    font-weight: 600;
    color: #2d3748;
}
.student-email {
    font-size: 12px;
    color: #718096;
}

/* Room Info */
.room-number {
    font-weight: 600;
    color: #2d3748;
}
.room-cite {
    display: block;
    font-size: 12px;
    color: #718096;
}

/* Amount */
.amount {
    font-weight: 700;
    font-size: 16px;
}

/* Method Badge */
.method-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    background: #f0f9ff;
    color: #0369a1;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}
.status-complete { background: #c6f6d5; color: #22543d; }
.status-en_attente { background: #fefcbf; color: #744210; }
.status-echoue { background: #fed7d7; color: #9b2c2c; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}
.modern-pagination {
    display: flex;
    list-style: none;
    padding: 0;
    gap: 8px;
}
.modern-pagination .page-item a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #f7fafc;
    color: #4a5568;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}
.modern-pagination .page-item.active a {
    background: #007A5E;
    color: white;
}
.modern-pagination .page-item a:hover {
    background: #007A5E;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state i {
    font-size: 60px;
    color: #cbd5e0;
    margin-bottom: 20px;
}
.empty-state h3 {
    color: #4a5568;
    margin-bottom: 10px;
}
.empty-state p {
    color: #a0aec0;
}

/* Modal */
.modern-modal .modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
}
.modern-modal .modal-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 25px;
}
.modern-modal .modal-body {
    padding: 25px;
}
.modern-modal .modal-footer {
    border-top: 1px solid #e2e8f0;
    padding: 20px 25px;
}

/* Responsive */
@media (max-width: 992px) {
    .stat-card {
        margin-bottom: 15px;
    }
}
@media (max-width: 768px) {
    .filter-card .row > div {
        margin-bottom: 10px;
    }
    .action-buttons {
        flex-wrap: wrap;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('paiementsChart')?.getContext('2d');
    if (ctx) {
        const data = <?php echo json_encode(array_reverse($stats_mensuelles)); ?>;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => {
                    const [year, month] = d.mois.split('-');
                    const mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                    return mois[parseInt(month) - 1] + ' ' + year.slice(2);
                }),
                datasets: [{
                    label: 'Encaissé (FCFA)',
                    data: data.map(d => d.encaisse),
                    backgroundColor: '#48bb78',
                    borderRadius: 8
                }, {
                    label: 'Remboursé (FCFA)',
                    data: data.map(d => d.rembourse),
                    backgroundColor: '#f56565',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', {notation: 'compact'}).format(value) + ' FCFA';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Tooltips
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(el) { return new bootstrap.Tooltip(el); });
});

// Modal réservation - mise à jour montant
document.getElementById('modal_reservation')?.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const reste = option.dataset.reste;
    const inputMontant = document.getElementById('modal_montant');
    inputMontant.value = reste;
    inputMontant.max = reste;
    document.getElementById('reste_affiche').textContent = new Intl.NumberFormat('fr-FR').format(reste) + ' FCFA';
});

// Edit paiement
function editPaiement(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_montant').value = Math.abs(p.montant);
    document.getElementById('edit_methode').value = p.methode_paiement;
    document.getElementById('edit_statut').value = p.statut;
}

// Remboursement
function rembourserPaiement(id, montant) {
    document.getElementById('remboursement_id').value = id;
    document.getElementById('remboursement_montant').value = montant;
    document.getElementById('remboursement_montant').max = montant;
    document.getElementById('remboursement_max').textContent = new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
}
</script>

<?php include '../includes/footer.php'; ?>