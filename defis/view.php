<?php
// defis/view.php - Affichage détaillé d'un défi

require_once '../config/config.php';

$defi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();

if (!$defi_id) {
    redirect('/defi_friends/defis/index.php');
}

// Récupérer les détails du défi
$defi = fetchOne(
    "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
            u.pseudo as createur_pseudo, u.photo_profil as createur_photo, u.id as createur_id
     FROM defis d
     LEFT JOIN categories c ON d.categorie_id = c.id
     LEFT JOIN utilisateurs u ON d.createur_id = u.id
     WHERE d.id = ? AND d.statut = 'actif'",
    [$defi_id]
);

if (!$defi) {
    $_SESSION['flash_message'] = "Défi non trouvé ou non disponible.";
    $_SESSION['flash_type'] = "error";
    redirect('/defi_friends/defis/index.php');
}

// Enregistrer une vue
recordDefiView($defi_id, $isLoggedIn ? $currentUser['id'] : null);

// Vérifier si l'utilisateur participe déjà
$userParticipation = null;
if ($isLoggedIn) {
    $userParticipation = fetchOne(
        "SELECT * FROM participations WHERE defi_id = ? AND utilisateur_id = ?",
        [$defi_id, $currentUser['id']]
    );
}

// Récupérer les participants
$participants = executeQuery(
    "SELECT p.*, u.pseudo, u.photo_profil, u.id as user_id,
            (SELECT COUNT(*) FROM votes v WHERE v.participation_id = p.id AND v.type_vote = 'like') as likes
     FROM participations p
     JOIN utilisateurs u ON p.utilisateur_id = u.id
     WHERE p.defi_id = ?
     ORDER BY p.date_participation ASC",
    [$defi_id]
);

// Récupérer les commentaires (limités pour l'affichage initial)
$commentaires = executeQuery(
    "SELECT c.*, u.pseudo, u.photo_profil, u.id as user_id
     FROM commentaires c
     JOIN utilisateurs u ON c.utilisateur_id = u.id
     WHERE c.defi_id = ?
     ORDER BY c.date_creation DESC
     LIMIT 10",
    [$defi_id]
);

// Statistiques du défi
$stats = [
    'nb_participants' => count($participants),
    'nb_participants_termines' => count(array_filter($participants, fn($p) => $p['statut'] === 'complete')),
    'nb_commentaires' => fetchValue("SELECT COUNT(*) FROM commentaires WHERE defi_id = ?", [$defi_id]),
    'nb_vues' => $defi['vues'] ?? 0
];

// Vérifier si l'utilisateur peut modifier ce défi
$canEdit = $isLoggedIn && ($currentUser['id'] == $defi['createur_id']);

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    validateCSRF();
    
    // Ajouter un commentaire
    if (isset($_POST['add_comment'])) {
        $commentaire_content = cleanInput($_POST['commentaire']);
        
        if (!empty($commentaire_content) && strlen($commentaire_content) <= 500) {
            $result = executeQuery(
                "INSERT INTO commentaires (utilisateur_id, defi_id, contenu, date_creation) VALUES (?, ?, ?, NOW())",
                [$currentUser['id'], $defi_id, $commentaire_content]
            );
            
            if ($result) {
                // Notifier le créateur du défi si ce n'est pas lui qui commente
                if ($currentUser['id'] != $defi['createur_id']) {
                    createNotification(
                        $defi['createur_id'],
                        'commentaire',
                        'Nouveau commentaire',
                        $currentUser['pseudo'] . ' a commenté votre défi "' . $defi['titre'] . '"',
                        ['url' => '/defi_friends/defis/view.php?id=' . $defi_id],
                        $currentUser['id']
                    );
                }
                
                $_SESSION['flash_message'] = "Commentaire ajouté avec succès !";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Erreur lors de l'ajout du commentaire.";
                $_SESSION['flash_type'] = "danger";
            }
        } else {
            $_SESSION['flash_message'] = "Le commentaire ne peut pas être vide ou dépasser 500 caractères.";
            $_SESSION['flash_type'] = "danger";
        }
        
        redirect('/defi_friends/defis/view.php?id=' . $defi_id);
    }
}

// Titre de la page
$pageTitle = htmlspecialchars($defi['titre']);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Défis', 'url' => '/defi_friends/defis/index.php'],
    ['label' => htmlspecialchars($defi['titre'])]
];

include '../includes/header.php';
?>

<div class="container">
    
    <!-- En-tête du défi -->
    <div class="card border-0 shadow-lg rounded-3 overflow-hidden mb-4">
        <div class="row g-0">
            
            <!-- Image ou placeholder -->
            <div class="col-md-5">
                <?php if ($defi['image_presentation']): ?>
                    <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                         class="img-fluid h-100 w-100" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                         style="object-fit: cover; min-height: 300px;">
                <?php else: ?>
                    <div class="h-100 d-flex justify-content-center align-items-center" 
                         style="background: linear-gradient(135deg, <?= $defi['categorie_couleur'] ?>30, <?= $defi['categorie_couleur'] ?>10); min-height: 300px;">
                        <div class="text-center">
                            <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-5x" 
                               style="color: <?= $defi['categorie_couleur'] ?>; opacity: 0.7;"></i>
                            <div class="mt-3 text-muted">
                                <h6><?= htmlspecialchars($defi['categorie_nom']) ?></h6>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Informations principales -->
            <div class="col-md-7">
                <div class="card-body p-4 h-100 d-flex flex-column">
                    
                    <!-- Badges et actions -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex gap-2 flex-wrap">
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
                            <?php if ($defi['collectif']): ?>
                                <span class="badge rounded-pill text-bg-info">
                                    <i class="fas fa-users me-1"></i>
                                    Collectif
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <button class="dropdown-item" onclick="partagerDefi()">
                                        <i class="fas fa-share me-2"></i>
                                        Partager
                                    </button>
                                </li>
                                <?php if ($canEdit): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="/defi_friends/defis/edit.php?id=<?= $defi_id ?>">
                                            <i class="fas fa-edit me-2"></i>
                                            Modifier
                                        </a>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="supprimerDefi()">
                                            <i class="fas fa-trash me-2"></i>
                                            Supprimer
                                        </button>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Titre -->
                    <h1 class="h2 fw-bold mb-3"><?= htmlspecialchars($defi['titre']) ?></h1>
                    
                    <!-- Créateur et date -->
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?= getUserAvatar($defi['createur_photo'], $defi['createur_pseudo'], 40) ?>" 
                             alt="Avatar" class="rounded-circle me-3" width="40" height="40">
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($defi['createur_pseudo']) ?></div>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Créé <?= formatDate($defi['date_creation']) ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Statistiques rapides -->
                    <div class="row g-3 mb-4">
                        <div class="col-3">
                            <div class="text-center">
                                <div class="h5 mb-0 text-primary"><?= $stats['nb_participants'] ?></div>
                                <small class="text-muted">Participants</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-center">
                                <div class="h5 mb-0 text-success"><?= $stats['nb_participants_termines'] ?></div>
                                <small class="text-muted">Terminés</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-center">
                                <div class="h5 mb-0 text-info"><?= $stats['nb_commentaires'] ?></div>
                                <small class="text-muted">Commentaires</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-center">
                                <div class="h5 mb-0 text-warning"><?= $stats['nb_vues'] ?></div>
                                <small class="text-muted">Vues</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="mt-auto">
                        <?php if (!$isLoggedIn): ?>
                            <div class="d-grid gap-2 d-md-flex">
                                <a href="/defi_friends/auth/login.php" class="btn btn-primary btn-lg flex-fill">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Se connecter pour participer
                                </a>
                            </div>
                        <?php elseif ($currentUser['id'] == $defi['createur_id']): ?>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Vous êtes le créateur de ce défi. Suivez les participations ci-dessous !
                            </div>
                        <?php elseif (!$userParticipation): ?>
                            <div class="d-grid gap-2 d-md-flex">
                                <a href="/defi_friends/defis/participate.php?id=<?= $defi_id ?>" 
                                   class="btn btn-primary btn-lg flex-fill">
                                    <i class="fas fa-play me-2"></i>
                                    Participer à ce défi
                                </a>
                                <button class="btn btn-outline-secondary btn-lg" onclick="ajouterAuxFavoris()">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-<?= $userParticipation['statut'] === 'complete' ? 'success' : 'warning' ?> mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-<?= $userParticipation['statut'] === 'complete' ? 'check-circle' : 'clock' ?> me-2"></i>
                                        <?php if ($userParticipation['statut'] === 'complete'): ?>
                                            <strong>Défi terminé !</strong> Félicitations pour avoir relevé ce défi.
                                        <?php else: ?>
                                            <strong>Vous participez à ce défi</strong> depuis le <?= formatDate($userParticipation['date_participation']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($userParticipation['statut'] !== 'complete'): ?>
                                        <a href="/defi_friends/defis/complete.php?id=<?= $defi_id ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i>
                                            Terminer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        
        <!-- Contenu principal -->
        <div class="col-lg-8">
            
            <!-- Description du défi -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-align-left me-2"></i>
                        Description du défi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="description-content">
                        <?= nl2br(htmlspecialchars($defi['description'])) ?>
                    </div>
                    
                    <!-- Informations supplémentaires -->
                    <?php if ($defi['date_limite'] || $defi['recompense'] || $defi['collectif']): ?>
                        <div class="border-top pt-3 mt-3">
                            <div class="row g-3">
                                <?php if ($defi['date_limite']): ?>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-calendar-times me-2 text-danger"></i>
                                            <div>
                                                <strong>Date limite</strong><br>
                                                <small><?= formatDate($defi['date_limite']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($defi['recompense']): ?>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-gift me-2 text-warning"></i>
                                            <div>
                                                <strong>Récompense</strong><br>
                                                <small><?= htmlspecialchars($defi['recompense']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($defi['collectif']): ?>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-users me-2 text-info"></i>
                                            <div>
                                                <strong>Défi collectif</strong><br>
                                                <small>
                                                    <?= $defi['min_participants'] ?>
                                                    <?= $defi['max_participants'] ? '-' . $defi['max_participants'] : '+' ?>
                                                    participants
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participants -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Participants (<?= count($participants) ?>)
                    </h5>
                    
                    <!-- Filtre des participants -->
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="participantFilter" id="filterAll" checked>
                        <label class="btn btn-outline-primary" for="filterAll">Tous</label>

                        <input type="radio" class="btn-check" name="participantFilter" id="filterActive">
                        <label class="btn btn-outline-primary" for="filterActive">En cours</label>

                        <input type="radio" class="btn-check" name="participantFilter" id="filterCompleted">
                        <label class="btn btn-outline-primary" for="filterCompleted">Terminés</label>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($participants)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                            <h6>Aucun participant pour le moment</h6>
                            <p>Soyez le premier à relever ce défi !</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3" id="participants-list">
                            <?php foreach ($participants as $participant): ?>
                                <div class="col-md-6 participant-item" data-status="<?= $participant['statut'] ?>">
                                    <div class="card border-0 bg-light h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start">
                                                <img src="<?= getUserAvatar($participant['photo_profil'], $participant['pseudo'], 50) ?>" 
                                                     alt="Avatar" class="rounded-circle me-3" width="50" height="50">
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($participant['pseudo']) ?></h6>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <span class="badge <?php 
                                                            echo match($participant['statut']) {
                                                                'en_cours' => 'bg-warning text-dark',
                                                                'complete' => 'bg-success',
                                                                'abandonne' => 'bg-danger',
                                                                default => 'bg-secondary'
                                                            }; 
                                                        ?> me-2">
                                                            <?php 
                                                            echo match($participant['statut']) {
                                                                'en_cours' => 'En cours',
                                                                'complete' => 'Terminé',
                                                                'abandonne' => 'Abandonné',
                                                                default => 'Inconnu'
                                                            };
                                                            ?>
                                                        </span>
                                                        <?php if ($participant['likes'] > 0): ?>
                                                            <span class="text-muted small">
                                                                <i class="fas fa-heart text-danger me-1"></i>
                                                                <?= $participant['likes'] ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        Rejoint <?= formatDate($participant['date_participation']) ?>
                                                        <?php if ($participant['date_completion']): ?>
                                                            <br>
                                                            <i class="fas fa-check me-1"></i>
                                                            Terminé <?= formatDate($participant['date_completion']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                    
                                                    <?php if ($participant['preuve_texte'] && $participant['statut'] === 'complete'): ?>
                                                        <div class="mt-2 p-2 bg-white rounded border-start border-success border-3">
                                                            <small><strong>Commentaire :</strong></small><br>
                                                            <small><?= htmlspecialchars(substr($participant['preuve_texte'], 0, 100)) ?>
                                                            <?= strlen($participant['preuve_texte']) > 100 ? '...' : '' ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section des commentaires -->
            <div class="card border-0 shadow-sm rounded-3" id="comments-container">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        Commentaires (<?= $stats['nb_commentaires'] ?>)
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Formulaire d'ajout de commentaire -->
                    <?php if ($isLoggedIn): ?>
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <div class="d-flex">
                                <img src="<?= getUserAvatar($currentUser['photo_profil'], $currentUser['pseudo'], 40) ?>" 
                                     alt="Avatar" class="rounded-circle me-3" width="40" height="40">
                                <div class="flex-grow-1">
                                    <div class="form-floating mb-2">
                                        <textarea class="form-control" id="commentaire" name="commentaire" 
                                                  placeholder="Votre commentaire..." maxlength="500" rows="3" required></textarea>
                                        <label for="commentaire">Ajouter un commentaire...</label>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <span id="comment-chars">0</span>/500 caractères
                                        </small>
                                        <button type="submit" name="add_comment" class="btn btn-primary" id="submit-comment">
                                            <i class="fas fa-paper-plane me-1"></i>
                                            Publier
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <a href="/defi_friends/auth/login.php" class="text-decoration-none">Connectez-vous</a> 
                            pour ajouter un commentaire.
                        </div>
                    <?php endif; ?>

                    <!-- Liste des commentaires -->
                    <div id="comments-list">
                        <?php if (empty($commentaires)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-comment-dots fa-3x mb-3"></i>
                                <h6>Aucun commentaire pour le moment</h6>
                                <p>Soyez le premier à commenter ce défi !</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($commentaires as $commentaire): ?>
                                <div class="d-flex mb-4" id="comment-<?= $commentaire['id'] ?>">
                                    <img src="<?= getUserAvatar($commentaire['photo_profil'], $commentaire['pseudo'], 40) ?>" 
                                         alt="Avatar" class="rounded-circle me-3" width="40" height="40">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($commentaire['pseudo']) ?></h6>
                                                <small class="text-muted"><?= formatDate($commentaire['date_creation']) ?></small>
                                            </div>
                                            <?php if ($isLoggedIn && ($commentaire['user_id'] == $currentUser['id'] || $canEdit)): ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <button class="dropdown-item text-danger" 
                                                                    onclick="supprimerCommentaire(<?= $commentaire['id'] ?>)">
                                                                <i class="fas fa-trash me-2"></i>
                                                                Supprimer
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($commentaire['contenu'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if ($stats['nb_commentaires'] > 10): ?>
                                <div class="text-center">
                                    <button class="btn btn-outline-primary" onclick="chargerPlusCommentaires()">
                                        <i class="fas fa-chevron-down me-2"></i>
                                        Voir plus de commentaires
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Loading indicator pour les commentaires -->
                    <div id="comments-loading" class="text-center py-3 d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>

                    <!-- Pagination des commentaires -->
                    <div id="comments-pagination" class="mt-3"></div>
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            
            <!-- Défis similaires -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Défis similaires
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $defisSimilaires = executeQuery(
                        "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone,
                                (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants
                         FROM defis d
                         LEFT JOIN categories c ON d.categorie_id = c.id
                         WHERE d.categorie_id = ? AND d.id != ? AND d.statut = 'actif'
                         ORDER BY RAND()
                         LIMIT 3",
                        [$defi['categorie_id'], $defi_id]
                    );
                    ?>
                    
                    <?php if (empty($defisSimilaires)): ?>
                        <p class="text-muted small">Aucun défi similaire trouvé.</p>
                    <?php else: ?>
                        <?php foreach ($defisSimilaires as $similaire): ?>
                            <div class="d-flex mb-3">
                                <div class="me-3">
                                    <i class="fas <?= htmlspecialchars($similaire['categorie_icone']) ?> fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <a href="/defi_friends/defis/view.php?id=<?= $similaire['id'] ?>" 
                                           class="text-decoration-none">
                                            <?= htmlspecialchars(substr($similaire['titre'], 0, 50)) ?>
                                            <?= strlen($similaire['titre']) > 50 ? '...' : '' ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i>
                                        <?= $similaire['nb_participants'] ?> participants
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistiques du créateur -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        À propos du créateur
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= getUserAvatar($defi['createur_photo'], $defi['createur_pseudo'], 80) ?>" 
                         alt="Avatar" class="rounded-circle mb-3" width="80" height="80">
                    <h6 class="fw-bold"><?= htmlspecialchars($defi['createur_pseudo']) ?></h6>
                    
                    <?php
                    $creatorStats = getUserStats($defi['createur_id']);
                    ?>
                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <div class="h6 mb-0 text-primary"><?= $creatorStats['defis_crees'] ?></div>
                            <small class="text-muted">Défis créés</small>
                        </div>
                        <div class="col-6">
                            <div class="h6 mb-0 text-success"><?= $creatorStats['participations_total'] ?></div>
                            <small class="text-muted">Participations</small>
                        </div>
                    </div>
                    
                    <?php if ($isLoggedIn && $currentUser['id'] != $defi['createur_id']): ?>
                        <div class="mt-3">
                            <a href="/defi_friends/amis/add.php?user_id=<?= $defi['createur_id'] ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus me-1"></i>
                                Ajouter en ami
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Compteur de caractères pour les commentaires
    const commentTextarea = document.getElementById('commentaire');
    const commentChars = document.getElementById('comment-chars');
    
    if (commentTextarea && commentChars) {
        commentTextarea.addEventListener('input', function() {
            const length = this.value.length;
            commentChars.textContent = length;
            
            if (length > 450) {
                commentChars.classList.add('text-danger');
            } else if (length > 400) {
                commentChars.classList.add('text-warning');
                commentChars.classList.remove('text-danger');
            } else {
                commentChars.classList.remove('text-warning', 'text-danger');
            }
        });
    }
    
    // Filtrage des participants
    const participantFilters = document.querySelectorAll('input[name="participantFilter"]');
    const participantItems = document.querySelectorAll('.participant-item');
    
    participantFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            const filterValue = this.id.replace('filter', '').toLowerCase();
            
            participantItems.forEach(item => {
                const status = item.getAttribute('data-status');
                
                if (filterValue === 'all' || 
                    (filterValue === 'active' && status === 'en_cours') ||
                    (filterValue === 'completed' && status === 'complete')) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

// Fonctions pour les actions
function partagerDefi() {
    if (navigator.share) {
        navigator.share({
            title: <?= escapeJs($defi['titre']) ?>,
            text: 'Découvrez ce défi sur <?= APP_NAME ?> !',
            url: window.location.href
        });
    } else {
        // Fallback : copier l'URL
        navigator.clipboard.writeText(window.location.href).then(() => {
            DefiApp.notifications.showToast('URL copiée dans le presse-papiers !', 'success');
        });
    }
}

function ajouterAuxFavoris() {
    // Ici vous pourriez implémenter un système de favoris
    DefiApp.notifications.showToast('Fonctionnalité de favoris à venir !', 'info');
}

function supprimerDefi() {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce défi ? Cette action est irréversible.')) {
        // Rediriger vers une page de suppression ou utiliser AJAX
        window.location.href = '/defi_friends/defis/delete.php?id=<?= $defi_id ?>';
    }
}

function supprimerCommentaire(commentId) {
    if (confirm('Supprimer ce commentaire ?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('comment_id', commentId);
        formData.append('csrf_token', window.APP_CONFIG.CSRF_TOKEN);
        
        fetch('/defi_friends/defis/comments.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`comment-${commentId}`).remove();
                DefiApp.notifications.showToast('Commentaire supprimé', 'success');
            } else {
                DefiApp.notifications.showToast(data.message || 'Erreur', 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            DefiApp.notifications.showToast('Erreur lors de la suppression', 'danger');
        });
    }
}

function chargerPlusCommentaires() {
    // Ici vous pourriez implémenter le chargement AJAX de plus de commentaires
    DefiApp.notifications.showToast('Chargement de plus de commentaires...', 'info');
}
</script>

<style>
/* Styles pour la description du défi */
.description-content {
    font-size: 1.1rem;
    line-height: 1.6;
}

/* Animation pour les commentaires */
.comment-highlight {
    animation: highlightComment 3s ease-in-out;
}

@keyframes highlightComment {
    0% { background-color: rgba(99, 27, 255, 0.1); }
    100% { background-color: transparent; }
}

/* Amélioration des cartes de participants */
.participant-item .card {
    transition: all 0.3s ease;
}

.participant-item .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Responsive pour les statistiques */
@media (max-width: 768px) {
    .row.g-3 .col-3 {
        margin-bottom: 1rem;
    }
}

/* Style pour les badges de statut */
.badge {
    font-size: 0.75rem;
}

/* Amélioration du formulaire de commentaire */
.form-floating textarea {
    min-height: 80px;
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
</style>

<?php include '../includes/footer.php'; ?>