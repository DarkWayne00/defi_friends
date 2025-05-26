<?php
// search.php - Page de recherche de défis

require_once 'config/config.php';

$pageTitle = "Recherche de défis";
$isLoggedIn = isLoggedIn();

// Récupérer les paramètres de recherche
$search_query = isset($_GET['q']) ? cleanInput($_GET['q']) : '';
$categorie_id = isset($_GET['categorie']) ? intval($_GET['categorie']) : '';
$difficulte = isset($_GET['difficulte']) ? cleanInput($_GET['difficulte']) : '';
$date_filter = isset($_GET['date']) ? cleanInput($_GET['date']) : '';
$sort = isset($_GET['sort']) ? cleanInput($_GET['sort']) : 'recent';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Récupérer les catégories pour les filtres
$categories = getActiveCategories();

// Construire les filtres pour la recherche
$filters = [];
if ($categorie_id) $filters['categorie_id'] = $categorie_id;
if ($difficulte) $filters['difficulte'] = $difficulte;
if ($date_filter) $filters['date'] = $date_filter;

// Effectuer la recherche
$searchResults = searchDefis($search_query, $filters, $limit, $offset);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Recherche']
];

if (!empty($search_query)) {
    $breadcrumb[] = ['label' => 'Résultats pour "' . htmlspecialchars($search_query) . '"'];
}

include 'includes/header.php';
?>

<div class="container">
    
    <!-- En-tête de recherche -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-3">
                <?php if (!empty($search_query)): ?>
                    Résultats pour "<?= htmlspecialchars($search_query) ?>"
                <?php else: ?>
                    Rechercher des défis
                <?php endif; ?>
            </h1>
            <?php if (!empty($search_query)): ?>
                <p class="text-muted">
                    <?= $searchResults['total'] ?> défi<?= $searchResults['total'] > 1 ? 's' : '' ?> trouvé<?= $searchResults['total'] > 1 ? 's' : '' ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($isLoggedIn): ?>
                <a href="/defi_friends/defis/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    Créer un défi
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Barre de recherche principale -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <form action="" method="get" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control form-control-lg" name="q" 
                               value="<?= htmlspecialchars($search_query) ?>" 
                               placeholder="Rechercher des défis par titre, description...">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-search me-2"></i>
                        Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres avancés -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-light p-3">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>
                Filtres avancés
            </h5>
        </div>
        <div class="card-body p-3">
            <form action="" method="get" class="row g-3">
                <input type="hidden" name="q" value="<?= htmlspecialchars($search_query) ?>">
                
                <div class="col-md-3">
                    <label for="categorie" class="form-label">Catégorie</label>
                    <select class="form-select" id="categorie" name="categorie">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categorie_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="difficulte" class="form-label">Difficulté</label>
                    <select class="form-select" id="difficulte" name="difficulte">
                        <option value="">Toutes</option>
                        <option value="facile" <?= $difficulte === 'facile' ? 'selected' : '' ?>>Facile</option>
                        <option value="moyen" <?= $difficulte === 'moyen' ? 'selected' : '' ?>>Moyen</option>
                        <option value="difficile" <?= $difficulte === 'difficile' ? 'selected' : '' ?>>Difficile</option>
                        <option value="extreme" <?= $difficulte === 'extreme' ? 'selected' : '' ?>>Extrême</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date" class="form-label">Date de création</label>
                    <select class="form-select" id="date" name="date">
                        <option value="">Toutes les dates</option>
                        <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                        <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Cette semaine</option>
                        <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Ce mois</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="sort" class="form-label">Trier par</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Plus populaires</option>
                        <option value="trending" <?= $sort === 'trending' ? 'selected' : '' ?>>Tendance</option>
                        <option value="alphabetical" <?= $sort === 'alphabetical' ? 'selected' : '' ?>>Alphabétique</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>
                        Appliquer les filtres
                    </button>
                    <a href="/defi_friends/search.php<?= !empty($search_query) ? '?q=' . urlencode($search_query) : '' ?>" 
                       class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres rapides par catégorie -->
    <?php if (empty($search_query)): ?>
    <div class="mb-4">
        <h5 class="mb-3">Recherche rapide par catégorie</h5>
        <div class="row g-3">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-3 col-sm-6">
                    <a href="?categorie=<?= $category['id'] ?>" class="text-decoration-none">
                        <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100 category-filter-card">
                            <div class="mb-2">
                                <i class="fas <?= htmlspecialchars($category['icone']) ?> fa-2x" 
                                   style="color: <?= $category['couleur'] ?>;"></i>
                            </div>
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($category['nom']) ?></h6>
                            <?php
                            $nbDefisCategorie = fetchValue("SELECT COUNT(*) FROM defis WHERE categorie_id = ? AND statut = 'actif'", [$category['id']]);
                            ?>
                            <small class="text-muted">
                                <?= $nbDefisCategorie ?> défi<?= $nbDefisCategorie > 1 ? 's' : '' ?>
                            </small>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Résultats de recherche -->
    <?php if (!empty($search_query) || !empty($filters)): ?>
        <!-- Filtres actifs -->
        <?php
        $activeFilters = [];
        if (!empty($search_query)) $activeFilters[] = 'Recherche: "' . htmlspecialchars($search_query) . '"';
        if ($categorie_id) {
            $categoryName = fetchValue("SELECT nom FROM categories WHERE id = ?", [$categorie_id]);
            $activeFilters[] = 'Catégorie: ' . htmlspecialchars($categoryName);
        }
        if ($difficulte) $activeFilters[] = 'Difficulté: ' . ucfirst(htmlspecialchars($difficulte));
        if ($date_filter) {
            $dateLabel = match($date_filter) {
                'today' => 'Aujourd\'hui',
                'week' => 'Cette semaine',
                'month' => 'Ce mois',
                default => $date_filter
            };
            $activeFilters[] = 'Date: ' . $dateLabel;
        }
        ?>

        <?php if (!empty($activeFilters)): ?>
        <div class="mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-bold me-2">Filtres actifs:</span>
                <?php foreach ($activeFilters as $filter): ?>
                    <span class="badge bg-primary rounded-pill"><?= $filter ?></span>
                <?php endforeach; ?>
                <a href="/defi_friends/search.php" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="fas fa-times me-1"></i>
                    Effacer tout
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Résultats -->
        <?php if (empty($searchResults['results'])): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>Aucun résultat trouvé</h4>
                <p class="text-muted">Essayez de modifier vos critères de recherche ou parcourez les catégories.</p>
                <a href="/defi_friends/search.php" class="btn btn-primary">
                    <i class="fas fa-undo me-2"></i>
                    Nouvelle recherche
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4 mb-4">
                <?php foreach ($searchResults['results'] as $index => $defi): ?>
                    <div class="col-md-4 animate-on-scroll" style="animation-delay: <?= $index * 100 ?>ms;">
                        <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                            <?php if ($defi['image_presentation']): ?>
                                <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                                     class="card-img-top" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top d-flex justify-content-center align-items-center bg-light" style="height: 200px;">
                                    <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge rounded-pill text-bg-primary">
                                        <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> me-1"></i>
                                        <?= htmlspecialchars($defi['categorie_nom']) ?>
                                    </span>
                                    <span class="badge rounded-pill <?php 
                                        echo match($defi['difficulte']) {
                                            'facile' => 'text-bg-success',
                                            'moyen' => 'text-bg-warning',
                                            'difficile' => 'text-bg-danger',
                                            'extreme' => 'text-bg-dark',
                                            default => 'text-bg-secondary'
                                        }; 
                                    ?>">
                                        <?= ucfirst(htmlspecialchars($defi['difficulte'])) ?>
                                    </span>
                                </div>
                                
                                <h5 class="card-title fw-bold">
                                    <?php
                                    $titre = htmlspecialchars($defi['titre']);
                                    // Mettre en évidence les termes de recherche
                                    if (!empty($search_query)) {
                                        $titre = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<mark>$1</mark>', $titre);
                                    }
                                    echo $titre;
                                    ?>
                                </h5>
                                
                                <p class="card-text flex-grow-1">
                                    <?php
                                    $description = htmlspecialchars(substr($defi['description'], 0, 100)) . '...';
                                    // Mettre en évidence les termes de recherche
                                    if (!empty($search_query)) {
                                        $description = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<mark>$1</mark>', $description);
                                    }
                                    echo $description;
                                    ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars($defi['createur_pseudo']) ?>
                                    </small>
                                    <div class="d-flex align-items-center">
                                        <span class="me-3">
                                            <i class="fas fa-users me-1 text-primary"></i>
                                            <?= $defi['nb_participants'] ?>
                                        </span>
                                        <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" class="btn btn-sm btn-primary rounded-pill">
                                            <i class="fas fa-eye me-1"></i>
                                            Voir
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($searchResults['pages'] > 1): ?>
            <nav aria-label="Pagination des résultats">
                <ul class="pagination justify-content-center">
                    
                    <!-- Bouton précédent -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                                Précédent
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">
                                <i class="fas fa-chevron-left"></i>
                                Précédent
                            </span>
                        </li>
                    <?php endif; ?>

                    <!-- Numéros de page -->
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($searchResults['pages'], $page + 2);
                    
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <?php if ($i === $page): ?>
                                <span class="page-link"><?= $i ?></span>
                            <?php else: ?>
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $searchResults['pages']): ?>
                        <?php if ($end < $searchResults['pages'] - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $searchResults['pages']])) ?>"><?= $searchResults['pages'] ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Bouton suivant -->
                    <?php if ($page < $searchResults['pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                Suivant
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">
                                Suivant
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </li>
                    <?php endif; ?>
                    
                </ul>
            </nav>

            <!-- Informations de pagination -->
            <div class="text-center text-muted mb-4">
                Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $searchResults['total']) ?> 
                sur <?= $searchResults['total'] ?> résultat<?= $searchResults['total'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Suggestions si pas de recherche -->
    <?php if (empty($search_query) && empty($filters)): ?>
    <div class="mb-4">
        <h5 class="mb-3">Défis populaires</h5>
        <div class="row g-4">
            <?php
            $defisPopulaires = executeQuery(
                "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, u.pseudo as createur_pseudo,
                        (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants
                 FROM defis d
                 LEFT JOIN categories c ON d.categorie_id = c.id
                 LEFT JOIN utilisateurs u ON d.createur_id = u.id
                 WHERE d.statut = 'actif'
                 ORDER BY nb_participants DESC, d.date_creation DESC
                 LIMIT 6"
            );
            
            foreach ($defisPopulaires as $index => $defi): ?>
                <div class="col-md-4 animate-on-scroll" style="animation-delay: <?= $index * 100 ?>ms;">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                        <?php if ($defi['image_presentation']): ?>
                            <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                                 class="card-img-top" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                                 style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top d-flex justify-content-center align-items-center bg-light" style="height: 200px;">
                                <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge rounded-pill text-bg-primary">
                                    <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> me-1"></i>
                                    <?= htmlspecialchars($defi['categorie_nom']) ?>
                                </span>
                                <?php if ($defi['nb_participants'] > 5): ?>
                                    <span class="badge rounded-pill text-bg-success">
                                        <i class="fas fa-fire me-1"></i>
                                        Populaire
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="card-title fw-bold"><?= htmlspecialchars($defi['titre']) ?></h5>
                            <p class="card-text flex-grow-1"><?= htmlspecialchars(substr($defi['description'], 0, 100)) ?>...</p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?= htmlspecialchars($defi['createur_pseudo']) ?>
                                </small>
                                <div class="d-flex align-items-center">
                                    <span class="me-3">
                                        <i class="fas fa-users me-1 text-primary"></i>
                                        <?= $defi['nb_participants'] ?>
                                    </span>
                                    <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" class="btn btn-sm btn-primary rounded-pill">
                                        <i class="fas fa-eye me-1"></i>
                                        Voir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.category-filter-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.category-filter-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

mark {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
}
</style>

<?php include 'includes/footer.php'; ?>