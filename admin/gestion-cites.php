<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $nom = trim($_POST['nom']);
                $adresse = trim($_POST['adresse']);
                $ville = trim($_POST['ville']);
                $code_postal = trim($_POST['code_postal']);
                $description = trim($_POST['description']);
                
                // Validation
                $errors = [];
                if (empty($nom)) $errors[] = "Le nom de la cité est requis";
                if (empty($adresse)) $errors[] = "L'adresse est requise";
                
                if (empty($errors)) {
                    // Gestion de l'image
                    $image_path = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                        $upload_result = uploadImageCite($_FILES['image']);
                        if ($upload_result['success']) {
                            $image_path = $upload_result['path'];
                        }
                    }
                    
                    $stmt = $db->prepare("INSERT INTO cites (nom, adresse, ville, code_postal, description, image) 
                                         VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$nom, $adresse, $ville, $code_postal, $description, $image_path])) {
                        $success = "Cité universitaire ajoutée avec succès";
                        logAction('ajout_cite', $_SESSION['user_id'], "Cité ajoutée: $nom");
                    } else {
                        $error = "Erreur lors de l'ajout de la cité";
                    }
                } else {
                    $error = implode('<br>', $errors);
                }
                break;
                
            case 'modifier':
                $id = $_POST['id'];
                $nom = trim($_POST['nom']);
                $adresse = trim($_POST['adresse']);
                $code_postal = trim($_POST['code_postal']);
                $description = trim($_POST['description']);
                
                // Récupérer l'ancienne image
                $stmt = $db->prepare("SELECT image FROM cites WHERE id = ?");
                $stmt->execute([$id]);
                $old_image = $stmt->fetchColumn();
                
                $image_path = $old_image;
                
                // Gestion de la nouvelle image
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $upload_result = uploadImageCite($_FILES['image']);
                    if ($upload_result['success']) {
                        // Supprimer l'ancienne image
                        if ($old_image && file_exists('../' . $old_image)) {
                            unlink('../' . $old_image);
                        }
                        $image_path = $upload_result['path'];
                    }
                }
                
                // Supprimer l'image si demandé
                if (isset($_POST['supprimer_image']) && $image_path) {
                    if (file_exists('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }
                    $image_path = null;
                }
                
                $stmt = $db->prepare("UPDATE cites SET nom = ?, adresse = ?, 
                                     code_postal = ?, description = ?, image = ? WHERE id = ?");
                if ($stmt->execute([$nom, $adresse, $code_postal, $description, $image_path, $id])) {
                    $success = "Cité universitaire modifiée avec succès";
                    logAction('modification_cite', $_SESSION['user_id'], "Cité modifiée: $nom");
                } else {
                    $error = "Erreur lors de la modification";
                }
                break;
                
            case 'supprimer':
                $id = $_POST['id'];
                
                // Vérifier s'il y a des chambres liées
                $stmt = $db->prepare("SELECT COUNT(*) FROM chambres WHERE cite_id = ?");
                $stmt->execute([$id]);
                $nb_chambres = $stmt->fetchColumn();
                
                if ($nb_chambres > 0) {
                    $error = "Impossible de supprimer cette cité car elle contient $nb_chambres chambre(s)";
                } else {
                    // Récupérer l'image avant suppression
                    $stmt = $db->prepare("SELECT image FROM cites WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetchColumn();
                    
                    $stmt = $db->prepare("DELETE FROM cites WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        // Supprimer l'image
                        if ($image && file_exists('../' . $image)) {
                            unlink('../' . $image);
                        }
                        $success = "Cité universitaire supprimée avec succès";
                        logAction('suppression_cite', $_SESSION['user_id'], "Cité supprimée ID: $id");
                    } else {
                        $error = "Erreur lors de la suppression";
                    }
                }
                break;
        }
    }
}

// Récupération des cités avec statistiques
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id) as nb_chambres,
          (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id AND disponible = 1) as nb_disponibles,
          (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id AND disponible = 0) as nb_occupees
          FROM cites c 
          ORDER BY c.nom";
$stmt = $db->query($query);
$cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$stmt = $db->query("SELECT 
    COUNT(*) as total_cites,
    (SELECT COUNT(*) FROM chambres) as total_chambres,
    (SELECT COUNT(*) FROM chambres WHERE disponible = 1) as chambres_disponibles,
    (SELECT AVG(prix_mensuel) FROM chambres) as prix_moyen
    FROM cites");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les quartiers pour le select
$quartiers = getQuartiersNgaoundere();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-building"></i> Gestion des Cités Universitaires
                </h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ajouterCiteModal">
                    <i class="bi bi-plus-circle"></i> Ajouter une Cité
                </button>
            </div>

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
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white" style="background-color: #007A5E;">
                <div class="card-body text-center">
                    <i class="bi bi-building display-4"></i>
                    <h3><?php echo $stats['total_cites']; ?></h3>
                    <p>Cités Universitaires</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background-color: #CE1126;">
                <div class="card-body text-center">
                    <i class="bi bi-door-open display-4"></i>
                    <h3><?php echo $stats['total_chambres']; ?></h3>
                    <p>Total Chambres</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background-color: #FCD116; color: #000 !important;">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle display-4"></i>
                    <h3><?php echo $stats['chambres_disponibles']; ?></h3>
                    <p>Chambres Disponibles</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background-color: #006400;">
                <div class="card-body text-center">
                    <i class="bi bi-cash display-4"></i>
                    <h3><?php echo number_format($stats['prix_moyen'], 2) ?></h3>
                    <p>Prix Moyen</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des cités -->
    <div class="row">
        <?php foreach ($cites as $cite): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header" style="background-color: #007A5E; color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($cite['nom']); ?></h5>
                            
                        </div>
                    </div>
                    
                    <?php if ($cite['image']): ?>
                        <img src="../<?php echo $cite['image']; ?>" class="card-img-top" 
                             alt="<?php echo htmlspecialchars($cite['nom']); ?>"
                             style="height: 180px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" 
                             style="height: 180px;">
                            <i class="bi bi-building display-1 text-muted"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <p class="mb-2">
                            <i class="bi bi-geo-alt-fill" style="color: #CE1126;"></i>
                            <?php echo htmlspecialchars($cite['adresse']); ?><br>
                            <span class="ms-4"><?php echo htmlspecialchars($cite['code_postal']); ?> Ngaoundéré</span>
                        </p>
                        
                        <p class="mb-2 small"><?php echo htmlspecialchars(truncate($cite['description'], 100)); ?></p>
                        
                        <hr>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <h6 class="mb-0"><?php echo $cite['nb_chambres']; ?></h6>
                                <small class="text-muted">Chambres</small>
                            </div>
                            <div class="col-6">
                                <h6 class="mb-0 text-success"><?php echo $cite['nb_disponibles']; ?></h6>
                                <small class="text-muted">Disponibles</small>
                            </div>
                        </div>
                        
                     
                    </div>
                    
                    <div class="card-footer bg-white">
                        <div class="btn-group w-100">
                            <a href="gestion-chambres.php?cite_id=<?php echo $cite['id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-door-open"></i> Voir les chambres
                            </a>
                            <button class="btn btn-outline-warning btn-sm" 
                                    onclick="modifierCite(<?php echo htmlspecialchars(json_encode($cite)); ?>)"
                                    data-bs-toggle="modal" data-bs-target="#modifierCiteModal">
                                <i class="bi bi-pencil"></i> Modifier
                            </button>
                            <button class="btn btn-outline-danger btn-sm" 
                                    onclick="supprimerCite(<?php echo $cite['id']; ?>, '<?php echo htmlspecialchars($cite['nom']); ?>', <?php echo $cite['nb_chambres']; ?>)"
                                    data-bs-toggle="modal" data-bs-target="#supprimerCiteModal">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($cites)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aucune cité universitaire enregistrée.
                    <button class="btn btn-link" data-bs-toggle="modal" data-bs-target="#ajouterCiteModal">
                        Ajouter une cité
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Cité -->
<div class="modal fade" id="ajouterCiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-header text-white" style="background-color: #007A5E;">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Ajouter une Cité Universitaire
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom de la cité *</label>
                            <input type="text" class="form-control" name="nom" required 
                                   placeholder="Ex: Cité Universitaire de ">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse complète *</label>
                        <input type="text" class="form-control" name="adresse" required 
                               placeholder="Ex:Adresse complète">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-control" name="ville" readonly
                               value="Ngaoundéré">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code Postal / BP</label>
                            <input type="text" class="form-control" name="code_postal" 
                                   placeholder="Ex: BP 454">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  placeholder="Description de la cité, commodités, environnement..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Photo de la cité</label>
                        <input type="file" class="form-control" name="image" accept="image/*">
                        <small class="text-muted">Format JPG, PNG ou GIF - Max 5MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Cité -->
<div class="modal fade" id="modifierCiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header text-white" style="background-color: #FCD116; color: #000 !important;">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Modifier la Cité Universitaire
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom de la cité *</label>
                            <input type="text" class="form-control" name="nom" id="edit_nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quartier *</label>
                            <select class="form-select" name="quartier" id="edit_quartier" required>
                                <?php foreach ($quartiers as $q): ?>
                                    <option value="<?php echo $q; ?>"><?php echo $q; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse complète *</label>
                        <input type="text" class="form-control" name="adresse" id="edit_adresse" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-control" value="Ngaoundéré" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code Postal / BP</label>
                            <input type="text" class="form-control" name="code_postal" id="edit_code_postal">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image actuelle</label>
                        <div id="current_image_container"></div>
                        <div class="form-check mt-2">
                            <input type="checkbox" class="form-check-input" name="supprimer_image" id="edit_supprimer_image">
                            <label class="form-check-label" for="edit_supprimer_image">Supprimer l'image</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouvelle photo</label>
                        <input type="file" class="form-control" name="image" accept="image/*">
                        <small class="text-muted">Laissez vide pour conserver l'image actuelle</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer Cité -->
<div class="modal fade" id="supprimerCiteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header text-white" style="background-color: #CE1126;">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmer la suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la cité <strong id="delete_nom"></strong> ?</p>
                    <p id="delete_warning" class="text-danger"></p>
                    <p class="text-danger mb-0">Cette action est irréversible.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" id="btn_supprimer">
                        <i class="bi bi-trash"></i> Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Détails Cité -->
<div class="modal fade" id="detailsCiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #007A5E;">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle"></i> Détails de la Cité
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="details_content">
                <!-- Chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function modifierCite(cite) {
    document.getElementById('edit_id').value = cite.id;
    document.getElementById('edit_nom').value = cite.nom;
    document.getElementById('edit_quartier').value = cite.quartier;
    document.getElementById('edit_adresse').value = cite.adresse;
    document.getElementById('edit_code_postal').value = cite.code_postal || '';
    document.getElementById('edit_description').value = cite.description || '';
    
    const container = document.getElementById('current_image_container');
    if (cite.image) {
        container.innerHTML = '<img src="../' + cite.image + '" style="max-width: 200px; max-height: 150px;" class="img-thumbnail">';
    } else {
        container.innerHTML = '<p class="text-muted">Aucune image</p>';
    }
}

function supprimerCite(id, nom, nbChambres) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_nom').textContent = nom;
    
    const warning = document.getElementById('delete_warning');
    const btnSupprimer = document.getElementById('btn_supprimer');
    
    if (nbChambres > 0) {
        warning.textContent = ' Cette cité contient ' + nbChambres + ' chambre(s). Vous devez d\'abord supprimer ou déplacer ces chambres.';
        btnSupprimer.disabled = true;
    } else {
        warning.textContent = '';
        btnSupprimer.disabled = false;
    }
}

function voirDetails(id) {
    fetch('ajax/get-cite-details.php?id=' + id)
        .then(response => response.text())
        .then(data => {
            document.getElementById('details_content').innerHTML = data;
            new bootstrap.Modal(document.getElementById('detailsCiteModal')).show();
        });
}
</script>

<?php include '../includes/footer.php'; ?>