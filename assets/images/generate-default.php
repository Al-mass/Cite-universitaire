<?php
/**
 * Script pour générer des images par défaut
 * À exécuter une seule fois pour créer les images de base
 */

// Créer une image par défaut pour les chambres
function createDefaultRoomImage() {
    $width = 800;
    $height = 600;
    
    $image = imagecreatetruecolor($width, $height);
    
    // Couleurs
    $bg_color = imagecolorallocate($image, 52, 152, 219); // Bleu
    $text_color = imagecolorallocate($image, 255, 255, 255); // Blanc
    $border_color = imagecolorallocate($image, 41, 128, 185); // Bleu foncé
    
    // Fond
    imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);
    
    // Bordure
    imagerectangle($image, 5, 5, $width-6, $height-6, $border_color);
    
    // Icône de porte (simulée avec du texte)
    $text = "🚪 Chambre";
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_height = imagefontheight($font_size);
    
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    imagestring($image, $font_size, $x, $y, $text, $text_color);
    
    // Texte supplémentaire
    $text2 = "Résidence Universitaire";
    $text2_width = imagefontwidth(3) * strlen($text2);
    $x2 = ($width - $text2_width) / 2;
    imagestring($image, 3, $x2, $y + 30, $text2, $text_color);
    
    // Sauvegarder
    imagejpeg($image, __DIR__ . '/chambres/default.jpg', 90);
    imagedestroy($image);
    
    echo "Image par défaut des chambres créée.<br>";
}

// Créer une image par défaut pour les cités
function createDefaultCiteImage() {
    $width = 1200;
    $height = 400;
    
    $image = imagecreatetruecolor($width, $height);
    
    // Dégradé
    for ($i = 0; $i < $height; $i++) {
        $color = imagecolorallocate($image, 46, 204 - ($i * 0.3), 113 - ($i * 0.2));
        imageline($image, 0, $i, $width, $i, $color);
    }
    
    // Couleurs
    $text_color = imagecolorallocate($image, 255, 255, 255);
    $shadow_color = imagecolorallocate($image, 0, 0, 0);
    
    // Texte avec ombre
    $text = "🏛️ Cité Universitaire";
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_height = imagefontheight($font_size);
    
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    // Ombre
    imagestring($image, $font_size, $x+2, $y+2, $text, $shadow_color);
    // Texte
    imagestring($image, $font_size, $x, $y, $text, $text_color);
    
    // Sauvegarder
    imagejpeg($image, __DIR__ . '/cites/default.jpg', 90);
    imagedestroy($image);
    
    echo "Image par défaut des cités créée.<br>";
}

// Créer un favicon simple
function createFavicon() {
    $width = 64;
    $height = 64;
    
    $image = imagecreatetruecolor($width, $height);
    
    // Fond transparent
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
    
    // Couleurs
    $blue = imagecolorallocate($image, 52, 152, 219);
    $white = imagecolorallocate($image, 255, 255, 255);
    
    // Dessiner un bâtiment simple
    imagefilledrectangle($image, 20, 30, 44, 60, $blue);
    imagefilledrectangle($image, 28, 15, 36, 30, $blue);
    
    // Fenêtres
    imagefilledrectangle($image, 24, 38, 28, 42, $white);
    imagefilledrectangle($image, 36, 38, 40, 42, $white);
    imagefilledrectangle($image, 24, 48, 28, 52, $white);
    imagefilledrectangle($image, 36, 48, 40, 52, $white);
    
    // Porte
    imagefilledrectangle($image, 29, 50, 35, 60, $white);
    
    // Sauvegarder en ICO
    // Note: PHP ne supporte pas nativement le format ICO
    // On sauvegarde en PNG et on le renomme
    imagepng($image, __DIR__ . '/favicon.png');
    copy(__DIR__ . '/favicon.png', __DIR__ . '/favicon.ico');
    unlink(__DIR__ . '/favicon.png');
    imagedestroy($image);
    
    echo "Favicon créé.<br>";
}

// Vérifier et créer les dossiers
if (!is_dir(__DIR__ . '/chambres')) {
    mkdir(__DIR__ . '/chambres', 0777, true);
}
if (!is_dir(__DIR__ . '/cites')) {
    mkdir(__DIR__ . '/cites', 0777, true);
}

// Créer les images
if (!file_exists(__DIR__ . '/chambres/default.jpg')) {
    createDefaultRoomImage();
} else {
    echo "L'image par défaut des chambres existe déjà.<br>";
}

if (!file_exists(__DIR__ . '/cites/default.jpg')) {
    createDefaultCiteImage();
} else {
    echo "L'image par défaut des cités existe déjà.<br>";
}

if (!file_exists(__DIR__ . '/favicon.ico')) {
    createFavicon();
} else {
    echo "Le favicon existe déjà.<br>";
}

echo "<br><strong>Terminé !</strong><br>";
echo "<a href='../index.php'>Retour à l'accueil</a>";
?>