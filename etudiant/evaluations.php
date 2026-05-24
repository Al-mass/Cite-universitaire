<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Traitement de l'évaluation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chambre_id = $_POST['chambre_id'];
    $note = $_POST['note'];
    $commentaire = $_POST['commentaire'];
    
    // Vérifier si l'étudiant a déjà évalué cette chambre
    $stmt = $db->prepare("SELECT id FROM evaluations WHERE utilisateur_id = ? AND chambre_id = ?");
    $stmt->execute([$user_id, $chambre_id]);
    
    if ($stmt->rowCount() > 0) {
        // Mise à jour
        $stmt = $db->prepare("UPDATE evaluations SET note = ?, commentaire = ? WHERE utilisateur_id = ? AND chambre_id = ?");
        $stmt->execute([$note, $commentaire, $user_id, $chambre_id]);
        $success = "Votre évaluation a été mise à jour";
    } else {
        // Nouvelle évaluation
        $stmt = $db->prepare("INSERT INTO evaluations (utilisateur_id, chambre_id, note, commentaire) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $chambre_id, $note, $commentaire]);
        $success = "Merci pour votre évaluation !";
    }
}

// Récupérer les chambres que l'étudiant peut évaluer (réservations terminées)
$stmt = $db->prepare("SELECT DISTINCT r.chambre_id, c.numero_chambre, c.type_chambre, ct.nom as cite_nom,
                      (SELECT note FROM evaluations WHERE utilisateur_id = ? AND chambre_id = r.chambre_id) as ma_note,
                      (SELECT commentaire FROM evaluations WHERE utilisateur_id = ? AND chambre_id = r.chambre_id) as mon_commentaire
                      FROM reservations r
                      JOIN chambres c ON r.chambre_id = c.id
                      JOIN cites ct ON c.cite_id = ct.id
                      WHERE r.utilisateur_id = ? AND r.statut = 'terminee'
                      ORDER BY r.date_fin DESC");
$stmt->execute([$user_id, $user_id, $user_id]);
$chambres_a_evaluer = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les évaluations de l'étudiant
$stmt = $db->prepare("SELECT e.*, c.numero_chambre, c.type_chambre, ct.nom as cite_nom
                      FROM evaluations e
                      JOIN chambres c ON e.chambre_id = c.id
                      JOIN cites ct ON c.cite_id = ct.id
                      WHERE e.utilisateur_id = ?
                      ORDER BY e.date_evaluation DESC");
$stmt->execute([$user_id]);
$mes_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">
                <i class="bi bi-star"></i> Mes Évaluations
            </h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chambres à évaluer -->
    <?php if (!empty($chambres_a_evaluer)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-pencil-square"></i> Chambres à évaluer
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($chambres_a_evaluer as $chambre): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?> - 
                                        <?php echo htmlspecialchars($chambre['cite_nom']); ?>
                                    </h6>
                                    <p class="text-muted">Type : <?php echo ucfirst($chambre['type_chambre']); ?></p>
                                    
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="chambre_id" value="<?php echo $chambre['chambre_id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Votre note</label>
                                            <div class="rating-stars mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="note" 
                                                               value="<?php echo $i; ?>" id="note_<?php echo $chambre['chambre_id']; ?>_<?php echo $i; ?>"
                                                               <?php echo (isset($chambre['ma_note']) && $chambre['ma_note'] == $i) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="note_<?php echo $chambre['chambre_id']; ?>_<?php echo $i; ?>">
                                                            <?php echo $i; ?> <i class="bi bi-star-fill text-warning"></i>
                                                        </label>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="commentaire_<?php echo $chambre['chambre_id']; ?>" class="form-label">
                                                Votre commentaire
                                            </label>
                                            <textarea class="form-control" id="commentaire_<?php echo $chambre['chambre_id']; ?>" 
                                                      name="commentaire" rows="3"><?php echo isset($chambre['mon_commentaire']) ? htmlspecialchars($chambre['mon_commentaire']) : ''; ?></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send"></i> 
                                            <?php echo isset($chambre['ma_note']) ? 'Mettre à jour' : 'Envoyer'; ?> l'évaluation
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Mes évaluations précédentes -->
    <?php if (!empty($mes_evaluations)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Mes évaluations précédentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($mes_evaluations as $eval): ?>
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6>Chambre <?php echo htmlspecialchars($eval['numero_chambre']); ?> - 
                                            <?php echo htmlspecialchars($eval['cite_nom']); ?>
                                        </h6>
                                        <div class="mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $eval['note'] ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2">(<?php echo $eval['note']; ?>/5)</span>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($eval['commentaire'])); ?></p>
                                        <small class="text-muted">
                                            Évalué le <?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($chambres_a_evaluer) && empty($mes_evaluations)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Vous n'avez pas encore d'évaluations.
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>