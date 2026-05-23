<?php
require_once '../includes/functions.php';

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$chambre_id = $_GET['id'];
$chambre = getChambreById($chambre_id);

if (!$chambre) {
    $_SESSION['error'] = "Chambre non trouvée";
    redirect('index.php');
}

// Récupérer les évaluations
$evaluations = getEvaluationsChambre($chambre_id);

// Vérifier si l'utilisateur peut évaluer
$peut_evaluer = false;
if (isLoggedIn() && !isAdmin()) {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations 
                         WHERE utilisateur_id = ? AND chambre_id = ? AND statut = 'terminee'");
    $stmt->execute([$_SESSION['user_id'], $chambre_id]);
    $peut_evaluer = $stmt->fetchColumn() > 0;
    
    // Vérifier si a déjà évalué
    $stmt = $db->prepare("SELECT note, commentaire FROM evaluations 
                         WHERE utilisateur_id = ? AND chambre_id = ?");
    $stmt->execute([$_SESSION['user_id'], $chambre_id]);
    $evaluation_existante = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Traitement de l'évaluation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'evaluer') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Veuillez vous connecter pour évaluer";
        redirect('../auth/login.php');
    }
    
    $note = $_POST['note'];
    $commentaire = trim($_POST['commentaire']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($evaluation_existante) {
        $stmt = $db->prepare("UPDATE evaluations SET note = ?, commentaire = ? 
                             WHERE utilisateur_id = ? AND chambre_id = ?");
        $stmt->execute([$note, $commentaire, $_SESSION['user_id'], $chambre_id]);
        $success = "Votre évaluation a été mise à jour";
    } else {
        $stmt = $db->prepare("INSERT INTO evaluations (utilisateur_id, chambre_id, note, commentaire) 
                             VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $chambre_id, $note, $commentaire]);
        $success = "Merci pour votre évaluation !";
    }
    
    // Rafraîchir les évaluations
    $evaluations = getEvaluationsChambre($chambre_id);
    $chambre = getChambreById($chambre_id);
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Accueil</a></li>
            <li class="breadcrumb-item"><a href="index.php">Chambres</a></li>
            <li class="breadcrumb-item active">Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-8">
            <!-- Images et détails -->
            <div class="card mb-4">
                <?php if ($chambre['image']): ?>
                    <img src="../<?php echo $chambre['image']; ?>" class="card-img-top" 
                         alt="Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?>"
                         style="height: 400px; object-fit: cover;">
                <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                         style="height: 400px;">
                        <i class="bi bi-door-open display-1 text-muted"></i>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="mb-1">Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?></h2>
                            <h5 class="text-muted">
                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($chambre['cite_nom']); ?>
                            </h5>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $chambre['disponible'] ? 'success' : 'danger'; ?> fs-6">
                                <?php echo $chambre['disponible'] ? 'Disponible' : 'Occupée'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p>
                                <i class="bi bi-geo-alt"></i> 
                                <?php echo htmlspecialchars($chambre['adresse']); ?><br>
                                <span class="ms-4"><?php echo htmlspecialchars($chambre['code_postal']); ?> <?php echo htmlspecialchars($chambre['ville']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <i class="bi bi-tag"></i> Type: <strong><?php echo ucfirst($chambre['type_chambre']); ?></strong><br>
                                <i class="bi bi-people"></i> Capacité: <strong><?php echo $chambre['capacite']; ?> personne(s)</strong>
                            </p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h4 class="text-primary mb-3">
                        <?php echo number_format($chambre['prix_mensuel'], 2); ?> FCFA <small class="text-muted fs-6">/ mois</small>
                    </h4>
                    
                    <?php if ($chambre['equipements']): ?>
                        <h5>Équipements</h5>
                        <div class="row mb-3">
                            <?php foreach (explode(',', $chambre['equipements']) as $equipement): ?>
                                <div class="col-md-6">
                                    <i class="bi bi-check-circle-fill text-success"></i> 
                                    <?php echo htmlspecialchars(trim($equipement)); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($chambre['description']): ?>
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($chambre['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($chambre['cite_description']): ?>
                        <h5>À propos de la cité</h5>
                        <p><?php echo nl2br(htmlspecialchars($chambre['cite_description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($chambre['disponible']): ?>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <?php if (isLoggedIn() && !isAdmin()): ?>
                                <a href="reserver.php?id=<?php echo $chambre['id']; ?>" class="btn btn-success btn-lg">
                                    <i class="bi bi-calendar-plus"></i> Réserver cette chambre
                                </a>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="../auth/login.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Connectez-vous pour réserver
                                </a>
                            <?php elseif (isAdmin()): ?>
                                <a href="../admin/gestion-chambres.php" class="btn btn-secondary">
                                    <i class="bi bi-gear"></i> Gérer les chambres
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> Cette chambre n'est pas disponible actuellement.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Résumé -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Résumé</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td><i class="bi bi-star-fill text-warning"></i> Note moyenne</td>
                            <td class="text-end">
                                <?php if ($chambre['note_moyenne']): ?>
                                    <strong><?php echo round($chambre['note_moyenne'], 1); ?>/5</strong>
                                    <small class="text-muted">(<?php echo $chambre['nombre_evaluations']; ?> avis)</small>
                                <?php else: ?>
                                    <span class="text-muted">Aucun avis</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-cash"></i> Prix mensuel</td>
                            <td class="text-end"><strong><?php echo number_format($chambre['prix_mensuel'], 2); ?> FCFA</strong></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-calendar"></i> Disponibilité</td>
                            <td class="text-end">
                                <span class="badge bg-<?php echo $chambre['disponible'] ? 'success' : 'danger'; ?>">
                                    <?php echo $chambre['disponible'] ? 'Disponible' : 'Occupée'; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Évaluations -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="bi bi-star"></i> Évaluations
                        <span class="badge bg-light text-dark"><?php echo count($evaluations); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($peut_evaluer): ?>
                        <div class="mb-3">
                            <button class="btn btn-outline-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#evaluationModal">
                                <i class="bi bi-pencil"></i> 
                                <?php echo $evaluation_existante ? 'Modifier mon évaluation' : 'Donner mon avis'; ?>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($evaluations)): ?>
                        <p class="text-muted text-center">Aucune évaluation pour le moment.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($evaluations, 0, 5) as $eval): ?>
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($eval['prenom']); ?></strong>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?>
                                    </small>
                                </div>
                                <div class="mb-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $eval['note'] ? '-fill' : ''; ?> text-warning small"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($eval['commentaire'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($evaluations) > 5): ?>
                            <button class="btn btn-link btn-sm w-100" data-bs-toggle="modal" data-bs-target="#allEvaluationsModal">
                                Voir tous les avis (<?php echo count($evaluations); ?>)
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Évaluation -->
<?php if ($peut_evaluer): ?>
<div class="modal fade" id="evaluationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="evaluer">
                <div class="modal-header">
                    <h5 class="modal-title">Donner mon avis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <label class="form-label d-block mb-2">Votre note</label>
                        <div class="rating-stars">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" class="btn-check" name="note" id="note<?php echo $i; ?>" 
                                       value="<?php echo $i; ?>" autocomplete="off"
                                       <?php echo ($evaluation_existante && $evaluation_existante['note'] == $i) ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-warning" for="note<?php echo $i; ?>">
                                    <?php echo $i; ?> <i class="bi bi-star-fill"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Votre commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="4" 
                                  placeholder="Partagez votre expérience..."><?php echo $evaluation_existante ? htmlspecialchars($evaluation_existante['commentaire']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Publier</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Tous les avis -->
<div class="modal fade" id="allEvaluationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tous les avis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <?php foreach ($evaluations as $eval): ?>
                    <div class="border-bottom mb-3 pb-3">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo htmlspecialchars($eval['prenom'] . ' ' . substr($eval['nom'], 0, 1) . '.'); ?></strong>
                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?></small>
                        </div>
                        <div class="mb-1">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?php echo $i <= $eval['note'] ? '-fill' : ''; ?> text-warning"></i>
                            <?php endfor; ?>
                            <span class="ms-2"><?php echo $eval['note']; ?>/5</span>
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($eval['commentaire'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>