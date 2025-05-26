<?php
// includes/footer.php - Pied de page de l'application

// Obtenir l'année actuelle pour le copyright
$currentYear = date('Y');

// Statistiques générales de la plateforme
$totalDefis = fetchValue("SELECT COUNT(*) FROM defis WHERE statut = 'actif'") ?? 0;
$totalUsers = fetchValue("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'actif'") ?? 0;
$totalParticipations = fetchValue("SELECT COUNT(*) FROM participations") ?? 0;
?>

    </main>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="footer-main">
            <div class="container">
                <div class="row g-4">
                    <!-- Informations sur l'application -->
                    <div class="col-lg-4 col-md-6">
                        <div class="footer-section">
                            <h5 class="footer-title">
                                <i class="fas fa-trophy me-2 text-warning"></i>
                                <?= APP_NAME ?>
                            </h5>
                            <p class="footer-description">
                                Rejoignez la communauté des défis entre amis ! Montrez vos talents, 
                                défiez vos amis et découvrez de nouvelles passions dans une 
                                atmosphère bienveillante et créative.
                            </p>
                            
                            <!-- Statistiques de la plateforme -->
                            <div class="footer-stats">
                                <div class="row g-3">
                                    <div class="col-4 text-center">
                                        <div class="stat-number"><?= number_format($totalDefis) ?></div>
                                        <div class="stat-label">Défis</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="stat-number"><?= number_format($totalUsers) ?></div>
                                        <div class="stat-label">Utilisateurs</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="stat-number"><?= number_format($totalParticipations) ?></div>
                                        <div class="stat-label">Participations</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liens rapides -->
                    <div class="col-lg-2 col-md-6">
                        <div class="footer-section">
                            <h6 class="footer-subtitle">Navigation</h6>
                            <ul class="footer-links">
                                <li><a href="<?= APP_URL ?>/index.php">Accueil</a></li>
                                <li><a href="<?= APP_URL ?>/defis/index.php">Explorer les défis</a></li>
                                <?php if (isLoggedIn()): ?>
                                    <li><a href="<?= APP_URL ?>/defis/create.php">Créer un défi</a></li>
                                    <li><a href="<?= APP_URL ?>/amis/index.php">Mes amis</a></li>
                                    <li><a href="<?= APP_URL ?>/profile.php">Mon profil</a></li>
                                <?php else: ?>
                                    <li><a href="<?= APP_URL ?>/auth/login.php">Connexion</a></li>
                                    <li><a href="<?= APP_URL ?>/auth/register.php">Inscription</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Catégories populaires -->
                    <div class="col-lg-3 col-md-6">
                        <div class="footer-section">
                            <h6 class="footer-subtitle">Catégories</h6>
                            <ul class="footer-links">
                                <?php
                                $categories = executeQuery("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre LIMIT 6");
                                foreach ($categories as $category):
                                ?>
                                    <li>
                                        <a href="<?= APP_URL ?>/search.php?categorie=<?= $category['id'] ?>">
                                            <i class="fas <?= $category['icone'] ?> me-1"></i>
                                            <?= htmlspecialchars($category['nom']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Informations de contact et réseaux sociaux -->
                    <div class="col-lg-3 col-md-6">
                        <div class="footer-section">
                            <h6 class="footer-subtitle">Restez connecté</h6>
                            <p class="small text-muted mb-3">
                                Suivez-nous pour ne rien manquer des nouveautés et des défis tendance !
                            </p>
                            
                            <!-- Réseaux sociaux fictifs -->
                            <div class="social-links mb-3">
                                <a href="#" class="social-link" aria-label="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="social-link" aria-label="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="social-link" aria-label="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="social-link" aria-label="Discord">
                                    <i class="fab fa-discord"></i>
                                </a>
                            </div>

                            <!-- Newsletter fictive -->
                            <div class="newsletter-signup">
                                <div class="input-group input-group-sm">
                                    <input type="email" class="form-control" placeholder="Votre email" aria-label="Email pour newsletter">
                                    <button class="btn btn-primary" type="button" disabled>
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Newsletter bientôt disponible</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer bottom -->
        <div class="footer-bottom">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="copyright">
                            <small>
                                &copy; <?= $currentYear ?> <?= APP_NAME ?>. 
                                Projet étudiant - École d'ingénieur.
                                <span class="text-muted">Version <?= APP_VERSION ?></span>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="footer-bottom-links">
                            <a href="#" class="footer-bottom-link">Politique de confidentialité</a>
                            <a href="#" class="footer-bottom-link">Conditions d'utilisation</a>
                            <a href="#" class="footer-bottom-link">Contact</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bouton retour en haut -->
    <button id="backToTop" class="back-to-top" aria-label="Retour en haut">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Modal de confirmation générique -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    Êtes-vous sûr de vouloir effectuer cette action ?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmModalConfirm">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay d-none">
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <div class="mt-2">Chargement en cours...</div>
        </div>
    </div>

    <!-- Scripts JavaScript -->
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Application JS -->
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>

    <!-- Scripts spécifiques à la page (si définis) -->
    <?php if (isset($pageScripts) && !empty($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= APP_URL ?>/assets/js/<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Script inline pour la page (si défini) -->
    <?php if (isset($inlineScript)): ?>
        <script>
            <?= $inlineScript ?>
        </script>
    <?php endif; ?>

    <!-- Analytics (à remplacer par un vrai service en production) -->
    <?php if (!APP_DEBUG): ?>
    <script>
        // Code Google Analytics ou autre service d'analyse
        console.log('Analytics: Page vue - <?= $_SERVER['REQUEST_URI'] ?>');
    </script>
    <?php endif; ?>

    <!-- Service Worker pour PWA (optionnel) -->
    <script>
        // Enregistrer le service worker si disponible
        if ('serviceWorker' in navigator && !<?= APP_DEBUG ? 'true' : 'false' ?>) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= APP_URL ?>/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
    </script>

    <!-- Debug info (seulement en développement) -->
    <?php if (APP_DEBUG): ?>
    <div id="debug-info" class="debug-info">
        <div class="debug-toggle" onclick="this.parentNode.classList.toggle('active')">
            <i class="fas fa-bug"></i>
        </div>
        <div class="debug-content">
            <h6>Informations de debug</h6>
            <small>
                <strong>Page:</strong> <?= basename($_SERVER['PHP_SELF']) ?><br>
                <strong>Utilisateur:</strong> <?= $isLoggedIn ? $currentUser['pseudo'] : 'Non connecté' ?><br>
                <strong>Thème:</strong> <?= $currentUser['theme_preference'] ?? 'light' ?><br>
                <strong>Mémoire:</strong> <?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB<br>
                <strong>Temps:</strong> <?= round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) ?> ms
            </small>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>