<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="bi bi-x-circle-fill text-danger display-1"></i>
                    </div>
                    
                    <h2 class="card-title mb-4">Paiement Annulé</h2>
                    
                    <p class="card-text">
                        Votre paiement a été annulé ou n'a pas pu aboutir.<br>
                        Aucun montant n'a été débité.
                    </p>
                    
                    <div class="d-grid gap-2">
                        <a href="../etudiant/paiements.php" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat"></i> Réessayer
                        </a>
                        <a href="../etudiant/mes-reservations.php" class="btn btn-outline-secondary">
                            <i class="bi bi-calendar"></i> Voir mes réservations
                        </a>
                        <a href="../index.php" class="btn btn-link">
                            <i class="bi bi-house"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>