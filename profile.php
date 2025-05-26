<?php
// profile.php - Page de profil utilisateur

require_once 'config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Mon profil";
$currentUser = getCurrentUser();
$user_id = $currentUser['id'];

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    validateCSRF();
    
    $pseudo = cleanInput($_POST['pseudo']);
    $nom = cleanInput($_POST['nom']);
    $prenom = cleanInput($_POST['prenom']);
    $bio = cleanInput($_POST['bio']);
    
    $errors = [];
    
    // Validation
    if (empty($pseudo) || strlen($pseudo) < 3) {
        $errors[] = "Le pseudo doit contenir au moins 3 caractères.";
    }
    
    if (!validateUsername($pseudo)) {
        $errors[] = "Le pseudo contient des caractères non autorisés.";
    }
    
    // Vérifier l'unicité du pseudo (sauf pour l'utilisateur actuel)
    $existingUser = fetchOne("SELECT id FROM utilisateurs WHERE pseudo = ? AND id != ?", [$pseudo, $user_id]);
    if ($existingUser) {
        $errors[] = "Ce pseudo est déjà utilisé.";
    }
    
    if (strlen($bio) > 500) {
        $errors[] = "La bio ne peut pas dépasser 500 caractères.";
    }
    
    // Traitement de l'upload de photo de profil
    $photoProfile = $currentUser['photo_profil'];
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = processFileUpload($_FILES['photo_profil'], 'profiles', $user_id);
        if ($uploadResult['success']) {
            // Supprimer l'ancienne photo si elle existe
            if ($currentUser['photo_profil'] && file_exists(UPLOAD_PATH . '/' . $currentUser['photo_profil'])) {
                unlink(UPLOAD_PATH . '/' . $currentUser['photo_profil']);
            }
            $photoProfile = $uploadResult['filename'];
        } else {
            $errors[] = $uploadResult['message'];
        }
    }
    
    if (empty($errors)) {
        $result = executeQuery(
            "UPDATE utilisateurs SET pseudo = ?, nom = ?, prenom = ?, bio = ?, photo_profil = ? WHERE id = ?",
            [$pseudo, $nom, $prenom, $bio, $photoProfile, $user_id]
        );
        
        if ($result > 0) {
            $_SESSION['flash_message'] = "Profil mis à jour avec succès !";
            $_SESSION['flash_type'] = "success";
            
            // Recharger les données utilisateur
            $currentUser = fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$user_id]);
        } else {
            $_SESSION['flash_message'] = "Erreur lors de la mise à jour du profil.";
            $_SESSION['flash_type'] = "danger";
        }
        
        redirect('/defi_friends/profile.php');
    }
}

// Récupérer les statistiques de l'utilisateur
$stats = getUserStats($user_id);

// Récupérer les badges de l'utilisateur
$badges = getUserBadges($user_id);

// Récupérer les défis créés par l'utilisateur
$defis_crees = executeQuery(
    "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone,
            (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants
     FROM defis d
     LEFT JOIN categories c ON d.categorie_id = c.id
     WHERE d.createur_id = ?
     ORDER BY d.date_creation DESC",
    [$user_id]
);

// Récupérer les participations de l'utilisateur
$defis_participes = executeQuery(
    "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone,
            p.statut as participation_statut, p.date_participation, p.date_completion,
            u.pseudo as createur_pseudo
     FROM participations p
     JOIN defis d ON p.defi_id = d.id
     LEFT JOIN categories c ON d.categorie_id = c.id
     LEFT JOIN utilisateurs u ON d.createur_id = u.id
     WHERE p.utilisateur_id = ?
     ORDER BY p.date_participation DESC",
    [$user_id]
);

// Récupérer l'onglet actuel
$activeTab = $_GET['tab'] ?? 'overview';

include 'includes/header.php';
?>

<div class="container">
    <!-- En-tête du profil -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-3 text-center text-md-start">
                <img src="<?= getUserAvatar($currentUser['photo_profil'], $currentUser['pseudo'], 120) ?>" 
                     alt="Photo de profil" class="profile-avatar">
            </div>
            <div class="col-md-9 text-center text-md-start mt-3 mt-md-0">
                <div class="profile-info">
                    <h1><?= htmlspecialchars($currentUser['pseudo']) ?></h1>
                    <?php if ($currentUser['nom'] || $currentUser['prenom']): ?>
                        <h5 class="opacity-75 mb-2"><?= htmlspecialchars($currentUser['prenom'] . ' ' . $currentUser['nom']) ?></h5>
                    <?php endif; ?>
                    <?php if ($currentUser['bio']): ?>
                        <p class="profile-bio"><?= htmlspecialchars($currentUser['bio']) ?></p>
                    <?php endif; ?>
                    <p class="mb-3">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Membre depuis <?= formatDate($currentUser['date_inscription']) ?>
                    </p>
                    
                    <!-- Statistiques rapides -->
                    <div class="row g-3 mt-3">
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h4 mb-0"><?= $stats['defis_crees'] ?></div>
                                <small>Défis créés</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h4 mb-0"><?= $stats['participations_total'] ?></div>
                                <small>Participations</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h4 mb-0"><?= $stats['nb_amis'] ?></div>
                                <small>Amis</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation par onglets -->
    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" 
                    id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" 
                    type="button" role="tab" aria-controls="overview">
                <i class="fas fa-chart-line me-2"></i>
                Vue d'ensemble
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'defis' ? 'active' : '' ?>" 
                    id="defis-tab" data-bs-toggle="tab" data-bs-target="#defis" 
                    type="button" role="tab" aria-controls="defis">
                <i class="fas fa-trophy me-2"></i>
                Mes défis
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'participations' ? 'active' : '' ?>" 
                    id="participations-tab" data-bs-toggle="tab" data-bs-target="#participations" 
                    type="button" role="tab" aria-controls="participations">
                <i class="fas fa-users me-2"></i>
                Participations
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'badges' ? 'active' : '' ?>" 
                    id="badges-tab" data-bs-toggle="tab" data-bs-target="#badges" 
                    type="button" role="tab" aria-controls="badges">
                <i class="fas fa-award me-2"></i>
                Badges (<?= count($badges) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>" 
                    id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" 
                    type="button" role="tab" aria-controls="settings">
                <i class="fas fa-cog me-2"></i>
                Paramètres
            </button>
        </li>
    </ul>

    <!-- Contenu des onglets -->
    <div class="tab-content" id="profileTabsContent">
        
        <!-- Onglet Vue d'ensemble -->
        <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" 
             id="overview" role="tabpanel" aria-labelledby="overview-tab">
            
            <div class="row g-4">
                
                <!-- Statistiques détaillées -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Statistiques
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <div class="stat-number text-primary"><?= $stats['defis_crees'] ?></div>
                                        <div class="stat-label">Défis créés</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <div class="stat-number text-success"><?= $stats['participations_terminees'] ?></div>
                                        <div class="stat-label">Défis terminés</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <div class="stat-number text-warning"><?= $stats['participations_en_cours'] ?></div>
                                        <div class="stat-label">En cours</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <div class="stat-number text-info"><?= $stats['taux_reussite'] ?>%</div>
                                        <div class="stat-label">Taux de réussite</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($stats['participations_total'] > 0): ?>
                                <div class="mt-4">
                                    <h6>Progression générale</h6>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= $stats['taux_reussite'] ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?= $stats['participations_terminees'] ?> défis terminés sur <?= $stats['participations_total'] ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Suivi de progression des défis en cours -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-tasks me-2"></i>
                                Défis en cours
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $defis_en_cours = array_filter($defis_participes, function($defi) {
                                return $defi['participation_statut'] === 'en_cours';
                            });
                            ?>
                            
                            <?php if (empty($defis_en_cours)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                                    <p>Tous vos défis sont terminés. Bravo !</p>
                                    <a href="/defi_friends/defis/index.php" class="btn btn-sm btn-primary">
                                        Découvrir de nouveaux défis
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($defis_en_cours, 0, 3) as $defi): ?>
                                    <div class="mb-3 pb-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?= htmlspecialchars($defi['titre']) ?></h6>
                                            <small class="text-muted">
                                                <?php
                                                $days_elapsed = floor((time() - strtotime($defi['date_participation'])) / (60 * 60 * 24));
                                                echo $days_elapsed . ' jour' . ($days_elapsed > 1 ? 's' : '');
                                                ?>
                                            </small>
                                        </div>
                                        
                                        <?php
                                        // Calculer la progression (basée sur le temps écoulé)
                                        $progress = min(100, round(($days_elapsed / 14) * 100));
                                        ?>
                                        <div class="progress mb-2" style="height: 6px;">
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                 style="width: <?= $progress ?>%"></div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="fas <?= $defi['categorie_icone'] ?> me-1"></i>
                                                <?= htmlspecialchars($defi['categorie_nom']) ?>
                                            </small>
                                            <a href="/defi_friends/defis/complete.php?id=<?= $defi['id'] ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check me-1"></i>
                                                Terminer
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($defis_en_cours) > 3): ?>
                                    <div class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="document.getElementById('participations-tab').click()">
                                            Voir tous les défis en cours
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badges récents -->
                <div class="col-md-12">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-medal me-2"></i>
                                Derniers badges obtenus
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($badges)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-award fa-3x mb-3"></i>
                                    <p>Pas encore de badges. Participez à plus de défis pour en gagner !</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach (array_slice($badges, 0, 4) as $badge): ?>
                                        <div class="col-md-3 col-6">
                                            <div class="text-center p-3 rounded-3 bg-light">
                                                <div class="mb-2">
                                                    <i class="fas <?= $badge['icone'] ?> fa-2x" 
                                                       style="color: <?= $badge['couleur'] ?>;"></i>
                                                </div>
                                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($badge['nom']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($badge['description']) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($badges) > 4): ?>
                                    <div class="text-center mt-3">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="document.getElementById('badges-tab').click()">
                                            Voir tous mes badges (<?= count($badges) ?>)
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Onglet Mes défis -->
        <div class="tab-pane fade <?= $activeTab === 'defis' ? 'show active' : '' ?>" 
             id="defis" role="tabpanel" aria-labelledby="defis-tab">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Mes défis créés (<?= count($defis_crees) ?>)</h4>
                <a href="/defi_friends/defis/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    Créer un nouveau défi
                </a>
            </div>

            <?php if (empty($defis_crees)): ?>
                <div class="text-center py-5 bg-light rounded-3">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h4>Aucun défi créé</h4>
                    <p class="text-muted">Commencez par créer votre premier défi !</p>
                    <a href="/defi_friends/defis/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        Créer mon premier défi
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($defis_crees as $defi): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge text-bg-primary">
                                            <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> me-1"></i>
                                            <?= htmlspecialchars($defi['categorie_nom']) ?>
                                        </span>
                                        <span class="badge <?php 
                                            echo match($defi['statut']) {
                                                'actif' => 'text-bg-success',
                                                'termine' => 'text-bg-secondary',
                                                'suspendu' => 'text-bg-warning',
                                                default => 'text-bg-secondary'
                                            }; 
                                        ?>">
                                            <?= ucfirst(htmlspecialchars($defi['statut'])) ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="card-title"><?= htmlspecialchars($defi['titre']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars(substr($defi['description'], 0, 100)) ?>...</p>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i>
                                                <?= $defi['nb_participants'] ?> participant<?= $defi['nb_participants'] > 1 ? 's' : '' ?>
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= formatDate($defi['date_creation']) ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-eye me-1"></i>
                                            Voir
                                        </a>
                                        <?php if ($defi['statut'] === 'actif'): ?>
                                            <a href="/defi_friends/defis/edit.php?id=<?= $defi['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Participations -->
        <div class="tab-pane fade <?= $activeTab === 'participations' ? 'show active' : '' ?>" 
             id="participations" role="tabpanel" aria-labelledby="participations-tab">
            
            <h4 class="mb-4">Mes participations (<?= count($defis_participes) ?>)</h4>

            <?php if (empty($defis_participes)): ?>
                <div class="text-center py-5 bg-light rounded-3">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h4>Aucune participation</h4>
                    <p class="text-muted">Commencez par participer à votre premier défi !</p>
                    <a href="/defi_friends/defis/index.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        Découvrir les défis
                    </a>
                </div>
            <?php else: ?>
                <!-- Filtres -->
                <div class="mb-4">
                    <div class="btn-group" role="group" aria-label="Filtres de statut">
                        <input type="radio" class="btn-check" name="statusFilter" id="all" value="all" checked>
                        <label class="btn btn-outline-primary" for="all">Tous</label>

                        <input type="radio" class="btn-check" name="statusFilter" id="en_cours" value="en_cours">
                        <label class="btn btn-outline-primary" for="en_cours">En cours</label>

                        <input type="radio" class="btn-check" name="statusFilter" id="complete" value="complete">
                        <label class="btn btn-outline-primary" for="complete">Terminés</label>
                    </div>
                </div>

                <div class="row g-4" id="participationsList">
                    <?php foreach ($defis_participes as $defi): ?>
                        <div class="col-md-6 participation-item" data-status="<?= $defi['participation_statut'] ?>">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge text-bg-primary">
                                            <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> me-1"></i>
                                            <?= htmlspecialchars($defi['categorie_nom']) ?>
                                        </span>
                                        <span class="badge <?php 
                                            echo match($defi['participation_statut']) {
                                                'en_cours' => 'text-bg-warning',
                                                'complete' => 'text-bg-success',
                                                'abandonne' => 'text-bg-danger',
                                                default => 'text-bg-secondary'
                                            }; 
                                        ?>">
                                            <?php 
                                            echo match($defi['participation_statut']) {
                                                'en_cours' => 'En cours',
                                                'complete' => 'Terminé',
                                                'abandonne' => 'Abandonné',
                                                default => 'Inconnu'
                                            };
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="card-title"><?= htmlspecialchars($defi['titre']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars(substr($defi['description'], 0, 100)) ?>...</p>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                Par <?= htmlspecialchars($defi['createur_pseudo']) ?>
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= formatDate($defi['date_participation']) ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="/defi_friends/defis/view.php?id=<?= $defi['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-eye me-1"></i>
                                            Voir le défi
                                        </a>
                                        <?php if ($defi['participation_statut'] === 'en_cours'): ?>
                                            <a href="/defi_friends/defis/complete.php?id=<?= $defi['id'] ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check me-1"></i>
                                                Terminer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Badges -->
        <div class="tab-pane fade <?= $activeTab === 'badges' ? 'show active' : '' ?>" 
             id="badges" role="tabpanel" aria-labelledby="badges-tab">
            
            <h4 class="mb-4">Mes badges (<?= count($badges) ?>)</h4>

            <?php if (empty($badges)): ?>
                <div class="text-center py-5 bg-light rounded-3">
                    <i class="fas fa-award fa-3x text-muted mb-3"></i>
                    <h4>Pas encore de badges</h4>
                    <p class="text-muted">Participez à plus de défis pour gagner des badges !</p>
                    <a href="/defi_friends/defis/index.php" class="btn btn-primary">
                        <i class="fas fa-trophy me-2"></i>
                        Découvrir les défis
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($badges as $badge): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card border-0 shadow-sm rounded-3 text-center h-100 badge-card">
                                <div class="card-body">
                                    <div class="badge-icon mb-3">
                                        <i class="fas <?= $badge['icone'] ?> fa-3x" style="color: <?= $badge['couleur'] ?>;"></i>
                                    </div>
                                    <h5 class="card-title fw-bold"><?= htmlspecialchars($badge['nom']) ?></h5>
                                    <p class="card-text text-muted"><?= htmlspecialchars($badge['description']) ?></p>
                                    
                                    <?php if (isset($badge['niveau'])): ?>
                                        <span class="badge rounded-pill" style="background-color: <?= $badge['couleur'] ?>;">
                                            Niveau <?= ucfirst($badge['niveau']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Paramètres -->
        <div class="tab-pane fade <?= $activeTab === 'settings' ? 'show active' : '' ?>" 
             id="settings" role="tabpanel" aria-labelledby="settings-tab">
            
            <h4 class="mb-4">Paramètres du profil</h4>

            <div class="row">
                <div class="col-md-8">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="update_profile" value="1">

                        <!-- Photo de profil -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Photo de profil</h5>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <img src="<?= getUserAvatar($currentUser['photo_profil'], $currentUser['pseudo'], 100) ?>" 
                                             alt="Photo de profil actuelle" class="rounded-circle mb-2" width="100" height="100">
                                    </div>
                                    <div class="col-md-9">
                                        <div class="mb-3">
                                            <label for="photo_profil" class="form-label">Changer la photo</label>
                                            <input type="file" class="form-control" id="photo_profil" name="photo_profil" 
                                                   accept="image/*">
                                            <div class="form-text">
                                                Formats acceptés : JPG, PNG, GIF. Taille maximale : 5MB.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informations personnelles -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informations personnelles</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="pseudo" class="form-label">Pseudo *</label>
                                        <input type="text" class="form-control" id="pseudo" name="pseudo" 
                                               value="<?= htmlspecialchars($currentUser['pseudo']) ?>" 
                                               required minlength="3" maxlength="30">
                                        <div class="invalid-feedback">
                                            Le pseudo doit contenir entre 3 et 30 caractères.
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="prenom" class="form-label">Prénom</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" 
                                               value="<?= htmlspecialchars($currentUser['prenom'] ?? '') ?>" 
                                               maxlength="50">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="nom" class="form-label">Nom</label>
                                        <input type="text" class="form-control" id="nom" name="nom" 
                                               value="<?= htmlspecialchars($currentUser['nom'] ?? '') ?>" 
                                               maxlength="50">
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label for="bio" class="form-label">Biographie</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="4" 
                                                  maxlength="500" placeholder="Parlez-nous de vous..."><?= htmlspecialchars($currentUser['bio'] ?? '') ?></textarea>
                                        <div class="form-text">
                                            <span id="bio-count">0</span>/500 caractères
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>
                                Sauvegarder les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion du filtrage des participations
document.addEventListener('DOMContentLoaded', function() {
    // Filtres de participations
    const statusFilters = document.querySelectorAll('input[name="statusFilter"]');
    const participationItems = document.querySelectorAll('.participation-item');

    statusFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            const selectedStatus = this.value;
            
            participationItems.forEach(item => {
                const itemStatus = item.getAttribute('data-status');
                
                if (selectedStatus === 'all' || selectedStatus === itemStatus) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // Compteur de caractères pour la bio
    const bioTextarea = document.getElementById('bio');
    const bioCount = document.getElementById('bio-count');

    if (bioTextarea && bioCount) {
        function updateBioCount() {
            const count = bioTextarea.value.length;
            bioCount.textContent = count;
            
            if (count > 450) {
                bioCount.classList.add('text-warning');
            } else if (count >= 500) {
                bioCount.classList.remove('text-warning');
                bioCount.classList.add('text-danger');
            } else {
                bioCount.classList.remove('text-warning', 'text-danger');
            }
        }

        bioTextarea.addEventListener('input', updateBioCount);
        updateBioCount(); // Initialiser le compteur
    }

    // Gestion des onglets via URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        const tabButton = document.getElementById(tab + '-tab');
        if (tabButton) {
            tabButton.click();
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>