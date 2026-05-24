<?php
/**
 * Fichier de fonctions globales pour l'application
 */

session_start();
require_once __DIR__ . '/../config/database.php';

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

/**
 * Vérifie si l'utilisateur est un étudiant
 * @return bool
 */
function isEtudiant() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'etudiant';
}

/**
 * Redirige vers une URL
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Récupère toutes les chambres disponibles avec filtres optionnels
 * @param array $filtres
 * @return array
 */
function getChambresDisponibles($filtres = []) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT c.*, ct.nom as cite_nom, ct.ville, ct.adresse,
              (SELECT AVG(note) FROM evaluations WHERE chambre_id = c.id) as note_moyenne,
              (SELECT COUNT(*) FROM evaluations WHERE chambre_id = c.id) as nombre_evaluations
              FROM chambres c 
              JOIN cites ct ON c.cite_id = ct.id 
              WHERE c.disponible = 1";
    
    $params = [];
    
    if (!empty($filtres['ville'])) {
        $query .= " AND ct.ville LIKE :ville";
        $params[':ville'] = '%' . $filtres['ville'] . '%';
    }
    
    if (!empty($filtres['type'])) {
        $query .= " AND c.type_chambre = :type";
        $params[':type'] = $filtres['type'];
    }
    
    if (!empty($filtres['prix_min'])) {
        $query .= " AND c.prix_mensuel >= :prix_min";
        $params[':prix_min'] = $filtres['prix_min'];
    }
    
    if (!empty($filtres['prix_max'])) {
        $query .= " AND c.prix_mensuel <= :prix_max";
        $params[':prix_max'] = $filtres['prix_max'];
    }
    
    if (!empty($filtres['capacite'])) {
        $query .= " AND c.capacite >= :capacite";
        $params[':capacite'] = $filtres['capacite'];
    }
    
    if (!empty($filtres['cite_id'])) {
        $query .= " AND c.cite_id = :cite_id";
        $params[':cite_id'] = $filtres['cite_id'];
    }
    
    $query .= " ORDER BY ct.nom, c.numero_chambre";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails d'une chambre par son ID
 * @param int $id
 * @return array|false
 */
function getChambreById($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT c.*, ct.nom as cite_nom, ct.ville, ct.adresse, ct.code_postal, ct.description as cite_description,
              (SELECT AVG(note) FROM evaluations WHERE chambre_id = c.id) as note_moyenne,
              (SELECT COUNT(*) FROM evaluations WHERE chambre_id = c.id) as nombre_evaluations
              FROM chambres c 
              JOIN cites ct ON c.cite_id = ct.id 
              WHERE c.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les évaluations d'une chambre
 * @param int $chambre_id
 * @return array
 */
function getEvaluationsChambre($chambre_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT e.*, u.prenom, u.nom 
              FROM evaluations e 
              JOIN utilisateurs u ON e.utilisateur_id = u.id 
              WHERE e.chambre_id = ? 
              ORDER BY e.date_evaluation DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$chambre_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si une chambre est disponible pour une période donnée
 * @param int $chambre_id
 * @param string $date_debut
 * @param string $date_fin
 * @param int $exclude_reservation_id
 * @return bool
 */
function isChambreDisponible($chambre_id, $date_debut, $date_fin, $exclude_reservation_id = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) FROM reservations 
              WHERE chambre_id = ? 
              AND statut IN ('en_attente', 'confirmee')
              AND ((date_debut <= ? AND date_fin >= ?) 
                   OR (date_debut <= ? AND date_fin >= ?)
                   OR (date_debut >= ? AND date_fin <= ?))";
    
    $params = [$chambre_id, $date_debut, $date_debut, $date_fin, $date_fin, $date_debut, $date_fin];
    
    if ($exclude_reservation_id) {
        $query .= " AND id != ?";
        $params[] = $exclude_reservation_id;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}

/**
 * Calcule le prix total d'une réservation
 * @param string $date_debut
 * @param string $date_fin
 * @param float $prix_mensuel
 * @return float
 */
function calculerPrixTotal($date_debut, $date_fin, $prix_mensuel) {
    $debut = new DateTime($date_debut);
    $fin = new DateTime($date_fin);
    $interval = $debut->diff($fin);
    
    $mois = $interval->m + ($interval->y * 12);
    $jours = $interval->d;
    
    // Calcul proportionnel
    $total = ($prix_mensuel * $mois) + ($prix_mensuel * ($jours / 30));
    
    return round($total, 2);
}

/**
 * Envoie une notification à un utilisateur
 * @param int $user_id
 * @param string $type
 * @param string $message
 * @return bool
 */
function envoyerNotification($user_id, $type, $message) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO notifications (utilisateur_id, type, message) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    return $stmt->execute([$user_id, $type, $message]);
}

/**
 * Envoie un email de notification
 * @param string $to
 * @param string $subject
 * @param string $message
 * @return bool
 */
function envoyerEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Residence Universitaire <noreply@residence-univ.com>' . "\r\n";
    
    $template = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Résidence Universitaire</h2>
            </div>
            <div class='content'>
                $message
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Résidence Universitaire. Tous droits réservés.</p>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return mail($to, $subject, $template, $headers);
}

/**
 * Récupère les notifications non lues d'un utilisateur
 * @param int $user_id
 * @return array
 */
function getNotificationsNonLues($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM notifications 
              WHERE utilisateur_id = ? AND lu = 0 
              ORDER BY date_creation DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marque une notification comme lue
 * @param int $notification_id
 * @param int $user_id
 * @return bool
 */
function marquerNotificationLue($notification_id, $user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?";
    $stmt = $db->prepare($query);
    return $stmt->execute([$notification_id, $user_id]);
}

/**
 * Récupère les statistiques globales pour l'admin
 * @return array
 */
function getStatistiquesGlobales() {
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [];
    
    // Nombre total de chambres
    $stmt = $db->query("SELECT COUNT(*) as total FROM chambres");
    $stats['total_chambres'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Chambres disponibles
    $stmt = $db->query("SELECT COUNT(*) as total FROM chambres WHERE disponible = 1");
    $stats['chambres_disponibles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre d'étudiants
    $stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'etudiant'");
    $stats['total_etudiants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Réservations du mois
    $stmt = $db->query("SELECT COUNT(*) as total FROM reservations 
                       WHERE MONTH(date_reservation) = MONTH(CURRENT_DATE()) 
                       AND YEAR(date_reservation) = YEAR(CURRENT_DATE())");
    $stats['reservations_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Revenus du mois
    $stmt = $db->query("SELECT SUM(montant) as total FROM paiements 
                       WHERE statut = 'complete' 
                       AND MONTH(date_paiement) = MONTH(CURRENT_DATE()) 
                       AND YEAR(date_paiement) = YEAR(CURRENT_DATE())");
    $stats['revenus_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Taux d'occupation
    $stmt = $db->query("SELECT 
                        (SELECT COUNT(*) FROM chambres WHERE disponible = 0) as chambres_occupees,
                        (SELECT COUNT(*) FROM chambres) as total_chambres");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['taux_occupation'] = $result['total_chambres'] > 0 
        ? round(($result['chambres_occupees'] / $result['total_chambres']) * 100, 1) 
        : 0;
    
    return $stats;
}

/**
 * Récupère les statistiques d'un étudiant
 * @param int $user_id
 * @return array
 */
function getStatistiquesEtudiant($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [];
    
    // Réservations actives
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations 
                         WHERE utilisateur_id = ? AND statut IN ('en_attente', 'confirmee')");
    $stmt->execute([$user_id]);
    $stats['reservations_actives'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Réservations terminées
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations 
                         WHERE utilisateur_id = ? AND statut = 'terminee'");
    $stmt->execute([$user_id]);
    $stats['reservations_terminees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total dépensé
    $stmt = $db->prepare("SELECT SUM(p.montant) as total FROM paiements p
                         JOIN reservations r ON p.reservation_id = r.id
                         WHERE r.utilisateur_id = ? AND p.statut = 'complete'");
    $stmt->execute([$user_id]);
    $stats['total_depense'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Note moyenne donnée
    $stmt = $db->prepare("SELECT AVG(note) as moyenne FROM evaluations WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $stats['note_moyenne'] = round($stmt->fetch(PDO::FETCH_ASSOC)['moyenne'] ?? 0, 1);
    
    return $stats;
}

/**
 * Génère un slug à partir d'une chaîne
 * @param string $string
 * @return string
 */
function slugify($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Tronque un texte à une longueur donnée
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Formate une date en français
 * @param string $date
 * @param bool $avec_heure
 * @return string
 */
function formatDate($date, $avec_heure = false) {
    $timestamp = strtotime($date);
    
    $mois = [
        '01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril',
        '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août',
        '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'
    ];
    
    $jour = date('d', $timestamp);
    $mois_num = date('m', $timestamp);
    $annee = date('Y', $timestamp);
    
    $date_formatee = $jour . ' ' . $mois[$mois_num] . ' ' . $annee;
    
    if ($avec_heure) {
        $date_formatee .= ' à ' . date('H:i', $timestamp);
    }
    
    return $date_formatee;
}

/**
 * Génère un mot de passe aléatoire
 * @param int $length
 * @return string
 */
function genererMotDePasse($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Valide une adresse email
 * @param string $email
 * @return bool
 */
function validerEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone français
 * @param string $telephone
 * @return bool
 */
function validerTelephone($telephone) {
    $pattern = '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/';
    return preg_match($pattern, $telephone);
}

/**
 * Nettoie les entrées utilisateur
 * @param string $input
 * @return string
 */
function cleanInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Vérifie si une réservation peut être modifiée
 * @param array $reservation
 * @return bool
 */
function peutModifierReservation($reservation) {
    // Ne peut pas modifier si la réservation est annulée ou terminée
    if (in_array($reservation['statut'], ['annulee', 'terminee'])) {
        return false;
    }
    
    // Ne peut pas modifier si la date de début est passée
    $date_debut = new DateTime($reservation['date_debut']);
    $aujourdhui = new DateTime();
    
    return $date_debut > $aujourdhui;
}

/**
 * Récupère le statut de paiement d'une réservation
 * @param int $reservation_id
 * @return array
 */
function getStatutPaiement($reservation_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT 
              (SELECT montant_total FROM reservations WHERE id = ?) as montant_total,
              COALESCE((SELECT SUM(montant) FROM paiements WHERE reservation_id = ? AND statut = 'complete'), 0) as montant_paye";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$reservation_id, $reservation_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['reste_a_payer'] = $result['montant_total'] - $result['montant_paye'];
    $result['est_paye'] = $result['reste_a_payer'] <= 0;
    $result['pourcentage_paye'] = $result['montant_total'] > 0 
        ? round(($result['montant_paye'] / $result['montant_total']) * 100) 
        : 0;
    
    return $result;
}

/**
 * Enregistre une action dans les logs
 * @param string $action
 * @param int $user_id
 * @param string $details
 */
function logAction($action, $user_id = null, $details = '') {
    $log_file = __DIR__ . '/../logs/actions.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . ' | ';
    $log_entry .= 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . ' | ';
    $log_entry .= 'User: ' . ($user_id ?? 'Guest') . ' | ';
    $log_entry .= 'Action: ' . $action . ' | ';
    $log_entry .= 'Details: ' . $details . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Récupère les cités universitaires
 * @return array
 */
function getCites() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM cites ORDER BY nom";
    $stmt = $db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère une cité par son ID
 * @param int $id
 * @return array|false
 */
function getCiteById($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM cites WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les types de chambres disponibles
 * @return array
 */
function getTypesChambres() {
    return [
        'simple' => 'Chambre Simple',
        'double' => 'Chambre Double',
        'studio' => 'Studio'
    ];
}

/**
 * Récupère les statuts de réservation
 * @return array
 */
function getStatutsReservation() {
    return [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'annulee' => 'Annulée',
        'terminee' => 'Terminée'
    ];
}

/**
 * Récupère les méthodes de paiement
 * @return array
 */
function getMethodesPaiement() {
    return [
        'carte_credit' => 'Carte de crédit',
        'ORA' => 'PayPal',
        'virement' => 'Virement bancaire'
    ];
}

/**
 * Récupère les quartiers de Ngaoundéré
 * @return array
 */
function getQuartiersNgaoundere() {
    return [
        'Dang',
        'Wakwa',
        'Mardock',
        'Campus',
        'Baladji',
        'Bamyanga',
        'Sabongari',
        'Tchabbal',
        'Poumpoumré',
        'Djitangui',
        'Gada',
        'Mbideng',
        'Madagascar',
        'Carrefour Mairie',
        'Quartier Administratif'
    ];
}

/**
 * Fonction d'upload d'image pour les cités
 * @param array $file
 * @return array
 */
function uploadImageCite($file) {
    $result = [
        'success' => false,
        'message' => '',
        'path' => ''
    ];
    
    // Vérifier s'il y a une erreur
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = getUploadErrorMessage($file['error']);
        return $result;
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $result['message'] = "L'image ne doit pas dépasser 5MB";
        return $result;
    }
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        $result['message'] = "Format d'image non autorisé. Utilisez JPG, PNG, GIF ou WEBP";
        return $result;
    }
    
    // Vérifier l'extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed_exts)) {
        $result['message'] = "Extension non autorisée";
        return $result;
    }
    
    // Créer le nom du fichier
    $new_filename = 'cite_' . uniqid() . '.' . $ext;
    $upload_dir = __DIR__ . '/../assets/images/cites/';
    $relative_path = 'assets/images/cites/' . $new_filename;
    $full_path = $upload_dir . $new_filename;
    
    // Créer le dossier si nécessaire
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        $result['success'] = true;
        $result['message'] = "Image uploadée avec succès";
        $result['path'] = $relative_path;
    } else {
        $result['message'] = "Erreur lors de l'enregistrement de l'image";
    }
    
    return $result;
}

/**
 * Retourne le message d'erreur correspondant au code d'upload
 * @param int $code
 * @return string
 */
function getUploadErrorMessage($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par PHP',
        UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
        UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement uploadé',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été uploadé',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
        UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
        UPLOAD_ERR_EXTENSION  => 'Une extension PHP a arrêté l\'upload'
    ];
    
    return isset($errors[$code]) ? $errors[$code] : 'Erreur inconnue lors de l\'upload';
}

/**
 * Fonction d'upload d'image pour les chambres
 * @param array $file
 * @return array
 */
function uploadImageChambre($file) {
    $result = [
        'success' => false,
        'message' => '',
        'path' => ''
    ];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = getUploadErrorMessage($file['error']);
        return $result;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        $result['message'] = "L'image ne doit pas dépasser 5MB";
        return $result;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        $result['message'] = "Format d'image non autorisé. Utilisez JPG, PNG, GIF ou WEBP";
        return $result;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed_exts)) {
        $result['message'] = "Extension non autorisée";
        return $result;
    }
    
    $new_filename = 'chambre_' . uniqid() . '.' . $ext;
    $upload_dir = __DIR__ . '/../assets/images/chambres/';
    $relative_path = 'assets/images/chambres/' . $new_filename;
    $full_path = $upload_dir . $new_filename;
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        $result['success'] = true;
        $result['message'] = "Image uploadée avec succès";
        $result['path'] = $relative_path;
    } else {
        $result['message'] = "Erreur lors de l'enregistrement de l'image";
    }
    
    return $result;
}



function formatFCFA($montant, $avec_symbole = true) {
    if ($montant === null || $montant === '') {
        $montant = 0;
    }
    $formatted = number_format(floatval($montant), 0, ',', ' ');
    return $avec_symbole ? $formatted . ' FCFA' : $formatted;
}

function getNomMethodePaiement($code) {
    $methodes = [
        'mobile_money' => 'Mobile Money',
        'om' => 'Orange Money',
        'orange_money' => 'Orange Money',
        'virement' => 'Virement bancaire',
        'especes' => 'Espèces'
    ];
    return $methodes[$code] ?? ucfirst(str_replace('_', ' ', $code));
}
?>