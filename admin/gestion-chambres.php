<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'modifier':
                $stmt = $db->prepare("SELECT image FROM chambres WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $old_image = $stmt->fetchColumn();
                
                $image_path = $old_image;
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $filename = $_FILES['image']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                        $upload_dir = '../assets/images/chambres/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        $new_filename = 'chambre_' . uniqid() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            if ($old_image && file_exists('../' . $old_image)) unlink('../' . $old_image);
                            $image_path = 'assets/images/chambres/' . $new_filename;
                        }
                    }
                }
                
                if (isset($_POST['supprimer_image'])) {
                    if ($image_path && file_exists('../' . $image_path)) unlink('../' . $image_path);
                    $image_path = null;
                }
                
                $stmt = $db->prepare("UPDATE chambres SET cite_id = ?, numero_chambre = ?, type_chambre = ?, 
                                     prix_mensuel = ?, capacite = ?, equipements = ?, description = ?, 
                                     disponible = ?, image = ? WHERE id = ?");
                $stmt->execute([$_POST['cite_id'], $_POST['numero_chambre'], $_POST['type_chambre'], 
                               $_POST['prix_mensuel'], $_POST['capacite'], $_POST['equipements'], 
                               $_POST['description'], isset($_POST['disponible']) ? 1 : 0, $image_path, $_POST['id']]);
                $success = "Chambre modifiée avec succès";
                break;
                
            case 'supprimer':
                $stmt = $db->prepare("SELECT image FROM chambres WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $image = $stmt->fetchColumn();
                if ($image && file_exists('../' . $image)) unlink('../' . $image);
                
                $stmt = $db->prepare("DELETE FROM chambres WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Chambre supprimée avec succès";
                break;
        }
    }
}

// Récupération des chambres
$stmt = $db->query("SELECT c.*, ct.nom as cite_nom, ct.ville,
                    (SELECT COUNT(*) FROM reservations WHERE chambre_id = c.id 
                     AND statut IN ('confirmee', 'en_attente') AND date_fin >= CURDATE()) as reservations_actives
                   FROM chambres c JOIN cites ct ON c.cite_id = ct.id ORDER BY ct.nom, c.numero_chambre");
$chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as disponibles FROM chambres");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-door-open-fill text-primary"></i> Gestion des Chambres</h2>
                </div>
                <!-- Rediriger vers la page d'ajout dédiée -->
                <a href="ajouter-chambre.php" class="btn btn-success btn-lg">
                    <i class="bi bi-plus-circle-fill"></i> Ajouter une Chambre
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col">
            <div class="stat-card bg-primary text-white">
                <div class="stat-icon"><i class="bi bi-door-open"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total chambres</span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="stat-card bg-success text-white">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['disponibles']; ?></span>
                    <span class="stat-label">Disponibles</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>ID</th>
                            <th>N° Chambre</th>
                            <th>Type</th>
                            <th>Cité</th>
                            <th>Prix/Mois</th>
                            <th>Statut</th>
                            <th>Réservations</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chambres as $chambre): ?>
                        <tr>
                            <td>
                                <?php if (!empty($chambre['image'])): ?>
                                    <img src="../<?php echo $chambre['image']; ?>" class="rounded" style="width:60px;height:45px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:60px;height:45px;">
                                        <i class="bi bi-door-open text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong>#<?php echo $chambre['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($chambre['numero_chambre']); ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst($chambre['type_chambre']); ?></span></td>
                            <td><?php echo htmlspecialchars($chambre['cite_nom']); ?></td>
                            <td><strong><?php echo formatFCFA($chambre['prix_mensuel']); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $chambre['disponible'] ? 'success' : 'danger'; ?>">
                                    <?php echo $chambre['disponible'] ? 'Disponible' : 'Indisponible'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($chambre['reservations_actives'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><?php echo $chambre['reservations_actives']; ?> active(s)</span>
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-warning me-1" 
                                        onclick="modifierChambre(<?php echo htmlspecialchars(json_encode($chambre)); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#modifierChambreModal" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="supprimerChambre(<?php echo $chambre['id']; ?>, '<?php echo htmlspecialchars($chambre['numero_chambre']); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#supprimerChambreModal" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($chambres)): ?>
                            <tr><td colspan="9" class="text-center py-5">Aucune chambre</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal fade" id="modifierChambreModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header bg-warning"><h5>Modifier la Chambre</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Cité *</label>
                            <select class="form-select" name="cite_id" id="edit_cite_id" required>
                                <?php 
                                $cites_list = $db->query("SELECT * FROM cites ORDER BY nom")->fetchAll();
                                foreach ($cites_list as $cite): ?>
                                    <option value="<?php echo $cite['id']; ?>"><?php echo htmlspecialchars($cite['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label>N° Chambre *</label><input type="text" class="form-control" name="numero_chambre" id="edit_numero_chambre" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Type *</label>
                            <select class="form-select" name="type_chambre" id="edit_type_chambre" required>
                                <option value="simple">Simple</option><option value="double">Double</option><option value="studio">Studio</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3"><label>Prix (FCFA) *</label><input type="number" class="form-control" name="prix_mensuel" id="edit_prix_mensuel" required></div>
                        <div class="col-md-4 mb-3"><label>Capacité *</label><input type="number" class="form-control" name="capacite" id="edit_capacite" min="1" required></div>
                    </div>
                    <div class="mb-3"><label>Équipements</label><input type="text" class="form-control" name="equipements" id="edit_equipements"></div>
                    <div class="mb-3"><label>Description</label><textarea class="form-control" name="description" id="edit_description" rows="2"></textarea></div>
                    <div class="mb-3">
                        <label>Image actuelle</label>
                        <div id="current_image_container" class="mb-2"><p class="text-muted">Aucune image</p></div>
                        <div class="form-check mb-2"><input type="checkbox" name="supprimer_image" id="edit_supprimer_image"><label>Supprimer l'image</label></div>
                    </div>
                    <div class="mb-3"><label>Nouvelle photo</label><input type="file" class="form-control" name="image" accept="image/*"></div>
                    <div class="form-check form-switch"><input type="checkbox" name="disponible" id="edit_disponible"><label><strong>Chambre disponible</strong></label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-warning">Enregistrer</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer -->
<div class="modal fade" id="supprimerChambreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header bg-danger text-white"><h5>Confirmer la suppression</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><p>Supprimer la chambre <strong id="delete_chambre_nom"></strong> ?</p><p class="text-danger">Action irréversible.</p></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-danger">Supprimer</button></div>
            </form>
        </div>
    </div>
</div>

<style>
.stat-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
.stat-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; opacity: 0.8; }
</style>

<script>
function modifierChambre(chambre) {
    document.getElementById('edit_id').value = chambre.id;
    document.getElementById('edit_cite_id').value = chambre.cite_id;
    document.getElementById('edit_numero_chambre').value = chambre.numero_chambre;
    document.getElementById('edit_type_chambre').value = chambre.type_chambre;
    document.getElementById('edit_prix_mensuel').value = chambre.prix_mensuel;
    document.getElementById('edit_capacite').value = chambre.capacite;
    document.getElementById('edit_equipements').value = chambre.equipements || '';
    document.getElementById('edit_description').value = chambre.description || '';
    document.getElementById('edit_disponible').checked = chambre.disponible == 1;
    document.getElementById('edit_supprimer_image').checked = false;
    const container = document.getElementById('current_image_container');
    container.innerHTML = chambre.image ? '<img src="../' + chambre.image + '" class="rounded" style="max-height:150px;">' : '<p class="text-muted">Aucune image</p>';
}
function supprimerChambre(id, numero) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_chambre_nom').textContent = numero;
}
</script>

<?php include '../includes/footer.php'; ?>