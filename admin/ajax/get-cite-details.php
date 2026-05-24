<?php
require_once '../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    exit('Accès non autorisé');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('ID invalide');
}

$database = new Database();
$db = $database->getConnection();
$id = $_GET['id'];

// Récupérer les détails de la cité
$stmt = $db->prepare("SELECT c.*, 
                      (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id) as nb_chambres,
                      (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id AND disponible = 1) as nb_disponibles,
                      (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id AND disponible = 0) as nb_occupees,
                      (SELECT AVG(prix_mensuel) FROM chambres WHERE cite_id = c.id) as prix_moyen,
                      (SELECT MIN(prix_mensuel) FROM chambres WHERE cite_id = c.id) as prix_min,
                      (SELECT MAX(prix_mensuel) FROM chambres WHERE cite_id = c.id) as prix_max
                      FROM cites c WHERE c.id = ?");
$stmt->execute([$id]);
$cite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cite) {
    echo '<p class="text-danger">Cité non trouvée</p>';
    exit;
}

// Récupérer les chambres de la cité
$stmt = $db->prepare("SELECT * FROM chambres WHERE cite_id = ? ORDER BY numero_chambre");
$stmt->execute([$id]);
$chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-6">
        <h6>Informations générales</h6>
        <table class="table table-sm">
            <tr>
                <th>Nom</th>
                <td><?php echo htmlspecialchars($cite['nom']); ?></td>
            </tr>
            <tr>
                <th>Quartier</th>
                <td><?php echo htmlspecialchars($cite['quartier']); ?></td>
            </tr>
            <tr>
                <th>Adresse</th>
                <td><?php echo htmlspecialchars($cite['adresse']); ?></td>
            </tr>
            <tr>
                <th>Code Postal</th>
                <td><?php echo htmlspecialchars($cite['code_postal'] ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Date de création</th>
                <td><?php echo date('d/m/Y', strtotime($cite['date_creation'])); ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Statistiques</h6>
        <table class="table table-sm">
            <tr>
                <th>Total chambres</th>
                <td><?php echo $cite['nb_chambres']; ?></td>
            </tr>
            <tr>
                <th>Chambres disponibles</th>
                <td class="text-success"><?php echo $cite['nb_disponibles']; ?></td>
            </tr>
            <tr>
                <th>Chambres occupées</th>
                <td class="text-danger"><?php echo $cite['nb_occupees']; ?></td>
            </tr>
            <tr>
                <th>Prix moyen</th>
                <td><?php echo formatFCFA($cite['prix_moyen']); ?></td>
            </tr>
            <tr>
                <th>Fourchette de prix</th>
                <td><?php echo formatFCFA($cite['prix_min']); ?> - <?php echo formatFCFA($cite['prix_max']); ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if ($cite['description']): ?>
<div class="mt-3">
    <h6>Description</h6>
    <p><?php echo nl2br(htmlspecialchars($cite['description'])); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($chambres)): ?>
<div class="mt-3">
    <h6>Liste des chambres (<?php echo count($chambres); ?>)</h6>
    <div class="table-responsive" style="max-height: 300px;">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Type</th>
                    <th>Prix</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chambres as $ch): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ch['numero_chambre']); ?></td>
                    <td><?php echo ucfirst($ch['type_chambre']); ?></td>
                    <td><?php echo formatFCFA($ch['prix_mensuel']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $ch['disponible'] ? 'success' : 'danger'; ?>">
                            <?php echo $ch['disponible'] ? 'Disponible' : 'Occupée'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>