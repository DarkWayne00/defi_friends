<?php
// defis/create.php - Créer un nouveau défi

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Créer un défi";
$currentUser = getCurrentUser();
$errors = [];

// Récupérer les catégories
$categories = getActiveCategories();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    // Vérification du rate limiting
    if (!rateLimitCheck('create_defi', 5, 3600)) {
        $errors[] = "Trop de défis créés récemment. Veuillez attendre avant d'en créer un nouveau.";
    } else {
        $titre = cleanInput($_POST['titre']);
        $description = cleanInput($_POST['description']);
        $difficulte = cleanInput($_POST['difficulte']);
        $categorie_id = intval($_POST['categorie_id']);
        $date_limite = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;
        $recompense = cleanInput($_POST['recompense']);
        $collectif = isset($_POST['defi_collectif']) ? 1 : 0;
        $min_participants = $collectif ? max(2, intval($_POST['min_participants'] ?? 2)) : 1;
        $max_participants = $collectif && !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null;
        
        // Validation du titre
        if (empty($titre)) {
            $errors[] = "Le titre est requis.";
        } elseif (strlen($titre) < 10) {
            $errors[] = "Le titre doit contenir au moins 10 caractères.";
        } elseif (strlen($titre) > 200) {
            $errors[] = "Le titre ne peut pas dépasser 200 caractères.";
        }
        
        // Validation de la description
        if (empty($description)) {
            $errors[] = "La description est requise.";
        } elseif (strlen($description) < 50) {
            $errors[] = "La description doit contenir au moins 50 caractères.";
        } elseif (strlen($description) > 2000) {
            $errors[] = "La description ne peut pas dépasser 2000 caractères.";
        }
        
        // Validation de la difficulté
        $difficulties_allowed = ['facile', 'moyen', 'difficile', 'extreme'];
        if (!in_array($difficulte, $difficulties_allowed)) {
            $errors[] = "Difficulté invalide.";
        }
        
        // Validation de la catégorie
        $valid_category = fetchOne("SELECT id FROM categories WHERE id = ? AND actif = 1", [$categorie_id]);
        if (!$valid_category) {
            $errors[] = "Catégorie invalide.";
        }
        
        // Validation de la date limite (optionnelle)
        if ($date_limite) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $date_limite);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $date_limite) {
                $errors[] = "Format de date invalide.";
            } elseif ($date_obj <= new DateTime()) {
                $errors[] = "La date limite doit être dans le futur.";
            } elseif ($date_obj > (new DateTime())->add(new DateInterval('P1Y'))) {
                $errors[] = "La date limite ne peut pas dépasser un an.";
            }
        }
        
        // Validation de la récompense (optionnelle)
        if (!empty($recompense) && strlen($recompense) > 255) {
            $errors[] = "La récompense ne peut pas dépasser 255 caractères.";
        }
        
        // Validation des participants pour défis collectifs
        if ($collectif) {
            if ($min_participants < 2 || $min_participants > 20) {
                $errors[] = "Le nombre minimum de participants doit être entre 2 et 20.";
            }
            if ($max_participants && ($max_participants < $min_participants || $max_participants > 50)) {
                $errors[] = "Le nombre maximum de participants doit être supérieur au minimum et ne pas dépasser 50.";
            }
        }
        
        // Traitement de l'image
        $image_filename = null;
        if (isset($_FILES['image_presentation']) && $_FILES['image_presentation']['error'] === UPLOAD_ERR_OK) {
            $upload_result = processFileUpload($_FILES['image_presentation'], 'defis', $currentUser['id']);
            if ($upload_result['success']) {
                $image_filename = $upload_result['filename'];
            } else {
                $errors[] = $upload_result['message'];
            }
        }
        
        // Vérifier l'unicité du titre pour cet utilisateur
        $existing_defi = fetchOne(
            "SELECT id FROM defis WHERE titre = ? AND createur_id = ? AND statut = 'actif'",
            [$titre, $currentUser['id']]
        );
        if ($existing_defi) {
            $errors[] = "Vous avez déjà créé un défi avec ce titre.";
        }
        
        // Si pas d'erreurs, créer le défi
        if (empty($errors)) {
            try {
                $result = executeQuery(
                    "INSERT INTO defis (titre, description, difficulte, categorie_id, createur_id, image_presentation, 
                                       date_limite, recompense, collectif, min_participants, max_participants, date_creation) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$titre, $description, $difficulte, $categorie_id, $currentUser['id'], $image_filename,
                     $date_limite, $recompense, $collectif, $min_participants, $max_participants]
                );
                
                if ($result) {
                    $defi_id = getLastInsertId();
                    
                    // Créer des notifications pour les amis
                    $amis = executeQuery(
                        "SELECT CASE WHEN utilisateur_id = ? THEN ami_id ELSE utilisateur_id END as ami_id
                         FROM amis WHERE (utilisateur_id = ? OR ami_id = ?) AND statut = 'accepte'",
                        [$currentUser['id'], $currentUser['id'], $currentUser['id']]
                    );
                    
                    foreach ($amis as $ami) {
                        createNotification(
                            $ami['ami_id'],
                            'nouveau_defi',
                            'Nouveau défi créé !',
                            $currentUser['pseudo'] . ' a créé un nouveau défi : "' . $titre . '"',
                            ['url' => '/defi_friends/defis/view.php?id=' . $defi_id],
                            $currentUser['id']
                        );
                    }
                    
                    $_SESSION['flash_message'] = "Défi créé avec succès !";
                    $_SESSION['flash_type'] = "success";
                    
                    redirect('/defi_friends/defis/view.php?id=' . $defi_id);
                } else {
                    $errors[] = "Erreur lors de la création du défi. Veuillez réessayer.";
                }
            } catch (Exception $e) {
                logError('Erreur lors de la création du défi', [
                    'user_id' => $currentUser['id'],
                    'titre' => $titre,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "Une erreur inattendue s'est produite. Veuillez réessayer.";
            }
        }
    }
}

// Breadcrumb
$breadcrumb = [
    ['label' => 'Défis', 'url' => '/defi_friends/defis/index.php'],
    ['label' => 'Créer un défi']
];

include '../includes/header.php';
?>

<div class="container">
    
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-plus-circle me-2 text-primary"></i>
                Créer un nouveau défi
            </h1>
            <p class="text-muted">
                Proposez un défi créatif à la communauté et voyez qui relèvera le challenge !
            </p>
        </div>
        <div class="col-md-4 text-end">
            <a href="/defi_friends/defis/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Retour aux défis
            </a>
        </div>
    </div>

    <!-- Conseils pour créer un bon défi -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <i class="fas fa-lightbulb me-2 text-warning"></i>
                Conseils pour créer un défi réussi
            </h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                        <div>
                            <strong>Soyez clair</strong><br>
                            <small class="text-muted">Titre et description précis</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-users text-primary me-2 mt-1"></i>
                        <div>
                            <strong>Pensez inclusif</strong><br>
                            <small class="text-muted">Accessible au plus grand nombre</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-star text-warning me-2 mt-1"></i>
                        <div>
                            <strong>Soyez créatif</strong><br>
                            <small class="text-muted">Originalité et fun</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Formulaire de création -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-primary text-white p-3">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Informations du défi
                    </h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Veuillez corriger les erreurs suivantes :</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="defi-form">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Titre -->
                        <div class="mb-4">
                            <label for="titre" class="form-label fw-bold">
                                <i class="fas fa-heading me-2"></i>
                                Titre du défi *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="titre" name="titre" 
                                   value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" 
                                   required minlength="10" maxlength="200" 
                                   placeholder="Ex: Cuisiner un plat avec 3 ingrédients seulement">
                            <div class="form-text">
                                <span id="titre-count">0</span>/200 caractères (minimum 10)
                            </div>
                            <div class="invalid-feedback">
                                Le titre doit contenir entre 10 et 200 caractères.
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold">
                                <i class="fas fa-align-left me-2"></i>
                                Description détaillée *
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="6" 
                                      required minlength="50" maxlength="2000"
                                      placeholder="Décrivez précisément votre défi : règles, objectifs, contraintes, conseils..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="form-text">
                                <span id="description-count">0</span>/2000 caractères (minimum 50)
                            </div>
                            <div class="invalid-feedback">
                                La description doit contenir entre 50 et 2000 caractères.
                            </div>
                        </div>

                        <!-- Catégorie et Difficulté -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="categorie_id" class="form-label fw-bold">
                                    <i class="fas fa-tags me-2"></i>
                                    Catégorie *
                                </label>
                                <select class="form-select form-select-lg" id="categorie_id" name="categorie_id" required>
                                    <option value="">Choisir une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= ($_POST['categorie_id'] ?? '') == $category['id'] ? 'selected' : '' ?>
                                                data-icon="<?= $category['icone'] ?>"
                                                data-color="<?= $category['couleur'] ?>">
                                            <?= htmlspecialchars($category['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez choisir une catégorie.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="difficulte" class="form-label fw-bold">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Niveau de difficulté *
                                </label>
                                <select class="form-select form-select-lg" id="difficulte" name="difficulte" required>
                                    <option value="">Choisir la difficulté</option>
                                    <option value="facile" <?= ($_POST['difficulte'] ?? '') === 'facile' ? 'selected' : '' ?>>
                                        🟢 Facile - Accessible à tous
                                    </option>
                                    <option value="moyen" <?= ($_POST['difficulte'] ?? '') === 'moyen' ? 'selected' : '' ?>>
                                        🟡 Moyen - Quelques compétences requises
                                    </option>
                                    <option value="difficile" <?= ($_POST['difficulte'] ?? '') === 'difficile' ? 'selected' : '' ?>>
                                        🟠 Difficile - Compétences avancées
                                    </option>
                                    <option value="extreme" <?= ($_POST['difficulte'] ?? '') === 'extreme' ? 'selected' : '' ?>>
                                        🔴 Extrême - Pour les experts uniquement
                                    </option>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez choisir un niveau de difficulté.
                                </div>
                            </div>
                        </div>

                        <!-- Image de présentation -->
                        <div class="mb-4">
                            <label for="image_presentation" class="form-label fw-bold">
                                <i class="fas fa-image me-2"></i>
                                Image de présentation (optionnel)
                            </label>
                            <input type="file" class="form-control" id="image_presentation" name="image_presentation" 
                                   accept="image/*">
                            <div class="form-text">
                                Formats acceptés : JPG, PNG, GIF, WebP. Taille maximale : 5MB.
                            </div>
                            <div id="image-preview" class="mt-3 d-none">
                                <img src="#" alt="Aperçu" class="img-fluid rounded" style="max-height: 200px;">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="remove-image">
                                    <i class="fas fa-times me-1"></i>
                                    Supprimer l'image
                                </button>
                            </div>
                        </div>

                        <!-- Options avancées -->
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-header bg-transparent">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-cogs me-2"></i>
                                    Options avancées
                                </h6>
                            </div>
                            <div class="card-body">
                                
                                <!-- Défi collectif -->
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="defi_collectif" name="defi_collectif"
                                           <?= isset($_POST['defi_collectif']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="defi_collectif">
                                        <i class="fas fa-users me-2"></i>
                                        Défi collectif
                                    </label>
                                    <div class="form-text">
                                        Les défis collectifs peuvent être réalisés en équipe avec vos amis.
                                    </div>
                                </div>

                                <div id="options_collectif" class="<?= isset($_POST['defi_collectif']) ? '' : 'd-none' ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="min_participants" class="form-label">Participants minimum</label>
                                            <input type="number" class="form-control" id="min_participants" name="min_participants" 
                                                   min="2" max="20" value="<?= $_POST['min_participants'] ?? 2 ?>">
                                            <div class="form-text">Entre 2 et 20 participants.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="max_participants" class="form-label">Participants maximum (optionnel)</label>
                                            <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                                   min="2" max="50" value="<?= $_POST['max_participants'] ?? '' ?>"
                                                   placeholder="Aucune limite">
                                            <div class="form-text">Laisser vide pour aucune limite.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Date limite -->
                                <div class="mb-3">
                                    <label for="date_limite" class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Date limite (optionnel)
                                    </label>
                                    <input type="date" class="form-control" id="date_limite" name="date_limite" 
                                           value="<?= $_POST['date_limite'] ?? '' ?>"
                                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                           max="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                                    <div class="form-text">
                                        Après cette date, le défi ne pourra plus recevoir de nouvelles participations.
                                    </div>
                                </div>

                                <!-- Récompense -->
                                <div class="mb-3">
                                    <label for="recompense" class="form-label fw-bold">
                                        <i class="fas fa-gift me-2"></i>
                                        Récompense promise (optionnel)
                                    </label>
                                    <input type="text" class="form-control" id="recompense" name="recompense" 
                                           value="<?= htmlspecialchars($_POST['recompense'] ?? '') ?>"
                                           maxlength="255" placeholder="Ex: Badge spécial, reconnaissance publique...">
                                    <div class="form-text">
                                        Décrivez ce que gagneront les participants qui réussiront le défi.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg flex-fill">
                                <i class="fas fa-rocket me-2"></i>
                                Créer le défi
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>
                                Réinitialiser
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Aperçu du défi -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top: 100px;">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-success text-white p-3">
                        <h5 class="mb-0">
                            <i class="fas fa-eye me-2"></i>
                            Aperçu du défi
                        </h5>
                    </div>
                    <div class="card-body p-0" id="defi-preview">
                        
                        <!-- Image de prévisualisation -->
                        <div id="preview-image" class="d-none">
                            <img src="#" alt="Image du défi" class="w-100" style="height: 200px; object-fit: cover;">
                        </div>
                        <div id="preview-placeholder" class="d-flex justify-content-center align-items-center bg-light" 
                             style="height: 200px;">
                            <div class="text-center text-muted">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <div>Aperçu de l'image</div>
                            </div>
                        </div>

                        <div class="p-4">
                            <!-- Badges -->
                            <div class="d-flex justify-content-between mb-3">
                                <span id="preview-category" class="badge rounded-pill text-bg-primary">
                                    <i class="fas fa-tag me-1"></i>
                                    Catégorie
                                </span>
                                <span id="preview-difficulty" class="badge rounded-pill text-bg-secondary">
                                    Difficulté
                                </span>
                            </div>

                            <!-- Titre -->
                            <h5 id="preview-title" class="fw-bold mb-3 text-muted">
                                Titre de votre défi...
                            </h5>

                            <!-- Description -->
                            <p id="preview-description" class="text-muted">
                                La description de votre défi apparaîtra ici...
                            </p>

                            <!-- Métadonnées -->
                            <div class="border-top pt-3">
                                <div class="row g-2 small text-muted">
                                    <div class="col-6">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars($currentUser['pseudo']) ?>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-calendar me-1"></i>
                                        Maintenant
                                    </div>
                                    <div class="col-6" id="preview-deadline">
                                        <i class="fas fa-clock me-1"></i>
                                        Pas de limite
                                    </div>
                                    <div class="col-6" id="preview-collective">
                                        <i class="fas fa-user me-1"></i>
                                        Individuel
                                    </div>
                                </div>
                                
                                <div id="preview-reward" class="mt-2 d-none">
                                    <div class="badge bg-warning text-dark">
                                        <i class="fas fa-gift me-1"></i>
                                        <span id="preview-reward-text"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bouton d'action -->
                            <div class="mt-3">
                                <button class="btn btn-primary w-100" disabled>
                                    <i class="fas fa-play me-2"></i>
                                    Participer au défi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques de l'utilisateur -->
                <div class="card border-0 shadow-sm rounded-3 mt-4">
                    <div class="card-body text-center">
                        <h6 class="fw-bold mb-3">Vos statistiques</h6>
                        <?php
                        $userStats = getUserStats($currentUser['id']);
                        ?>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="h5 mb-0 text-primary"><?= $userStats['defis_crees'] ?></div>
                                <small class="text-muted">Défis créés</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 mb-0 text-success"><?= $userStats['participations_total'] ?></div>
                                <small class="text-muted">Participations</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments du formulaire
    const titre = document.getElementById('titre');
    const description = document.getElementById('description');
    const categorieSelect = document.getElementById('categorie_id');
    const difficulteSelect = document.getElementById('difficulte');
    const dateLimite = document.getElementById('date_limite');
    const recompense = document.getElementById('recompense');
    const collectifCheckbox = document.getElementById('defi_collectif');
    const minParticipants = document.getElementById('min_participants');
    const maxParticipants = document.getElementById('max_participants');
    const imageInput = document.getElementById('image_presentation');
    
    // Éléments de l'aperçu
    const previewTitle = document.getElementById('preview-title');
    const previewDescription = document.getElementById('preview-description');
    const previewCategory = document.getElementById('preview-category');
    const previewDifficulty = document.getElementById('preview-difficulty');
    const previewDeadline = document.getElementById('preview-deadline');
    const previewCollective = document.getElementById('preview-collective');
    const previewReward = document.getElementById('preview-reward');
    const previewRewardText = document.getElementById('preview-reward-text');
    const previewImage = document.getElementById('preview-image');
    const previewPlaceholder = document.getElementById('preview-placeholder');
    
    // Compteurs de caractères
    const titreCount = document.getElementById('titre-count');
    const descriptionCount = document.getElementById('description-count');
    
    // Gestion des compteurs de caractères
    function updateCharCount(input, counter, maxLength) {
        input.addEventListener('input', function() {
            const length = this.value.length;
            counter.textContent = length;
            
            if (length > maxLength * 0.9) {
                counter.classList.add('text-warning');
            } else if (length >= maxLength) {
                counter.classList.remove('text-warning');
                counter.classList.add('text-danger');
            } else {
                counter.classList.remove('text-warning', 'text-danger');
            }
        });
    }
    
    updateCharCount(titre, titreCount, 200);
    updateCharCount(description, descriptionCount, 2000);
    
    // Mise à jour de l'aperçu en temps réel
    function updatePreview() {
        // Titre
        if (titre.value.trim()) {
            previewTitle.textContent = titre.value;
            previewTitle.classList.remove('text-muted');
        } else {
            previewTitle.textContent = 'Titre de votre défi...';
            previewTitle.classList.add('text-muted');
        }
        
        // Description
        if (description.value.trim()) {
            let desc = description.value;
            if (desc.length > 150) {
                desc = desc.substring(0, 150) + '...';
            }
            previewDescription.textContent = desc;
            previewDescription.classList.remove('text-muted');
        } else {
            previewDescription.textContent = 'La description de votre défi apparaîtra ici...';
            previewDescription.classList.add('text-muted');
        }
        
        // Catégorie
        const selectedCategory = categorieSelect.options[categorieSelect.selectedIndex];
        if (selectedCategory.value) {
            const icon = selectedCategory.dataset.icon || 'fa-tag';
            previewCategory.innerHTML = `<i class="fas ${icon} me-1"></i>${selectedCategory.textContent}`;
            previewCategory.className = 'badge rounded-pill text-bg-primary';
        } else {
            previewCategory.innerHTML = '<i class="fas fa-tag me-1"></i>Catégorie';
            previewCategory.className = 'badge rounded-pill text-bg-secondary';
        }
        
        // Difficulté
        if (difficulteSelect.value) {
            const difficultyMap = {
                'facile': { text: 'Facile', class: 'text-bg-success' },
                'moyen': { text: 'Moyen', class: 'text-bg-warning' },
                'difficile': { text: 'Difficile', class: 'text-bg-danger' },
                'extreme': { text: 'Extrême', class: 'text-bg-dark' }
            };
            const diff = difficultyMap[difficulteSelect.value];
            previewDifficulty.textContent = diff.text;
            previewDifficulty.className = `badge rounded-pill ${diff.class}`;
        } else {
            previewDifficulty.textContent = 'Difficulté';
            previewDifficulty.className = 'badge rounded-pill text-bg-secondary';
        }
        
        // Date limite
        if (dateLimite.value) {
            const date = new Date(dateLimite.value);
            previewDeadline.innerHTML = `<i class="fas fa-clock me-1"></i>Limite: ${date.toLocaleDateString('fr-FR')}`;
        } else {
            previewDeadline.innerHTML = '<i class="fas fa-clock me-1"></i>Pas de limite';
        }
        
        // Défi collectif
        if (collectifCheckbox.checked) {
            let text = `${minParticipants.value}`;
            if (maxParticipants.value) {
                text += `-${maxParticipants.value}`;
            } else {
                text += '+';
            }
            text += ' participants';
            previewCollective.innerHTML = `<i class="fas fa-users me-1"></i>${text}`;
        } else {
            previewCollective.innerHTML = '<i class="fas fa-user me-1"></i>Individuel';
        }
        
        // Récompense
        if (recompense.value.trim()) {
            previewRewardText.textContent = recompense.value;
            previewReward.classList.remove('d-none');
        } else {
            previewReward.classList.add('d-none');
        }
    }
    
    // Événements pour la mise à jour de l'aperçu
    [titre, description, categorieSelect, difficulteSelect, dateLimite, recompense, minParticipants, maxParticipants].forEach(element => {
        element.addEventListener('input', updatePreview);
        element.addEventListener('change', updatePreview);
    });
    
    collectifCheckbox.addEventListener('change', function() {
        updatePreview();
        const optionsCollectif = document.getElementById('options_collectif');
        if (this.checked) {
            optionsCollectif.classList.remove('d-none');
        } else {
            optionsCollectif.classList.add('d-none');
        }
    });
    
    // Gestion de l'aperçu d'image
    imageInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.querySelector('img').src = e.target.result;
                previewImage.classList.remove('d-none');
                previewPlaceholder.classList.add('d-none');
                
                // Afficher le bouton de suppression
                document.getElementById('image-preview').classList.remove('d-none');
                document.getElementById('image-preview').querySelector('img').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Bouton de suppression d'image
    document.getElementById('remove-image').addEventListener('click', function() {
        imageInput.value = '';
        document.getElementById('image-preview').classList.add('d-none');
        previewImage.classList.add('d-none');
        previewPlaceholder.classList.remove('d-none');
    });
    
    // Validation en temps réel
    const form = document.getElementById('defi-form');
    form.addEventListener('input', function() {
        // Validation du titre
        if (titre.value.length >= 10 && titre.value.length <= 200) {
            titre.classList.remove('is-invalid');
            titre.classList.add('is-valid');
        } else if (titre.value.length > 0) {
            titre.classList.remove('is-valid');
            titre.classList.add('is-invalid');
        } else {
            titre.classList.remove('is-valid', 'is-invalid');
        }
        
        // Validation de la description
        if (description.value.length >= 50 && description.value.length <= 2000) {
            description.classList.remove('is-invalid');
            description.classList.add('is-valid');
        } else if (description.value.length > 0) {
            description.classList.remove('is-valid');
            description.classList.add('is-invalid');
        } else {
            description.classList.remove('is-valid', 'is-invalid');
        }
    });
    
    // Soumission du formulaire
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            
            // Scroll vers le premier champ invalide
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
        } else {
            // Désactiver le bouton pour éviter les soumissions multiples
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Création en cours...';
        }
        form.classList.add('was-validated');
    });
    
    // Initialiser l'aperçu
    updatePreview();
    
    // Déclencheurs de compteurs initiaux
    titre.dispatchEvent(new Event('input'));
    description.dispatchEvent(new Event('input'));
});

// Fonction pour réinitialiser le formulaire
function resetForm() {
    if (confirm('Êtes-vous sûr de vouloir réinitialiser le formulaire ? Toutes les données saisies seront perdues.')) {
        document.getElementById('defi-form').reset();
        document.getElementById('image-preview').classList.add('d-none');
        document.getElementById('preview-image').classList.add('d-none');
        document.getElementById('preview-placeholder').classList.remove('d-none');
        document.getElementById('options_collectif').classList.add('d-none');
        
        // Réinitialiser l'aperçu
        document.getElementById('preview-title').textContent = 'Titre de votre défi...';
        document.getElementById('preview-title').classList.add('text-muted');
        document.getElementById('preview-description').textContent = 'La description de votre défi apparaîtra ici...';
        document.getElementById('preview-description').classList.add('text-muted');
        
        // Réinitialiser les compteurs
        document.getElementById('titre-count').textContent = '0';
        document.getElementById('description-count').textContent = '0';
        
        // Supprimer les classes de validation
        document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
            el.classList.remove('is-valid', 'is-invalid');
        });
        document.querySelector('form').classList.remove('was-validated');
    }
}
</script>

<style>
/* Styles pour l'aperçu sticky */
.sticky-top {
    z-index: 1020;
}

/* Animation pour l'aperçu */
#defi-preview {
    transition: all 0.3s ease;
}

/* Styles pour les compteurs de caractères */
.text-warning {
    color: #ffc107 !important;
}

.text-danger {
    color: #dc3545 !important;
}

/* Amélioration de l'aperçu d'image */
#image-preview img {
    max-width: 100%;
    height: auto;
    border: 2px solid #dee2e6;
}

/* Responsive pour l'aperçu */
@media (max-width: 991px) {
    .sticky-top {
        position: static !important;
        top: auto !important;
    }
}

/* Animation pour les options collectifs */
#options_collectif {
    transition: all 0.3s ease;
}

/* Style pour les sélecteurs améliorés */
.form-select option {
    padding: 0.5rem;
}

/* Validation visuelle améliorée */
.form-control.is-valid,
.form-select.is-valid {
    border-color: #198754;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.86.86a.9.9 0 0 0 1.28 0l2.5-2.5a.9.9 0 0 0-1.28-1.27L4 5.18l-.69-.69a.9.9 0 0 0-1.28 1.27z'/%3e%3c/svg%3e");
}

.form-control.is-invalid,
.form-select.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 5.8 4.4 4.4M10.2 5.8 5.8 10.2'/%3e%3c/svg%3e");
}
</style>

<?php include '../includes/footer.php'; ?>