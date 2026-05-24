    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-white mt-auto">
        <div class="container py-5">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="text-primary">
                        <i class="bi bi-building"></i> Résidence Universitaire
                    </h5>
                    <p class="text-muted">
                        Votre solution de logement étudiant depuis 2025. 
                        Nous proposons des chambres confortables et abordables 
                        dans toutes les grandes villes universitaires de Cameroun.
                    </p>
                    <div class="social-links">
                        <a href="#" class="text-white me-2"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white me-2"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h6>Navigation</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo $base_path; ?>" class="text-muted text-decoration-none">
                                <i class="bi bi-house"></i> Accueil
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo $base_path; ?>chambres/" class="text-muted text-decoration-none">
                                <i class="bi bi-search"></i> Chambres
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo $base_path; ?>contact.php" class="text-muted text-decoration-none">
                                <i class="bi bi-envelope"></i> Contact
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h6>Informations</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="#" class="text-muted text-decoration-none">
                                <i class="bi bi-question-circle"></i> FAQ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-muted text-decoration-none">
                                <i class="bi bi-shield-check"></i> Politique de confidentialité
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-muted text-decoration-none">
                                <i class="bi bi-file-text"></i> Conditions générales
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-muted text-decoration-none">
                                <i class="bi bi-cookie"></i> Gestion des cookies
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h6>Contact</h6>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt"></i>Dang<br>
                            <span class="ms-4">454 Ngaoundéré, Cameroun</span>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-telephone"></i> +237 6 55 76 22 112
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-envelope"></i> adoummahamatzene671@gmail.com
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock"></i> Lun - Ven : 9h - 18h
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="bg-secondary my-4">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        &copy; <?php echo date('Y'); ?> Résidence Universitaire. Tous droits réservés.
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bouton retour en haut -->
    <button id="backToTop" class="btn btn-primary back-to-top" style="display: none;">
        <i class="bi bi-arrow-up"></i>
    </button>
    
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo $base_path; ?>assets/js/main.js"></script>
    
    <!-- Script pour le bouton retour en haut -->
    <script>
        // Afficher/masquer le bouton retour en haut
        window.addEventListener('scroll', function() {
            const backToTop = document.getElementById('backToTop');
            if (window.pageYOffset > 300) {
                backToTop.style.display = 'block';
            } else {
                backToTop.style.display = 'none';
            }
        });
        
        // Retour en haut
        document.getElementById('backToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Activer les tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Activer les popovers Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    
    <style>
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            transition: all 0.3s;
        }
        
        .back-to-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .social-links a {
            transition: color 0.3s;
            font-size: 1.2rem;
        }
        
        .social-links a:hover {
            color: #007bff !important;
        }
        
        footer a:hover {
            color: #007bff !important;
        }
    </style>
</body>
</html>
<script>
// Boutons de montant rapide
document.querySelectorAll('.montant-rapide').forEach(btn => {
    btn.addEventListener('click', function() {
        const montant = parseInt(this.dataset.montant);
        const inputMontant = document.getElementById('montant_input');
        
        if (inputMontant) {
            const max = parseInt(inputMontant.max);
            const min = parseInt(inputMontant.min);
            
            // Ajuster le montant si nécessaire
            let montantFinal = montant;
            if (montantFinal > max) {
                montantFinal = max;
            }
            if (montantFinal < min) {
                montantFinal = min;
            }
            
            // Arrondir au multiple de 500
            montantFinal = Math.round(montantFinal / 500) * 500;
            
            inputMontant.value = montantFinal;
        }
    });
});

// Validation du montant avant soumission
document.getElementById('montantForm')?.addEventListener('submit', function(e) {
    const inputMontant = document.getElementById('montant_input');
    if (inputMontant) {
        let montant = parseInt(inputMontant.value);
        const min = parseInt(inputMontant.min);
        const max = parseInt(inputMontant.max);
        
        // Validation
        if (isNaN(montant) || montant < min) {
            e.preventDefault();
            alert('Le montant minimum est de ' + formatFCFA(min));
            inputMontant.value = min;
            return false;
        }
        
        if (montant > max) {
            e.preventDefault();
            alert('Le montant maximum est de ' + formatFCFA(max));
            inputMontant.value = max;
            return false;
        }
        
        // Arrondir au multiple de 500
        montant = Math.round(montant / 500) * 500;
        inputMontant.value = montant;
    }
});

// Fonction de formatage
function formatFCFA(montant) {
    return new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
}

// Mise à jour en temps réel de la validation
document.getElementById('montant_input')?.addEventListener('input', function() {
    let montant = parseInt(this.value);
    const min = parseInt(this.min);
    const max = parseInt(this.max);
    
    if (montant < min) {
        this.classList.add('is-invalid');
    } else if (montant > max) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});
</script>
<script>
// Boutons de montant rapide
document.querySelectorAll('.montant-rapide').forEach(btn => {
    btn.addEventListener('click', function() {
        const montant = parseInt(this.dataset.montant);
        const inputMontant = document.getElementById('montant_input');
        if (inputMontant) {
            const max = parseInt(inputMontant.max);
            const min = parseInt(inputMontant.min);
            let montantFinal = Math.min(montant, max);
            montantFinal = Math.max(montantFinal, min);
            inputMontant.value = montantFinal;
        }
    });
});

// Validation du formulaire
document.getElementById('montantForm')?.addEventListener('submit', function(e) {
    const inputMontant = document.getElementById('montant_input');
    if (inputMontant) {
        let montant = parseInt(inputMontant.value);
        const min = parseInt(inputMontant.min);
        const max = parseInt(inputMontant.max);
        
        if (isNaN(montant) || montant < min) {
            e.preventDefault();
            alert('Le montant minimum est de ' + formatFCFA(min));
            inputMontant.value = min;
            return false;
        }
        if (montant > max) {
            e.preventDefault();
            alert('Le montant maximum est de ' + formatFCFA(max));
            inputMontant.value = max;
            return false;
        }
    }
});

function formatFCFA(montant) {
    return new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
}
</script>