// Fonctions JavaScript communes

document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialisation des popovers Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Validation des formulaires
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Confirmation de suppression
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
            }
        });
    });
    
    // Calcul automatique du prix total pour les réservations
    const dateDebut = document.getElementById('date_debut');
    const dateFin = document.getElementById('date_fin');
    const prixMensuel = document.getElementById('prix_mensuel');
    const prixTotal = document.getElementById('prix_total');
    
    if (dateDebut && dateFin && prixMensuel && prixTotal) {
        function calculerPrix() {
            if (dateDebut.value && dateFin.value) {
                const debut = new Date(dateDebut.value);
                const fin = new Date(dateFin.value);
                const diffTime = Math.abs(fin - debut);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const mois = diffDays / 30;
                const total = parseFloat(prixMensuel.value) * mois;
                prixTotal.value = total.toFixed(2);
            }
        }
        
        dateDebut.addEventListener('change', calculerPrix);
        dateFin.addEventListener('change', calculerPrix);
    }
    
    // Système de notation par étoiles
    const ratingInputs = document.querySelectorAll('.rating-input');
    const ratingStars = document.querySelectorAll('.rating-star');
    
    ratingStars.forEach((star, index) => {
        star.addEventListener('click', () => {
            const rating = index + 1;
            document.getElementById('note').value = rating;
            
            ratingStars.forEach((s, i) => {
                if (i < rating) {
                    s.classList.add('text-warning');
                    s.classList.remove('text-muted');
                } else {
                    s.classList.remove('text-warning');
                    s.classList.add('text-muted');
                }
            });
        });
    });
});

// Fonction pour afficher les notifications
function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Fonction pour charger plus de résultats (pagination infinie)
let currentPage = 1;
function loadMore(url) {
    currentPage++;
    fetch(`${url}?page=${currentPage}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('results-container').insertAdjacentHTML('beforeend', data);
        })
        .catch(error => console.error('Erreur:', error));
}

// Validation d'email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validation de numéro de téléphone français
function validatePhone(phone) {
    const re = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
    return re.test(phone);
}

// Formatage de date
function formatDate(date) {
    return new Date(date).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Gestion du mode sombre (optionnel)
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDarkMode = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDarkMode);
}

// Vérifier le mode sombre au chargement
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
}
// Prévisualisation d'image avant upload
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Drag and drop pour upload d'image
function initImageDropZone(zoneId, inputId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    
    if (!zone || !input) return;
    
    zone.addEventListener('click', () => input.click());
    
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
    });
    
    zone.addEventListener('dragleave', () => {
        zone.classList.remove('dragover');
    });
    
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            input.files = files;
            
            // Déclencher l'événement change
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        }
    });
}

// Lazy loading des images
document.addEventListener('DOMContentLoaded', function() {
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
});