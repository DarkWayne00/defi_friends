<?php
// defis/complete.php - Terminer un défi

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$defi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$currentUser = getCurrentUser();
$errors = [];

if (!$defi_id) {
    redirect('/defi_friends/defis/index.php');
}

// Récupérer les détails du défi et de la participation
$defiData = fetchOne(
    "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
            u.pseudo as createur_pseudo, u.id as createur_id,
            p.id as participation_id, p.statut as participation_statut, p.date_participation,
            p.preuve_texte, p.preuve_image, p.note_auto_evaluation, p.commentaire_completion
     FROM defis d
     LEFT JOIN categories c ON d.categorie_id = c.id
     LEFT JOIN utilisateurs u ON d.createur_id = u.id
     LEFT JOIN participations p ON d.id = p.defi_id AND p.utilisateur_id = ?
     WHERE d.id = ? AND d.statut = 'actif'",
    [$currentUser['id'], $defi_id]
);

if (!$defiData || !$defiData['participation_id']) {
    $_SESSION['flash_message'] = "Vous ne participez pas à ce défi ou il n'existe pas.";
    $_SESSION['flash_type'] = "error";
    redirect('/defi_friends/defis/index.php');
}

$defi = $defiData;
$participation = $defiData;

// Vérifier si le défi n'est pas déjà terminé
if ($participation['participation_statut'] === 'complete') {
    $_SESSION['flash_message'] = "Vous avez déjà terminé ce défi !";
    $_SESSION['flash_type'] = "info";
    redirect('/defi_friends/defis/view.php?id=' . $defi_id);
}

// Traitement de la finalisation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $commentaire = cleanInput($_POST['commentaire'] ?? '');
    $note_auto = isset($_POST['note_auto']) ? intval($_POST['note_auto']) : null;
    $preuve_texte = cleanInput($_POST['preuve_texte'] ?? '');
    
    // Validation
    if (empty($preuve_texte)) {
        $errors[] = "Vous devez fournir une description de votre réalisation.";
    } elseif (strlen($preuve_texte) < 20) {
        $errors[] = "La description de votre réalisation doit contenir au moins 20 caractères.";
    } elseif (strlen($preuve_texte) > 1000) {
        $errors[] = "La description ne peut pas dépasser 1000 caractères.";
    }
    
    if ($note_auto !== null && ($note_auto < 1 || $note_auto > 5)) {
        $errors[] = "L'auto-évaluation doit être entre 1 et 5.";
    }
    
    if (strlen($commentaire) > 500) {
        $errors[] = "Le commentaire ne peut pas dépasser 500 caractères.";
    }
    
    // Traitement de l'image de preuve
    $preuve_image = $participation['preuve_image'];
    if (isset($_FILES['preuve_image']) && $_FILES['preuve_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = processFileUpload($_FILES['preuve_image'], 'preuves', $currentUser['id']);
        if ($upload_result['success']) {
            // Supprimer l'ancienne image si elle existe
            if ($participation['preuve_image'] && file_exists(UPLOAD_PATH . '/' . $participation['preuve_image'])) {
                unlink(UPLOAD_PATH . '/' . $participation['preuve_image']);
            }
            $preuve_image = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            // Mettre à jour la participation
            $result = executeQuery(
                "UPDATE participations 
                 SET statut = 'complete', 
                     date_completion = NOW(), 
                     preuve_texte = ?, 
                     preuve_image = ?, 
                     note_auto_evaluation = ?, 
                     commentaire_completion = ?
                 WHERE id = ?",
                [$preuve_texte, $preuve_image, $note_auto, $commentaire, $participation['participation_id']]
            );
            
            if ($result) {
                // Créer une notification pour le créateur du défi
                createNotification(
                    $defi['createur_id'],
                    'completion',
                    'Défi terminé !',
                    $currentUser['pseudo'] . ' a terminé votre défi "' . $defi['titre'] . '"',
                    ['url' => '/defi_friends/defis/view.php?id=' . $defi_id],
                    $currentUser['id']
                );
                
                // Créer une notification pour l'utilisateur (badge potentiel)
                createNotification(
                    $currentUser['id'],
                    'completion',
                    'Félicitations !',
                    'Vous avez terminé le défi "' . $defi['titre'] . '" avec succès !',
                    ['url' => '/defi_friends/profile.php?tab=participations']
                );
                
                $_SESSION['flash_message'] = "Félicitations ! Vous avez terminé ce défi avec succès !";
                $_SESSION['flash_type'] = "success";
                
                redirect('/defi_friends/defis/view.php?id=' . $defi_id);
            } else {
                $errors[] = "Erreur lors de la finalisation du défi. Veuillez réessayer.";
            }
        } catch (Exception $e) {
            logError('Erreur lors de la finalisation du défi', [
                'user_id' => $currentUser['id'],
                'defi_id' => $defi_id,
                'participation_id' => $participation['participation_id'],
                'error' => $e->getMessage()
            ]);
            $errors[] = "Une erreur inattendue s'est produite. Veuillez réessayer.";
        }
    }
}

$pageTitle = "Terminer le défi : " . htmlspecialchars($defi['titre']);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Défis', 'url' => '/defi_friends/defis/index.php'],
    ['label' => htmlspecialchars($defi['titre']), 'url' => '/defi_friends/defis/view.php?id=' . $defi_id],
    ['label' => 'Terminer']
];

include '../includes/header.php';
?>

<div class="container">
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- En-tête -->
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="fas fa-flag-checkered fa-3x text-success"></i>
                </div>
                <h1 class="h2 fw-bold">Terminer le défi</h1>
                <p class="text-muted">Félicitations ! Vous êtes sur le point de finaliser votre participation.</p>
            </div>

            <!-- Aperçu du défi -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy me-2"></i>
                        Récapitulatif de votre participation
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="fw-bold mb-2"><?= htmlspecialchars($defi['titre']) ?></h4>
                            <div class="d-flex gap-2 mb-2">
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
                            <p class="text-muted mb-2">
                                Créé par <strong><?= htmlspecialchars($defi['createur_pseudo']) ?></strong>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Vous participez depuis <?= formatDate($participation['date_participation']) ?>
                                <?php
                                $days_participating = floor((time() - strtotime($participation['date_participation'])) / (60 * 60 * 24));
                                if ($days_participating > 0) {
                                    echo " (" . $days_participating . " jour" . ($days_participating > 1 ? "s" : "") . ")";
                                }
                                ?>
                            </small>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-2x" 
                                   style="color: <?= $defi['categorie_couleur'] ?>;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Motivation initiale -->
            <?php if (!empty($participation['preuve_texte'])): ?>
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-quote-left me-2"></i>
                        Votre motivation initiale
                    </h6>
                </div>
                <div class="card-body">
                    <blockquote class="blockquote mb-0">
                        <p><?= nl2br(htmlspecialchars($participation['preuve_texte'])) ?></p>
                    </blockquote>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulaire de finalisation -->
            <div class="card border-0 shadow-lg rounded-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Finaliser votre participation
                    </h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Description de la réalisation -->
                        <div class="mb-4">
                            <label for="preuve_texte" class="form-label fw-bold">
                                <i class="fas fa-pen me-2"></i>
                                Décrivez votre réalisation *
                            </label>
                            <textarea class="form-control" id="preuve_texte" name="preuve_texte" rows="6" 
                                      required minlength="20" maxlength="1000"
                                      placeholder="Racontez comment vous avez relevé ce défi : ce que vous avez fait, les difficultés rencontrées, ce que vous avez appris..."><?= htmlspecialchars($_POST['preuve_texte'] ?? '') ?></textarea>
                            <div class="form-text">
                                <span id="preuve-count">0</span>/1000 caractères (minimum 20)
                            </div>
                            <div class="invalid-feedback">
                                La description doit contenir entre 20 et 1000 caractères.
                            </div>
                        </div>

                        <!-- Image de preuve -->
                        <div class="mb-4">
                            <label for="preuve_image" class="form-label fw-bold">
                                <i class="fas fa-camera me-2"></i>
                                Image de preuve (optionnel)
                            </label>
                            <input type="file" class="form-control" id="preuve_image" name="preuve_image" 
                                   accept="image/*">
                            <div class="form-text">
                                Ajoutez une photo qui montre votre réalisation. Formats acceptés : JPG, PNG, GIF, WebP. Taille max : 5MB.
                            </div>
                            
                            <!-- Aperçu de l'image -->
                            <?php if (!empty($participation['preuve_image'])): ?>
                                <div class="mt-3">
                                    <label class="form-label small text-muted">Image actuelle :</label>
                                    <div>
                                        <img src="/defi_friends/uploads/<?= htmlspecialchars($participation['preuve_image']) ?>" 
                                             alt="Preuve actuelle" class="img-fluid rounded" style="max-height: 200px;">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div id="image-preview" class="mt-3 d-none">
                                <label class="form-label small text-muted">Nouvelle image :</label>
                                <div>
                                    <img src="#" alt="Aperçu" class="img-fluid rounded" style="max-height: 200px;">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="remove-image">
                                        <i class="fas fa-times me-1"></i>
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Auto-évaluation -->
                        <div class="mb-4">
                            <label for="note_auto" class="form-label fw-bold">
                                <i class="fas fa-star me-2"></i>
                                Auto-évaluation (optionnel)
                            </label>
                            <div class="rating-container mb-2">
                                <div class="btn-group rating-group" role="group" aria-label="Note d'auto-évaluation">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="btn-check" name="note_auto" id="note_<?= $i ?>" value="<?= $i ?>"
                                               <?= ($_POST['note_auto'] ?? '') == $i ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-warning" for="note_<?= $i ?>">
                                            <i class="fas fa-star"></i> <?= $i ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-text">
                                Comment évaluez-vous votre réalisation de ce défi ? (1 = Difficile, 5 = Excellent)
                            </div>
                        </div>

                        <!-- Commentaire libre -->
                        <div class="mb-4">
                            <label for="commentaire" class="form-label fw-bold">
                                <i class="fas fa-comment me-2"></i>
                                Commentaire libre (optionnel)
                            </label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" 
                                      maxlength="500"
                                      placeholder="Partagez vos impressions, conseils pour les futurs participants, ou tout autre commentaire..."><?= htmlspecialchars($_POST['commentaire'] ?? '') ?></textarea>
                            <div class="form-text">
                                <span id="commentaire-count">0</span>/500 caractères
                            </div>
                        </div>

                        <!-- Rappel des accomplissements -->
                        <div class="alert alert-success">
                            <h6 class="fw-bold mb-2">
                                <i class="fas fa-trophy me-2"></i>
                                Félicitations !
                            </h6>
                            <p class="mb-0">
                                En terminant ce défi, vous gagnez des points d'expérience et vous rapprochez de nouveaux badges. 
                                Votre réalisation sera visible par la communauté et pourra inspirer d'autres participants !
                            </p>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-success btn-lg flex-fill">
                                <i class="fas fa-flag-checkered me-2"></i>
                                Terminer le défi !
                            </button>
                            <a href="/defi_friends/defis/view.php?id=<?= $defi_id ?>" 
                               class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>
                                Retour
                            </a>
                        </div>

                        <!-- Note finale -->
                        <div class="mt-4 p-3 bg-info bg-opacity-10 border border-info rounded">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-info me-2 mt-1"></i>
                                <div class="small">
                                    <strong>Important :</strong> Une fois le défi marqué comme terminé, vous ne pourrez plus modifier 
                                    vos preuves. Assurez-vous que toutes les informations sont correctes avant de valider.
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Compteurs de caractères
    const preuveTextarea = document.getElementById('preuve_texte');
    const preuveCount = document.getElementById('preuve-count');
    const commentaireTextarea = document.getElementById('commentaire');
    const commentaireCount = document.getElementById('commentaire-count');
    
    function updateCharCount(textarea, counter, maxLength) {
        if (!textarea || !counter) return;
        
        function update() {
            const length = textarea.value.length;
            counter.textContent = length;
            
            if (length > maxLength * 0.9) {
                counter.classList.add('text-warning');
                counter.classList.remove('text-danger');
            } else if (length >= maxLength) {
                counter.classList.remove('text-warning');
                counter.classList.add('text-danger');
            } else {
                counter.classList.remove('text-warning', 'text-danger');
            }
        }
        
        textarea.addEventListener('input', update);
        update(); // Initialiser
    }
    
    updateCharCount(preuveTextarea, preuveCount, 1000);
    updateCharCount(commentaireTextarea, commentaireCount, 500);
    
    // Gestion de l'aperçu d'image
    const imageInput = document.getElementById('preuve_image');
    const imagePreview = document.getElementById('image-preview');
    const removeImageBtn = document.getElementById('remove-image');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.querySelector('img').src = e.target.result;
                    imagePreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            }
        });
        
        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', function() {
                imageInput.value = '';
                imagePreview.classList.add('d-none');
            });
        }
    }
    
    // Gestion des étoiles de notation
    const ratingInputs = document.querySelectorAll('input[name="note_auto"]');
    const ratingLabels = document.querySelectorAll('.rating-group label');
    
    function updateRatingDisplay() {
        const selectedValue = parseInt(document.querySelector('input[name="note_auto"]:checked')?.value || 0);
        
        ratingLabels.forEach((label, index) => {
            const star = label.querySelector('i');
            if (index < selectedValue) {
                star.classList.remove('far');
                star.classList.add('fas');
                label.classList.remove('btn-outline-warning');
                label.classList.add('btn-warning');
            } else {
                star.classList.remove('fas');
                star.classList.add('far');
                label.classList.remove('btn-warning');
                label.classList.add('btn-outline-warning');
            }
        });
    }
    
    ratingInputs.forEach(input => {
        input.addEventListener('change', updateRatingDisplay);
    });
    
    // Initialiser l'affichage des étoiles
    updateRatingDisplay();
    
    // Validation du formulaire
    const form = document.querySelector('form');
    if (form) {
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
                // Confirmation avant soumission
                const confirmed = confirm('Êtes-vous sûr de vouloir terminer ce défi ? Cette action est définitive.');
                if (!confirmed) {
                    event.preventDefault();
                    return;
                }
                
                // Désactiver le bouton pour éviter les soumissions multiples
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Finalisation...';
                }
            }
            form.classList.add('was-validated');
        });
    }
    
    // Animation d'entrée
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });
    
    // Validation en temps réel pour la description
    if (preuveTextarea) {
        preuveTextarea.addEventListener('input', function() {
            if (this.value.length >= 20 && this.value.length <= 1000) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    }
});

// Fonction pour sauvegarder le brouillon (localStorage)
function saveDraft() {
    const preuveTexte = document.getElementById('preuve_texte').value;
    const commentaire = document.getElementById('commentaire').value;
    const noteAuto = document.querySelector('input[name="note_auto"]:checked')?.value;
    
    const draft = {
        preuve_texte: preuveTexte,
        commentaire: commentaire,
        note_auto: noteAuto,
        timestamp: new Date().toISOString()
    };
    
    localStorage.setItem('defi_completion_draft_<?= $defi_id ?>', JSON.stringify(draft));
}

// Fonction pour restaurer le brouillon
function restoreDraft() {
    const draftData = localStorage.getItem('defi_completion_draft_<?= $defi_id ?>');
    if (draftData) {
        try {
            const draft = JSON.parse(draftData);
            
            // Vérifier si le brouillon n'est pas trop ancien (24h)
            const draftAge = new Date() - new Date(draft.timestamp);
            if (draftAge < 24 * 60 * 60 * 1000) {
                if (confirm('Un brouillon de ce défi a été trouvé. Voulez-vous le restaurer ?')) {
                    document.getElementById('preuve_texte').value = draft.preuve_texte || '';
                    document.getElementById('commentaire').value = draft.commentaire || '';
                    
                    if (draft.note_auto) {
                        const radioBtn = document.getElementById('note_' + draft.note_auto);
                        if (radioBtn) {
                            radioBtn.checked = true;
                            radioBtn.dispatchEvent(new Event('change'));
                        }
                    }
                    
                    // Mettre à jour les compteurs
                    document.getElementById('preuve_texte').dispatchEvent(new Event('input'));
                    document.getElementById('commentaire').dispatchEvent(new Event('input'));
                }
            }
        } catch (e) {
            console.error('Erreur lors de la restauration du brouillon:', e);
        }
    }
}

// Sauvegarder automatiquement le brouillon
setInterval(saveDraft, 30000); // Toutes les 30 secondes

// Restaurer le brouillon au chargement
window.addEventListener('load', restoreDraft);

// Supprimer le brouillon lors de la soumission réussie
window.addEventListener('beforeunload', function() {
    if (document.querySelector('form').checkValidity()) {
        localStorage.removeItem('defi_completion_draft_<?= $defi_id ?>');
    }
});
</script>

<style>
/* Styles pour la page de finalisation */
.rating-group .btn {
    transition: all 0.3s ease;
}

.rating-group .btn i.far {
    color: #ffc107;
}

.rating-group .btn i.fas {
    color: #ffc107;
}

.rating-group .btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

/* Animation pour les étoiles */
.rating-group label:hover i {
    animation: starPulse 0.3s ease;
}

@keyframes starPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* Style pour l'aperçu d'image */
#image-preview img {
    max-width: 100%;
    height: auto;
    border: 2px solid var(--bs-success);
    border-radius: var(--bs-border-radius);
}

/* Amélioration des alertes */
.alert-success {
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.1), rgba(25, 135, 84, 0.05));
    border-left: 4px solid var(--bs-success);
}

.alert-info {
    background: linear-gradient(135deg, rgba(13, 202, 240, 0.1), rgba(13, 202, 240, 0.05));
    border-left: 4px solid var(--bs-info);
}

/* Style pour le textarea de description */
#preuve_texte {
    min-height: 150px;
    resize: vertical;
}

/* Animation pour les compteurs de caractères */
.text-warning {
    animation: warningPulse 2s infinite;
}

.text-danger {
    animation: dangerPulse 1s infinite;
}

@keyframes warningPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

@keyframes dangerPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Responsive pour les boutons */
@media (max-width: 768px) {
    .d-flex.gap-3 {
        flex-direction: column;
    }
    
    .rating-group {
        flex-direction: column;
    }
    
    .rating-group .btn {
        margin-bottom: 0.25rem;
    }
}

/* Style pour la validation du formulaire */
.form-control.is-valid {
    border-color: var(--bs-success);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.86.86a.9.9 0 0 0 1.28 0l2.5-2.5a.9.9 0 0 0-1.28-1.27L4 5.18l-.69-.69a.9.9 0 0 0-1.28 1.27z'/%3e%3c/svg%3e");
}

.form-control.is-invalid {
    border-color: var(--bs-danger);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 5.8 4.4 4.4M10.2 5.8 5.8 10.2'/%3e%3c/svg%3e");
}

/* Animation du bouton de soumission */
.btn-success {
    background: linear-gradient(135deg, var(--bs-success), #20c997);
    border: none;
    transition: all 0.3s ease;
}

.btn-success:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
}

.btn-success:disabled {
    opacity: 0.8;
    transform: none;
}
</style>

<?php include '../includes/footer.php'; ?>