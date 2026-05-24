<?php
require_once '../includes/functions.php';

if (!function_exists('formatFCFA')) {
    function formatFCFA($montant, $avec_symbole = true) {
        if ($montant === null || $montant === '') $montant = 0;
        $formatted = number_format(floatval($montant), 0, ',', ' ');
        return $avec_symbole ? $formatted . ' FCFA' : $formatted;
    }
}

$database = new Database();
$db = $database->getConnection();

// Récupération des cités
$stmt = $db->query("SELECT * FROM cites ORDER BY nom");
$cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$types_chambres = ['simple' => 'Simple', 'double' => 'Double', 'studio' => 'Studio', 'moderne' => 'Moderne'];

// Filtres
$filtres = [
    'ville' => $_GET['ville'] ?? '',
    'cite_id' => $_GET['cite_id'] ?? '',
    'type' => $_GET['type'] ?? '',
    'prix_min' => $_GET['prix_min'] ?? '',
    'prix_max' => $_GET['prix_max'] ?? '',
    'capacite' => $_GET['capacite'] ?? '',
    'disponible' => $_GET['disponible'] ?? '',
    'tri' => $_GET['tri'] ?? 'recent',
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Construction WHERE
$where = ["1=1"];
$params = [];

if ($filtres['disponible'] === '1') {
    $where[] = "c.disponible = 1";
} elseif ($filtres['disponible'] === '0') {
    $where[] = "c.disponible = 0";
}
if (!empty($filtres['ville'])) { $where[] = "ct.ville LIKE ?"; $params[] = '%' . $filtres['ville'] . '%'; }
if (!empty($filtres['cite_id'])) { $where[] = "c.cite_id = ?"; $params[] = $filtres['cite_id']; }
if (!empty($filtres['type'])) { $where[] = "c.type_chambre = ?"; $params[] = $filtres['type']; }
if (!empty($filtres['prix_min'])) { $where[] = "c.prix_mensuel >= ?"; $params[] = $filtres['prix_min']; }
if (!empty($filtres['prix_max'])) { $where[] = "c.prix_mensuel <= ?"; $params[] = $filtres['prix_max']; }
if (!empty($filtres['capacite'])) { $where[] = "c.capacite >= ?"; $params[] = $filtres['capacite']; }

$whereClause = implode(" AND ", $where);

// Tri
$orderBy = "c.id DESC";
switch ($filtres['tri']) {
    case 'prix_asc': $orderBy = "c.prix_mensuel ASC"; break;
    case 'prix_desc': $orderBy = "c.prix_mensuel DESC"; break;
    case 'note': $orderBy = "note_moyenne DESC"; break;
}

// Compter
$stmt = $db->prepare("SELECT COUNT(*) FROM chambres c JOIN cites ct ON c.cite_id = ct.id WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_pages = ceil($total / $limit);

// Récupérer les chambres
$query = "SELECT c.*, ct.nom as cite_nom, ct.ville,
          (SELECT AVG(note) FROM evaluations WHERE chambre_id = c.id) as note_moyenne
          FROM chambres c JOIN cites ct ON c.cite_id = ct.id 
          WHERE $whereClause ORDER BY $orderBy LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Séparer disponibles et occupées
$disponibles = array_filter($chambres, function($c) { return $c['disponible'] == 1; });
$occupees = array_filter($chambres, function($c) { return $c['disponible'] == 0; });

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-search"></i> Chambres <span class="badge bg-success ms-2"><?php echo $total; ?></span></h2>
        <button class="btn btn-outline-success d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas"><i class="bi bi-funnel"></i> Filtres</button>
    </div>

    <div class="row">
        <!-- SIDEBAR FILTRES -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm sticky-top" style="top:80px;">
                <div class="card-header bg-success text-white"><strong><i class="bi bi-funnel-fill"></i> Filtres</strong></div>
                <div class="card-body">
                    <form method="GET" action="">
                        
                        <!-- Disponibilité -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-eye"></i> Disponibilité</label>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?disponible=1" class="btn btn-sm <?php echo $filtres['disponible'] === '1' ? 'btn-success' : 'btn-outline-success'; ?>"><i class="bi bi-check-circle"></i> Disponibles</a>
                                <a href="?disponible=0" class="btn btn-sm <?php echo $filtres['disponible'] === '0' ? 'btn-danger' : 'btn-outline-danger'; ?>"><i class="bi bi-x-circle"></i> Occupées</a>
                                <a href="?" class="btn btn-sm <?php echo $filtres['disponible'] === '' ? 'btn-secondary' : 'btn-outline-secondary'; ?>"><i class="bi bi-list"></i> Toutes</a>
                            </div>
                        </div>

                        <!-- Tri -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-arrow-down-up"></i> Trier</label>
                            <select class="form-select" name="tri" onchange="this.form.submit()">
                                <option value="recent" <?php echo $filtres['tri']=='recent'?'selected':''; ?>>Plus récentes</option>
                                <option value="prix_asc" <?php echo $filtres['tri']=='prix_asc'?'selected':''; ?>>Prix croissant</option>
                                <option value="prix_desc" <?php echo $filtres['tri']=='prix_desc'?'selected':''; ?>>Prix décroissant</option>
                                <option value="note" <?php echo $filtres['tri']=='note'?'selected':''; ?>>Mieux notées</option>
                            </select>
                        </div>

                        <hr>

                        <!-- Ville -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-geo-alt"></i> Ville</label>
                            <input type="text" class="form-control" name="ville" placeholder="Ngaoundéré..." value="<?php echo htmlspecialchars($filtres['ville']); ?>">
                        </div>

                        <!-- Cité -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-building"></i> Cité</label>
                            <select class="form-select" name="cite_id">
                                <option value="">Toutes</option>
                                <?php foreach ($cites as $cite): ?>
                                    <option value="<?php echo $cite['id']; ?>" <?php echo $filtres['cite_id'] == $cite['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cite['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Type -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-door-open"></i> Type</label>
                            <select class="form-select" name="type">
                                <option value="">Tous</option>
                                <?php foreach ($types_chambres as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo $filtres['type'] == $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Prix -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-cash"></i> Prix (FCFA)</label>
                            <div class="row g-2">
                                <div class="col-6"><input type="number" class="form-control" name="prix_min" placeholder="Min" value="<?php echo $filtres['prix_min']; ?>"></div>
                                <div class="col-6"><input type="number" class="form-control" name="prix_max" placeholder="Max" value="<?php echo $filtres['prix_max']; ?>"></div>
                            </div>
                        </div>

                        <!-- Capacité -->
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-people"></i> Capacité</label>
                            <select class="form-select" name="capacite">
                                <option value="">Peu importe</option>
                                <option value="1" <?php echo $filtres['capacite']=='1'?'selected':''; ?>>1 personne</option>
                                <option value="2" <?php echo $filtres['capacite']=='2'?'selected':''; ?>>2 personnes</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success"><i class="bi bi-search"></i> Appliquer</button>
                            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Réinitialiser</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- LISTE DES CHAMBRES -->
        <div class="col-lg-9">
            <?php if (empty($chambres)): ?>
                <div class="alert alert-info">Aucune chambre trouvée. <a href="index.php">Voir tout</a></div>
            <?php else: ?>
                
                <!-- CHAMBRES DISPONIBLES -->
                <?php if (!empty($disponibles)): ?>
                    <h4 class="mb-3"><i class="bi bi-check-circle-fill text-success"></i> Disponibles (<?php echo count($disponibles); ?>)</h4>
                    <div class="row g-4 mb-5">
                        <?php foreach ($disponibles as $c): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="position-relative">
                                        <?php if ($c['image']): ?>
                                            <img src="../<?php echo $c['image']; ?>" class="card-img-top" style="height:200px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px;"><i class="bi bi-door-open display-1 text-muted"></i></div>
                                        <?php endif; ?>
                                        <span class="badge bg-success position-absolute top-0 end-0 m-2">Disponible</span>
                                        <span class="badge bg-<?php echo $c['type_chambre']=='simple'?'info':($c['type_chambre']=='double'?'primary':'warning'); ?> position-absolute top-0 start-0 m-2"><?php echo ucfirst($c['type_chambre']); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <h5>Chambre <?php echo htmlspecialchars($c['numero_chambre']); ?></h5>
                                        <p class="text-muted"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($c['cite_nom']); ?> - <?php echo htmlspecialchars($c['ville']); ?></p>
                                        <p><i class="bi bi-people"></i> <?php echo $c['capacite']; ?> pers.</p>
                                        <?php if ($c['note_moyenne']): ?><p><i class="bi bi-star-fill text-warning"></i> <?php echo round($c['note_moyenne'],1); ?></p><?php endif; ?>
                                        <h4 class="text-success"><?php echo formatFCFA($c['prix_mensuel']); ?> <small>/mois</small></h4>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <a href="details.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-success btn-sm">Détails</a>
                                        <?php if (isLoggedIn() && !isAdmin()): ?>
                                            <a href="reserver.php?id=<?php echo $c['id']; ?>" class="btn btn-success btn-sm">Réserver</a>
                                        <?php elseif (!isLoggedIn()): ?>
                                            <a href="../auth/login.php" class="btn btn-success btn-sm">Connexion</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- CHAMBRES OCCUPÉES -->
                <?php if (!empty($occupees)): ?>
                    <h4 class="mb-3"><i class="bi bi-x-circle-fill text-danger"></i> Occupées (<?php echo count($occupees); ?>)</h4>
                    <div class="row g-4">
                        <?php foreach ($occupees as $c): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 shadow-sm" style="opacity:0.8;">
                                    <div class="position-relative">
                                        <?php if ($c['image']): ?>
                                            <img src="../<?php echo $c['image']; ?>" class="card-img-top" style="height:200px;object-fit:cover;filter:grayscale(30%);">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px;"><i class="bi bi-door-open display-1 text-muted"></i></div>
                                        <?php endif; ?>
                                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">Occupée</span>
                                        <span class="badge bg-<?php echo $c['type_chambre']=='simple'?'info':($c['type_chambre']=='double'?'primary':'warning'); ?> position-absolute top-0 start-0 m-2"><?php echo ucfirst($c['type_chambre']); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <h5>Chambre <?php echo htmlspecialchars($c['numero_chambre']); ?></h5>
                                        <p class="text-muted"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($c['cite_nom']); ?> - <?php echo htmlspecialchars($c['ville']); ?></p>
                                        <p><i class="bi bi-people"></i> <?php echo $c['capacite']; ?> pers.</p>
                                        <h4 class="text-danger"><?php echo formatFCFA($c['prix_mensuel']); ?> <small>/mois</small></h4>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <a href="details.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-secondary btn-sm">Détails</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4"><ul class="pagination justify-content-center">
                        <?php $base = 'index.php?' . http_build_query(array_diff_key($_GET, ['page' => ''])); if ($base != 'index.php?') $base .= '&'; ?>
                        <li class="page-item <?php echo $page<=1?'disabled':''; ?>"><a class="page-link" href="<?php echo $base; ?>page=<?php echo $page-1; ?>">&laquo;</a></li>
                        <?php for($i=1; $i<=$total_pages; $i++): ?>
                            <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="<?php echo $base; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page>=$total_pages?'disabled':''; ?>"><a class="page-link" href="<?php echo $base; ?>page=<?php echo $page+1; ?>">&raquo;</a></li>
                    </ul></nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Offcanvas Mobile -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas">
    <div class="offcanvas-header"><h5>Filtres</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body">
        <form method="GET">
            <div class="mb-3"><label class="fw-bold">Disponibilité</label>
                <select class="form-select" name="disponible"><option value="">Toutes</option><option value="1">Disponibles</option><option value="0">Occupées</option></select>
            </div>
            <div class="mb-3"><label class="fw-bold">Ville</label><input type="text" class="form-control" name="ville" value="<?php echo htmlspecialchars($filtres['ville']); ?>"></div>
            <div class="mb-3"><label class="fw-bold">Cité</label>
                <select class="form-select" name="cite_id"><option value="">Toutes</option><?php foreach ($cites as $cite): ?><option value="<?php echo $cite['id']; ?>"><?php echo htmlspecialchars($cite['nom']); ?></option><?php endforeach; ?></select>
            </div>
            <div class="mb-3"><label class="fw-bold">Type</label>
                <select class="form-select" name="type"><option value="">Tous</option><?php foreach ($types_chambres as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select>
            </div>
            <div class="row mb-3"><div class="col-6"><label>Prix min</label><input type="number" class="form-control" name="prix_min"></div><div class="col-6"><label>Prix max</label><input type="number" class="form-control" name="prix_max"></div></div>
            <button type="submit" class="btn btn-success w-100">Appliquer</button>
        </form>
    </div>
</div>

<style>
.pagination .page-item.active .page-link { background-color: #007A5E; border-color: #007A5E; }
.pagination .page-link { color: #007A5E; }
</style>

<?php include '../includes/footer.php'; ?>