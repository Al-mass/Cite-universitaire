<?php
require_once '../includes/functions.php';

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

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Récupérer les cités pour le select
$stmt = $db->query("SELECT * FROM cites ORDER BY nom");
$cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cite_id = $_POST['cite_id'];
    $numero_chambre = trim($_POST['numero_chambre']);
    $type_chambre = $_POST['type_chambre'];
    $prix_mensuel = $_POST['prix_mensuel'];
    $capacite = $_POST['capacite'];
    $equipements = trim($_POST['equipements']);
    $description = trim($_POST['description']);
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($cite_id)) {
        $errors[] = "Veuillez sélectionner une cité universitaire";
    }
    
    if (empty($numero_chambre)) {
        $errors[] = "Le numéro de chambre est requis";
    } else {
        // Vérifier si le numéro de chambre existe déjà dans cette cité
        $stmt = $db->prepare("SELECT id FROM chambres WHERE cite_id = ? AND numero_chambre = ?");
        $stmt->execute([$cite_id, $numero_chambre]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Ce numéro de chambre existe déjà dans cette cité";
        }
    }
    
    if (empty($type_chambre)) {
        $errors[] = "Le type de chambre est requis";
    }
    
    if (empty($prix_mensuel) || $prix_mensuel <= 0) {
        $errors[] = "Le prix mensuel doit être supérieur à 0 FCFA";
    }
    
    if (empty($capacite) || $capacite < 1) {
        $errors[] = "La capacité doit être d'au moins 1 personne";
    }
    
    // Gestion de l'upload d'image
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $size = $_FILES['image']['size'];
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format d'image non autorisé. Utilisez JPG, PNG, GIF ou WEBP";
        } elseif ($size > 5 * 1024 * 1024) {
            $errors[] = "L'image ne doit pas dépasser 5MB";
        } else {
            // Créer le dossier s'il n'existe pas
            $upload_dir = '../assets/images/chambres/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'chambre_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_path = 'assets/images/chambres/' . $new_filename;
            } else {
                $errors[] = "Erreur lors de l'upload de l'image. Vérifiez les permissions du dossier.";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO chambres (cite_id, numero_chambre, type_chambre, prix_mensuel, 
                                  capacite, equipements, description, disponible, image) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$cite_id, $numero_chambre, $type_chambre, $prix_mensuel, 
                               $capacite, $equipements, $description, $disponible, $image_path])) {
                
                // Logger l'action
                if (function_exists('logAction')) {
                    logAction('ajout_chambre', $_SESSION['user_id'], "Chambre ajoutée: $numero_chambre");
                }
                
                $success = "Chambre " . htmlspecialchars($numero_chambre) . " ajoutée avec succès !";
                
                // Réinitialiser le formulaire
                $_POST = [];
            } else {
                $error = "Erreur lors de l'ajout de la chambre";
            }
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="bi bi-plus-circle-fill text-success"></i> Ajouter une Chambre
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item"><a href="gestion-chambres.php">Chambres</a></li>
                            <li class="breadcrumb-item active">Ajouter</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="gestion-chambres.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulaire principal -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Informations de la chambre</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        
                        <!-- Cité et Numéro -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cite_id" class="form-label">
                                    <i class="bi bi-building"></i> Cité Universitaire <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="cite_id" name="cite_id" required>
                                    <option value="">Sélectionner une cité</option>
                                    <?php foreach ($cites as $cite): ?>
                                        <option value="<?php echo $cite['id']; ?>" 
                                            <?php echo (isset($_POST['cite_id']) && $_POST['cite_id'] == $cite['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cite['nom'] . ' - ' . $cite['ville']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une cité</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="numero_chambre" class="form-label">
                                    <i class="bi bi-door-open"></i> Numéro de Chambre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="numero_chambre" name="numero_chambre" 
                                       value="<?php echo isset($_POST['numero_chambre']) ? htmlspecialchars($_POST['numero_chambre']) : ''; ?>"
                                       placeholder="Ex: A101" required>
                                <div class="invalid-feedback">Le numéro de chambre est requis</div>
                            </div>
                        </div>
                        
                        <!-- Type, Prix, Capacité -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="type_chambre" class="form-label">
                                    <i class="bi bi-tag"></i> Type de Chambre <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="type_chambre" name="type_chambre" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="simple" <?php echo (isset($_POST['type_chambre']) && $_POST['type_chambre'] == 'simple') ? 'selected' : ''; ?>>Simple</option>
                                    <option value="double" <?php echo (isset($_POST['type_chambre']) && $_POST['type_chambre'] == 'double') ? 'selected' : ''; ?>>Double</option>
                                    <option value="studio" <?php echo (isset($_POST['type_chambre']) && $_POST['type_chambre'] == 'studio') ? 'selected' : ''; ?>>Studio</option>
    
                                </select>
                                <div class="invalid-feedback">Le type de chambre est requis</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="prix_mensuel" class="form-label">
                                    <i class="bi bi-cash"></i> Prix Mensuel (FCFA) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="prix_mensuel" name="prix_mensuel" 
                                           value="<?php echo isset($_POST['prix_mensuel']) ? htmlspecialchars($_POST['prix_mensuel']) : ''; ?>"
                                           placeholder="Ex: 25000" min="500" step="500" required>
                                    <span class="input-group-text">FCFA</span>
                                </div>
                                <div class="invalid-feedback">Le prix doit être supérieur à 0</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="capacite" class="form-label">
                                    <i class="bi bi-people"></i> Capacité (personnes) <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="capacite" name="capacite" 
                                       value="<?php echo isset($_POST['capacite']) ? htmlspecialchars($_POST['capacite']) : '1'; ?>"
                                       min="1" max="10" required>
                                <div class="invalid-feedback">La capacité doit être d'au moins 1</div>
                            </div>
                        </div>
                        
                        <!-- Équipements -->
                        <div class="mb-3">
                            <label for="equipements" class="form-label">
                                <i class="bi bi-wifi"></i> Équipements
                            </label>
                            <input type="text" class="form-control" id="equipements" name="equipements" 
                                   value="<?php echo isset($_POST['equipements']) ? htmlspecialchars($_POST['equipements']) : ''; ?>"
                                   placeholder="Ex: Equipements">
                            <small class="text-muted">Séparez les équipements par des virgules</small>
                        </div>
                        
                        <!-- Équipements rapides -->
                        <div class="mb-3">
                            <label class="form-label">Équipements courants</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php 
                                $equipements_courants = ['Equiper', 'Non-equiper'];
                                foreach ($equipements_courants as $eq): 
                                ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm equipement-tag" 
                                            onclick="ajouterEquipement('<?php echo $eq; ?>')">
                                        <i class="bi bi-plus-circle"></i> <?php echo $eq; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <i class="bi bi-text-paragraph"></i> Description
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Description détaillée de la chambre..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Upload Image -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-camera"></i> Photo de la chambre
                            </label>
                            
                            <!-- Zone d'upload -->
                            <div class="upload-zone" id="uploadZone">
                                <div class="upload-content">
                                    <div class="upload-icon">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </div>
                                    <h6>Cliquez pour sélectionner une image</h6>
                                    <p class="text-muted small mb-0">ou glissez-déposez ici</p>
                                    <p class="text-muted small">JPG, PNG, GIF ou WEBP - Max 5MB</p>
                                </div>
                                <input type="file" class="upload-input" id="image" name="image" accept="image/*">
                            </div>
                            
                            <!-- Prévisualisation -->
                            <div class="image-preview-container mt-3" id="imagePreviewContainer" style="display: none;">
                                <div class="position-relative d-inline-block">
                                    <img id="imagePreview" src="" alt="Aperçu" class="img-thumbnail" style="max-height: 200px;">
                                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                            onclick="supprimerImage()">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Disponibilité -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="disponible" name="disponible" 
                                       <?php echo (!isset($_POST['disponible']) || isset($_POST['disponible'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="disponible">
                                    <strong>Chambre disponible à la réservation</strong>
                                </label>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Boutons -->
                        <div class="d-flex justify-content-between">
                            <a href="gestion-chambres.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-x-circle"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-save"></i> Ajouter la chambre
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Barre latérale d'aide -->
        <div class="col-lg-4">
            <!-- Guide -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Guide</h6>
                </div>
                <div class="card-body">
                    <h6 class="mb-2">Types de chambres :</h6>
                    <ul class="small mb-3">
                        <li><strong>Simple :</strong> Chambre individuelle</li>
                        <li><strong>Double :</strong> Chambre pour 2 personnes</li>
                        <li><strong>Studio :</strong> Logement indépendant avec kitchenette</li>
                        
                    </ul>
                    
                    <h6 class="mb-2">Prix indicatifs :</h6>
                    <ul class="small mb-0">
                        <li>Simple : 15 000 - 30 000 FCFA</li>
                        <li>Double : 20 000 - 40 000 FCFA</li>
                        <li>Studio : 35 000 - 60 000 FCFA</li>
                      
                    </ul>
                </div>
            </div>
            
            <!-- Important -->
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Important</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-0">
                        Assurez-vous que la cité universitaire existe déjà dans le système 
                        avant d'ajouter une chambre. Si ce n'est pas le cas, 
                        <a href="gestion-cites.php" class="fw-bold">ajoutez d'abord la cité</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Zone d'upload */
.upload-zone {
    border: 2px dashed #cbd5e0;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8fafc;
    position: relative;
}
.upload-zone:hover {
    border-color: #007A5E;
    background: #f0fdf4;
}
.upload-zone.dragover {
    border-color: #007A5E;
    background: #d4edda;
}
.upload-icon {
    font-size: 48px;
    color: #a0aec0;
    margin-bottom: 15px;
}
.upload-zone:hover .upload-icon {
    color: #007A5E;
}
.upload-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

/* Prévisualisation */
.image-preview-container {
    text-align: center;
}
.image-preview-container img {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Équipements tags */
.equipement-tag {
    border-radius: 30px;
    font-size: 13px;
    transition: all 0.2s;
}
.equipement-tag:hover {
    background: #007A5E;
    color: white;
    border-color: #007A5E;
}

/* Form switch */
.form-switch .form-check-input:checked {
    background-color: #007A5E;
    border-color: #007A5E;
}

/* Card */
.card {
    border: none;
    border-radius: 16px;
}
.card-header {
    border-radius: 16px 16px 0 0 !important;
}
</style>

<script>
// Validation du formulaire
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Gestion de l'upload d'image
const uploadZone = document.getElementById('uploadZone');
const imageInput = document.getElementById('image');
const imagePreview = document.getElementById('imagePreview');
const imagePreviewContainer = document.getElementById('imagePreviewContainer');

// Clique sur la zone
uploadZone.addEventListener('click', function() {
    imageInput.click();
});

// Drag & Drop
uploadZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', function() {
    this.classList.remove('dragover');
});

uploadZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        imageInput.files = files;
        afficherApercu(files[0]);
    }
});

// Changement de fichier
imageInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            alert('Le fichier est trop volumineux. Taille maximum : 5MB');
            this.value = '';
            return;
        }
        afficherApercu(file);
    }
});

// Afficher l'aperçu
function afficherApercu(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        imagePreview.src = e.target.result;
        imagePreviewContainer.style.display = 'block';
        uploadZone.querySelector('.upload-content').style.opacity = '0.5';
    };
    reader.readAsDataURL(file);
}

// Supprimer l'image
function supprimerImage() {
    imageInput.value = '';
    imagePreview.src = '';
    imagePreviewContainer.style.display = 'none';
    uploadZone.querySelector('.upload-content').style.opacity = '1';
}

// Ajouter un équipement
function ajouterEquipement(equipement) {
    const input = document.getElementById('equipements');
    let equipements = input.value.split(',').map(e => e.trim()).filter(e => e !== '');
    
    if (!equipements.includes(equipement)) {
        equipements.push(equipement);
        input.value = equipements.join(', ');
    } else {
        // Retirer si déjà présent
        equipements = equipements.filter(e => e !== equipement);
        input.value = equipements.join(', ');
    }
    
    input.focus();
}
</script>

<?php include '../includes/footer.php'; ?>