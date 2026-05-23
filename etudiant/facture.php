<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Vérifier si l'ID de paiement est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('paiements.php');
}

$paiement_id = $_GET['id'];

// Récupérer les informations du paiement
$stmt = $db->prepare("SELECT p.*, r.id as reservation_id, r.date_debut, r.date_fin, r.montant_total,
                      c.numero_chambre, c.type_chambre, ct.nom as cite_nom, ct.adresse, ct.ville, ct.code_postal,
                      u.nom, u.prenom, u.email, u.telephone
                      FROM paiements p
                      JOIN reservations r ON p.reservation_id = r.id
                      JOIN chambres c ON r.chambre_id = c.id
                      JOIN cites ct ON c.cite_id = ct.id
                      JOIN utilisateurs u ON r.utilisateur_id = u.id
                      WHERE p.id = ? AND r.utilisateur_id = ?");
$stmt->execute([$paiement_id, $user_id]);
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$facture) {
    redirect('paiements.php');
}

// Générer un numéro de facture unique
$numero_facture = 'FACT-' . date('Y') . '-' . str_pad($paiement_id, 6, '0', STR_PAD_LEFT);

// Configuration pour l'affichage
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo $numero_facture; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
        }
        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .invoice-footer {
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Imprimer
            </button>
            <a href="paiements.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="invoice-header">
                    <div class="row">
                        <div class="col-6">
                            <h2>FACTURE</h2>
                            <p class="mb-1"><strong>N° <?php echo $numero_facture; ?></strong></p>
                            <p>Date : <?php echo date('d/m/Y', strtotime($facture['date_paiement'])); ?></p>
                        </div>
                        <div class="col-6 text-end">
                            <h4>Résidence Universitaire</h4>
                            <p class="mb-1">123 Rue de l'Université</p>
                            <p class="mb-1">Ngaoundéré</p>
                            <p>SIRET : 123 456 789 00012</p>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <h6>Facturé à :</h6>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($facture['prenom'] . ' ' . $facture['nom']); ?></strong></p>
                        <p class="mb-1"><?php echo htmlspecialchars($facture['email']); ?></p>
                        <?php if ($facture['telephone']): ?>
                            <p class="mb-1">Tél : <?php echo htmlspecialchars($facture['telephone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 text-end">
                        <h6>Réservation :</h6>
                        <p class="mb-1"><strong>N° <?php echo $facture['reservation_id']; ?></strong></p>
                        <p class="mb-1">Transaction : <?php echo htmlspecialchars($facture['transaction_id']); ?></p>
                        <p>Méthode : <?php echo ucfirst($facture['methode_paiement']); ?></p>
                    </div>
                </div>
                
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th>Période</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>Location chambre <?php echo htmlspecialchars($facture['numero_chambre']); ?></strong><br>
                                <small><?php echo htmlspecialchars($facture['cite_nom']); ?> - 
                                <?php echo htmlspecialchars($facture['adresse']); ?>, 
                                <?php echo htmlspecialchars($facture['code_postal']); ?> 
                                <?php echo htmlspecialchars($facture['ville']); ?></small><br>
                                <small>Type : <?php echo ucfirst($facture['type_chambre']); ?></small>
                            </td>
                            <td>
                                Du <?php echo date('d/m/Y', strtotime($facture['date_debut'])); ?><br>
                                Au <?php echo date('d/m/Y', strtotime($facture['date_fin'])); ?>
                            </td>
                            <td class="text-end"><?php echo number_format($facture['montant'], 2); ?> FCFA</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end"><strong>Total</strong></td>
                            <td class="text-end"><strong><?php echo number_format($facture['montant'], 2); ?> FCFA</strong></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="invoice-footer">
                    <div class="row">
                        <div class="col-8">
                            <small>
                                <strong>Conditions de paiement :</strong> Paiement reçu le <?php echo date('d/m/Y', strtotime($facture['date_paiement'])); ?><br>
                                <strong>Statut :</strong> 
                                <span class="badge bg-<?php echo $facture['statut'] == 'complete' ? 'success' : 'warning'; ?>">
                                    <?php echo $facture['statut'] == 'complete' ? 'Payé' : 'En attente'; ?>
                                </span>
                            </small>
                        </div>
                        <div class="col-4 text-end">
                            <small>
                                Facture générée le <?php echo date('d/m/Y'); ?><br>
                                Résidence Universitaire - Tous droits réservés
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>