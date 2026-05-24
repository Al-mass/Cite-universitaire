<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $telephone = trim($_POST['telephone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Récupérer les informations actuelles
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Validation
    $errors = [];
    
    if (empty($nom)) $errors[] = "Le nom est requis";
    if (empty($prenom)) $errors[] = "Le prénom est requis";
    
    // Vérifier le mot de passe si on veut le changer
    if (!empty($new_password)) {
        if (!password_verify($current_password, $user['mot_de_passe'])) {
            $errors[] = "Le mot de passe actuel est incorrect";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "Les nouveaux mots de passe ne correspondent pas";
        }
        if (strlen($new_password) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, telephone = ?, mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $telephone, $hashed_password, $user_id]);
            } else {
                $stmt = $db->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, telephone = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $telephone, $user_id]);
            }
            
            $_SESSION['user_name'] = $prenom . ' ' . $nom;
            $success = "Profil mis à jour avec succès";
            
            // Utiliser la fonction logAction du fichier functions.php (écrit dans un fichier log)
            logAction('modification_profil_admin', $user_id, "Profil administrateur modifié");
            
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
} else {
    // Récupérer les informations du profil
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Statistiques de l'administrateur (sans utiliser la table logs)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'admin'");
$stmt->execute();
$nb_admins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Compter les actions depuis le fichier log si nécessaire
$nb_actions = 0;
$log_file = __DIR__ . '/../logs/actions.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    foreach ($lines as $line) {
        if (strpos($line, "User: $user_id") !== false) {
            $nb_actions++;
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-white" style="background-color: #007A5E;">
                    <h4 class="mb-0">
                        <i class="bi bi-person-gear"></i> Profil Administrateur
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <h5 class="mb-3">Informations personnelles</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="prenom" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly disabled>
                            <small class="text-muted">L'email administrateur ne peut pas être modifié</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>"
                                   placeholder="6XXXXXXXX">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rôle</label>
                            <input type="text" class="form-control" value="Administrateur" readonly disabled>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Changer le mot de passe</h5>
                        <small class="text-muted">Laissez vide pour conserver le mot de passe actuel</small>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirmer</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                        
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Retour
                        </a>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header text-white" style="background-color: #FCD116; color: #000 !important;">
                    <h5 class="mb-0">Informations du compte</h5>
                </div>
                <div class="card-body">
                    <p><strong>ID Administrateur :</strong> #<?php echo $user['id'] ?? ''; ?></p>
                    <p><strong>Membre depuis :</strong> <?php echo isset($user['date_inscription']) ? date('d/m/Y', strtotime($user['date_inscription'])) : ''; ?></p>
                    <p><strong>Statut :</strong> 
                        <span class="badge bg-<?php echo (isset($user['statut']) && $user['statut']) ? 'success' : 'danger'; ?>">
                            <?php echo (isset($user['statut']) && $user['statut']) ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header text-white" style="background-color: #CE1126;">
                    <h5 class="mb-0">Statistiques</h5>
                </div>
                <div class="card-body">
                    <p><strong>Administrateurs :</strong> <?php echo $nb_admins; ?></p>
                    <p><strong>Actions effectuées :</strong> <?php echo $nb_actions; ?></p>
                    <p><strong>Dernière connexion :</strong> <?php echo date('d/m/Y H:i'); ?></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header text-white" style="background-color: #006400;">
                    <h5 class="mb-0">Sécurité</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-shield-lock"></i>
                        <strong>Recommandations :</strong>
                        <ul class="mb-0 mt-2">
                            <li>Changez votre mot de passe régulièrement</li>
                            <li>Utilisez un mot de passe fort</li>
                            <li>Ne partagez jamais vos identifiants</li>
                            <li>Déconnectez-vous après chaque session</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>