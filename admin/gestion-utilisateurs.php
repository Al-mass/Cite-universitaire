<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'changer_role':
                $stmt = $db->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?");
                $stmt->execute([$_POST['role'], $_POST['user_id']]);
                $success = "Rôle modifié avec succès";
                break;
                
            case 'changer_statut':
                $stmt = $db->prepare("UPDATE utilisateurs SET statut = ? WHERE id = ?");
                $stmt->execute([$_POST['statut'], $_POST['user_id']]);
                $success = "Statut modifié avec succès";
                break;
                
            case 'supprimer':
                $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ? AND role != 'admin'");
                $stmt->execute([$_POST['user_id']]);
                $success = "Utilisateur supprimé avec succès";
                break;
        }
    }
}

// Récupération des utilisateurs
$stmt = $db->query("SELECT * FROM utilisateurs ORDER BY date_inscription DESC");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">
                <i class="bi bi-people"></i> Gestion des Utilisateurs
            </h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom Complet</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Date d'inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilisateurs as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['telephone']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['statut'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $user['statut'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="changerRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')"
                                                        title="Changer le rôle">
                                                    <i class="bi bi-shield"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="changerStatut(<?php echo $user['id']; ?>, <?php echo $user['statut']; ?>)"
                                                        title="Activer/Désactiver">
                                                    <i class="bi bi-toggle-on"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="supprimerUser(<?php echo $user['id']; ?>)"
                                                        title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Changer Rôle -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="changer_role">
                <input type="hidden" name="user_id" id="role_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Changer le rôle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nouveau rôle</label>
                        <select class="form-select" name="role" id="role_select">
                            <option value="etudiant">Étudiant</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Changer Statut -->
<div class="modal fade" id="statutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="changer_statut">
                <input type="hidden" name="user_id" id="statut_user_id">
                <input type="hidden" name="statut" id="statut_value">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer l'action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="statut_message"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changerRole(userId, currentRole) {
    document.getElementById('role_user_id').value = userId;
    document.getElementById('role_select').value = currentRole;
    var modal = new bootstrap.Modal(document.getElementById('roleModal'));
    modal.show();
}

function changerStatut(userId, currentStatut) {
    document.getElementById('statut_user_id').value = userId;
    var newStatut = currentStatut ? 0 : 1;
    document.getElementById('statut_value').value = newStatut;
    
    var message = newStatut ? 'Voulez-vous activer cet utilisateur ?' : 'Voulez-vous désactiver cet utilisateur ?';
    document.getElementById('statut_message').textContent = message;
    
    var modal = new bootstrap.Modal(document.getElementById('statutModal'));
    modal.show();
}

function supprimerUser(userId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="supprimer">' +
                        '<input type="hidden" name="user_id" value="' + userId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>