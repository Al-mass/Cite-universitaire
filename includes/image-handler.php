<?php
/**
 * Gestionnaire d'images pour les réservations et chambres
 */

require_once 'functions.php';

/**
 * Génère une miniature d'une image
 * @param string $source_path
 * @param string $dest_path
 * @param int $thumb_width
 * @param int $thumb_height
 * @return bool
 */
function createThumbnail($source_path, $dest_path, $thumb_width = 300, $thumb_height = 200) {
    if (!file_exists($source_path)) {
        return false;
    }
    
    list($width, $height, $type) = getimagesize($source_path);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // Calculer les dimensions pour garder les proportions
    $ratio_orig = $width / $height;
    
    if ($thumb_width / $thumb_height > $ratio_orig) {
        $thumb_width = $thumb_height * $ratio_orig;
    } else {
        $thumb_height = $thumb_width / $ratio_orig;
    }
    
    $thumb = imagecreatetruecolor($thumb_width, $thumb_height);
    
    // Conserver la transparence pour PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $thumb_width, $thumb_height, $transparent);
    }
    
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($thumb, $dest_path, 85);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($thumb, $dest_path, 8);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($thumb, $dest_path);
            break;
        default:
            $success = false;
    }
    
    imagedestroy($source);
    imagedestroy($thumb);
    
    return $success;
}

/**
 * Ajoute un filigrane à une image
 * @param string $image_path
 * @param string $watermark_text
 * @return bool
 */
function addWatermark($image_path, $watermark_text = '© Résidence Universitaire') {
    if (!file_exists($image_path)) {
        return false;
    }
    
    list($width, $height, $type) = getimagesize($image_path);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($image_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($image_path);
            break;
        default:
            return false;
    }
    
    // Couleur du filigrane (blanc avec transparence)
    $color = imagecolorallocatealpha($image, 255, 255, 255, 60);
    
    // Position du filigrane (en bas à droite)
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($watermark_text);
    $text_height = imagefontheight($font_size);
    
    $x = $width - $text_width - 10;
    $y = $height - $text_height - 10;
    
    imagestring($image, $font_size, $x, $y, $watermark_text, $color);
    
    // Sauvegarder
    switch ($type) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($image, $image_path, 90);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($image, $image_path, 8);
            break;
        default:
            $success = false;
    }
    
    imagedestroy($image);
    
    return $success;
}
?>