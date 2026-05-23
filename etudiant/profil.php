<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $telephone = $_POST['telephone'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $error = '';
    $success = '';
    
    // Récupérer les informations actuelles
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier le mot de passe actuel si on veut changer le mot de passe
    if (!empty($new_password)) {
        if (!password_verify($current_password, $user['mot_de_passe'])) {
            $error = 'Le mot de passe actuel est incorrect';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success = 'Mot de passe modifié avec succès';
        }
    }
    
    // Mettre à jour les informations du profil
    if (empty($error)) {
        $stmt = $db->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, telephone = ? WHERE id = ?");
        if ($stmt->execute([$nom, $prenom, $telephone, $user_id])) {
            $_SESSION['user_name'] = $prenom . ' ' . $nom;
            if (empty($success)) {
                $success = 'Profil mis à jour avec succès';
            }
        }
    }
} else {
    // Récupérer les informations du profil
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-person-gear"></i> Mon Profil
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error) && $error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success) && $success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <h5 class="mb-3">Informations personnelles</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled>
                            <small class="text-muted">L'email ne peut pas être modifié</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   value="<?php echo htmlspecialchars($user['telephone']); ?>">
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Changer le mot de passe</h5>
                        <small class="text-muted">Laissez vide si vous ne souhaitez pas changer de mot de passe</small>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Statut du compte</label>
                            <div>
                                <span class="badge bg-<?php echo $user['statut'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['statut'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Membre depuis</label>
                            <div>
                                <?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                        
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Retour
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>