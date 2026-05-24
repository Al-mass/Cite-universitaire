<?php
/**
 * Fonctions de gestion des uploads d'images
 */

/**
 * Upload une image pour une chambre
 * @param array $file $_FILES['input_name']
 * @param int $chambre_id
 * @return array [success, message, path]
 */
function uploadImageChambre($file, $chambre_id = null) {
    return uploadImage($file, 'chambres', $chambre_id);
}

/**
 * Upload une image pour une cité
 * @param array $file $_FILES['input_name']
 * @param int $cite_id
 * @return array [success, message, path]
 */
function uploadImageCite($file, $cite_id = null) {
    return uploadImage($file, 'cites', $cite_id);
}

/**
 * Fonction générique d'upload d'image
 * @param array $file
 * @param string $type 'chambres' ou 'cites'
 * @param int $id
 * @return array
 */
function uploadImage($file, $type, $id = null) {
    $result = [
        'success' => false,
        'message' => '',
        'path' => ''
    ];
    
    // Vérifier les erreurs
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
    $new_filename = $type . '_' . ($id ? $id . '_' : '') . uniqid() . '.' . $ext;
    $upload_dir = __DIR__ . '/../assets/images/' . $type . '/';
    $relative_path = 'assets/images/' . $type . '/' . $new_filename;
    $full_path = $upload_dir . $new_filename;
    
    // Créer le dossier si nécessaire
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        // Optimiser l'image
        optimizeImage($full_path, $mime);
        
        $result['success'] = true;
        $result['message'] = "Image uploadée avec succès";
        $result['path'] = $relative_path;
    } else {
        $result['message'] = "Erreur lors de l'enregistrement de l'image";
    }
    
    return $result;
}

/**
 * Optimise une image
 * @param string $path
 * @param string $mime
 */
function optimizeImage($path, $mime) {
    list($width, $height) = getimagesize($path);
    
    // Redimensionner si trop grande
    $max_width = 1920;
    $max_height = 1080;
    
    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        $src = null;
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $src = imagecreatefrompng($path);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($path);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($path);
                break;
        }
        
        if ($src) {
            $dst = imagecreatetruecolor($new_width, $new_height);
            
            // Conserver la transparence pour PNG
            if ($mime == 'image/png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            switch ($mime) {
                case 'image/jpeg':
                    imagejpeg($dst, $path, 85);
                    break;
                case 'image/png':
                    imagepng($dst, $path, 8);
                    break;
                case 'image/gif':
                    imagegif($dst, $path);
                    break;
                case 'image/webp':
                    imagewebp($dst, $path, 85);
                    break;
            }
            
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
}

/**
 * Supprime une image
 * @param string $path Chemin relatif depuis la racine du projet
 * @return bool
 */
function deleteImage($path) {
    if (empty($path)) {
        return false;
    }
    
    $full_path = __DIR__ . '/../' . $path;
    
    // Ne pas supprimer les images par défaut
    if (strpos($path, 'default.jpg') !== false) {
        return false;
    }
    
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    
    return false;
}


/**
 * Upload une image pour une cité
 * @param array $file $_FILES['input_name']
 * @return array [success, message, path]
 */
function uploadImageCite($file) {
    $result = [
        'success' => false,
        'message' => '',
        'path' => ''
    ];
    
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
        // Créer une miniature
        $thumb_dir = $upload_dir . 'thumbnails/';
        if (!is_dir($thumb_dir)) {
            mkdir($thumb_dir, 0777, true);
        }
        
        createThumbnail($full_path, $thumb_dir . $new_filename, 400, 200);
        
        $result['success'] = true;
        $result['message'] = "Image uploadée avec succès";
        $result['path'] = $relative_path;
    } else {
        $result['message'] = "Erreur lors de l'enregistrement de l'image";
    }
    
    return $result;
}

/**
 * Retourne le message d'erreur correspondant au code
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
 * Récupère l'URL d'une image avec fallback
 * @param string|null $path
 * @param string $type 'chambres' ou 'cites'
 * @return string
 */
function getImageUrl($path, $type = 'chambres') {
    if ($path && file_exists(__DIR__ . '/../' . $path)) {
        return '../' . $path;
    }
    return '../assets/images/' . $type . '/default.jpg';
}
?>