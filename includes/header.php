<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Déterminer le chemin de base pour les liens
$base_path = '/cite-universitaire/';
$current_page = basename($_SERVER['PHP_SELF']);

// Récupérer les notifications non lues si l'utilisateur est connecté
$notifications_count = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications_count = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Application de location et réservation de chambres dans les cités universitaires">
    <meta name="keywords" content="résidence universitaire, location chambre étudiant, cité U, logement étudiant">
    <meta name="author" content="Résidence Universitaire">
    
    <title>Résidence Universitaire - Location de Chambres</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $base_path; ?>assets/images/favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-building"></i> Résidence Univ
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] != 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" 
                           href="<?php echo $base_path; ?>">
                            <i class="bi bi-house"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'chambres') !== false ? 'active' : ''; ?>" 
                           href="<?php echo $base_path; ?>chambres/">
                            <i class="bi bi-search"></i> Chambres
                        </a>
                    </li>
                     <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-speedometer2"></i> Administration
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>admin/dashboard.php">
                                            <i class="bi bi-grid"></i> Tableau de bord
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>admin/gestion-chambres.php">
                                            <i class="bi bi-door-open"></i> Gestion des chambres
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>admin/gestion-reservations.php">
                                            <i class="bi bi-calendar-check"></i> Gestion des réservations
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>admin/gestion-utilisateurs.php">
                                            <i class="bi bi-people"></i> Gestion des utilisateurs
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>admin/gestion-cites.php">
                                            <i class="bi bi-building"></i> Gestion des cités
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>admin/rapports.php">
                                            <i class="bi bi-file-text"></i> Rapports
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="etudiantDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person"></i> Mon Espace
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>etudiant/dashboard.php">
                                            <i class="bi bi-grid"></i> Tableau de bord
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>etudiant/mes-reservations.php">
                                            <i class="bi bi-calendar"></i> Mes réservations
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>etudiant/historique-reservations.php">
                                            <i class="bi bi-clock-history"></i> Historique
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>etudiant/paiements.php">
                                            <i class="bi bi-credit-card"></i> Paiements
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>etudiant/evaluations.php">
                                            <i class="bi bi-star"></i> Évaluations
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $base_path; ?>etudiant/profil.php">
                                            <i class="bi bi-gear"></i> Mon profil
                                        </a>
                                    </li>
                                </ul>
                            </li>
                             <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>contact.php">
                            <i class="bi bi-envelope"></i> Contact
                        </a>
                    </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                   
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-bell"></i>
                                <?php if ($notifications_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $notifications_count; ?>
                                        <span class="visually-hidden">notifications non lues</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                                <h6 class="dropdown-header">Notifications</h6>
                                <?php
                                if ($notifications_count > 0 && isset($_SESSION['role']) && $_SESSION['role'] == 'etudiant') {
                                    $stmt = $db->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? AND lu = 0 ORDER BY date_creation DESC LIMIT 5");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($notifications as $notif) {
                                        echo '<a class="dropdown-item" href="' . $base_path . 'etudiant/notifications.php">';
                                        echo '<small class="text-muted">' . date('d/m/Y H:i', strtotime($notif['date_creation'])) . '</small><br>';
                                        echo htmlspecialchars($notif['message']);
                                        echo '</a>';
                                    }
                                } else {
                                    echo '<p class="dropdown-item text-muted mb-0">Aucune notification</p>';
                                }
                                ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-center" href="<?php echo $base_path; ?>etudiant/notifications.php">
                                    Voir toutes les notifications
                                </a>
                            </div>
                        </li>
                        
                        <!-- Profil utilisateur -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo $base_path . ($_SESSION['role'] == 'admin' ? 'admin/profil.php' : 'etudiant/profil.php'); ?>">
                                        <i class="bi bi-person"></i> Mon profil
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo $base_path; ?>auth/logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Déconnexion
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>auth/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>auth/register.php">
                                <i class="bi bi-person-plus"></i> Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Breadcrumb -->
    <?php if ($current_page != 'index.php'): ?>
    <nav aria-label="breadcrumb" class="bg-light py-2">
        <div class="container">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo $base_path; ?>">Accueil</a></li>
                <?php
                $path = dirname($_SERVER['PHP_SELF']);
                $parts = explode('/', trim($path, '/'));
                
                if (in_array('admin', $parts)) {
                    echo '<li class="breadcrumb-item"><a href="' . $base_path . 'admin/dashboard.php">Administration</a></li>';
                } elseif (in_array('etudiant', $parts)) {
                    echo '<li class="breadcrumb-item"><a href="' . $base_path . 'etudiant/dashboard.php">Espace Étudiant</a></li>';
                } elseif (in_array('chambres', $parts)) {
                    echo '<li class="breadcrumb-item"><a href="' . $base_path . 'chambres/">Chambres</a></li>';
                }
                ?>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php 
                    $titles = [
                        'dashboard.php' => 'Tableau de bord',
                        'gestion-chambres.php' => 'Gestion des chambres',
                        'gestion-reservations.php' => 'Gestion des réservations',
                        'gestion-utilisateurs.php' => 'Gestion des utilisateurs',
                        'mes-reservations.php' => 'Mes réservations',
                        'historique-reservations.php' => 'Historique',
                        'paiements.php' => 'Paiements',
                        'evaluations.php' => 'Évaluations',
                        'notifications.php' => 'Notifications',
                        'profil.php' => 'Mon profil',
                        'details.php' => 'Détails de la chambre',
                        'reserver.php' => 'Réservation'
                    ];
                    echo $titles[$current_page] ?? ucfirst(str_replace('.php', '', $current_page));
                    ?>
                </li>
            </ol>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-0" role="alert">
            <div class="container">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0" role="alert">
            <div class="container">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show m-0" role="alert">
            <div class="container">
                <i class="bi bi-info-circle"></i> <?php echo $_SESSION['info']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>
    
    <main class="py-4">