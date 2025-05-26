// assets/js/app.js - JavaScript principal de l'application

// Utilisation de modules JavaScript et meilleures pratiques
const DefiApp = (function() {
    'use strict';
    
    // Configuration globale
    const config = {
        animationDuration: 500,
        themeStorageKey: 'defi-friends-theme',
        apiEndpoints: {
            updatePreferences: '/defi_friends/api/update_preferences.php'
        }
    };
    
    // Cache des éléments DOM fréquemment utilisés
    const domCache = {};
    
    // Initialisation de l'application
    function init() {
        // Mettre en cache les éléments DOM fréquemment utilisés
        cacheElements();
        
        // Initialiser tous les modules
        ThemeManager.init();
        UIComponents.init();
        FormValidator.init();
        AnimationManager.init();
        NotificationManager.init();
        ScrollManager.init();
        
        // Marquer l'application comme prête
        document.dispatchEvent(new CustomEvent('app:ready'));
        
        console.log('DefiApp initialized successfully');
    }
    
    // Mise en cache des éléments DOM
    function cacheElements() {
        domCache.searchBar = document.querySelector('.search-bar');
        domCache.themeToggle = document.getElementById('theme-toggle');
        domCache.navLinks = document.querySelectorAll('.nav-link');
        domCache.forms = document.querySelectorAll('form');
        domCache.animateElements = document.querySelectorAll('.animate-on-scroll, .fade-in-element');
        domCache.backToTop = document.getElementById('backToTop');
        domCache.loadingOverlay = document.getElementById('loadingOverlay');
    }
    
    // Gestionnaire de thème
    const ThemeManager = {
        themes: {
            light: 'light',
            dark: 'dark'
        },
        
        init: function() {
            this.applyInitialTheme();
            this.setupEventListeners();
        },
        
        applyInitialTheme: function() {
            const savedTheme = localStorage.getItem(config.themeStorageKey);
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            let themeToUse;
            if (savedTheme) {
                themeToUse = savedTheme;
            } else if (prefersDarkScheme) {
                themeToUse = this.themes.dark;
            } else {
                themeToUse = this.themes.light;
            }
            
            this.setTheme(themeToUse);
        },
        
        setTheme: function(themeName) {
            if (!this.themes[themeName]) {
                console.warn(`Le thème "${themeName}" n'est pas valide.`);
                return;
            }
            
            localStorage.setItem(config.themeStorageKey, themeName);
            document.documentElement.setAttribute('data-theme', themeName);
            this.updateIcon(themeName);
            
            if (window.APP_CONFIG && window.APP_CONFIG.USER_LOGGED_IN) {
                this.saveThemePreference(themeName);
            }
            
            document.dispatchEvent(new CustomEvent('themeChanged', { 
                detail: { theme: themeName } 
            }));
        },
        
        toggleTheme: function() {
            const currentTheme = localStorage.getItem(config.themeStorageKey) || this.themes.light;
            const newTheme = currentTheme === this.themes.light 
                ? this.themes.dark 
                : this.themes.light;
            this.setTheme(newTheme);
        },
        
        updateIcon: function(themeName) {
            if (domCache.themeToggle) {
                const themeIcon = domCache.themeToggle.querySelector('i');
                if (themeIcon) {
                    themeIcon.className = themeName === this.themes.dark 
                        ? 'fas fa-sun' 
                        : 'fas fa-moon';
                }
            }
        },
        
        saveThemePreference: function(themeName) {
            if (!window.APP_CONFIG || !window.APP_CONFIG.CSRF_TOKEN) return;
            
            const formData = new FormData();
            formData.append('theme', themeName);
            formData.append('csrf_token', window.APP_CONFIG.CSRF_TOKEN);
            
            fetch(config.apiEndpoints.updatePreferences, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .catch(error => console.error('Erreur lors de la sauvegarde des préférences:', error));
        },
        
        setupEventListeners: function() {
            if (domCache.themeToggle) {
                domCache.themeToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleTheme();
                });
            }
            
            // Exposer la fonction au niveau global pour les boutons HTML
            window.toggleTheme = this.toggleTheme.bind(this);
        }
    };
    
    // Gestionnaire des composants UI
    const UIComponents = {
        init: function() {
            this.initializeTooltips();
            this.highlightActiveNavLinks();
            this.setupSearch();
            this.setupModals();
            this.setupDropdowns();
        },
        
        initializeTooltips: function() {
            // Initialiser les tooltips Bootstrap
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover focus'
                });
            });
        },
        
        highlightActiveNavLinks: function() {
            const currentLocation = window.location.pathname;
            
            if (domCache.navLinks) {
                domCache.navLinks.forEach(link => {
                    const href = link.getAttribute('href');
                    
                    if (href === currentLocation || 
                        (currentLocation.includes('/defis/') && href.includes('/defis/')) ||
                        (currentLocation.includes('/auth/') && href.includes('/auth/')) ||
                        (currentLocation.includes('/amis/') && href.includes('/amis/'))) {
                        link.classList.add('active');
                    }
                });
            }
        },
        
        setupSearch: function() {
            const searchBars = document.querySelectorAll('.search-bar');
            
            searchBars.forEach(searchBar => {
                searchBar.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const query = this.value.trim();
                        if (query) {
                            window.location.href = '/defi_friends/search.php?q=' + encodeURIComponent(query);
                        }
                    }
                });
                
                // Ajouter un effet de focus
                searchBar.addEventListener('focus', function() {
                    this.parentNode.classList.add('search-focused');
                });
                
                searchBar.addEventListener('blur', function() {
                    this.parentNode.classList.remove('search-focused');
                });
            });
        },
        
        setupModals: function() {
            // Configuration du modal de confirmation
            const confirmModal = document.getElementById('confirmModal');
            if (confirmModal) {
                window.showConfirmModal = function(message, callback) {
                    document.getElementById('confirmModalBody').textContent = message;
                    
                    const confirmBtn = document.getElementById('confirmModalConfirm');
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    
                    newConfirmBtn.addEventListener('click', function() {
                        callback();
                        bootstrap.Modal.getInstance(confirmModal).hide();
                    });
                    
                    new bootstrap.Modal(confirmModal).show();
                };
            }
        },
        
        setupDropdowns: function() {
            // Fermer les dropdowns au clic extérieur
            document.addEventListener('click', function(e) {
                const dropdowns = document.querySelectorAll('.dropdown-menu.show');
                dropdowns.forEach(dropdown => {
                    if (!dropdown.contains(e.target) && !dropdown.previousElementSibling.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
            });
        }
    };
    
    // Gestionnaire de validation des formulaires
    const FormValidator = {
        init: function() {
            this.setupFormValidation();
            this.setupCharacterCounters();
            this.setupPasswordStrength();
        },
        
        setupFormValidation: function() {
            if (domCache.forms) {
                domCache.forms.forEach(form => {
                    if (form.classList.contains('needs-validation')) {
                        form.addEventListener('submit', function(event) {
                            if (!form.checkValidity()) {
                                event.preventDefault();
                                event.stopPropagation();
                                
                                // Faire défiler vers le premier champ invalide
                                const firstInvalid = form.querySelector(':invalid');
                                if (firstInvalid) {
                                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    firstInvalid.focus();
                                }
                            }
                            form.classList.add('was-validated');
                        }, false);
                    }
                });
            }
        },
        
        setupCharacterCounters: function() {
            const textareas = document.querySelectorAll('textarea[maxlength]');
            textareas.forEach(textarea => {
                const maxLength = textarea.getAttribute('maxlength');
                const counter = document.createElement('div');
                counter.className = 'character-counter text-muted small mt-1';
                counter.innerHTML = `<span class="current">0</span>/${maxLength} caractères`;
                
                textarea.parentNode.appendChild(counter);
                
                textarea.addEventListener('input', function() {
                    const current = this.value.length;
                    const currentSpan = counter.querySelector('.current');
                    currentSpan.textContent = current;
                    
                    if (current > maxLength * 0.9) {
                        counter.classList.add('text-warning');
                    } else {
                        counter.classList.remove('text-warning');
                    }
                    
                    if (current >= maxLength) {
                        counter.classList.add('text-danger');
                        counter.classList.remove('text-warning');
                    } else {
                        counter.classList.remove('text-danger');
                    }
                });
                
                // Déclencher l'événement pour l'initialisation
                textarea.dispatchEvent(new Event('input'));
            });
        },
        
        setupPasswordStrength: function() {
            const passwordInputs = document.querySelectorAll('input[type="password"][data-strength="true"]');
            passwordInputs.forEach(input => {
                const strengthMeter = document.createElement('div');
                strengthMeter.className = 'password-strength mt-2';
                strengthMeter.innerHTML = `
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" role="progressbar"></div>
                    </div>
                    <small class="strength-text text-muted">Saisissez un mot de passe</small>
                `;
                
                input.parentNode.appendChild(strengthMeter);
                
                input.addEventListener('input', function() {
                    const password = this.value;
                    const strength = this.calculatePasswordStrength(password);
                    const progressBar = strengthMeter.querySelector('.progress-bar');
                    const strengthText = strengthMeter.querySelector('.strength-text');
                    
                    progressBar.style.width = strength.percentage + '%';
                    progressBar.className = `progress-bar bg-${strength.color}`;
                    strengthText.textContent = strength.text;
                    strengthText.className = `strength-text text-${strength.color}`;
                });
                
                // Méthode pour calculer la force du mot de passe
                input.calculatePasswordStrength = function(password) {
                    let score = 0;
                    
                    if (password.length >= 8) score += 25;
                    if (/[a-z]/.test(password)) score += 25;
                    if (/[A-Z]/.test(password)) score += 25;
                    if (/[0-9]/.test(password)) score += 25;
                    if (/[^A-Za-z0-9]/.test(password)) score += 25;
                    
                    if (score <= 25) return { percentage: 25, color: 'danger', text: 'Très faible' };
                    if (score <= 50) return { percentage: 50, color: 'warning', text: 'Faible' };
                    if (score <= 75) return { percentage: 75, color: 'info', text: 'Moyenne' };
                    if (score <= 100) return { percentage: 100, color: 'success', text: 'Forte' };
                    
                    return { percentage: 100, color: 'success', text: 'Très forte' };
                };
            });
        }
    };
    
    // Gestionnaire d'animations
    const AnimationManager = {
        init: function() {
            this.setupScrollAnimations();
            this.setupHoverEffects();
        },
        
        setupScrollAnimations: function() {
            if (domCache.animateElements && domCache.animateElements.length > 0) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { 
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                });
                
                domCache.animateElements.forEach(element => {
                    observer.observe(element);
                });
            }
        },
        
        setupHoverEffects: function() {
            // Effet de hover sur les cartes
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        }
    };
    
    // Gestionnaire de notifications
    const NotificationManager = {
        init: function() {
            this.setupNotificationHandlers();
            this.checkForNewNotifications();
        },
        
        setupNotificationHandlers: function() {
            // Marquer les notifications comme lues au clic
            const notificationLinks = document.querySelectorAll('.notification-dropdown .dropdown-item[href*="notifications.php"]');
            notificationLinks.forEach(link => {
                link.addEventListener('click', function() {
                    this.classList.remove('notification-unread');
                });
            });
        },
        
        checkForNewNotifications: function() {
            // Vérifier les nouvelles notifications toutes les 30 secondes
            if (window.APP_CONFIG && window.APP_CONFIG.USER_LOGGED_IN) {
                setInterval(() => {
                    this.fetchNotificationCount();
                }, 30000);
            }
        },
        
        fetchNotificationCount: function() {
            fetch('/defi_friends/api/notification_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateNotificationBadge(data.count);
                    }
                })
                .catch(error => console.error('Erreur lors de la récupération des notifications:', error));
        },
        
        updateNotificationBadge: function(count) {
            const badge = document.querySelector('#notificationsDropdown .badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        },
        
        showToast: function(message, type = 'info') {
            // Créer un toast Bootstrap
            const toastContainer = document.querySelector('.toast-container') || this.createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        },
        
        createToastContainer: function() {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1055';
            document.body.appendChild(container);
            return container;
        }
    };
    
    // Gestionnaire de défilement
    const ScrollManager = {
        init: function() {
            this.setupBackToTop();
            this.setupSmoothScrolling();
        },
        
        setupBackToTop: function() {
            if (domCache.backToTop) {
                window.addEventListener('scroll', () => {
                    if (window.pageYOffset > 300) {
                        domCache.backToTop.classList.add('show');
                    } else {
                        domCache.backToTop.classList.remove('show');
                    }
                });
                
                domCache.backToTop.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        },
        
        setupSmoothScrolling: function() {
            // Défilement fluide pour les liens d'ancrage
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            anchorLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;
                    
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }
    };
    
    // Gestionnaire de loading
    const LoadingManager = {
        show: function(message = 'Chargement en cours...') {
            if (domCache.loadingOverlay) {
                const loadingText = domCache.loadingOverlay.querySelector('.loading-content div:last-child');
                if (loadingText) {
                    loadingText.textContent = message;
                }
                domCache.loadingOverlay.classList.remove('d-none');
            }
        },
        
        hide: function() {
            if (domCache.loadingOverlay) {
                domCache.loadingOverlay.classList.add('d-none');
            }
        }
    };
    
    // Utilitaires
    const Utils = {
        // Débounce function pour optimiser les performances
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        // Throttle function
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },
        
        // Formater les dates
        formatDate: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 1) {
                return 'Hier';
            } else if (diffDays < 7) {
                return `Il y a ${diffDays} jours`;
            } else {
                return date.toLocaleDateString('fr-FR');
            }
        },
        
        // Copier du texte dans le presse-papiers
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    NotificationManager.showToast('Copié dans le presse-papiers', 'success');
                });
            } else {
                // Fallback pour les navigateurs plus anciens
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                NotificationManager.showToast('Copié dans le presse-papiers', 'success');
            }
        }
    };
    
    // API publique
    return {
        init: init,
        theme: ThemeManager,
        ui: UIComponents,
        forms: FormValidator,
        animations: AnimationManager,
        notifications: NotificationManager,
        scroll: ScrollManager,
        loading: LoadingManager,
        utils: Utils
    };
})();

// Initialiser l'application quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    DefiApp.init();
});

// Exposer certaines fonctions au niveau global pour la compatibilité
window.DefiApp = DefiApp;
window.showLoading = DefiApp.loading.show;
window.hideLoading = DefiApp.loading.hide;
window.showToast = DefiApp.notifications.showToast;