<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('gestion-chambres.php');
}

$chambre_id = $_GET['id'];

// Récupérer les informations de la chambre
$stmt = $db->prepare("SELECT c.*, ct.nom as cite_nom FROM chambres c 
                      JOIN cites ct ON c.cite_id = ct.id 
                      WHERE c.id = ?");
$stmt->execute([$chambre_id]);
$chambre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chambre) {
    $_SESSION['error'] = "Chambre non trouvée";
    redirect('gestion-chambres.php');
}

// Vérifier les réservations liées
$stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE chambre_id = ?");
$stmt->execute([$chambre_id]);
$nb_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$error = '';

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmer'])) {
    try {
        $db->beginTransaction();
        
        // Supprimer l'image si elle existe
        if ($chambre['image'] && file_exists('../' . $chambre['image'])) {
            unlink('../' . $chambre['image']);
        }
        
        // Supprimer les réservations liées (cascade)
        // Supprimer les évaluations liées (cascade)
        
        // Supprimer la chambre
        $stmt = $db->prepare("DELETE FROM chambres WHERE id = ?");
        $stmt->execute([$chambre_id]);
        
        $db->commit();
        
        logAction('suppression_chambre', $_SESSION['user_id'], "Chambre supprimée: {$chambre['numero_chambre']}");
        
        $_SESSION['success'] = "Chambre supprimée avec succès";
        redirect('gestion-chambres.php');
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur lors de la suppression : " . $e->getMessage();
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
                        <i class="bi bi-exclamation-triangle"></i> Confirmation de suppression
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-circle"></i> Attention !</h5>
                        <p>Vous êtes sur le point de supprimer définitivement cette chambre.</p>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Détails de la chambre</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="150">ID :</th>
                                    <td>#<?php echo $chambre['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Numéro :</th>
                                    <td><?php echo htmlspecialchars($chambre['numero_chambre']); ?></td>
                                </tr>
                                <tr>
                                    <th>Type :</th>
                                    <td><?php echo ucfirst($chambre['type_chambre']); ?></td>
                                </tr>
                                <tr>
                                    <th>Cité :</th>
                                    <td><?php echo htmlspecialchars($chambre['cite_nom']); ?></td>
                                </tr>
                                <tr>
                                    <th>Prix mensuel :</th>
                                    <td><?php echo number_format($chambre['prix_mensuel'], 2); ?> €</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($nb_reservations > 0): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-calendar-x"></i> 
                            <strong>Attention :</strong> Cette chambre a <strong><?php echo $nb_reservations; ?> réservation(s)</strong> associée(s). 
                            La suppression de la chambre entraînera également la suppression de toutes les réservations liées !
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Cette action est <strong>irréversible</strong>. Une fois supprimée, la chambre ne pourra pas être récupérée.
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="confirmation_check" required>
                                <label class="form-check-label" for="confirmation_check">
                                    Je comprends que cette action est définitive et je confirme la suppression
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="gestion-chambres.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" name="confirmer" class="btn btn-danger" id="btn_supprimer" disabled>
                                <i class="bi bi-trash"></i> Supprimer définitivement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('confirmation_check').addEventListener('change', function() {
    document.getElementById('btn_supprimer').disabled = !this.checked;
});
</script>

<?php include '../includes/footer.php'; ?>