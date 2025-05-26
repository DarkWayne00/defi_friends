<?php
// index.php - Page d'accueil

require_once 'config/config.php';

$pageTitle = "Accueil";
$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();

// Récupérer les défis en tendance
$defisTendance = getTrendingDefis(3);

// Récupérer les défis récents
$defisRecents = getRecentDefis(6, $isLoggedIn ? $currentUser['id'] : null);

// Récupérer les catégories pour l'affichage
$categories = getActiveCategories();

// Statistiques générales
$totalDefis = fetchValue("SELECT COUNT(*) FROM defis WHERE statut = 'actif'") ?? 0;
$totalUsers = fetchValue("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'actif'") ?? 0;
$totalParticipations = fetchValue("SELECT COUNT(*) FROM participations") ?? 0;

// Suggestions personnalisées pour les utilisateurs connectés
$suggestions = [];
if ($isLoggedIn) {
    $suggestions = getSuggestedDefis($currentUser['id'], 4);
}

// Activités des amis pour les utilisateurs connectés
$activitesAmis = [];
if ($isLoggedIn) {
    $activitesAmis = getFriendsActivities($currentUser['id'], 10);
}

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title fade-in-up">
                Bienvenue sur <span class="text-warning"><?= APP_NAME ?></span>
            </h1>
            <p class="hero-subtitle fade-in-up fade-in-delay-1">
                Défiez vos amis, montrez vos talents et découvrez de nouveaux challenges dans une communauté bienveillante
            </p>
            
            <?php if (!$isLoggedIn): ?>
                <div class="hero-actions fade-in-up fade-in-delay-2">
                    <a href="/defi_friends/auth/register.php" class="btn btn-warning btn-lg me-3">
                        <i class="fas fa-rocket me-2"></i>
                        Rejoindre la communauté
                    </a>
                    <a href="/defi_friends/defis/index.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-search me-2"></i>
                        Découvrir les défis
                    </a>
                </div>
            <?php else: ?>
                <div class="hero-actions fade-in-up fade-in-delay-2">
                    <a href="/defi_friends/defis/create.php" class="btn btn-warning btn-lg me-3">
                        <i class="fas fa-plus me-2"></i>
                        Créer un défi
                    </a>
                    <a href="/defi_friends/defis/index.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-trophy me-2"></i>
                        Voir tous les défis
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container">
    
    <!-- Statistiques de la plateforme -->
    <section class="stats-section fade-in-up fade-in-delay-3">
        <div class="row text-center">
            <div class="col-md-4">
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($totalDefis) ?></span>
                    <span class="stat-label">Défis créés</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($totalUsers) ?></span>
                    <span class="stat-label">Utilisateurs actifs</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($totalParticipations) ?></span>
                    <span class="stat-label">Participations</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Défis en tendance -->
    <?php if (!empty($defisTendance)): ?>
    <section class="mb-5">
        <h2 class="section-title fade-in-delay-3">🔥 Défis en tendance</h2>
        <div class="row g-4">
            <?php foreach ($defisTendance as $index => $defi): ?>
                <div class="col-md-4 animate-on-scroll" style="animation-delay: <?= $index * 100 + 300 ?>ms;">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden position-relative">
                        <div class="position-absolute top-0 start-0 m-2">
                            <div class="badge rounded-pill bg-danger d-flex align-items-center" style="padding: 0.5rem 0.75rem;">
                                <i class="fas fa-fire me-1"></i>
                                <span>Tendance</span>
                            </div>
                        </div>
                        
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
        
        <div class="text-center mt-4">
            <a href="/defi_friends/defis/index.php?sort=trending" class="btn btn-outline-primary">
                <i class="fas fa-fire me-2"></i>
                Voir tous les défis tendance
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Catégories populaires -->
    <section class="mb-5">
        <h2 class="section-title">Explorez par catégorie</h2>
        <div class="row g-4">
            <?php foreach (array_slice($categories, 0, 8) as $index => $category): ?>
                <div class="col-md-3 col-sm-6 animate-on-scroll" style="animation-delay: <?= $index * 50 ?>ms;">
                    <a href="/defi_friends/search.php?categorie=<?= $category['id'] ?>" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-4 category-card" 
                             style="background: linear-gradient(135deg, <?= $category['couleur'] ?>15, <?= $category['couleur'] ?>05);">
                            <div class="mb-3">
                                <i class="fas <?= htmlspecialchars($category['icone']) ?> fa-3x" 
                                   style="color: <?= $category['couleur'] ?>;"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($category['nom']) ?></h5>
                            <?php if ($category['description']): ?>
                                <p class="card-text text-muted small"><?= htmlspecialchars(substr($category['description'], 0, 60)) ?>...</p>
                            <?php endif; ?>
                            
                            <?php
                            // Compter les défis dans cette catégorie
                            $nbDefisCategorie = fetchValue("SELECT COUNT(*) FROM defis WHERE categorie_id = ? AND statut = 'actif'", [$category['id']]);
                            ?>
                            <div class="mt-auto">
                                <small class="text-muted">
                                    <i class="fas fa-trophy me-1"></i>
                                    <?= $nbDefisCategorie ?> défi<?= $nbDefisCategorie > 1 ? 's' : '' ?>
                                </small>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($isLoggedIn): ?>
        <!-- Suggestions personnalisées -->
        <?php if (!empty($suggestions)): ?>
        <section class="mb-5">
            <h2 class="section-title">Suggestions pour vous</h2>
            <div class="row g-4">
                <?php foreach ($suggestions as $index => $defi): ?>
                    <div class="col-md-3 animate-on-scroll" style="animation-delay: <?= $index * 100 ?>ms;">
                        <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                            <?php if ($defi['image_presentation']): ?>
                                <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                                     class="card-img-top" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                                     style="height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top d-flex justify-content-center align-items-center bg-light" style="height: 150px;">
                                    <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title fw-bold"><?= htmlspecialchars($defi['titre']) ?></h6>
                                <p class="card-text small flex-grow-1"><?= htmlspecialchars(substr($defi['description'], 0, 80)) ?>...</p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i>
                                        <?= $defi['nb_participants'] ?>
                                    </small>
                                    <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Voir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Activités des amis -->
        <?php if (!empty($activitesAmis)): ?>
        <section class="mb-5">
            <h2 class="section-title">Activités de vos amis</h2>
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <?php foreach (array_slice($activitesAmis, 0, 5) as $activite): ?>
                        <div class="d-flex align-items-center py-3 border-bottom">
                            <div class="me-3">
                                <img src="<?= getUserAvatar(null, $activite['user_pseudo'], 40) ?>" 
                                     alt="Avatar" class="rounded-circle" width="40" height="40">
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center">
                                    <strong><?= htmlspecialchars($activite['user_pseudo']) ?></strong>
                                    <span class="mx-2">
                                        <?php if ($activite['type'] === 'defi_cree'): ?>
                                            <i class="fas fa-plus-circle text-success"></i>
                                            a créé le défi
                                        <?php else: ?>
                                            <i class="fas fa-user-plus text-primary"></i>
                                            participe au défi
                                        <?php endif; ?>
                                    </span>
                                    <a href="/defi_friends/defis/view.php?id=<?= $activite['item_id'] ?>" class="text-decoration-none fw-bold">
                                        "<?= htmlspecialchars($activite['item_title']) ?>"
                                    </a>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= formatDate($activite['activity_date']) ?>
                                    <?php if ($activite['categorie']): ?>
                                        • Catégorie: <?= htmlspecialchars($activite['categorie']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($activitesAmis) > 5): ?>
                        <div class="text-center pt-3">
                            <a href="/defi_friends/amis/index.php?tab=activites" class="btn btn-sm btn-outline-primary">
                                Voir toutes les activités
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Défis récents -->
    <section class="mb-5">
        <h2 class="section-title">Défis récents</h2>
        <div class="row g-4">
            <?php foreach ($defisRecents as $index => $defi): ?>
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
                                    <?php if ($isLoggedIn && isset($defi['user_participe']) && $defi['user_participe'] > 0): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>
                                            Participant
                                        </span>
                                    <?php else: ?>
                                        <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" class="btn btn-sm btn-primary rounded-pill">
                                            <i class="fas fa-eye me-1"></i>
                                            Voir
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="/defi_friends/defis/index.php" class="btn btn-outline-primary">
                <i class="fas fa-list me-2"></i>
                Voir tous les défis
            </a>
        </div>
    </section>

    <!-- Call to action pour les utilisateurs non connectés -->
    <?php if (!$isLoggedIn): ?>
    <section class="mb-5">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden" 
             style="background: linear-gradient(135deg, #631bff 0%, #4607d0 100%);">
            <div class="card-body text-center text-white p-5">
                <h3 class="fw-bold mb-3">Prêt à relever des défis ?</h3>
                <p class="mb-4">Rejoignez notre communauté et découvrez un monde de possibilités créatives !</p>
                <a href="/defi_friends/auth/register.php" class="btn btn-warning btn-lg me-3">
                    <i class="fas fa-rocket me-2"></i>
                    Créer mon compte
                </a>
                <a href="/defi_friends/auth/login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Se connecter
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>