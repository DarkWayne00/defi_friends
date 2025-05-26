<?php
// includes/header.php - En-tête de l'application

// Inclure la configuration si pas déjà fait
if (!defined('APP_NAME')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// Obtenir l'utilisateur connecté
$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();

// Obtenir les notifications si connecté
$notifications = [];
$notificationCount = 0;
if ($isLoggedIn) {
    $notifications = getUnreadNotifications($currentUser['id'], 5);
    $notificationCount = countUnreadNotifications($currentUser['id']);
}

// Générer le token CSRF
$csrfToken = generateCSRFToken();

// Déterminer la page actuelle pour la navigation
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= $currentUser['theme_preference'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <meta name="description" content="Plateforme de défis entre amis - Montrez vos talents et votre créativité">
    <meta name="keywords" content="défis, amis, challenge, créativité, compétition">
    <meta name="author" content="Défi Friends">
    
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/images/favicon.svg">
    <link rel="alternate icon" href="<?= APP_URL ?>/assets/images/favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS personnalisé -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    
    <!-- Meta pour les réseaux sociaux -->
    <meta property="og:title" content="<?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= APP_NAME ?>">
    <meta property="og:description" content="Plateforme de défis entre amis - Montrez vos talents et votre créativité">
    <meta property="og:image" content="<?= APP_URL ?>/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= APP_URL . $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:type" content="website">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= APP_NAME ?>">
    <meta name="twitter:description" content="Plateforme de défis entre amis - Montrez vos talents et votre créativité">
    <meta name="twitter:image" content="<?= APP_URL ?>/assets/images/og-image.jpg">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="<?= APP_URL ?>/assets/css/style.css" as="style">
    <link rel="preload" href="<?= APP_URL ?>/assets/js/app.js" as="script">
</head>
<body data-user-logged-in="<?= $isLoggedIn ? 'true' : 'false' ?>">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(135deg, #631bff 0%, #4607d0 100%); backdrop-filter: blur(10px);">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= APP_URL ?>/index.php">
                <i class="fas fa-trophy me-2 text-warning"></i>
                <span class="gradient-text"><?= APP_NAME ?></span>
            </a>

            <!-- Bouton menu mobile -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Menu principal -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'index' && $currentDir !== 'defis') ? 'active' : '' ?>" href="<?= APP_URL ?>/index.php">
                            <i class="fas fa-home me-1"></i>
                            Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentDir === 'defis' ? 'active' : '' ?>" href="<?= APP_URL ?>/defis/index.php">
                            <i class="fas fa-list me-1"></i>
                            Défis
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentDir === 'amis' ? 'active' : '' ?>" href="<?= APP_URL ?>/amis/index.php">
                            <i class="fas fa-users me-1"></i>
                            Amis
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Barre de recherche -->
                <div class="search-wrapper me-3 d-none d-md-block">
                    <div class="search-container">
                        <input type="text" class="search-bar form-control" placeholder="Rechercher des défis..." aria-label="Rechercher">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>

                <!-- Menu utilisateur -->
                <ul class="navbar-nav">
                    <?php if ($isLoggedIn): ?>
                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if ($notificationCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?= $notificationCount > 9 ? '9+' : $notificationCount ?>
                                        <span class="visually-hidden">notifications non lues</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationsDropdown">
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Notifications</span>
                                    <?php if ($notificationCount > 0): ?>
                                        <small class="badge bg-primary rounded-pill"><?= $notificationCount ?></small>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (empty($notifications)): ?>
                                    <div class="dropdown-item-text text-center py-3 text-muted">
                                        <i class="fas fa-bell-slash fa-2x mb-2"></i><br>
                                        Aucune notification
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <a href="<?= APP_URL ?>/notifications.php?read=<?= $notification['id'] ?>" class="dropdown-item py-2 <?= $notification['lu'] ? '' : 'notification-unread' ?>">
                                            <div class="d-flex">
                                                <div class="notification-icon me-2">
                                                    <?php
                                                    $iconClass = match($notification['type']) {
                                                        'nouveau_defi' => 'fa-plus-circle text-success',
                                                        'participation' => 'fa-user-plus text-primary',
                                                        'commentaire' => 'fa-comment text-info',
                                                        'amitie' => 'fa-heart text-danger',
                                                        'completion' => 'fa-trophy text-warning',
                                                        default => 'fa-bell text-secondary'
                                                    };
                                                    ?>
                                                    <i class="fas <?= $iconClass ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold small"><?= htmlspecialchars($notification['titre']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars(substr($notification['message'], 0, 50)) ?>...</div>
                                                    <div class="text-muted small"><?= formatDate($notification['date_creation']) ?></div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="<?= APP_URL ?>/notifications.php" class="dropdown-item text-center py-2">
                                        <small>Voir toutes les notifications</small>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </li>

                        <!-- Menu créer -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="createDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-plus-circle"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="createDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?= APP_URL ?>/defis/create.php">
                                        <i class="fas fa-plus me-2"></i>
                                        Nouveau défi
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Menu utilisateur -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?= getUserAvatar($currentUser['photo_profil'], $currentUser['pseudo'], 32) ?>" 
                                     alt="Avatar" class="rounded-circle me-2" width="32" height="32">
                                <span class="d-none d-lg-inline"><?= htmlspecialchars($currentUser['pseudo']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?= APP_URL ?>/profile.php">
                                        <i class="fas fa-user me-2"></i>
                                        Mon profil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= APP_URL ?>/profile.php?tab=defis">
                                        <i class="fas fa-trophy me-2"></i>
                                        Mes défis
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" onclick="toggleTheme()">
                                        <i class="fas fa-moon me-2" id="theme-icon"></i>
                                        Changer le thème
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        Déconnexion
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Utilisateur non connecté -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>
                                Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm ms-2" href="<?= APP_URL ?>/auth/register.php">
                                Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Bouton thème (toujours visible) -->
                    <li class="nav-item">
                        <button class="nav-link btn btn-link border-0" onclick="toggleTheme()" id="theme-toggle" aria-label="Changer le thème">
                            <i class="fas fa-moon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Barre de recherche mobile -->
    <div class="search-mobile d-md-none bg-light border-bottom">
        <div class="container py-2">
            <div class="search-wrapper">
                <div class="search-container">
                    <input type="text" class="search-bar form-control" placeholder="Rechercher des défis..." aria-label="Rechercher">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Conteneur pour les messages flash -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="container mt-3">
                <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $_SESSION['flash_type'] === 'success' ? 'check-circle' : ($_SESSION['flash_type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                    <?= htmlspecialchars($_SESSION['flash_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <?php 
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            ?>
        <?php endif; ?>

        <!-- Breadcrumb (si défini) -->
        <?php if (isset($breadcrumb) && !empty($breadcrumb)): ?>
            <div class="container mt-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?= APP_URL ?>/index.php">
                                <i class="fas fa-home me-1"></i>
                                Accueil
                            </a>
                        </li>
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if (isset($item['url'])): ?>
                                <li class="breadcrumb-item">
                                    <a href="<?= $item['url'] ?>"><?= htmlspecialchars($item['label']) ?></a>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?= htmlspecialchars($item['label']) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </div>
        <?php endif; ?>

<script>
// Variables globales JavaScript
window.APP_CONFIG = {
    BASE_URL: '<?= APP_URL ?>',
    CSRF_TOKEN: '<?= $csrfToken ?>',
    USER_LOGGED_IN: <?= $isLoggedIn ? 'true' : 'false' ?>,
    USER_ID: <?= $isLoggedIn ? $currentUser['id'] : 'null' ?>,
    NOTIFICATION_COUNT: <?= $notificationCount ?>
};
</script>