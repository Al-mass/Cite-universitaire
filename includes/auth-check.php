<?php
/**
 * Middleware de vérification d'authentification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Veuillez vous connecter pour accéder à cette page.";
    header('Location: /cite-universitaire/auth/login.php');
    exit();
}

// Vérifier le rôle si spécifié
if (isset($required_role) && $_SESSION['role'] !== $required_role) {
    $_SESSION['error'] = "Vous n'avez pas les permissions nécessaires pour accéder à cette page.";
    
    if ($_SESSION['role'] === 'admin') {
        header('Location: /cite-universitaire/admin/dashboard.php');
    } else {
        header('Location: /cite-universitaire/etudiant/dashboard.php');
    }
    exit();
}
?>