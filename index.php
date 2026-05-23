<?php
require_once 'includes/functions.php';

// Définir formatFCFA si non définie
if (!function_exists('formatFCFA')) {
    function formatFCFA($montant, $avec_symbole = true) {
        if ($montant === null || $montant === '') {
            $montant = 0;
        }
        $formatted = number_format(floatval($montant), 0, ',', ' ');
        return $avec_symbole ? $formatted . ' FCFA' : $formatted;
    }
}

$database = new Database();
$db = $database->getConnection();

// ==================== STATISTIQUES ====================
$stmt = $db->query("SELECT COUNT(*) FROM cites");
$nb_cites = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM chambres WHERE disponible = 1");
$nb_chambres_disponibles = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM chambres");
$nb_chambres_total = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'etudiant'");
$nb_etudiants = $stmt->fetchColumn();

$stmt = $db->query("SELECT AVG(note) FROM evaluations");
$note_moyenne = round($stmt->fetchColumn() ?? 4.5, 1);

$stmt = $db->query("SELECT COUNT(*) FROM reservations WHERE MONTH(date_reservation) = MONTH(CURRENT_DATE()) AND YEAR(date_reservation) = YEAR(CURRENT_DATE())");
$nb_reservations_mois = $stmt->fetchColumn();

// ==================== CHAMBRES POPULAIRES ====================
$query = "SELECT c.*, ct.nom as cite_nom, ct.ville,
          (SELECT AVG(note) FROM evaluations WHERE chambre_id = c.id) as note_moyenne,
          (SELECT COUNT(*) FROM evaluations WHERE chambre_id = c.id) as nombre_evaluations
          FROM chambres c 
          JOIN cites ct ON c.cite_id = ct.id 
          WHERE c.disponible = 1 
          ORDER BY c.id DESC LIMIT 6";
$chambres_populaires = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ==================== CITÉS UNIVERSITAIRES ====================
$stmt = $db->query("SELECT c.*, 
                    (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id) as nb_chambres,
                    (SELECT COUNT(*) FROM chambres WHERE cite_id = c.id AND disponible = 1) as nb_disponibles
                    FROM cites c ORDER BY c.nom");
$cites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== TÉMOIGNAGES ====================
$query = "SELECT e.*, u.nom, u.prenom, c.numero_chambre, ct.nom as cite_nom
          FROM evaluations e
          JOIN utilisateurs u ON e.utilisateur_id = u.id
          JOIN chambres c ON e.chambre_id = c.id
          JOIN cites ct ON c.cite_id = ct.id
          WHERE e.note >= 4
          ORDER BY e.date_evaluation DESC LIMIT 3";
$temoignages = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<!-- Hero Section Premium -->
<section class="hero-premium">
    <div class="hero-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>
    <div class="container hero-content">
        <div class="row align-items-center min-vh-80">
            <div class="col-lg-7">
                <div class="hero-badge-premium mb-3">
                    <span class="badge-premium">
                        <i class="bi bi-star-fill"></i> Université de Ngaoundéré
                    </span>
                    <span class="badge-premium ms-2">
                        <i class="bi bi-shield-check"></i> Logements certifiés
                    </span>
                </div>
                <h1 class="hero-title-premium">
                    Votre <span class="gradient-text">logement étudiant</span> idéal vous attend
                </h1>
                <p class="hero-subtitle-premium">
                    Découvrez des chambres confortables et abordables dans les meilleures cités universitaires de Ngaoundéré. 
                    Réservez en quelques clics et concentrez-vous sur l'essentiel : vos études.
                </p>
                <div class="hero-actions">
                    <a href="chambres/" class="btn btn-primary-premium btn-lg">
                        <i class="bi bi-search me-2"></i> Explorer les chambres
                        <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                    <a href="auth/register.php" class="btn btn-outline-premium btn-lg">
                        <i class="bi bi-person-plus me-2"></i> Créer un compte
                    </a>
                </div>
                <div class="hero-stats-premium">
                    <div class="stat-item-premium">
                        <div class="stat-icon-circle bg-success-premium">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <span class="stat-number-premium"><?php echo $nb_cites; ?>+</span>
                            <span class="stat-label-premium">Cités</span>
                        </div>
                    </div>
                    <div class="stat-item-premium">
                        <div class="stat-icon-circle bg-primary-premium">
                            <i class="bi bi-door-open"></i>
                        </div>
                        <div>
                            <span class="stat-number-premium"><?php echo $nb_chambres_disponibles; ?>+</span>
                            <span class="stat-label-premium">Chambres</span>
                        </div>
                    </div>
                    <div class="stat-item-premium">
                        <div class="stat-icon-circle bg-warning-premium">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <span class="stat-number-premium"><?php echo $nb_etudiants; ?>+</span>
                            <span class="stat-label-premium">Étudiants</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div class="hero-visual">
                    <div class="hero-image-wrapper">
                        <img src="assets\images\cites\cite_6.jpg" alt="Cité Universitaire" class="hero-image-premium">
                        <div class="hero-image-glow"></div>
                    </div>
                    <div class="floating-card-premium floating-card-1">
                        <div class="d-flex align-items-center gap-3">
                            <div class="floating-icon bg-warning-premium">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div>
                                <span class="fw-bold fs-5"><?php echo $note_moyenne; ?>/5</span>
                                <p class="mb-0 small text-muted">Note moyenne</p>
                            </div>
                        </div>
                    </div>
                    <div class="floating-card-premium floating-card-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="floating-icon bg-success-premium">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div>
                                <span class="fw-bold fs-5"><?php echo $nb_reservations_mois; ?></span>
                                <p class="mb-0 small text-muted">Ce mois</p>
                            </div>
                        </div>
                    </div>
                    <div class="floating-card-premium floating-card-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="floating-icon bg-danger-premium">
                                <i class="bi bi-heart-fill"></i>
                            </div>
                            <div>
                                <span class="fw-bold fs-5">98%</span>
                                <p class="mb-0 small text-muted">Satisfaction</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="hero-wave-premium">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120">
            <path fill="#f8fafc" d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,58.7L1440,64L1440,120L1360,120C1280,120,1120,120,960,120C800,120,640,120,480,120C320,120,160,120,80,120L0,120Z"></path>
        </svg>
    </div>
</section>

<!-- Section Recherche Rapide Premium -->
<section class="search-section">
    <div class="container">
        <div class="search-card-premium">
            <form action="chambres/" method="GET" class="row g-3 align-items-end">
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label-premium"><i class="bi bi-building me-1"></i> Cité Universitaire</label>
                    <select class="form-select-premium" name="cite_id">
                        <option value="">Toutes les cités</option>
                        <?php foreach ($cites as $cite): ?>
                            <option value="<?php echo $cite['id']; ?>">
                                <?php echo htmlspecialchars($cite['nom']); ?> (<?php echo $cite['nb_disponibles']; ?> disp.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label-premium"><i class="bi bi-door-open me-1"></i> Type de chambre</label>
                    <select class="form-select-premium" name="type">
                        <option value="">Tous les types</option>
                        <option value="simple">Simple</option>
                        <option value="double">Double</option>
                        <option value="studio">Studio</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label-premium"><i class="bi bi-cash me-1"></i> Budget maximum</label>
                    <select class="form-select-premium" name="prix_max">
                        <option value="">Tous les prix</option>
                        <option value="20000">20 000 FCFA</option>
                        <option value="30000">30 000 FCFA</option>
                        <option value="40000">40 000 FCFA</option>
                        <option value="50000">50 000 FCFA</option>
                        <option value="60000">60 000 FCFA</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <button type="submit" class="btn btn-search-premium w-100">
                        <i class="bi bi-search me-2"></i> Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Section Statistiques Premium -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid-premium">
            <div class="stat-card-premium">
                <div class="stat-icon-premium bg-green-gradient">
                    <i class="bi bi-building"></i>
                </div>
                <h3 class="stat-number-premium-lg"><?php echo $nb_cites; ?></h3>
                <p class="stat-label-premium-lg">Cités Universitaires</p>
            </div>
            <div class="stat-card-premium">
                <div class="stat-icon-premium bg-blue-gradient">
                    <i class="bi bi-door-open"></i>
                </div>
                <h3 class="stat-number-premium-lg"><?php echo $nb_chambres_disponibles; ?></h3>
                <p class="stat-label-premium-lg">Chambres Disponibles</p>
            </div>
            <div class="stat-card-premium">
                <div class="stat-icon-premium bg-orange-gradient">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="stat-number-premium-lg"><?php echo $nb_etudiants; ?>+</h3>
                <p class="stat-label-premium-lg">Étudiants Logés</p>
            </div>
            <div class="stat-card-premium">
                <div class="stat-icon-premium bg-purple-gradient">
                    <i class="bi bi-star-fill"></i>
                </div>
                <h3 class="stat-number-premium-lg"><?php echo $note_moyenne; ?>/5</h3>
                <p class="stat-label-premium-lg">Satisfaction</p>
            </div>
        </div>
    </div>
</section>

<!-- Section Cités Universitaires Premium -->
<section class="cites-section">
    <div class="container">
        <div class="section-header-premium text-center mb-5">
            <span class="section-badge-premium">Nos emplacements</span>
            <h2 class="section-title-premium">Découvrez nos Résidences</h2>
            <p class="section-subtitle-premium">Des cadres de vie modernes au cœur de Ngaoundéré</p>
        </div>
        <div class="row g-4">
            <?php foreach (array_slice($cites, 0, 4) as $cite): ?>
            <div class="col-md-6 col-lg-3">
                <div class="cite-card-premium">
                    <div class="cite-card-image-premium">
                        <?php if (!empty($cite['image'])): ?>
                            <img src="<?php echo $cite['image']; ?>" alt="<?php echo htmlspecialchars($cite['nom']); ?>">
                        <?php else: ?>
                            <div class="cite-image-placeholder-premium">
                                <i class="bi bi-building"></i>
                            </div>
                        <?php endif; ?>
                        <div class="cite-overlay-premium">
                            <a href="chambres/?cite_id=<?php echo $cite['id']; ?>" class="btn btn-light btn-sm rounded-pill">
                                <i class="bi bi-eye me-1"></i> Découvrir
                            </a>
                        </div>
                        <div class="cite-badge-premium">
                            <?php echo $cite['nb_disponibles']; ?> disponibles
                        </div>
                    </div>
                    <div class="cite-card-body-premium">
                        <h5 class="cite-title-premium"><?php echo htmlspecialchars($cite['nom']); ?></h5>
                        <p class="cite-address-premium">
                            <i class="bi bi-geo-alt-fill text-danger"></i> <?php echo htmlspecialchars($cite['ville'] ?? 'Ngaoundéré'); ?>
                        </p>
                        <div class="cite-info-premium">
                            <span><i class="bi bi-door-open me-1"></i> <?php echo $cite['nb_chambres']; ?> chambres</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section Chambres Populaires-->
<section class="rooms-section">
    <div class="container">
        <div class="section-header-premium text-center mb-5">
            <span class="section-badge-premium">Les plus demandées</span>
            <h2 class="section-title-premium">Chambres Populaires</h2>
            <p class="section-subtitle-premium">Les logements préférés de nos étudiants</p>
        </div>
        <div class="row g-4">
            <?php foreach ($chambres_populaires as $chambre): ?>
            <div class="col-md-6 col-lg-4">
                <div class="room-card-premium">
                    <div class="room-card-image-premium">
                        <?php if (!empty($chambre['image'])): ?>
                            <img src="<?php echo $chambre['image']; ?>" alt="Chambre">
                        <?php else: ?>
                            <div class="room-image-placeholder-premium">
                                <i class="bi bi-door-open"></i>
                            </div>
                        <?php endif; ?>
                        <div class="room-badge-premium <?php echo $chambre['type_chambre']; ?>">
                            <?php echo ucfirst($chambre['type_chambre']); ?>
                        </div>
                        <?php if ($chambre['note_moyenne']): ?>
                        <div class="room-rating-premium">
                            <i class="bi bi-star-fill"></i> <?php echo round($chambre['note_moyenne'], 1); ?>
                        </div>
                        <?php endif; ?>
                        <div class="room-price-tag-premium">
                            <?php echo formatFCFA($chambre['prix_mensuel']); ?> <small>/mois</small>
                        </div>
                    </div>
                    <div class="room-card-body-premium">
                        <h5 class="room-title-premium">Chambre <?php echo htmlspecialchars($chambre['numero_chambre']); ?></h5>
                        <p class="room-location-premium">
                            <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($chambre['cite_nom']); ?>
                        </p>
                        <div class="room-features-premium">
                            <?php if (!empty($chambre['equipements'])): 
                                foreach (array_slice(explode(',', $chambre['equipements']), 0, 3) as $eq): ?>
                                <span class="feature-tag-premium"><?php echo trim($eq); ?></span>
                            <?php endforeach; endif; ?>
                        </div>
                        <a href="chambres/details.php?id=<?php echo $chambre['id']; ?>" class="btn btn-outline-premium-2 w-100">
                            Voir les détails <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="chambres/" class="btn btn-primary-premium btn-lg">
                <i class="bi bi-search me-2"></i> Voir toutes les chambres
            </a>
        </div>
    </div>
</section>

<!-- Section Témoignages Premium -->
<?php if (!empty($temoignages)): ?>
<section class="testimonials-section">
    <div class="container">
        <div class="section-header-premium text-center mb-5">
            <span class="section-badge-premium">Avis étudiants</span>
            <h2 class="section-title-premium">Ce que disent nos résidents</h2>
            <p class="section-subtitle-premium">La satisfaction de nos étudiants est notre priorité</p>
        </div>
        <div class="row g-4">
            <?php foreach ($temoignages as $tem): ?>
            <div class="col-md-4">
                <div class="testimonial-card-premium">
                    <div class="testimonial-quote">"</div>
                    <p class="testimonial-text-premium"><?php echo htmlspecialchars(truncate($tem['commentaire'], 120)); ?></p>
                    <div class="testimonial-rating-premium mb-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?php echo $i <= $tem['note'] ? '-fill' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="testimonial-author-premium">
                        <div class="author-avatar-premium">
                            <?php echo strtoupper(substr($tem['prenom'], 0, 1) . substr($tem['nom'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($tem['prenom'] . ' ' . substr($tem['nom'], 0, 1) . '.'); ?></h6>
                            <small class="text-muted">Chambre <?php echo htmlspecialchars($tem['numero_chambre']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section Comment ça marche Premium -->
<section class="how-it-works-section">
    <div class="container">
        <div class="section-header-premium text-center mb-5">
            <span class="section-badge-premium">Simple et rapide</span>
            <h2 class="section-title-premium">Comment ça marche ?</h2>
            <p class="section-subtitle-premium">Trois étapes simples pour trouver votre logement</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-card-premium">
                    <div class="step-number-premium">01</div>
                    <div class="step-icon-premium bg-blue-gradient">
                        <i class="bi bi-search"></i>
                    </div>
                    <h4>Explorez</h4>
                    <p>Parcourez notre catalogue de chambres et trouvez celle qui correspond parfaitement à vos besoins.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card-premium">
                    <div class="step-number-premium">02</div>
                    <div class="step-icon-premium bg-green-gradient">
                        <i class="bi bi-calendar-plus"></i>
                    </div>
                    <h4>Réservez</h4>
                    <p>Réservez en ligne en quelques clics avec un paiement 100% sécurisé en FCFA.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card-premium">
                    <div class="step-number-premium">03</div>
                    <div class="step-icon-premium bg-orange-gradient">
                        <i class="bi bi-house-heart"></i>
                    </div>
                    <h4>Emménagez</h4>
                    <p>Installez-vous dans votre nouveau chez-vous et profitez pleinement de votre vie étudiante.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Paiements Premium -->
<section class="payment-section">
    <div class="container">
        <div class="section-header-premium text-center mb-5">
            <span class="section-badge-premium">Paiement sécurisé</span>
            <h2 class="section-title-premium">Méthodes de Paiement</h2>
            <p class="section-subtitle-premium"> Le moyen de paiement </p>
        </div>
        <div class="">
            <div class="container m-5 px-0">
                <div class="payment-card-premium">
                    <div class="payment-icon-premium bg-blue-gradient">
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                    <h5>Carte credit</h5>
                    <span class="payment-badge-premium">Sécurisé</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section CTA Premium -->
<section class="cta-section-premium">
    <div class="container">
        <div class="cta-card-premium">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2>Prêt à trouver votre logement ?</h2>
                    <p class="mb-0">Rejoignez la communauté d'étudiants qui nous font confiance</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="auth/register.php" class="btn btn-light-premium btn-lg">
                        <i class="bi bi-person-plus me-2"></i> Créer un compte gratuit
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* ========== VARIABLES ========== */
:root {
    --primary: #007A5E;
    --primary-dark: #005a45;
    --secondary: #1a1a2e;
    --accent: #FCD116;
    --danger: #CE1126;
    --surface: #f8fafc;
    --text: #1a202c;
    --text-muted: #718096;
    --radius: 20px;
    --radius-sm: 12px;
    --shadow: 0 4px 24px rgba(0,0,0,0.06);
    --shadow-lg: 0 12px 40px rgba(0,0,0,0.1);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ========== HERO PREMIUM ========== */
.hero-premium {
    position: relative;
    min-height: 90vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #0a1628 0%, #1a2a4a 50%, #0d1f35 100%);
    overflow: hidden;
    margin-top: -76px;
    padding-top: 76px;
}
.hero-bg-shapes .shape {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
}
.shape-1 { width: 600px; height: 600px; background: #007A5E; top: -200px; right: -100px; }
.shape-2 { width: 400px; height: 400px; background: #FCD116; bottom: -100px; left: -100px; }
.shape-3 { width: 300px; height: 300px; background: #CE1126; top: 50%; left: 30%; }
.shape-4 { width: 250px; height: 250px; background: #4299e1; top: 20%; right: 40%; }
.hero-content { position: relative; z-index: 2; }
.min-vh-80 { min-height: 80vh; }
.badge-premium {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 13px;
}
.hero-title-premium {
    color: white;
    font-size: 4rem;
    font-weight: 800;
    line-height: 1.15;
    margin: 24px 0;
}
.gradient-text {
    background: linear-gradient(135deg, #FCD116 0%, #FF9966 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-subtitle-premium {
    color: rgba(255,255,255,0.75);
    font-size: 1.15rem;
    line-height: 1.7;
    margin-bottom: 32px;
    max-width: 540px;
}
.hero-actions { display: flex; gap: 16px; margin-bottom: 40px; }
.btn-primary-premium {
    background: linear-gradient(135deg, #007A5E, #00a67e);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 50px;
    font-weight: 600;
    transition: var(--transition);
    box-shadow: 0 8px 24px rgba(0,122,94,0.3);
}
.btn-primary-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(0,122,94,0.4);
    color: white;
}
.btn-outline-premium {
    background: transparent;
    color: white;
    border: 2px solid rgba(255,255,255,0.4);
    padding: 14px 28px;
    border-radius: 50px;
    font-weight: 600;
    transition: var(--transition);
}
.btn-outline-premium:hover {
    background: rgba(255,255,255,0.1);
    border-color: white;
    color: white;
}
.hero-stats-premium { display: flex; gap: 40px; }
.stat-item-premium { display: flex; align-items: center; gap: 14px; }
.stat-icon-circle {
    width: 50px; height: 50px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 22px;
}
.bg-success-premium { background: linear-gradient(135deg, #48bb78, #38a169); }
.bg-primary-premium { background: linear-gradient(135deg, #4299e1, #3182ce); }
.bg-warning-premium { background: linear-gradient(135deg, #FCD116, #e6b800); color: #000 !important; }
.bg-danger-premium { background: linear-gradient(135deg, #f56565, #e53e3e); }
.stat-number-premium { font-size: 26px; font-weight: 700; color: white; display: block; line-height: 1.2; }
.stat-label-premium { font-size: 13px; color: rgba(255,255,255,0.6); }

/* Hero Visual */
.hero-visual { position: relative; }
.hero-image-wrapper { position: relative; }
.hero-image-premium {
    width: 100%; border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}
.hero-image-glow {
    position: absolute; inset: -20px;
    background: radial-gradient(circle, rgba(0,122,94,0.3) 0%, transparent 70%);
    border-radius: 30px; z-index: -1;
}
.floating-card-premium {
    position: absolute;
    background: white;
    border-radius: 16px;
    padding: 16px 20px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.2);
    animation: floatAnimation 3s ease-in-out infinite;
}
.floating-card-1 { bottom: 40px; left: -30px; animation-delay: 0s; }
.floating-card-2 { top: 30px; right: -20px; animation-delay: 1s; }
.floating-card-3 { bottom: 120px; right: -40px; animation-delay: 2s; }
.floating-icon {
    width: 45px; height: 45px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 20px;
}
@keyframes floatAnimation {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.hero-wave-premium {
    position: absolute; bottom: 0; left: 0; width: 100%; line-height: 0;
}

/* ========== SEARCH SECTION ========== */
.search-section { margin-top: -40px; position: relative; z-index: 10; }
.search-card-premium {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.1);
}
.form-label-premium { font-weight: 600; color: var(--text); font-size: 14px; margin-bottom: 8px; }
.form-select-premium {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 15px;
    transition: var(--transition);
}
.form-select-premium:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(0,122,94,0.1);
}
.btn-search-premium {
    background: linear-gradient(135deg, #007A5E, #00a67e);
    color: white;
    border: none;
    padding: 14px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    transition: var(--transition);
}
.btn-search-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,122,94,0.3);
}

/* ========== STATS SECTION ========== */
.stats-section { padding: 80px 0; background: var(--surface); }
.stats-grid-premium {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
}
.stat-card-premium {
    background: white;
    border-radius: var(--radius);
    padding: 36px 24px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: var(--transition);
}
.stat-card-premium:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}
.stat-icon-premium {
    width: 70px; height: 70px;
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    color: white; font-size: 30px;
}
.bg-green-gradient { background: linear-gradient(135deg, #48bb78, #38a169); }
.bg-blue-gradient { background: linear-gradient(135deg, #667eea, #764ba2); }
.bg-orange-gradient { background: linear-gradient(135deg, #f6ad55, #ed8936); }
.bg-purple-gradient { background: linear-gradient(135deg, #9f7aea, #805ad5); }
.stat-number-premium-lg { font-size: 2.8rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.stat-label-premium-lg { color: var(--text-muted); font-size: 1rem; font-weight: 500; }

/* ========== CITES SECTION ========== */
.cites-section { padding: 80px 0; }
.section-header-premium { max-width: 600px; margin: 0 auto; }
.section-badge-premium {
    display: inline-block;
    background: #e8f5e9;
    color: var(--primary);
    padding: 6px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
}
.section-title-premium { font-size: 2.5rem; font-weight: 800; color: var(--text); margin-bottom: 12px; }
.section-subtitle-premium { color: var(--text-muted); font-size: 1.1rem; }

.cite-card-premium {
    background: white;
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: var(--transition);
}
.cite-card-premium:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}
.cite-card-image-premium {
    position: relative;
    height: 220px;
    overflow: hidden;
}
.cite-card-image-premium img {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform 0.5s ease;
}
.cite-card-premium:hover .cite-card-image-premium img { transform: scale(1.08); }
.cite-image-placeholder-premium {
    width: 100%; height: 100%;
    background: linear-gradient(135deg, #007A5E, #006400);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 50px;
}
.cite-overlay-premium {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
    padding: 20px; padding-top: 40px;
    opacity: 0; transition: var(--transition);
}
.cite-card-premium:hover .cite-overlay-premium { opacity: 1; }
.cite-badge-premium {
    position: absolute; top: 16px; right: 16px;
    background: rgba(255,255,255,0.95);
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
}
.cite-card-body-premium { padding: 20px; }
.cite-title-premium { font-weight: 700; margin-bottom: 6px; }
.cite-address-premium { color: var(--text-muted); font-size: 14px; margin-bottom: 12px; }
.cite-info-premium { font-size: 13px; color: var(--text-muted); }

/* ========== ROOMS SECTION ========== */
.rooms-section { padding: 80px 0; background: var(--surface); }

.room-card-premium {
    background: white;
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: var(--transition);
}
.room-card-premium:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}
.room-card-image-premium {
    position: relative;
    height: 200px;
    overflow: hidden;
}
.room-card-image-premium img {
    width: 100%; height: 100%; object-fit: cover;
}
.room-image-placeholder-premium {
    width: 100%; height: 100%;
    background: #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    color: #a0aec0; font-size: 50px;
}
.room-badge-premium {
    position: absolute; top: 14px; left: 14px;
    padding: 6px 14px; border-radius: 50px;
    color: white; font-size: 12px; font-weight: 600;
}
.room-badge-premium.simple { background: #4299e1; }
.room-badge-premium.double { background: #667eea; }
.room-badge-premium.studio { background: #ed8936; }
.room-badge-premium.moderne { background: #9f7aea; }
.room-rating-premium {
    position: absolute; top: 14px; right: 14px;
    background: rgba(255,255,255,0.95);
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 13px; font-weight: 600;
}
.room-rating-premium i { color: #FCD116; }
.room-price-tag-premium {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    padding: 30px 16px 12px;
    color: white; font-size: 20px; font-weight: 700;
}
.room-price-tag-premium small { font-size: 13px; font-weight: 400; opacity: 0.8; }
.room-card-body-premium { padding: 20px; }
.room-title-premium { font-weight: 700; margin-bottom: 8px; }
.room-location-premium { color: var(--text-muted); font-size: 14px; margin-bottom: 12px; }
.room-features-premium { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
.feature-tag-premium {
    background: #f0fdf4; color: var(--primary);
    padding: 4px 12px; border-radius: 50px;
    font-size: 12px; font-weight: 500;
}
.btn-outline-premium-2 {
    border: 2px solid #e2e8f0;
    color: var(--text);
    padding: 10px;
    border-radius: 12px;
    font-weight: 600;
    transition: var(--transition);
}
.btn-outline-premium-2:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* ========== TESTIMONIALS ========== */
.testimonials-section { padding: 80px 0; }

.testimonial-card-premium {
    background: white;
    border-radius: var(--radius);
    padding: 30px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
}
.testimonial-card-premium:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}
.testimonial-quote {
    font-size: 80px;
    color: var(--primary);
    opacity: 0.15;
    position: absolute;
    top: 10px; right: 20px;
    font-family: serif;
    line-height: 1;
}
.testimonial-text-premium {
    color: var(--text);
    font-style: italic;
    line-height: 1.7;
    margin-bottom: 16px;
}
.testimonial-rating-premium i { color: #FCD116; font-size: 16px; }
.testimonial-author-premium { display: flex; align-items: center; gap: 12px; }
.author-avatar-premium {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #007A5E, #00a67e);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
}

/* ========== HOW IT WORKS ========== */
.how-it-works-section { padding: 80px 0; background: var(--surface); }

.step-card-premium {
    background: white;
    border-radius: var(--radius);
    padding: 40px 24px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
}
.step-card-premium:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}
.step-number-premium {
    position: absolute; top: -20px; left: 50%; transform: translateX(-50%);
    width: 40px; height: 40px;
    background: var(--secondary);
    color: white;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 16px;
}
.step-icon-premium {
    width: 80px; height: 80px;
    border-radius: 24px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
    color: white; font-size: 36px;
}
.step-card-premium h4 { font-weight: 700; margin-bottom: 12px; }
.step-card-premium p { color: var(--text-muted); margin-bottom: 0; }

/* ========== PAYMENT SECTION ========== */
.payment-section { padding: 80px 0; }

.payment-card-premium {
    background: white;
    border-radius: var(--radius);
    padding: 36px 20px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
    border: 2px solid transparent;
}
.payment-card-premium:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.payment-icon-premium {
    width: 72px; height: 72px;
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    color: white; font-size: 32px;
}
.payment-card-premium h5 { font-weight: 700; margin-bottom: 6px; }
.payment-card-premium p { color: var(--text-muted); margin-bottom: 12px; }
.payment-badge-premium {
    background: #e8f5e9; color: var(--primary);
    padding: 4px 14px; border-radius: 50px;
    font-size: 12px; font-weight: 600;
}

/* ========== CTA SECTION ========== */
.cta-section-premium {
    padding: 80px 0;
    background: linear-gradient(135deg, #0a1628, #1a2a4a);
}
.cta-card-premium {
    background: linear-gradient(135deg, #007A5E, #00a67e);
    border-radius: var(--radius);
    padding: 48px 40px;
    color: white;
}
.cta-card-premium h2 { font-weight: 800; margin-bottom: 8px; }
.cta-card-premium p { opacity: 0.9; }
.btn-light-premium {
    background: white; color: var(--primary);
    border: none; padding: 14px 32px;
    border-radius: 50px; font-weight: 700;
    transition: var(--transition);
}
.btn-light-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

/* ========== RESPONSIVE ========== */
@media (max-width: 1200px) {
    .stats-grid-premium { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 992px) {
    .hero-title-premium { font-size: 2.8rem; }
    .hero-premium { min-height: auto; padding: 120px 0 80px; }
    .min-vh-80 { min-height: auto; }
    .floating-card-premium { display: none; }
}
@media (max-width: 768px) {
    .hero-title-premium { font-size: 2.2rem; }
    .hero-stats-premium { flex-wrap: wrap; gap: 20px; }
    .section-title-premium { font-size: 2rem; }
    .stats-grid-premium { grid-template-columns: 1fr; }
    .cta-card-premium { text-align: center; padding: 30px 20px; }
}
</style>

<?php include 'includes/footer.php'; ?>