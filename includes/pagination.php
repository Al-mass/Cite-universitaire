<!-- Pagination améliorée -->
<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-4">
    <div class="pagination-info">
        <span class="text-muted">
            Affichage de <strong><?php echo (($page - 1) * $limit) + 1; ?></strong> 
            à <strong><?php echo min($page * $limit, $total); ?></strong> 
            sur <strong><?php echo $total; ?></strong> chambres
        </span>
    </div>
    
    <nav aria-label="Pagination des chambres">
        <ul class="pagination mb-0">
            <?php
            // Construire l'URL de base avec tous les filtres existants
            $url_params = $_GET;
            unset($url_params['page']);
            $base_url = 'index.php?' . http_build_query($url_params);
            if ($base_url != 'index.php?') $base_url .= '&';
            ?>
            
            <!-- Bouton Première page -->
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url; ?>page=1" aria-label="Première">
                    <i class="bi bi-chevron-double-left"></i>
                </a>
            </li>
            
            <!-- Bouton Précédent -->
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" aria-label="Précédent">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <?php
            // Déterminer les pages à afficher
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            // Afficher la première page si nécessaire
            if ($start > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?php echo $base_url; ?>page=1">1</a>
                </li>
                <?php if ($start > 2): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>">
                        <?php echo $total_pages; ?>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Bouton Suivant -->
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" aria-label="Suivant">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            
            <!-- Bouton Dernière page -->
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>" aria-label="Dernière">
                    <i class="bi bi-chevron-double-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Sélecteur de nombre d'éléments par page -->
    <div class="pagination-per-page">
        <form method="GET" class="d-flex align-items-center" id="perPageForm">
            <?php 
            // Conserver les filtres existants
            foreach ($_GET as $key => $value) {
                if ($key != 'per_page' && $key != 'page') {
                    echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                }
            }
            ?>
            <label class="me-2 text-muted">Afficher</label>
            <select class="form-select form-select-sm" name="per_page" style="width: auto;" onchange="this.form.submit()">
                <option value="12" <?php echo $limit == 12 ? 'selected' : ''; ?>>12</option>
                <option value="24" <?php echo $limit == 24 ? 'selected' : ''; ?>>24</option>
                <option value="48" <?php echo $limit == 48 ? 'selected' : ''; ?>>48</option>
                <option value="96" <?php echo $limit == 96 ? 'selected' : ''; ?>>96</option>
            </select>
            <span class="ms-2 text-muted">par page</span>
        </form>
    </div>
</div>
<?php endif; ?>