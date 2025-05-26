<?php
// notifications.php - Page de gestion des notifications

require_once 'config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Notifications";
$currentUser = getCurrentUser();
$user_id = $currentUser['id'];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    if (isset($_POST['mark_all_read'])) {
        // Marquer toutes les notifications comme lues
        executeQuery("UPDATE notifications SET lu = 1 WHERE utilisateur_id = ?", [$user_id]);
        $_SESSION['flash_message'] = "Toutes les notifications ont été marquées comme lues.";
        $_SESSION['flash_type'] = "success";
        redirect('/defi_friends/notifications.php');
    }
    
    if (isset($_POST['delete_notification'])) {
        $notification_id = intval($_POST['notification_id']);
        $result = executeQuery("DELETE FROM notifications WHERE id = ? AND utilisateur_id = ?", [$notification_id, $user_id]);
        
        if ($result > 0) {
            $_SESSION['flash_message'] = "Notification supprimée.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Erreur lors de la suppression.";
            $_SESSION['flash_type'] = "danger";
        }
        redirect('/defi_friends/notifications.php');
    }
    
    if (isset($_POST['delete_all_read'])) {
        // Supprimer toutes les notifications lues
        $result = executeQuery("DELETE FROM notifications WHERE utilisateur_id = ? AND lu = 1", [$user_id]);
        $_SESSION['flash_message'] = "$result notification(s) supprimée(s).";
        $_SESSION['flash_type'] = "success";
        redirect('/defi_friends/notifications.php');
    }
}

// Traitement de la lecture d'une notification spécifique
if (isset($_GET['read'])) {
    $notification_id = intval($_GET['read']);
    $notification = fetchOne("SELECT * FROM notifications WHERE id = ? AND utilisateur_id = ?", [$notification_id, $user_id]);
    
    if ($notification) {
        // Marquer comme lue
        executeQuery("UPDATE notifications SET lu = 1 WHERE id = ?", [$notification_id]);
        
        // Rediriger vers l'URL liée si disponible
        if (!empty($notification['data'])) {
            $data = json_decode($notification['data'], true);
            if (isset($data['url'])) {
                redirect($data['url']);
                exit;
            }
        }
    }
}

// Récupérer les paramètres de filtrage
$filter = $_GET['filter'] ?? 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Construire la requête de base
$whereClause = "WHERE n.utilisateur_id = ?";
$params = [$user_id];

// Appliquer les filtres
switch ($filter) {
    case 'unread':
        $whereClause .= " AND n.lu = 0";
        break;
    case 'read':
        $whereClause .= " AND n.lu = 1";
        break;
    case 'amitie':
        $whereClause .= " AND n.type = 'amitie'";
        break;
    case 'defis':
        $whereClause .= " AND n.type IN ('nouveau_defi', 'participation', 'completion')";
        break;
    case 'commentaires':
        $whereClause .= " AND n.type = 'commentaire'";
        break;
}

// Compter le total pour la pagination
$totalQuery = "SELECT COUNT(*) FROM notifications n $whereClause";
$total = fetchValue($totalQuery, $params);
$totalPages = ceil($total / $limit);

// Récupérer les notifications
$sql = "SELECT n.*, u.pseudo as expediteur_pseudo, u.photo_profil as expediteur_photo
        FROM notifications n
        LEFT JOIN utilisateurs u ON n.expediteur_id = u.id
        $whereClause
        ORDER BY n.date_creation DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$notifications = executeQuery($sql, $params);

// Récupérer les statistiques des notifications
$stats = getNotificationStats($user_id);
$totalUnread = countUnreadNotifications($user_id);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Notifications']
];

include 'includes/header.php';
?>

<div class="container">
    
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-bell me-2"></i>
                Notifications
                <?php if ($totalUnread > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $totalUnread ?></span>
                <?php endif; ?>
            </h1>
            <p class="text-muted">Gérez vos notifications et restez informé des dernières activités.</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($totalUnread > 0): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                        <i class="fas fa-check-double me-2"></i>
                        Tout marquer comme lu
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-primary"><?= $total ?></div>
                    <small class="text-muted">Total</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-danger"><?= $totalUnread ?></div>
                    <small class="text-muted">Non lues</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-success"><?= $stats['amitie'] ?? 0 ?></div>
                    <small class="text-muted">Amitié</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-info"><?= ($stats['nouveau_defi'] ?? 0) + ($stats['participation'] ?? 0) + ($stats['completion'] ?? 0) ?></div>
                    <small class="text-muted">Défis</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-warning"><?= $stats['commentaire'] ?? 0 ?></div>
                    <small class="text-muted">Commentaires</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <form method="POST" onsubmit="return confirm('Supprimer toutes les notifications lues ?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" name="delete_all_read" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-trash me-1"></i>
                            Nettoyer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap gap-2">
                <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-list me-1"></i>
                    Toutes (<?= $total ?>)
                </a>
                <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-circle me-1"></i>
                    Non lues (<?= $totalUnread ?>)
                </a>
                <a href="?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-check-circle me-1"></i>
                    Lues
                </a>
                <a href="?filter=amitie" class="btn btn-sm <?= $filter === 'amitie' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-heart me-1"></i>
                    Amitié (<?= $stats['amitie'] ?? 0 ?>)
                </a>
                <a href="?filter=defis" class="btn btn-sm <?= $filter === 'defis' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-trophy me-1"></i>
                    Défis
                </a>
                <a href="?filter=commentaires" class="btn btn-sm <?= $filter === 'commentaires' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-comment me-1"></i>
                    Commentaires (<?= $stats['commentaire'] ?? 0 ?>)
                </a>
            </div>
        </div>
    </div>

    <!-- Liste des notifications -->
    <?php if (empty($notifications)): ?>
        <div class="text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <h4>Aucune notification</h4>
            <p class="text-muted">
                <?php if ($filter === 'all'): ?>
                    Vous n'avez aucune notification pour le moment.
                <?php else: ?>
                    Aucune notification ne correspond à ce filtre.
                <?php endif; ?>
            </p>
            <?php if ($filter !== 'all'): ?>
                <a href="/defi_friends/notifications.php" class="btn btn-primary">
                    <i class="fas fa-list me-2"></i>
                    Voir toutes les notifications
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <?php foreach ($notifications as $index => $notification): ?>
                    <div class="notification-item p-3 <?= !$notification['lu'] ? 'notification-unread' : '' ?> <?= $index < count($notifications) - 1 ? 'border-bottom' : '' ?>">
                        <div class="row align-items-start">
                            
                            <!-- Icône et avatar -->
                            <div class="col-auto">
                                <div class="position-relative">
                                    <?php if ($notification['expediteur_id']): ?>
                                        <img src="<?= getUserAvatar($notification['expediteur_photo'], $notification['expediteur_pseudo'], 50) ?>" 
                                             alt="Avatar" class="rounded-circle" width="50" height="50">
                                    <?php else: ?>
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <i class="fas fa-bell text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Icône de type de notification -->
                                    <div class="position-absolute bottom-0 end-0">
                                        <div class="notification-type-icon rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 20px; height: 20px; background-color: <?php
                                                echo match($notification['type']) {
                                                    'nouveau_defi' => '#28a745',
                                                    'participation' => '#007bff',
                                                    'commentaire' => '#17a2b8',
                                                    'amitie' => '#dc3545',
                                                    'completion' => '#ffc107',
                                                    'mention' => '#6f42c1',
                                                    default => '#6c757d'
                                                };
                                             ?>;">
                                            <i class="fas <?php
                                                echo match($notification['type']) {
                                                    'nouveau_defi' => 'fa-plus',
                                                    'participation' => 'fa-user-plus',
                                                    'commentaire' => 'fa-comment',
                                                    'amitie' => 'fa-heart',
                                                    'completion' => 'fa-trophy',
                                                    'mention' => 'fa-at',
                                                    default => 'fa-bell'
                                                };
                                             ?> text-white" style="font-size: 10px;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contenu de la notification -->
                            <div class="col">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 fw-bold <?= !$notification['lu'] ? 'text-primary' : '' ?>">
                                        <?= htmlspecialchars($notification['titre']) ?>
                                    </h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!$notification['lu']): ?>
                                            <span class="badge bg-primary rounded-pill">Nouveau</span>
                                        <?php endif; ?>
                                        <small class="text-muted"><?= formatDate($notification['date_creation']) ?></small>
                                    </div>
                                </div>
                                
                                <p class="mb-2 text-muted"><?= htmlspecialchars($notification['message']) ?></p>
                                
                                <!-- Actions de notification -->
                                <div class="d-flex gap-2 align-items-center">
                                    <?php
                                    $data = $notification['data'] ? json_decode($notification['data'], true) : null;
                                    if ($data && isset($data['url'])):
                                    ?>
                                        <a href="<?= htmlspecialchars($data['url']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-1"></i>
                                            Voir
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$notification['lu']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check me-1"></i>
                                                Marquer comme lue
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette notification ?')">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                        <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash me-1"></i>
                                            Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Pagination des notifications" class="mt-4">
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
            sur <?= $total ?> notification<?= $total > 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<style>
.notification-item {
    transition: all 0.3s ease;
}

.notification-item:hover {
    background-color: var(--bg-secondary);
}

.notification-unread {
    background-color: rgba(99, 27, 255, 0.05);
    border-left: 4px solid var(--primary-color);
}

.notification-type-icon {
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Animation pour les nouvelles notifications */
@keyframes notificationPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.notification-unread .notification-type-icon {
    animation: notificationPulse 2s ease-in-out infinite;
}
</style>

<script>
// Auto-refresh des notifications toutes les 30 secondes
setInterval(function() {
    // Vérifier s'il y a de nouvelles notifications
    fetch('/defi_friends/api/notification_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > <?= $totalUnread ?>) {
                // Afficher une indication de nouvelles notifications
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-info alert-dismissible fade show position-fixed';
                alertDiv.style.cssText = 'top: 100px; right: 20px; z-index: 1050; max-width: 300px;';
                alertDiv.innerHTML = `
                    <i class="fas fa-bell me-2"></i>
                    Vous avez de nouvelles notifications !
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(alertDiv);
                
                // Supprimer automatiquement après 5 secondes
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        })
        .catch(error => console.error('Erreur lors de la vérification des notifications:', error));
}, 30000);

// Marquer automatiquement les notifications comme lues après 3 secondes de survol
document.querySelectorAll('.notification-unread').forEach(notification => {
    let hoverTimer;
    
    notification.addEventListener('mouseenter', function() {
        hoverTimer = setTimeout(() => {
            // Marquer comme lue automatiquement
            const notificationId = this.querySelector('input[name="notification_id"]')?.value;
            if (notificationId) {
                fetch('/defi_friends/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `csrf_token=${window.APP_CONFIG.CSRF_TOKEN}&notification_id=${notificationId}&mark_read=1`
                }).then(() => {
                    this.classList.remove('notification-unread');
                    const badge = this.querySelector('.badge.bg-primary');
                    if (badge) badge.remove();
                });
            }
        }, 3000);
    });
    
    notification.addEventListener('mouseleave', function() {
        if (hoverTimer) {
            clearTimeout(hoverTimer);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>