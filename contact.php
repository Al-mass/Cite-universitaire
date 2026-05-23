<?php
require_once 'includes/functions.php';

// Définir la fonction si elle n'existe pas déjà
if (!function_exists('getCitesNgaoundere')) {
    function getCitesNgaoundere() {
        $database = new Database();
        $db = $database->getConnection();
        $query = "SELECT * FROM cites WHERE ville = 'Ngaoundéré' ORDER BY nom";
        $stmt = $db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Définir formatFCFA si elle n'existe pas
if (!function_exists('formatFCFA')) {
    function formatFCFA($montant, $avec_symbole = true) {
        if ($montant === null || $montant === '') {
            $montant = 0;
        }
        $formatted = number_format(floatval($montant), 0, ',', ' ');
        return $avec_symbole ? $formatted . ' FCFA' : $formatted;
    }
}

// Définir validerTelephoneCameroun si elle n'existe pas
if (!function_exists('validerTelephoneCameroun')) {
    function validerTelephoneCameroun($telephone) {
        $pattern = '/^(?:\+237|237)?[6][0-9]{8}$/';
        return preg_match($pattern, preg_replace('/\s+/', '', $telephone));
    }
}

$success = '';
$error = '';

// Traitement du formulaire de contact
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = cleanInput($_POST['nom'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $telephone = cleanInput($_POST['telephone'] ?? '');
    $sujet = cleanInput($_POST['sujet'] ?? '');
    $message = cleanInput($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = "Le nom est requis";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est requis";
    } elseif (!validerEmail($email)) {
        $errors[] = "L'adresse email n'est pas valide";
    }
    
    if (!empty($telephone) && !validerTelephoneCameroun($telephone)) {
        $errors[] = "Le numéro de téléphone n'est pas valide (format camerounais attendu)";
    }
    
    if (empty($message)) {
        $errors[] = "Le message est requis";
    }
    
    if (empty($errors)) {
        // Préparer l'email
        $to = "contact@univ-ndere.cm";
        $subject = "[Cité U Ngaoundéré] $sujet - Message de $nom";
        
        // Corps du message
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #007A5E; color: white; padding: 20px; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; }
                .info { background-color: #FCD116; padding: 10px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Nouveau message de contact</h2>
            </div>
            <div class='content'>
                <div class='info'>
                    <strong>Nom :</strong> $nom<br>
                    <strong>Email :</strong> $email<br>
                    <strong>Téléphone :</strong> " . ($telephone ?: 'Non renseigné') . "<br>
                    <strong>Sujet :</strong> $sujet<br>
                    <strong>Date :</strong> " . date('d/m/Y H:i') . "
                </div>
                <h3>Message :</h3>
                <p>" . nl2br($message) . "</p>
            </div>
            <div class='footer'>
                <p>Message envoyé depuis le formulaire de contact du site Cité Universitaire de Ngaoundéré</p>
                <p>IP: " . $_SERVER['REMOTE_ADDR'] . "</p>
            </div>
        </body>
        </html>
        ";
        
        // Headers pour l'email HTML
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $email" . "\r\n";
        $headers .= "Reply-To: $email" . "\r\n";
        
        // Envoi de l'email
        if (@mail($to, $subject, $body, $headers)) {
            $success = "Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.";
            
            // Email de confirmation
            $confirmation_subject = "[Cité U Ngaoundéré] Confirmation de réception de votre message";
            $confirmation_body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .header { background-color: #007A5E; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h2>Merci de nous avoir contactés !</h2>
                </div>
                <div class='content'>
                    <p>Bonjour $nom,</p>
                    <p>Nous avons bien reçu votre message concernant <strong>$sujet</strong>.</p>
                    <p>Notre équipe va traiter votre demande et vous répondra dans les plus brefs délais.</p>
                    <p>Si vous avez des questions urgentes, n'hésitez pas à nous appeler au :</p>
                    <p style='font-size: 18px;'><strong>+237 699 999 999</strong></p>
                </div>
                <div class='footer'>
                    <p>Citié Universitaire de Ngaoundéré<br>BP 454 Ngaoundéré, Cameroun</p>
                </div>
            </body>
            </html>
            ";
            
            $confirmation_headers = "MIME-Version: 1.0" . "\r\n";
            $confirmation_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $confirmation_headers .= "From: Cité U Ngaoundéré <contact@univ-ndere.cm>" . "\r\n";
            
            @mail($email, $confirmation_subject, $confirmation_body, $confirmation_headers);
            
            $_POST = [];
        } else {
            $error = "Erreur lors de l'envoi du message. Veuillez réessayer plus tard.";
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Récupérer les cités pour l'affichage
try {
    $cites = getCitesNgaoundere();
} catch (Exception $e) {
    $cites = [];
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="p-4 rounded" style="background: linear-gradient(135deg, #007A5E 0%, #006400 100%); color: white;">
                <h2 class="mb-2">
                    <i class="bi bi-envelope"></i> Contactez-nous
                </h2>
                <p class="lead mb-0">
                    Une question ? Une suggestion ? N'hésitez pas à nous contacter.
                    Notre équipe est à votre disposition.
                </p>
            </div>
        </div>
    </div>

    <!-- Messages de succès/erreur -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulaire de contact -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm">
                <div class="card-header" style="background-color: #007A5E; color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil-square"></i> Envoyez-nous un message
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="contactForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nom" class="form-label">Nom complet *</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>"
                                       placeholder="Ex: Hamadou Issa" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="exemple@email.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <div class="input-group">
                                <span class="input-group-text">+237</span>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>"
                                       placeholder="6XXXXXXXX">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sujet" class="form-label">Sujet</label>
                            <select class="form-select" id="sujet" name="sujet">
                                <option value="Information">Demande d'information</option>
                                <option value="Reservation">Question sur une réservation</option>
                                <option value="Paiement">Problème de paiement</option>
                                <option value="Reclamation">Réclamation</option>
                                <option value="Suggestion">Suggestion</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Votre message..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-send"></i> Envoyer le message
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Informations de contact -->
        <div class="col-lg-5">
            <!-- Coordonnées principales -->
            <div class="card shadow-sm mb-4">
                <div class="card-header" style="background-color: #CE1126; color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle"></i> Nos coordonnées
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-building fs-3" style="color: #007A5E;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>Université de Ngaoundéré</h6>
                            <p class="mb-0">Cité Universitaire<br>BP 454 Ngaoundéré, Cameroun</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-geo-alt fs-3" style="color: #CE1126;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>Bureau des Cités Universitaires</h6>
                            <p class="mb-0">Campus Principal, Bâtiment C<br>Quartier Dang, Ngaoundéré</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-clock fs-3" style="color: #FCD116;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>Heures d'ouverture</h6>
                            <p class="mb-0">
                                Lundi - Vendredi : 8h00 - 17h00<br>
                                Samedi : 9h00 - 13h00<br>
                                Dimanche : Fermé
                            </p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-telephone fs-3" style="color: #007A5E;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>Téléphone</h6>
                            <p class="mb-0">
                                <strong>Standard :</strong> +237 699 999 999<br>
                                <strong>WhatsApp :</strong> +237 699 888 777
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-envelope fs-3" style="color: #CE1126;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>Email</h6>
                            <p class="mb-0">
                                <strong>Informations :</strong> info@univ-ndere.cm<br>
                                <strong>Réservations :</strong> reservations@univ-ndere.cm
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cités disponibles -->
            <div class="card shadow-sm mb-4">
                <div class="card-header" style="background-color: #FCD116; color: #000;">
                    <h5 class="mb-0">
                        <i class="bi bi-building"></i> Nos Cités à Ngaoundéré
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($cites)): ?>
                        <?php foreach ($cites as $cite): ?>
                        <div class="d-flex mb-2 pb-2 border-bottom">
                            <div class="flex-shrink-0">
                                <i class="bi bi-geo-alt-fill" style="color: #CE1126;"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <strong><?php echo htmlspecialchars($cite['nom']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($cite['adresse']); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucune cité disponible pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- FAQ Rapide -->
            <div class="card shadow-sm">
                <div class="card-header" style="background-color: #006400; color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-question-circle"></i> Questions fréquentes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="bi bi-calendar"></i> Comment réserver ?</h6>
                        <p class="small text-muted">
                            Créez un compte, parcourez les chambres disponibles, 
                            sélectionnez vos dates et confirmez votre réservation.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="bi bi-credit-card"></i> Modes de paiement</h6>
                        <p class="small text-muted">
                            Orange Money, Carte bancaire, virement bancaire.
                        </p>
                    </div>
                    <div>
                        <h6><i class="bi bi-arrow-repeat"></i> Annulation</h6>
                        <p class="small text-muted">
                            L'annulation est possible selon nos conditions. 
                            Consultez la politique lors de la réservation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>