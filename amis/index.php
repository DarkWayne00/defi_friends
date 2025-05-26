<?php
// amis/index.php - Gestion des amis

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Mes amis";
$currentUser = getCurrentUser();
$user_id = $currentUser['id'];

// Récupérer l'onglet actuel
$activeTab = $_GET['tab'] ?? 'friends';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $action = $_POST['action'] ?? '';
    $friend_id = isset($_POST['friend_id']) ? intval($_POST['friend_id']) : 0;
    
    if ($action === 'accept' && $friend_id) {
        // Accepter une demande d'amitié
        $result = executeQuery(
            "UPDATE amis SET statut = 'accepte', date_reponse = NOW() 
             WHERE ami_id = ? AND utilisateur_id = ? AND statut = 'en_attente'",
            [$user_id, $friend_id]
        );
        
        if ($result > 0) {
            // Créer la relation inverse
            executeQuery(
                "INSERT IGNORE INTO amis (utilisateur_id, ami_id, statut, date_demande, date_reponse, demandeur_id) 
                 VALUES (?, ?, 'accepte', NOW(), NOW(), ?)",
                [$user_id, $friend_id, $friend_id]
            );
            
            // Notification
            $friend_name = fetchValue("SELECT pseudo FROM utilisateurs WHERE id = ?", [$friend_id]);
            createNotification(
                $friend_id,
                'amitie',
                'Demande d\'amitié acceptée',
                $currentUser['pseudo'] . ' a accepté votre demande d\'amitié !',
                ['url' => '/defi_friends/amis/index.php'],
                $user_id
            );
            
            $_SESSION['flash_message'] = "Demande d'amitié acceptée !";
            $_SESSION['flash_type'] = "success";
        }
    } elseif ($action === 'reject' && $friend_id) {
        // Refuser une demande d'amitié
        $result = executeQuery(
            "UPDATE amis SET statut = 'refuse', date_reponse = NOW() 
             WHERE ami_id = ? AND utilisateur_id = ? AND statut = 'en_attente'",
            [$user_id, $friend_id]
        );
        
        if ($result > 0) {
            $_SESSION['flash_message'] = "Demande d'amitié refusée.";
            $_SESSION['flash_type'] = "info";
        }
    } elseif ($action === 'remove' && $friend_id) {
        // Supprimer un ami
        $result = executeQuery(
            "DELETE FROM amis WHERE ((utilisateur_id = ? AND ami_id = ?) OR (utilisateur_id = ? AND ami_id = ?)) AND statut = 'accepte'",
            [$user_id, $friend_id, $friend_id, $user_id]
        );
        
        if ($result > 0) {
            $_SESSION['flash_message'] = "Ami supprimé de votre liste.";
            $_SESSION['flash_type'] = "info";
        }
    }
    
    redirect('/defi_friends/amis/index.php?tab=' . $activeTab);
}

// Récupérer la liste des amis
$amis = executeQuery(
    "SELECT u.id, u.pseudo, u.photo_profil, u.derniere_connexion, a.date_demande,
            (SELECT COUNT(*) FROM defis WHERE createur_id = u.id AND statut = 'actif') as nb_defis,
            (SELECT COUNT(*) FROM participations WHERE utilisateur_id = u.id AND statut = 'complete') as nb_defis_termines
     FROM amis a
     JOIN utilisateurs u ON (
        CASE 
            WHEN a.utilisateur_id = ? THEN u.id = a.ami_id
            ELSE u.id = a.utilisateur_id
        END
     )
     WHERE (a.utilisateur_id = ? OR a.ami_id = ?) AND a.statut = 'accepte'
     ORDER BY u.derniere_connexion DESC NULLS LAST",
    [$user_id, $user_id, $user_id]
);

// Récupérer les demandes d'amitié en attente (reçues)
$demandes_recues = executeQuery(
    "SELECT u.id, u.pseudo, u.photo_profil, u.date_inscription, a.date_demande,
            (SELECT COUNT(*) FROM defis WHERE createur_id = u.id AND statut = 'actif') as nb_defis
     FROM amis a
     JOIN utilisateurs u ON a.utilisateur_id = u.id
     WHERE a.ami_id = ? AND a.statut = 'en_attente'
     ORDER BY a.date_demande DESC",
    [$user_id]
);

// Récupérer les demandes d'amitié envoyées
$demandes_envoyees = executeQuery(
    "SELECT u.id, u.pseudo, u.photo_profil, a.date_demande
     FROM amis a
     JOIN utilisateurs u ON a.ami_id = u.id
     WHERE a.utilisateur_id = ? AND a.statut = 'en_attente'
     ORDER BY a.date_demande DESC",
    [$user_id]
);

// Récupérer les activités des amis
$activites = getFriendsActivities($user_id, 20);

// Statistiques
$stats = [
    'nb_amis' => count($amis),
    'demandes_recues' => count($demandes_recues),
    'demandes_envoyees' => count($demandes_envoyees)
];

// Breadcrumb
$breadcrumb = [
    ['label' => 'Mes amis']
];

include '../includes/header.php';
?>

<div class="container">
    
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-users me-2 text-primary"></i>
                Mes amis
            </h1>
            <p class="text-muted">Gérez vos relations et découvrez les activités de vos amis.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="/defi_friends/amis/add.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>
                Ajouter un ami
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-primary"><?= $stats['nb_amis'] ?></div>
                    <small class="text-muted">Amis</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-success"><?= $stats['demandes_recues'] ?></div>
                    <small class="text-muted">Demandes reçues</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-warning"><?= $stats['demandes_envoyees'] ?></div>
                    <small class="text-muted">En attente</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <?php
                    $amis_en_ligne = count(array_filter($amis, function($ami) {
                        return $ami['derniere_connexion'] && 
                               (time() - strtotime($ami['derniere_connexion'])) < 300; // 5 minutes
                    }));
                    ?>
                    <div class="h4 mb-1 text-info"><?= $amis_en_ligne ?></div>
                    <small class="text-muted">En ligne</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation par onglets -->
    <ul class="nav nav-tabs mb-4" id="friendsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'friends' ? 'active' : '' ?>" 
                    id="friends-tab" data-bs-toggle="tab" data-bs-target="#friends" 
                    type="button" role="tab">
                <i class="fas fa-users me-2"></i>
                Mes amis (<?= $stats['nb_amis'] ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'requests' ? 'active' : '' ?>" 
                    id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" 
                    type="button" role="tab">
                <i class="fas fa-inbox me-2"></i>
                Demandes reçues 
                <?php if ($stats['demandes_recues'] > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $stats['demandes_recues'] ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'sent' ? 'active' : '' ?>" 
                    id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" 
                    type="button" role="tab">
                <i class="fas fa-paper-plane me-2"></i>
                Demandes envoyées (<?= $stats['demandes_envoyees'] ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'activites' ? 'active' : '' ?>" 
                    id="activites-tab" data-bs-toggle="tab" data-bs-target="#activites" 
                    type="button" role="tab">
                <i class="fas fa-clock me-2"></i>
                Activités
            </button>
        </li>
    </ul>

    <!-- Contenu des onglets -->
    <div class="tab-content" id="friendsTabsContent">
        
        <!-- Onglet Mes amis -->
        <div class="tab-pane fade <?= $activeTab === 'friends' ? 'show active' : '' ?>" 
             id="friends" role="tabpanel" aria-labelledby="friends-tab">
            
            <?php if (empty($amis)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                    <h4>Vous n'avez pas encore d'amis</h4>
                    <p class="text-muted">Commencez par ajouter des amis pour découvrir leurs activités !</p>
                    <a href="/defi_friends/amis/add.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Ajouter un ami
                    </a>
                </div>
            <?php else: ?>
                <!-- Barre de recherche -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="search-friends" 
                                   placeholder="Rechercher parmi vos amis...">
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" id="filter-friends">
                                <option value="all">Tous les amis</option>
                                <option value="online">En ligne</option>
                                <option value="recent">Récemment actifs</option>
                                <option value="active">Très actifs</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row g-4" id="friends-list">
                    <?php foreach ($amis as $ami): ?>
                        <div class="col-md-6 col-lg-4 friend-item" data-pseudo="<?= strtolower($ami['pseudo']) ?>">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-body text-center p-4">
                                    <div class="position-relative d-inline-block mb-3">
                                        <img src="<?= getUserAvatar($ami['photo_profil'], $ami['pseudo'], 80) ?>" 
                                             alt="Avatar" class="rounded-circle" width="80" height="80">
                                        
                                        <!-- Indicateur en ligne -->
                                        <?php 
                                        $is_online = $ami['derniere_connexion'] && 
                                                   (time() - strtotime($ami['derniere_connexion'])) < 300;
                                        ?>
                                        <span class="position-absolute bottom-0 end-0 translate-middle-x 
                                                     badge rounded-pill <?= $is_online ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $is_online ? 'En ligne' : 'Hors ligne' ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="fw-bold mb-2"><?= htmlspecialchars($ami['pseudo']) ?></h5>
                                    
                                    <!-- Statistiques de l'ami -->
                                    <div class="row g-2 mb-3 small text-muted">
                                        <div class="col-6">
                                            <i class="fas fa-trophy me-1"></i>
                                            <?= $ami['nb_defis'] ?> défis
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <?= $ami['nb_defis_termines'] ?> terminés
                                        </div>
                                    </div>
                                    
                                    <!-- Dernière connexion -->
                                    <small class="text-muted d-block mb-3">
                                        <?php if ($ami['derniere_connexion']): ?>
                                            <i class="fas fa-clock me-1"></i>
                                            <?= $is_online ? 'En ligne maintenant' : 'Vu ' . formatDate($ami['derniere_connexion']) ?>
                                        <?php else: ?>
                                            <i class="fas fa-user-plus me-1"></i>
                                            Ami depuis <?= formatDate($ami['date_demande']) ?>
                                        <?php endif; ?>
                                    </small>
                                    
                                    <!-- Actions -->
                                    <div class="d-flex gap-2">
                                        <a href="/defi_friends/profile.php?user_id=<?= $ami['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-user me-1"></i>
                                            Profil
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="/defi_friends/messages/chat.php?user_id=<?= $ami['id'] ?>">
                                                        <i class="fas fa-comment me-2"></i>
                                                        Envoyer un message
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Supprimer cet ami ?')">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="friend_id" value="<?= $ami['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-user-times me-2"></i>
                                                            Supprimer l'ami
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Demandes reçues -->
        <div class="tab-pane fade <?= $activeTab === 'requests' ? 'show active' : '' ?>" 
             id="requests" role="tabpanel" aria-labelledby="requests-tab">
            
            <?php if (empty($demandes_recues)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h4>Aucune demande d'amitié</h4>
                    <p class="text-muted">Vous n'avez pas de nouvelles demandes d'amitié.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($demandes_recues as $demande): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= getUserAvatar($demande['photo_profil'], $demande['pseudo'], 60) ?>" 
                                             alt="Avatar" class="rounded-circle me-3" width="60" height="60">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($demande['pseudo']) ?></h5>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Demande envoyée <?= formatDate($demande['date_demande']) ?>
                                            </small>
                                            <div class="mt-1">
                                                <small class="text-muted">
                                                    <i class="fas fa-trophy me-1"></i>
                                                    <?= $demande['nb_defis'] ?> défis créés
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <form method="POST" class="flex-fill">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="friend_id" value="<?= $demande['id'] ?>">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fas fa-check me-1"></i>
                                                Accepter
                                            </button>
                                        </form>
                                        <form method="POST" class="flex-fill">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="friend_id" value="<?= $demande['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger w-100">
                                                <i class="fas fa-times me-1"></i>
                                                Refuser
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Demandes envoyées -->
        <div class="tab-pane fade <?= $activeTab === 'sent' ? 'show active' : '' ?>" 
             id="sent" role="tabpanel" aria-labelledby="sent-tab">
            
            <?php if (empty($demandes_envoyees)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                    <h4>Aucune demande en attente</h4>
                    <p class="text-muted">Vous n'avez pas de demandes d'amitié en attente de réponse.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($demandes_envoyees as $demande): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= getUserAvatar($demande['photo_profil'], $demande['pseudo'], 60) ?>" 
                                             alt="Avatar" class="rounded-circle me-3" width="60" height="60">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($demande['pseudo']) ?></h5>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Envoyée <?= formatDate($demande['date_demande']) ?>
                                            </small>
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-hourglass-half me-1"></i>
                                                    En attente
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Activités -->
        <div class="tab-pane fade <?= $activeTab === 'activites' ? 'show active' : '' ?>" 
             id="activites" role="tabpanel" aria-labelledby="activites-tab">
            
            <?php if (empty($activites)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                    <h4>Aucune activité récente</h4>
                    <p class="text-muted">Vos amis n'ont pas d'activité récente ou vous n'avez pas encore d'amis.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body">
                        <?php foreach ($activites as $activite): ?>
                            <div class="d-flex align-items-start py-3 border-bottom">
                                <img src="<?= getUserAvatar(null, $activite['user_pseudo'], 40) ?>" 
                                     alt="Avatar" class="rounded-circle me-3" width="40" height="40">
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
                                        <a href="/defi_friends/defis/view.php?id=<?= $activite['item_id'] ?>" 
                                           class="text-decoration-none fw-bold">
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
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recherche d'amis
    const searchInput = document.getElementById('search-friends');
    const filterSelect = document.getElementById('filter-friends');
    const friendItems = document.querySelectorAll('.friend-item');
    
    function filterFriends() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const filterValue = filterSelect ? filterSelect.value : 'all';
        
        friendItems.forEach(item => {
            const pseudo = item.getAttribute('data-pseudo');
            const matchesSearch = pseudo.includes(searchTerm);
            
            let matchesFilter = true;
            if (filterValue === 'online') {
                matchesFilter = item.querySelector('.bg-success') !== null;
            } else if (filterValue === 'recent') {
                // Logique pour filtrer les amis récemment actifs
                const lastSeen = item.querySelector('small:last-of-type').textContent;
                matchesFilter = lastSeen.includes('maintenant') || lastSeen.includes('minutes') || lastSeen.includes('heures');
            } else if (filterValue === 'active') {
                // Logique pour filtrer les amis très actifs (beaucoup de défis)
                const statsText = item.querySelector('.row.g-2').textContent;
                const defisCount = parseInt(statsText.match(/(\d+) défis/)?.[1] || '0');
                matchesFilter = defisCount >= 5;
            }
            
            if (matchesSearch && matchesFilter) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterFriends);
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', filterFriends);
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
    
    // Mettre à jour l'URL lors du changement d'onglet
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(event) {
            const tabId = event.target.getAttribute('data-bs-target').substring(1);
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState(null, '', url);
        });
    });
    
    // Auto-refresh pour les statuts en ligne
    setInterval(function() {
        // Ici on pourrait faire un appel AJAX pour mettre à jour les statuts
        // Pour l'instant, on se contente de recharger la page si l'utilisateur est inactif
        if (document.hidden) {
            return;
        }
        
        // Mise à jour des badges "en ligne" (simulation)
        document.querySelectorAll('.badge.bg-success, .badge.bg-secondary').forEach(badge => {
            // Logique pour mettre à jour le statut en ligne
            // En production, cela devrait être fait via une API
        });
    }, 60000); // Toutes les minutes
});

// Fonction pour actualiser la liste d'amis
function refreshFriendsList() {
    window.location.reload();
}

// Confirmation avant suppression d'ami
function confirmRemoveFriend(friendName) {
    return confirm(`Êtes-vous sûr de vouloir supprimer ${friendName} de votre liste d'amis ?`);
}
</script>

<style>
/* Styles pour la page des amis */
.friend-item {
    transition: all 0.3s ease;
}

.friend-item .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

/* Indicateurs de statut */
.badge.bg-success {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
    }
}

/* Styles pour les activités */
.border-bottom:last-child {
    border-bottom: none !important;
}

/* Animation pour les onglets */
.tab-pane {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 768px) {
    .d-flex.gap-2 {
        flex-direction: column;
    }
    
    .friend-item .card-body {
        padding: 1rem;
    }
}

/* Style pour la recherche */
#search-friends:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(99, 27, 255, 0.25);
}

/* Amélioration des cartes de demandes */
.card-body .d-flex.gap-2 .btn {
    transition: all 0.3s ease;
}

.card-body .d-flex.gap-2 .btn:hover {
    transform: translateY(-2px);
}
</style>

<?php include '../includes/footer.php'; ?>