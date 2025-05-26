<?php
// defis/index.php - Liste des défis

require_once '../config/config.php';

$pageTitle = "Explorer les défis";
$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();

// Récupérer les paramètres de tri et filtrage
$sort = $_GET['sort'] ?? 'recent';
$categorie_id = isset($_GET['categorie']) ? intval($_GET['categorie']) : '';
$difficulte = $_GET['difficulte'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Récupérer les catégories pour les filtres
$categories = getActiveCategories();

// Construire la requête SQL de base
$whereConditions = ["d.statut = 'actif'"];
$params = [];

// Appliquer les filtres
if ($categorie_id) {
    $whereConditions[] = "d.categorie_id = ?";
    $params[] = $categorie_id;
}

if ($difficulte) {
    $whereConditions[] = "d.difficulte = ?";
    $params[] = $difficulte;
}

if ($search) {
    $whereConditions[] = "(d.titre LIKE ? OR d.description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

// Définir l'ordre selon le tri
$orderClause = match($sort) {
    'popular' => 'nb_participants DESC, d.date_creation DESC',
    'trending' => '(nb_participants_recent * 3 + nb_commentaires_recent * 2) DESC, d.date_creation DESC',
    'alphabetical' => 'd.titre ASC',
    'oldest' => 'd.date_creation ASC',
    default => 'd.date_creation DESC' // recent
};

// Compter le total pour la pagination
$countSql = "SELECT COUNT(*) FROM defis d WHERE " . $whereClause;
$total = fetchValue($countSql, $params);
$totalPages = ceil($total / $limit);

// Récupérer les défis avec statistiques
$sql = "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
               u.pseudo as createur_pseudo,
               (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants,
               (SELECT COUNT(*) FROM participations WHERE defi_id = d.id AND date_participation > DATE_SUB(NOW(), INTERVAL 7 DAY)) as nb_participants_recent,
               (SELECT COUNT(*) FROM commentaires WHERE defi_id = d.id AND date_creation > DATE_SUB(NOW(), INTERVAL 14 DAY)) as nb_commentaires_recent" .
               ($isLoggedIn ? ", (SELECT COUNT(*) FROM participations WHERE defi_id = d.id AND utilisateur_id = ?) as user_participe" : "") . "
        FROM defis d
        LEFT JOIN categories c ON d.categorie_id = c.id
        LEFT JOIN utilisateurs u ON d.createur_id = u.id
        WHERE " . $whereClause . "
        ORDER BY " . $orderClause . "
        LIMIT ? OFFSET ?";

if ($isLoggedIn) {
    $params[] = $currentUser['id'];
}
$params[] = $limit;
$params[] = $offset;

$defis = executeQuery($sql, $params);

// Statistiques pour l'affichage
$totalDefis = fetchValue("SELECT COUNT(*) FROM defis WHERE statut = 'actif'");
$totalParticipations = fetchValue("SELECT COUNT(*) FROM participations");

// Breadcrumb
$breadcrumb = [
    ['label' => 'Défis']
];

include '../includes/header.php';
?>

<div class="container">
    
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-trophy me-2 text-primary"></i>
                Explorer les défis
            </h1>
            <p class="text-muted">
                Découvrez <?= number_format($totalDefis) ?> défis créés par la communauté avec 
                <?= number_format($totalParticipations) ?> participations au total.
            </p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($isLoggedIn): ?>
                <a href="/defi_friends/defis/create.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>
                    Créer un défi
                </a>
            <?php else: ?>
                <a href="/defi_friends/auth/register.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus me-2"></i>
                    Rejoindre
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-primary"><?= number_format($totalDefis) ?></div>
                    <small class="text-muted">Défis actifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-success"><?= number_format($totalParticipations) ?></div>
                    <small class="text-muted">Participations</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-info"><?= count($categories) ?></div>
                    <small class="text-muted">Catégories</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-warning"><?= $total ?></div>
                    <small class="text-muted">Résultats</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <form action="" method="get" class="row g-3">
                
                <!-- Recherche -->
                <div class="col-md-4">
                    <label for="search" class="form-label">Rechercher</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Titre, description...">
                    </div>
                </div>
                
                <!-- Catégorie -->
                <div class="col-md-2">
                    <label for="categorie" class="form-label">Catégorie</label>
                    <select class="form-select" id="categorie" name="categorie">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categorie_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Difficulté -->
                <div class="col-md-2">
                    <label for="difficulte" class="form-label">Difficulté</label>
                    <select class="form-select" id="difficulte" name="difficulte">
                        <option value="">Toutes</option>
                        <option value="facile" <?= $difficulte === 'facile' ? 'selected' : '' ?>>Facile</option>
                        <option value="moyen" <?= $difficulte === 'moyen' ? 'selected' : '' ?>>Moyen</option>
                        <option value="difficile" <?= $difficulte === 'difficile' ? 'selected' : '' ?>>Difficile</option>
                        <option value="extreme" <?= $difficulte === 'extreme' ? 'selected' : '' ?>>Extrême</option>
                    </select>
                </div>
                
                <!-- Tri -->
                <div class="col-md-2">
                    <label for="sort" class="form-label">Trier par</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Plus populaires</option>
                        <option value="trending" <?= $sort === 'trending' ? 'selected' : '' ?>>Tendance</option>
                        <option value="alphabetical" <?= $sort === 'alphabetical' ? 'selected' : '' ?>>Alphabétique</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Plus anciens</option>
                    </select>
                </div>
                
                <!-- Boutons -->
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>
                            Filtrer
                        </button>
                    </div>
                </div>
                
            </form>
            
            <!-- Filtres actifs -->
            <?php
            $activeFilters = [];
            if ($search) $activeFilters[] = 'Recherche: "' . htmlspecialchars($search) . '"';
            if ($categorie_id) {
                $categoryName = fetchValue("SELECT nom FROM categories WHERE id = ?", [$categorie_id]);
                $activeFilters[] = 'Catégorie: ' . htmlspecialchars($categoryName);
            }
            if ($difficulte) $activeFilters[] = 'Difficulté: ' . ucfirst(htmlspecialchars($difficulte));
            ?>
            
            <?php if (!empty($activeFilters)): ?>
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong class="me-2">Filtres actifs:</strong>
                    <?php foreach ($activeFilters as $filter): ?>
                        <span class="badge bg-primary rounded-pill"><?= $filter ?></span>
                    <?php endforeach; ?>
                    <a href="/defi_friends/defis/index.php" class="btn btn-sm btn-outline-secondary ms-2">
                        <i class="fas fa-times me-1"></i>
                        Réinitialiser
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Raccourcis par catégorie -->
    <?php if (empty($search) && !$categorie_id): ?>
    <div class="mb-4">
        <h5 class="mb-3">Parcourir par catégorie</h5>
        <div class="row g-3">
            <?php foreach (array_slice($categories, 0, 6) as $category): ?>
                <div class="col-md-2 col-sm-4 col-6">
                    <a href="?categorie=<?= $category['id'] ?>" class="text-decoration-none">
                        <div class="card border-0 shadow-sm text-center p-3 h-100 category-quick-filter">
                            <div class="mb-2">
                                <i class="fas <?= htmlspecialchars($category['icone']) ?> fa-2x" 
                                   style="color: <?= $category['couleur'] ?>;"></i>
                            </div>
                            <h6 class="fw-bold mb-1 small"><?= htmlspecialchars($category['nom']) ?></h6>
                            <?php
                            $nbDefisCategorie = fetchValue("SELECT COUNT(*) FROM defis WHERE categorie_id = ? AND statut = 'actif'", [$category['id']]);
                            ?>
                            <small class="text-muted"><?= $nbDefisCategorie ?></small>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Liste des défis -->
    <?php if (empty($defis)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4>Aucun défi trouvé</h4>
            <p class="text-muted">
                <?php if (!empty($activeFilters)): ?>
                    Aucun défi ne correspond à vos critères de recherche.
                <?php else: ?>
                    Il n'y a actuellement aucun défi disponible.
                <?php endif; ?>
            </p>
            
            <div class="mt-3">
                <?php if (!empty($activeFilters)): ?>
                    <a href="/defi_friends/defis/index.php" class="btn btn-primary me-2">
                        <i class="fas fa-undo me-2"></i>
                        Voir tous les défis
                    </a>
                <?php endif; ?>
                
                <?php if ($isLoggedIn): ?>
                    <a href="/defi_friends/defis/create.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>
                        Créer le premier défi
                    </a>
                <?php else: ?>
                    <a href="/defi_friends/auth/register.php" class="btn btn-success">
                        <i class="fas fa-user-plus me-2"></i>
                        Rejoindre pour créer
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Informations sur les résultats -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <strong><?= number_format($total) ?></strong> défi<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?>
                <?php if ($page > 1): ?>
                    <span class="text-muted">
                        (page <?= $page ?> sur <?= $totalPages ?>)
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Vue rapide -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Type d'affichage">
                <input type="radio" class="btn-check" name="view" id="view_grid" checked>
                <label class="btn btn-outline-secondary" for="view_grid">
                    <i class="fas fa-th"></i>
                </label>
                <input type="radio" class="btn-check" name="view" id="view_list">
                <label class="btn btn-outline-secondary" for="view_list">
                    <i class="fas fa-list"></i>
                </label>
            </div>
        </div>

        <!-- Grille des défis -->
        <div class="row g-4 mb-4" id="defis-grid">
            <?php foreach ($defis as $index => $defi): ?>
                <div class="col-lg-4 col-md-6 animate-on-scroll" style="animation-delay: <?= $index * 50 ?>ms;">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden defi-card">
                        
                        <!-- Image ou icône -->
                        <div class="position-relative">
                            <?php if ($defi['image_presentation']): ?>
                                <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                                     class="card-img-top" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top d-flex justify-content-center align-items-center" 
                                     style="height: 200px; background: linear-gradient(135deg, <?= $defi['categorie_couleur'] ?>20, <?= $defi['categorie_couleur'] ?>05);">
                                    <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-4x" 
                                       style="color: <?= $defi['categorie_couleur'] ?>;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges de statut -->
                            <div class="position-absolute top-0 start-0 m-2">
                                <?php if ($defi['nb_participants_recent'] > 3): ?>
                                    <span class="badge bg-danger rounded-pill mb-1 d-block">
                                        <i class="fas fa-fire me-1"></i>
                                        Tendance
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($isLoggedIn && isset($defi['user_participe']) && $defi['user_participe'] > 0): ?>
                                    <span class="badge bg-success rounded-pill d-block">
                                        <i class="fas fa-check me-1"></i>
                                        Participant
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <!-- Catégorie et difficulté -->
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
                            
                            <!-- Titre et description -->
                            <h5 class="card-title fw-bold line-clamp-2"><?= htmlspecialchars($defi['titre']) ?></h5>
                            <p class="card-text flex-grow-1 line-clamp-3 text-muted">
                                <?= htmlspecialchars($defi['description']) ?>
                            </p>
                            
                            <!-- Métadonnées -->
                            <div class="row g-2 mb-3 small text-muted">
                                <div class="col-6">
                                    <i class="fas fa-user me-1"></i>
                                    <?= htmlspecialchars($defi['createur_pseudo']) ?>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?= formatDate($defi['date_creation']) ?>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-users me-1 text-primary"></i>
                                    <?= $defi['nb_participants'] ?> participant<?= $defi['nb_participants'] > 1 ? 's' : '' ?>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-eye me-1 text-info"></i>
                                    <?= $defi['vues'] ?? 0 ?> vue<?= ($defi['vues'] ?? 0) > 1 ? 's' : '' ?>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="d-flex gap-2 mt-auto">
                                <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" 
                                   class="btn btn-outline-primary flex-fill">
                                    <i class="fas fa-eye me-1"></i>
                                    Voir détails
                                </a>
                                
                                <?php if ($isLoggedIn): ?>
                                    <?php if (!isset($defi['user_participe']) || $defi['user_participe'] == 0): ?>
                                        <a href="/defi_friends/defis/participate.php?id=<?= $defi['id'] ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-play me-1"></i>
                                            Participer
                                        </a>
                                    <?php else: ?>
                                        <a href="/defi_friends/defis/complete.php?id=<?= $defi['id'] ?>" 
                                           class="btn btn-success">
                                            <i class="fas fa-check me-1"></i>
                                            Terminer
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="/defi_friends/auth/login.php" 
                                       class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-1"></i>
                                        Connexion
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Vue liste (cachée par défaut) -->
        <div class="d-none" id="defis-list">
            <?php foreach ($defis as $defi): ?>
                <div class="card border-0 shadow-sm rounded-3 mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <?php if ($defi['image_presentation']): ?>
                                    <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                                         class="img-fluid rounded" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                                         style="height: 80px; width: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div class="d-flex justify-content-center align-items-center rounded bg-light" 
                                         style="height: 80px;">
                                        <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex gap-2 mb-2">
                                    <span class="badge rounded-pill text-bg-primary small">
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
                                    ?> small">
                                        <?= ucfirst(htmlspecialchars($defi['difficulte'])) ?>
                                    </span>
                                </div>
                                <h6 class="fw-bold mb-2"><?= htmlspecialchars($defi['titre']) ?></h6>
                                <p class="mb-2 small text-muted"><?= htmlspecialchars(substr($defi['description'], 0, 150)) ?>...</p>
                                <div class="small text-muted">
                                    Par <?= htmlspecialchars($defi['createur_pseudo']) ?> • 
                                    <?= $defi['nb_participants'] ?> participants • 
                                    <?= formatDate($defi['date_creation']) ?>
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="d-flex flex-column gap-2">
                                    <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        Voir détails
                                    </a>
                                    <?php if ($isLoggedIn): ?>
                                        <?php if (!isset($defi['user_participe']) || $defi['user_participe'] == 0): ?>
                                            <a href="/defi_friends/defis/participate.php?id=<?= $defi['id'] ?>" 
                                               class="btn btn-sm btn-primary">
                                                Participer
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Pagination des défis" class="mt-4">
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
                $end = min($totalPages, $page + 2);
                
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

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <!-- Bouton suivant -->
                <?php if ($page < $totalPages): ?>
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
            Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $total) ?> 
            sur <?= $total ?> défi<?= $total > 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<style>
/* Styles pour les cartes de défis */
.defi-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.defi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.category-quick-filter {
    transition: all 0.3s ease;
    cursor: pointer;
}

.category-quick-filter:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

/* Limitation du nombre de lignes */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Animation d'apparition */
.animate-on-scroll {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s ease;
}

.animate-on-scroll.visible {
    opacity: 1;
    transform: translateY(0);
}

/* Responsive pour les cartes */
@media (max-width: 768px) {
    .defi-card .card-body {
        padding: 1rem;
    }
    
    .category-quick-filter {
        padding: 1rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basculer entre vue grille et vue liste
    const viewGrid = document.getElementById('view_grid');
    const viewList = document.getElementById('view_list');
    const defisGrid = document.getElementById('defis-grid');
    const defisList = document.getElementById('defis-list');
    
    if (viewGrid && viewList && defisGrid && defisList) {
        viewGrid.addEventListener('change', function() {
            if (this.checked) {
                defisGrid.classList.remove('d-none');
                defisList.classList.add('d-none');
                localStorage.setItem('defis_view_preference', 'grid');
            }
        });
        
        viewList.addEventListener('change', function() {
            if (this.checked) {
                defisGrid.classList.add('d-none');
                defisList.classList.remove('d-none');
                localStorage.setItem('defis_view_preference', 'list');
            }
        });
        
        // Restaurer la préférence de vue
        const savedView = localStorage.getItem('defis_view_preference');
        if (savedView === 'list') {
            viewList.checked = true;
            viewList.dispatchEvent(new Event('change'));
        }
    }
    
    // Soumission automatique du formulaire lors du changement de tri
    const sortSelect = document.getElementById('sort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    // Animation d'apparition des cartes au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
    
    // Mise à jour des vues (enregistrer qu'un défi a été vu)
    const defiCards = document.querySelectorAll('.defi-card');
    defiCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Si le clic n'est pas sur un bouton ou un lien, rediriger vers la page de détails
            if (!e.target.closest('a, button')) {
                const viewLink = this.querySelector('a[href*="view.php"]');
                if (viewLink) {
                    window.location.href = viewLink.href;
                }
            }
        });
    });
    
    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Ctrl+F pour focus sur la recherche
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Flèches pour navigation pagination
        if (e.altKey) {
            if (e.key === 'ArrowLeft') {
                const prevLink = document.querySelector('.pagination .page-item:not(.disabled) .page-link[href*="page=' + (<?= $page ?> - 1) + '"]');
                if (prevLink) window.location.href = prevLink.href;
            } else if (e.key === 'ArrowRight') {
                const nextLink = document.querySelector('.pagination .page-item:not(.disabled) .page-link[href*="page=' + (<?= $page ?> + 1) + '"]');
                if (nextLink) window.location.href = nextLink.href;
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>