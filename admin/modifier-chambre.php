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

// Récupérer les cités pour le select
$stmt = $db->query("SELECT * FROM cites ORDER BY nom");
$cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

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
        // Vérifier si le numéro existe déjà (sauf pour cette chambre)
        $stmt = $db->prepare("SELECT id FROM chambres WHERE cite_id = ? AND numero_chambre = ? AND id != ?");
        $stmt->execute([$cite_id, $numero_chambre, $chambre_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Ce numéro de chambre existe déjà dans cette cité";
        }
    }
    
    if (empty($prix_mensuel) || $prix_mensuel <= 0) {
        $errors[] = "Le prix mensuel doit être supérieur à 0";
    }
    
    // Gestion de l'upload d'image
    $image_path = $chambre['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format d'image non autorisé. Utilisez JPG, PNG ou GIF";
        } else {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = '../assets/images/chambres/' . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Supprimer l'ancienne image si elle existe
                if ($chambre['image'] && file_exists('../' . $chambre['image'])) {
                    unlink('../' . $chambre['image']);
                }
                $image_path = 'assets/images/chambres/' . $new_filename;
            }
        }
    }
    
    // Supprimer l'image si demandé
    if (isset($_POST['supprimer_image']) && $image_path) {
        if (file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        $image_path = null;
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE chambres SET cite_id = ?, numero_chambre = ?, type_chambre = ?, 
                                  prix_mensuel = ?, capacite = ?, equipements = ?, description = ?, 
                                  disponible = ?, image = ? WHERE id = ?");
            
            if ($stmt->execute([$cite_id, $numero_chambre, $type_chambre, $prix_mensuel, 
                               $capacite, $equipements, $description, $disponible, $image_path, $chambre_id])) {
                
                logAction('modification_chambre', $_SESSION['user_id'], "Chambre modifiée: $numero_chambre");
                $success = "Chambre modifiée avec succès !";
                
                // Rafraîchir les données
                $stmt = $db->prepare("SELECT c.*, ct.nom as cite_nom FROM chambres c 
                                      JOIN cites ct ON c.cite_id = ct.id 
                                      WHERE c.id = ?");
                $stmt->execute([$chambre_id]);
                $chambre = $stmt->fetch(PDO::FETCH_ASSOC);
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

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-pencil-square"></i> Modifier la Chambre
                </h2>
                <a href="gestion-chambres.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour à la liste
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Modification de la chambre #<?php echo $chambre['id']; ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cite_id" class="form-label">Cité Universitaire *</label>
                                <select class="form-select" id="cite_id" name="cite_id" required>
                                    <?php foreach ($cites as $cite): ?>
                                        <option value="<?php echo $cite['id']; ?>" 
                                            <?php echo $chambre['cite_id'] == $cite['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cite['nom'] . ' - ' . $cite['ville']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="numero_chambre" class="form-label">Numéro de Chambre *</label>
                                <input type="text" class="form-control" id="numero_chambre" name="numero_chambre" 
                                       value="<?php echo htmlspecialchars($chambre['numero_chambre']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="type_chambre" class="form-label">Type de Chambre *</label>
                                <select class="form-select" id="type_chambre" name="type_chambre" required>
                                    <option value="simple" <?php echo $chambre['type_chambre'] == 'simple' ? 'selected' : ''; ?>>Simple</option>
                                    <option value="double" <?php echo $chambre['type_chambre'] == 'double' ? 'selected' : ''; ?>>Double</option>
                                    <option value="studio" <?php echo $chambre['type_chambre'] == 'studio' ? 'selected' : ''; ?>>Studio</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="prix_mensuel" class="form-label">Prix Mensuel (€) *</label>
                                <input type="number" step="0.01" class="form-control" id="prix_mensuel" name="prix_mensuel" 
                                       value="<?php echo htmlspecialchars($chambre['prix_mensuel']); ?>" min="0.01" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="capacite" class="form-label">Capacité (personnes) *</label>
                                <input type="number" class="form-control" id="capacite" name="capacite" 
                                       value="<?php echo htmlspecialchars($chambre['capacite']); ?>" min="1" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="equipements" class="form-label">Équipements</label>
                            <input type="text" class="form-control" id="equipements" name="equipements" 
                                   value="<?php echo htmlspecialchars($chambre['equipements']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($chambre['description']); ?></textarea>
                        </div>
                        
                        <?php if ($chambre['image']): ?>
                            <div class="mb-3">
                                <label class="form-label">Image actuelle</label>
                                <div>
                                    <img src="../<?php echo $chambre['image']; ?>" alt="Chambre" style="max-width: 200px; max-height: 150px;">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" id="supprimer_image" name="supprimer_image">
                                        <label class="form-check-label" for="supprimer_image">Supprimer l'image</label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Nouvelle photo</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Laissez vide pour conserver l'image actuelle</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="disponible" name="disponible" 
                                       <?php echo $chambre['disponible'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="disponible">
                                    Chambre disponible à la réservation
                                </label>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="gestion-chambres.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">Informations</h6>
                </div>
                <div class="card-body">
                    <p><strong>ID Chambre :</strong> #<?php echo $chambre['id']; ?></p>
                    <p><strong>Cité actuelle :</strong> <?php echo htmlspecialchars($chambre['cite_nom']); ?></p>
                    <p><strong>Date d'ajout :</strong> <?php echo date('d/m/Y', strtotime($chambre['date_creation'])); ?></p>
                    
                    <hr>
                    
                    <h6>Réservations liées :</h6>
                    <?php
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE chambre_id = ?");
                    $stmt->execute([$chambre_id]);
                    $nb_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                    <p>Cette chambre a <?php echo $nb_reservations; ?> réservation(s).</p>
                    
                    <?php if ($nb_reservations > 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Les modifications peuvent affecter les réservations existantes.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>