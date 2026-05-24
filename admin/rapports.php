<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Période sélectionnée
$periode = $_GET['periode'] ?? 'mois';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');

// Statistiques générales
$stats = getStatistiquesGlobales();

// Réservations par mois
$stmt = $db->query("SELECT 
    DATE_FORMAT(date_reservation, '%Y-%m') as mois,
    COUNT(*) as total,
    SUM(montant_total) as montant_total
    FROM reservations 
    WHERE date_reservation >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
    GROUP BY mois
    ORDER BY mois DESC");
$reservations_par_mois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 des chambres les plus réservées
$stmt = $db->query("SELECT c.numero_chambre, c.type_chambre, ct.nom as cite_nom,
                    COUNT(r.id) as nb_reservations,
                    SUM(r.montant_total) as revenu_total
                    FROM chambres c
                    JOIN cites ct ON c.cite_id = ct.id
                    LEFT JOIN reservations r ON c.id = r.chambre_id AND r.statut != 'annulee'
                    GROUP BY c.id
                    ORDER BY nb_reservations DESC
                    LIMIT 5");
$top_chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 des étudiants les plus actifs
$stmt = $db->query("SELECT u.nom, u.prenom, u.email,
                    COUNT(r.id) as nb_reservations,
                    SUM(r.montant_total) as total_depense
                    FROM utilisateurs u
                    LEFT JOIN reservations r ON u.id = r.utilisateur_id
                    WHERE u.role = 'etudiant'
                    GROUP BY u.id
                    ORDER BY nb_reservations DESC
                    LIMIT 5");
$top_etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Taux d'occupation par cité
$stmt = $db->query("SELECT ct.nom, ct.ville,
                    COUNT(c.id) as total_chambres,
                    SUM(CASE WHEN c.disponible = 0 THEN 1 ELSE 0 END) as chambres_occupees,
                    ROUND(SUM(CASE WHEN c.disponible = 0 THEN 1 ELSE 0 END) / COUNT(c.id) * 100, 1) as taux_occupation
                    FROM cites ct
                    LEFT JOIN chambres c ON ct.id = c.cite_id
                    GROUP BY ct.id
                    ORDER BY taux_occupation DESC");
$occupation_cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-file-text"></i> Rapports et Statistiques
                </h2>
                <button class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Période</label>
                    <select class="form-select" name="periode" onchange="this.form.submit()">
                        <option value="semaine" <?php echo $periode == 'semaine' ? 'selected' : ''; ?>>Cette semaine</option>
                        <option value="mois" <?php echo $periode == 'mois' ? 'selected' : ''; ?>>Ce mois</option>
                        <option value="trimestre" <?php echo $periode == 'trimestre' ? 'selected' : ''; ?>>Ce trimestre</option>
                        <option value="annee" <?php echo $periode == 'annee' ? 'selected' : ''; ?>>Cette année</option>
                        <option value="personnalise" <?php echo $periode == 'personnalise' ? 'selected' : ''; ?>>Personnalisé</option>
                    </select>
                </div>
                <?php if ($periode == 'personnalise'): ?>
                <div class="col-md-3">
                    <label class="form-label">Date début</label>
                    <input type="date" class="form-control" name="date_debut" value="<?php echo $date_debut; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date fin</label>
                    <input type="date" class="form-control" name="date_fin" value="<?php echo $date_fin; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Appliquer</button>
                </div>
                <?php endif; ?>
                <div class="col-md-3 ms-auto">
                    <label class="form-label">&nbsp;</label>
                    <a href="export-csv.php" class="btn btn-outline-success w-100">
                        <i class="bi bi-download"></i> Exporter CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cartes statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Réservations</h6>
                    <h3><?php echo $stats['reservations_mois']; ?></h3>
                    <small>Ce mois</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Revenus</h6>
                    <h3><?php echo number_format($stats['revenus_mois'], 0); ?> FCFA</h3>
                    <small>Ce mois</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Taux d'occupation</h6>
                    <h3><?php echo $stats['taux_occupation']; ?>%</h3>
                    <small>Global</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Étudiants actifs</h6>
                    <h3><?php echo $stats['total_etudiants']; ?></h3>
                    <small>Total</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Réservations par mois -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Réservations (12 derniers mois)</h5>
                </div>
                <div class="card-body">
                    <canvas id="reservationsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Taux d'occupation par cité -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Taux d'occupation par cité</h5>
                </div>
                <div class="card-body">
                    <canvas id="occupationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top chambres -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top 5 des chambres les plus réservées</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Chambre</th>
                                    <th>Type</th>
                                    <th>Cité</th>
                                    <th>Réservations</th>
                                    <th>Revenu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_chambres as $chambre): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($chambre['numero_chambre']); ?></td>
                                    <td><?php echo ucfirst($chambre['type_chambre']); ?></td>
                                    <td><?php echo htmlspecialchars($chambre['cite_nom']); ?></td>
                                    <td><?php echo $chambre['nb_reservations']; ?></td>
                                    <td><?php echo number_format($chambre['revenu_total'], 2); ?> FCFA</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top étudiants -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top 5 des étudiants les plus actifs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Email</th>
                                    <th>Réservations</th>
                                    <th>Total dépensé</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_etudiants as $etudiant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($etudiant['email']); ?></td>
                                    <td><?php echo $etudiant['nb_reservations']; ?></td>
                                    <td><?php echo number_format($etudiant['total_depense'] ?? 0, 2); ?> FCFA</td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des réservations
const reservationsData = <?php echo json_encode(array_reverse($reservations_par_mois)); ?>;
const ctx1 = document.getElementById('reservationsChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: reservationsData.map(d => d.mois),
        datasets: [{
            label: 'Nombre de réservations',
            data: reservationsData.map(d => d.total),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        }
    }
});

// Graphique d'occupation
const occupationData = <?php echo json_encode($occupation_cites); ?>;
const ctx2 = document.getElementById('occupationChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: occupationData.map(d => d.nom),
        datasets: [{
            label: 'Taux d\'occupation (%)',
            data: occupationData.map(d => d.taux_occupation),
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>