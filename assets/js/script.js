// Modern Employee Management System - Enhanced JavaScript
// Advanced animations, improved UX, and modern interactions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeAnimations();
    initializeParticleBackground();
    initializeAlerts();
    initializeModals();
    initializeForms();
    initializeTables();
    initializeSidebar();
    initializeStats();
    initializeTooltips();
    initializePageTransitions();
    initializeDarkMode();

    // Add page transition effect
    document.body.classList.add('page-enter');
});

// Particle background for login page
function initializeParticleBackground() {
    const loginContainer = document.querySelector('.login-container');
    if (!loginContainer) return;

    const canvas = document.createElement('canvas');
    canvas.id = 'particles-canvas';
    canvas.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
    `;
    loginContainer.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    let particles = [];
    let animationId;

    function resizeCanvas() {
        canvas.width = loginContainer.offsetWidth;
        canvas.height = loginContainer.offsetHeight;
    }

    function createParticles() {
        particles = [];
        const particleCount = Math.floor((canvas.width * canvas.height) / 15000);
        
        for (let i = 0; i < particleCount; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                size: Math.random() * 3 + 1,
                speedX: (Math.random() - 0.5) * 0.5,
                speedY: (Math.random() - 0.5) * 0.5,
                opacity: Math.random() * 0.5 + 0.2
            });
        }
    }

    function animateParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        particles.forEach(particle => {
            particle.x += particle.speedX;
            particle.y += particle.speedY;
            
            if (particle.x > canvas.width) particle.x = 0;
            if (particle.x < 0) particle.x = canvas.width;
            if (particle.y > canvas.height) particle.y = 0;
            if (particle.y < 0) particle.y = canvas.height;
            
            ctx.beginPath();
            ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${particle.opacity})`;
            ctx.fill();
            
            // Draw connections
            particles.forEach(otherParticle => {
                const dx = particle.x - otherParticle.x;
                const dy = particle.y - otherParticle.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < 100) {
                    ctx.beginPath();
                    ctx.moveTo(particle.x, particle.y);
                    ctx.lineTo(otherParticle.x, otherParticle.y);
                    ctx.strokeStyle = `rgba(255, 255, 255, ${0.1 * (1 - distance / 100)})`;
                    ctx.lineWidth = 1;
                    ctx.stroke();
                }
            });
        });
        
        animationId = requestAnimationFrame(animateParticles);
    }

    resizeCanvas();
    createParticles();
    animateParticles();

    window.addEventListener('resize', () => {
        resizeCanvas();
        createParticles();
    });
}

// Enhanced animation utilities
function initializeAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.classList.add('animated');
            }
        });
    }, observerOptions);

    // Observe all cards and stat cards
    const animatedElements = document.querySelectorAll('.card, .stat-card, .table');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(el);
    });

    // Enhanced button interactions
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.02)';
        });

        button.addEventListener('mouseleave', function() {
            if (!this.classList.contains('loading')) {
                this.style.transform = '';
            }
        });

        button.addEventListener('click', function(e) {
            if (!this.disabled && !this.classList.contains('no-loading')) {
                addLoadingState(this);
                
                // Create ripple effect
                createRippleEffect(e, this);
            }
        });
    });
}

// Ripple effect for buttons
function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// Page transitions
function initializePageTransitions() {
    const links = document.querySelectorAll('a[href]:not([href^="http"]):not([href^="#"]):not([target="_blank"])');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#' && !href.startsWith('javascript:')) {
                e.preventDefault();
                
                // Fade out current page
                document.body.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                document.body.style.opacity = '0';
                document.body.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            }
        });
    });
}

// Enhanced alert system
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(function(alert, index) {
        // Add enhanced styling
        alert.style.animationDelay = `${index * 0.1}s`;
        
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.className = 'alert-close';
        closeBtn.style.cssText = `
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            opacity: 0.7;
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        alert.style.position = 'relative';
        alert.style.paddingRight = '4rem';
        alert.appendChild(closeBtn);
        
        closeBtn.addEventListener('click', () => dismissAlert(alert));
        closeBtn.addEventListener('mouseenter', () => {
            closeBtn.style.opacity = '1';
            closeBtn.style.background = 'rgba(0, 0, 0, 0.1)';
            closeBtn.style.transform = 'scale(1.1)';
        });
        closeBtn.addEventListener('mouseleave', () => {
            closeBtn.style.opacity = '0.7';
            closeBtn.style.background = 'none';
            closeBtn.style.transform = 'scale(1)';
        });

        // Auto-hide alerts after 5 seconds with progress bar
        const progressBar = document.createElement('div');
        progressBar.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: currentColor;
            opacity: 0.3;
            animation: progressBar 5s linear;
            width: 100%;
        `;
        alert.appendChild(progressBar);

        setTimeout(() => {
            if (alert.parentNode) {
                dismissAlert(alert);
            }
        }, 5000);
    });
}

function dismissAlert(alert) {
    alert.style.animation = 'slideUp 0.3s ease-out forwards';
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 300);
}

// Enhanced modal system
function initializeModals() {
    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target;
            closeModalWithAnimation(modal);
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal-overlay:not(.hidden)');
            if (openModal) {
                closeModalWithAnimation(openModal);
            }
        }
    });
}

function closeModalWithAnimation(modal) {
    const modalContent = modal.querySelector('.modal');
    if (modalContent) {
        modalContent.style.animation = 'modalSlideOut 0.3s ease-out forwards';
    }
    modal.style.animation = 'fadeOut 0.3s ease-out forwards';
    
    setTimeout(() => {
        modal.style.display = 'none';
        if (modalContent) {
            modalContent.style.animation = '';
        }
        modal.style.animation = '';
    }, 300);
}

// Enhanced form handling
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(function(form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        // Add floating labels and validation
        inputs.forEach(input => {
            if (input.type !== 'submit' && input.type !== 'button') {
                enhanceFormInput(input);
            }
        });

        // Enhanced form submission
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            
            if (submitBtn && !form.dataset.skipValidation) {
                if (!validateForm(form)) {
                    e.preventDefault();
                    showFormErrors(form);
                    return;
                }
                
                addLoadingState(submitBtn);
            }
        });
    });
}

function enhanceFormInput(input) {
    const formGroup = input.closest('.form-group');
    if (!formGroup) return;

    // Add input wrapper for better styling
    if (!input.parentNode.classList.contains('input-wrapper')) {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-wrapper';
        wrapper.style.position = 'relative';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
    }

    // Enhanced focus/blur effects
    input.addEventListener('focus', function() {
        this.parentNode.classList.add('focused');
        this.style.transform = 'translateY(-1px)';
        
        // Add focus ring animation
        if (!this.dataset.focusRing) {
            const focusRing = document.createElement('div');
            focusRing.style.cssText = `
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                border: 2px solid var(--primary-indigo);
                border-radius: inherit;
                pointer-events: none;
                animation: focusRing 0.3s ease-out;
            `;
            this.parentNode.appendChild(focusRing);
            this.dataset.focusRing = 'true';
            
            setTimeout(() => focusRing.remove(), 300);
        }
    });

    input.addEventListener('blur', function() {
        if (!this.value) {
            this.parentNode.classList.remove('focused');
        }
        this.style.transform = '';
        this.dataset.focusRing = '';
        
        // Validate on blur
        validateInput(this);
    });

    input.addEventListener('input', function() {
        // Real-time validation feedback
        if (this.classList.contains('error')) {
            validateInput(this);
        }
        
        // Add typing animation
        this.style.borderColor = 'var(--primary-indigo-light)';
        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => {
            this.style.borderColor = '';
        }, 300);
    });

    // Check if already has value
    if (input.value) {
        input.parentNode.classList.add('focused');
    }
}

function validateInput(input) {
    const isValid = input.checkValidity() && input.value.trim() !== '';
    
    if (isValid) {
        input.classList.remove('error');
        input.classList.add('success');
        input.style.borderColor = 'var(--success)';
        
        // Add success animation
        const successIcon = document.createElement('i');
        successIcon.className = 'fas fa-check';
        successIcon.style.cssText = `
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--success);
            font-size: 0.875rem;
            animation: successBounce 0.5s ease;
        `;
        
        const existingIcon = input.parentNode.querySelector('.fas');
        if (existingIcon) existingIcon.remove();
        input.parentNode.appendChild(successIcon);
        
        setTimeout(() => {
            input.style.borderColor = '';
            input.classList.remove('success');
        }, 2000);
    } else {
        input.classList.add('error');
        input.classList.remove('success');
        input.style.borderColor = 'var(--error)';
        
        // Add error animation
        input.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            input.style.animation = '';
        }, 500);
    }
    
    return isValid;
}

function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!validateInput(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function showFormErrors(form) {
    const firstError = form.querySelector('.error');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
    }
}

// Enhanced table interactions
function initializeTables() {
    const tables = document.querySelectorAll('.table');
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            
            // Enhanced hover effects
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(8px) scale(1.01)';
                this.style.zIndex = '10';
                
                // Highlight corresponding cells
                const cells = this.querySelectorAll('td');
                cells.forEach(cell => {
                    cell.style.backgroundColor = 'rgba(79, 70, 229, 0.08)';
                });
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.zIndex = '';
                
                const cells = this.querySelectorAll('td');
                cells.forEach(cell => {
                    cell.style.backgroundColor = '';
                });
            });
            
            // Click animation
            row.addEventListener('click', function() {
                this.style.animation = 'rowClick 0.3s ease';
                setTimeout(() => {
                    this.style.animation = '';
                }, 300);
            });
        });
        
        // Add sortable columns
        addTableSorting(table);
        
        // Add row selection if checkboxes present
        addRowSelection(table);
    });
}

function addTableSorting(table) {
    const headers = table.querySelectorAll('th');
    
    headers.forEach((header, index) => {
        if (!header.classList.contains('no-sort')) {
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.style.position = 'relative';
            header.classList.add('sortable');
            
            const sortIcon = document.createElement('i');
            sortIcon.className = 'fas fa-sort sort-icon';
            sortIcon.style.cssText = `
                margin-left: 0.5rem;
                opacity: 0.5;
                transition: all 0.2s ease;
                font-size: 0.75rem;
            `;
            header.appendChild(sortIcon);
            
            header.addEventListener('mouseenter', () => {
                sortIcon.style.opacity = '1';
                header.style.backgroundColor = 'rgba(79, 70, 229, 0.05)';
            });
            
            header.addEventListener('mouseleave', () => {
                sortIcon.style.opacity = '0.5';
                header.style.backgroundColor = '';
            });
            
            header.addEventListener('click', () => {
                // Add sorting logic here
                sortIcon.style.animation = 'sortRotate 0.3s ease';
                setTimeout(() => {
                    sortIcon.style.animation = '';
                }, 300);
            });
        }
    });
}

function addRowSelection(table) {
    const checkboxes = table.querySelectorAll('input[type="checkbox"]');
    if (checkboxes.length === 0) return;
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('selected');
                row.style.backgroundColor = 'rgba(79, 70, 229, 0.1)';
            } else {
                row.classList.remove('selected');
                row.style.backgroundColor = '';
            }
        });
    });
}

// Enhanced sidebar
function initializeSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('sidebar-open');
            
            // Add animation class
            sidebar.style.animation = sidebar.classList.contains('show') 
                ? 'slideInLeft 0.3s ease' 
                : 'slideOutLeft 0.3s ease';
        });
    }

    // Active state management with animation
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    const currentPath = window.location.pathname;
    
    menuLinks.forEach(link => {
        // Enhanced active state detection
        const isActive = checkIfLinkIsActive(link.href, currentPath);
        
        if (isActive) {
            link.classList.add('active');
            
            // Add pulse animation to active link
            setTimeout(() => {
                link.style.animation = 'activePulse 0.6s ease';
                setTimeout(() => {
                    link.style.animation = '';
                }, 600);
            }, 100);
        }
        
        // Enhanced hover effects
        link.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateX(8px)';
                this.querySelector('i').style.transform = 'scale(1.1)';
            }
        });
        
        link.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = '';
                this.querySelector('i').style.transform = '';
            }
        });
    });
}

function checkIfLinkIsActive(href, currentPath) {
    return href.includes(currentPath) || 
           (currentPath.includes('/employees/') && href.includes('/employees/')) ||
           (currentPath.includes('/attendance/') && href.includes('/attendance/')) ||
           (currentPath.includes('/petty_cash/') && href.includes('/petty_cash/')) ||
           (currentPath.includes('/reports/') && href.includes('/reports/')) ||
           (currentPath.includes('/settings/') && href.includes('/settings/')) ||
           (currentPath.includes('/tasks/') && href.includes('/tasks/')) ||
           (currentPath.includes('/salary/') && href.includes('/salary/'));
}

// Animated statistics with enhanced effects
function initializeStats() {
    const statNumbers = document.querySelectorAll('.stat-info h3');
    
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent) || 0;
        if (finalValue > 0) {
            animateNumberWithEffects(stat, finalValue);
        }
    });
}

function animateNumberWithEffects(element, finalValue) {
    const duration = 2000;
    const steps = 60;
    const increment = finalValue / steps;
    let current = 0;
    
    // Add counting animation class
    element.style.animation = 'countUp 2s ease-out';
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= finalValue) {
            current = finalValue;
            clearInterval(timer);
            
            // Add completion effect
            element.style.animation = 'countComplete 0.5s ease';
            setTimeout(() => {
                element.style.animation = '';
            }, 500);
        }
        element.textContent = Math.floor(current);
    }, duration / steps);
}

// Enhanced loading states
function addLoadingState(button) {
    if (button.classList.contains('loading')) return;
    
    const originalText = button.innerHTML;
    button.classList.add('loading');
    button.disabled = true;
    
    // Enhanced loading animation
    button.innerHTML = `
        <div class="loading-spinner" style="
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        "></div>
        Loading...
    `;
    
    // Auto-remove loading state
    setTimeout(() => {
        removeLoadingState(button, originalText);
    }, 3000);
}

function removeLoadingState(button, originalText) {
    button.classList.remove('loading');
    button.disabled = false;
    button.innerHTML = originalText;
}

// Enhanced tooltip system
function initializeTooltips() {
    const elements = document.querySelectorAll('[data-tooltip]');
    
    elements.forEach(element => {
        element.addEventListener('mouseenter', showEnhancedTooltip);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('mousemove', updateTooltipPosition);
    });
}

function showEnhancedTooltip(e) {
    const text = e.target.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip enhanced-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: var(--gray-900);
        color: var(--white);
        padding: 0.75rem 1rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        z-index: 1000;
        pointer-events: none;
        opacity: 0;
        transition: all 0.3s ease;
        white-space: nowrap;
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    updateTooltipPosition(e);
    
    // Show tooltip with animation
    setTimeout(() => {
        tooltip.style.opacity = '1';
        tooltip.style.transform = 'translateY(-8px)';
    }, 10);
    
    e.target.tooltipElement = tooltip;
}

function updateTooltipPosition(e) {
    const tooltip = e.target.tooltipElement;
    if (!tooltip) return;
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 12 + 'px';
}

function hideTooltip(e) {
    const tooltip = e.target.tooltipElement;
    if (tooltip) {
        tooltip.style.opacity = '0';
        tooltip.style.transform = 'translateY(0)';
        setTimeout(() => {
            tooltip.remove();
        }, 300);
        e.target.tooltipElement = null;
    }
}

// Dark mode toggle
function initializeDarkMode() {
    // Check for saved dark mode preference
    const savedMode = localStorage.getItem('darkMode');
    if (savedMode === 'true') {
        document.body.classList.add('dark-mode');
    }
    
    // Create dark mode toggle button
    const toggle = document.createElement('button');
    toggle.innerHTML = '<i class="fas fa-moon"></i>';
    toggle.className = 'dark-mode-toggle';
    toggle.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: none;
        background: var(--primary-indigo);
        color: var(--white);
        cursor: pointer;
        box-shadow: var(--shadow-lg);
        transition: all var(--transition-normal);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    toggle.addEventListener('click', toggleDarkMode);
    toggle.addEventListener('mouseenter', () => {
        toggle.style.transform = 'scale(1.1)';
        toggle.style.boxShadow = 'var(--shadow-xl)';
    });
    toggle.addEventListener('mouseleave', () => {
        toggle.style.transform = 'scale(1)';
        toggle.style.boxShadow = 'var(--shadow-lg)';
    });
    
    document.body.appendChild(toggle);
    
    // Update icon based on current mode
    updateDarkModeIcon(toggle);
}

function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
    
    const toggle = document.querySelector('.dark-mode-toggle');
    updateDarkModeIcon(toggle);
    
    // Add transition animation
    document.body.style.transition = 'all 0.3s ease';
    setTimeout(() => {
        document.body.style.transition = '';
    }, 300);
}

function updateDarkModeIcon(toggle) {
    const isDark = document.body.classList.contains('dark-mode');
    const icon = toggle.querySelector('i');
    icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}

// Utility functions
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Enhanced AJAX helper
function ajaxRequest(url, method = 'GET', data = null, options = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        // Add loading indicator
        const loadingOverlay = options.showLoading !== false ? showLoadingOverlay() : null;
        
        xhr.onload = function() {
            if (loadingOverlay) hideLoadingOverlay(loadingOverlay);
            
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(`Request failed with status ${xhr.status}`));
            }
        };
        
        xhr.onerror = function() {
            if (loadingOverlay) hideLoadingOverlay(loadingOverlay);
            reject(new Error('Network error'));
        };
        
        if (data) {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

function showLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeIn 0.2s ease;
    `;
    
    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';
    spinner.style.cssText = `
        width: 50px;
        height: 50px;
        border: 4px solid var(--gray-200);
        border-top: 4px solid var(--primary-indigo);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    `;
    
    overlay.appendChild(spinner);
    document.body.appendChild(overlay);
    return overlay;
}

function hideLoadingOverlay(overlay) {
    if (overlay && overlay.parentNode) {
        overlay.style.animation = 'fadeOut 0.2s ease forwards';
        setTimeout(() => overlay.remove(), 200);
    }
}

// Performance optimizations
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
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
}

// Add dynamic CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes progressBar {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    @keyframes slideUp {
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
    
    @keyframes modalSlideOut {
        to {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.95);
        }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    @keyframes fadeOut {
        to { opacity: 0; }
    }
    
    @keyframes focusRing {
        from {
            transform: scale(0.95);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    @keyframes successBounce {
        0% {
            transform: translateY(-50%) scale(0);
        }
        50% {
            transform: translateY(-50%) scale(1.2);
        }
        100% {
            transform: translateY(-50%) scale(1);
        }
    }
    
    @keyframes rowClick {
        0% { background-color: transparent; }
        50% { background-color: rgba(79, 70, 229, 0.1); }
        100% { background-color: transparent; }
    }
    
    @keyframes sortRotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(180deg); }
    }
    
    @keyframes slideInLeft {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
    }
    
    @keyframes slideOutLeft {
        from { transform: translateX(0); }
        to { transform: translateX(-100%); }
    }
    
    @keyframes activePulse {
        0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
        100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
    }
    
    @keyframes countUp {
        from { transform: scale(0.5); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    
    @keyframes countComplete {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);

// Export functions for global use
window.EMS = {
    dismissAlert,
    closeModalWithAnimation,
    addLoadingState,
    removeLoadingState,
    formatCurrency,
    formatDate,
    ajaxRequest,
    showLoadingOverlay,
    hideLoadingOverlay,
    validateInput,
    validateForm,
    debounce,
    throttle
};


