<?php
require_once '../includes/functions.php';

// ==================== CONFIGURATION ====================
// Activer l'affichage des erreurs en développement (à désactiver en production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// ==================== VÉRIFICATION DES PERMISSIONS ====================
if (!isLoggedIn()) {
    $_SESSION['error'] = "Veuillez vous connecter pour effectuer un paiement";
    redirect('../auth/login.php');
}

if (isAdmin()) {
    $_SESSION['error'] = "Les administrateurs ne peuvent pas effectuer de paiement";
    redirect('../admin/dashboard.php');
}

// ==================== DÉFINITION DES FONCTIONS ====================
if (!function_exists('formatFCFA')) {
    function formatFCFA($montant, $avec_symbole = true) {
        if ($montant === null || $montant === '') {
            $montant = 0;
        }
        $formatted = number_format(floatval($montant), 0, ',', ' ');
        return $avec_symbole ? $formatted . ' FCFA' : $formatted;
    }
}

/**
 * Génère un identifiant de transaction unique
 */
function generateTransactionId($methode) {
    $prefix = 'PAY';
    switch ($methode) {
        case 'orange_money':
        case 'om':
            $prefix = 'OM';
            break;
        case 'carte_credit':
            $prefix = 'CB';
            break;
        case 'mobile_money':
            $prefix = 'MM';
            break;
        case 'virement':
            $prefix = 'VIR';
            break;
        case 'especes':
            $prefix = 'CASH';
            break;
    }
    return $prefix . '-' . strtoupper(uniqid()) . '-' . date('Ymd');
}

/**
 * Valide les données du formulaire de paiement
 */
function validerDonneesPaiement($data) {
    $errors = [];
    
    if (empty($data['reservation_id']) || !is_numeric($data['reservation_id'])) {
        $errors[] = "ID de réservation invalide";
    }
    
    if (empty($data['methode_paiement'])) {
        $errors[] = "Méthode de paiement requise";
    }
    
    $methodes_valides = ['carte_credit', 'orange_money', 'om', 'mobile_money', 'virement', 'especes'];
    if (!in_array($data['methode_paiement'], $methodes_valides)) {
        $errors[] = "Méthode de paiement non supportée";
    }
    
    if (empty($data['montant']) || !is_numeric($data['montant']) || $data['montant'] <= 0) {
        $errors[] = "Montant invalide";
    }
    
    return $errors;
}

/**
 * Traite le paiement par Orange Money
 */
function traiterPaiementOrangeMoney($db, $reservation_id, $montant, $telephone, $user_id) {
    // Simulation de l'appel API Orange Money
    $code_marchand = 'UNIVNDERE';
    $api_response = [
        'success' => true,
        'transaction_id' => 'OM-' . strtoupper(uniqid()),
        'message' => 'Paiement Orange Money réussi'
    ];
    
    // En environnement réel, vous feriez un appel API ici
    // $api_response = appelAPIOrangeMoney($telephone, $montant, $code_marchand);
    
    return $api_response;
}

/**
 * Traite le paiement par Carte Bancaire
 */
function traiterPaiementCarteBancaire($db, $reservation_id, $montant, $user_id) {
    // Simulation de l'appel API de paiement par carte
    $api_response = [
        'success' => true,
        'transaction_id' => 'CB-' . strtoupper(uniqid()),
        'message' => 'Paiement par carte réussi',
        'authorization_code' => strtoupper(substr(md5(uniqid()), 0, 6))
    ];
    
    // En environnement réel, vous feriez un appel à une passerelle de paiement
    // $api_response = appelPasserellePaiement($montant, $carte_info);
    
    return $api_response;
}

/**
 * Traite le paiement par Virement Bancaire
 */
function traiterPaiementVirement($db, $reservation_id, $montant, $reference, $user_id) {
    $transaction_id = $reference ?: 'VIR-' . strtoupper(uniqid()) . '-' . date('Ymd');
    
    return [
        'success' => true,
        'transaction_id' => $transaction_id,
        'message' => 'Virement enregistré. En attente de confirmation bancaire.',
        'statut' => 'en_attente' // Le virement nécessite une validation
    ];
}

/**
 * Traite le paiement en Espèces
 */
function traiterPaiementEspeces($db, $reservation_id, $montant, $user_id) {
    $transaction_id = 'CASH-' . strtoupper(uniqid()) . '-' . date('Ymd');
    
    return [
        'success' => true,
        'transaction_id' => $transaction_id,
        'message' => 'Paiement en espèces enregistré',
        'statut' => 'complete'
    ];
}

/**
 * Enregistre le paiement dans la base de données
 */
function enregistrerPaiement($db, $reservation_id, $montant, $methode, $transaction_id, $statut = 'complete') {
    $stmt = $db->prepare("INSERT INTO paiements (reservation_id, montant, methode_paiement, statut, transaction_id) 
                         VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$reservation_id, $montant, $methode, $statut, $transaction_id]);
}

/**
 * Met à jour le statut de la réservation si nécessaire
 */
function mettreAJourStatutReservation($db, $reservation_id, $montant) {
    // Récupérer le total payé
    $stmt = $db->prepare("SELECT r.montant_total, r.statut,
                          (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE reservation_id = r.id AND statut = 'complete') as total_paye
                          FROM reservations r WHERE r.id = ?");
    $stmt->execute([$reservation_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($res && $res['statut'] == 'en_attente' && ($res['total_paye'] + $montant) >= $res['montant_total']) {
        $stmt = $db->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = ?");
        $stmt->execute([$reservation_id]);
        return true;
    }
    
    return false;
}

/**
 * Envoie les notifications
 */
function envoyerNotificationsPaiement($db, $user_id, $reservation_id, $montant, $transaction_id, $methode) {
    // Notification à l'étudiant
    if (function_exists('envoyerNotification')) {
        $message = "Paiement de " . formatFCFA($montant) . " reçu pour la réservation #$reservation_id. ";
        $message .= "Transaction: $transaction_id";
        envoyerNotification($user_id, 'paiement', $message);
    }
    
    // Notification à l'administrateur
    if (function_exists('envoyerNotification')) {
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin_id = $stmt->fetchColumn();
        
        if ($admin_id) {
            $stmt = $db->prepare("SELECT nom, prenom FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message_admin = "Nouveau paiement de " . formatFCFA($montant) . " ";
            $message_admin .= "par " . $user['prenom'] . " " . $user['nom'] . " ";
            $message_admin .= "pour la réservation #$reservation_id. ";
            $message_admin .= "Méthode: " . getNomMethodePaiement($methode);
            
            envoyerNotification($admin_id, 'paiement', $message_admin);
        }
    }
}

/**
 * Journalise l'action
 */
function journaliserPaiement($user_id, $reservation_id, $montant, $methode, $transaction_id, $success) {
    if (function_exists('logAction')) {
        $status = $success ? 'Réussi' : 'Échoué';
        $message = "Paiement $status de " . formatFCFA($montant) . " ($methode) pour réservation #$reservation_id. Transaction: $transaction_id";
        logAction('paiement', $user_id, $message);
    }
    
    // Journal supplémentaire dans un fichier dédié aux paiements
    $log_file = __DIR__ . '/../logs/paiements.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . ' | ';
    $log_entry .= 'User: ' . $user_id . ' | ';
    $log_entry .= 'Reservation: #' . $reservation_id . ' | ';
    $log_entry .= 'Montant: ' . $montant . ' FCFA | ';
    $log_entry .= 'Methode: ' . $methode . ' | ';
    $log_entry .= 'Transaction: ' . $transaction_id . ' | ';
    $log_entry .= 'Status: ' . ($success ? 'SUCCESS' : 'FAILED') . ' | ';
    $log_entry .= 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// ==================== TRAITEMENT PRINCIPAL ====================
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_SESSION['error'] = "Méthode non autorisée";
    redirect('../etudiant/paiements.php');
}

// Récupération et nettoyage des données
$reservation_id = intval($_POST['reservation_id'] ?? 0);
$methode_paiement = trim($_POST['methode_paiement'] ?? '');
$montant = floatval($_POST['montant'] ?? 0);
$telephone = trim($_POST['telephone'] ?? '');
$reference = trim($_POST['reference'] ?? '');
$action = $_POST['action'] ?? '';

// Validation des données
$errors = validerDonneesPaiement([
    'reservation_id' => $reservation_id,
    'methode_paiement' => $methode_paiement,
    'montant' => $montant
]);

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    redirect('../etudiant/paiements.php');
}

// Vérifier que la réservation appartient à l'utilisateur
$stmt = $db->prepare("SELECT r.*, c.numero_chambre, ct.nom as cite_nom 
                      FROM reservations r
                      JOIN chambres c ON r.chambre_id = c.id
                      JOIN cites ct ON c.cite_id = ct.id
                      WHERE r.id = ? AND r.utilisateur_id = ?");
$stmt->execute([$reservation_id, $user_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    $_SESSION['error'] = "Réservation non trouvée ou accès non autorisé";
    journaliserPaiement($user_id, $reservation_id, $montant, $methode_paiement, 'N/A', false);
    redirect('../etudiant/paiements.php');
}

// Vérifier le montant déjà payé
$stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM paiements 
                     WHERE reservation_id = ? AND statut = 'complete'");
$stmt->execute([$reservation_id]);
$deja_paye = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);

$montant_max = $reservation['montant_total'] - $deja_paye;

if ($montant <= 0 || $montant > $montant_max) {
    $_SESSION['error'] = "Montant invalide. Montant maximum autorisé : " . formatFCFA($montant_max);
    journaliserPaiement($user_id, $reservation_id, $montant, $methode_paiement, 'N/A', false);
    redirect('../etudiant/paiements.php');
}

// Vérifier le statut de la réservation
if (!in_array($reservation['statut'], ['en_attente', 'confirmee'])) {
    $_SESSION['error'] = "Cette réservation ne peut plus être payée (statut: " . $reservation['statut'] . ")";
    journaliserPaiement($user_id, $reservation_id, $montant, $methode_paiement, 'N/A', false);
    redirect('../etudiant/mes-reservations.php');
}

// ==================== TRAITEMENT SELON LA MÉTHODE DE PAIEMENT ====================
$paiement_result = null;
$statut_paiement = 'complete';

try {
    switch ($methode_paiement) {
        case 'orange_money':
        case 'om':
            // Validation supplémentaire pour Orange Money
            if (empty($telephone)) {
                throw new Exception("Le numéro de téléphone est requis pour Orange Money");
            }
            if (!preg_match('/^[6][0-9]{8}$/', preg_replace('/\s+/', '', $telephone))) {
                throw new Exception("Numéro de téléphone Orange invalide");
            }
            
            $paiement_result = traiterPaiementOrangeMoney($db, $reservation_id, $montant, $telephone, $user_id);
            break;
            
        case 'carte_credit':
            $paiement_result = traiterPaiementCarteBancaire($db, $reservation_id, $montant, $user_id);
            break;
            
        case 'virement':
            $paiement_result = traiterPaiementVirement($db, $reservation_id, $montant, $reference, $user_id);
            $statut_paiement = $paiement_result['statut'] ?? 'en_attente';
            break;
            
        case 'mobile_money':
            $paiement_result = [
                'success' => true,
                'transaction_id' => 'MM-' . strtoupper(uniqid()),
                'message' => 'Paiement MTN Mobile Money réussi'
            ];
            break;
            
        case 'especes':
            $paiement_result = traiterPaiementEspeces($db, $reservation_id, $montant, $user_id);
            break;
            
        default:
            throw new Exception("Méthode de paiement non supportée");
    }
    
    // Vérifier si le traitement a réussi
    if (!$paiement_result || !($paiement_result['success'] ?? false)) {
        throw new Exception($paiement_result['message'] ?? "Échec du traitement du paiement");
    }
    
    $transaction_id = $paiement_result['transaction_id'];
    
    // Démarrer la transaction de base de données
    $db->beginTransaction();
    
    // Enregistrer le paiement
    if (!enregistrerPaiement($db, $reservation_id, $montant, $methode_paiement, $transaction_id, $statut_paiement)) {
        throw new Exception("Erreur lors de l'enregistrement du paiement");
    }
    
    $paiement_id = $db->lastInsertId();
    
    // Mettre à jour le statut de la réservation si nécessaire
    if ($statut_paiement == 'complete') {
        mettreAJourStatutReservation($db, $reservation_id, $montant);
    }
    
    // Envoyer les notifications
    envoyerNotificationsPaiement($db, $user_id, $reservation_id, $montant, $transaction_id, $methode_paiement);
    
    // Valider la transaction
    $db->commit();
    
    // Journaliser le succès
    journaliserPaiement($user_id, $reservation_id, $montant, $methode_paiement, $transaction_id, true);
    
    // Message de succès
    $message_succes = "Paiement de " . formatFCFA($montant) . " effectué avec succès !";
    if ($statut_paiement == 'en_attente') {
        $message_succes = "Votre virement de " . formatFCFA($montant) . " a été enregistré. Il sera validé dans les 24-48h.";
    }
    
    $_SESSION['success'] = $message_succes;
    
    // Redirection vers la page de succès
    $redirect_url = "success.php?reservation_id=$reservation_id&paiement_id=$paiement_id&transaction_id=" . urlencode($transaction_id);
    
    // Ajouter le montant pour l'affichage
    $redirect_url .= "&montant=$montant";
    
    redirect($redirect_url);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Journaliser l'échec
    journaliserPaiement($user_id, $reservation_id, $montant, $methode_paiement, $transaction_id ?? 'N/A', false);
    
    // Message d'erreur
    $_SESSION['error'] = "Erreur lors du paiement : " . $e->getMessage();
    
    // Redirection vers la page de paiement avec les paramètres
    $redirect_url = "../etudiant/paiements.php?etape=3&reservation_id=$reservation_id&methode=$methode_paiement&montant=$montant";
    
    // Ajouter l'erreur dans l'URL pour débogage (optionnel)
    // $redirect_url .= "&error=" . urlencode($e->getMessage());
    
    redirect($redirect_url);
}

// Si on arrive ici, quelque chose s'est mal passé
$_SESSION['error'] = "Une erreur inattendue est survenue";
redirect('../etudiant/paiements.php');
?>
// Après un paiement réussi
if (function_exists('envoyerNotification')) {
    // Notifier l'étudiant
    envoyerNotification($user_id, 'paiement', "Paiement de " . formatFCFA($montant) . " reçu");
    
    // Notifier TOUS les administrateurs
    $stmt = $db->query("SELECT id FROM utilisateurs WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $admin_id) {
        envoyerNotification($admin_id, 'paiement', "Nouveau paiement de " . formatFCFA($montant) . " reçu");
    }
}