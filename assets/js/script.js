// Modern Employee Management System - Enhanced JavaScript
// Smooth animations, improved UX, and modern interactions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeAnimations();
    initializeAlerts();
    initializeModals();
    initializeForms();
    initializeTables();
    initializeSidebar();
    initializeStats();
    initializeTooltips();
    
    // Add page transition effect
    document.body.classList.add('page-enter');
});

// Animation utilities
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
            }
        });
    }, observerOptions);

    // Observe all cards and stat cards
    const animatedElements = document.querySelectorAll('.card, .stat-card');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(el);
    });

    // Add loading animation to buttons on click
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled && !this.classList.contains('no-loading')) {
                addLoadingState(this);
            }
        });
    });
}

// Enhanced alert system
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(function(alert, index) {
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.className = 'alert-close';
        closeBtn.style.cssText = `
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        `;
        
        alert.style.position = 'relative';
        alert.appendChild(closeBtn);
        
        closeBtn.addEventListener('click', () => dismissAlert(alert));
        closeBtn.addEventListener('mouseenter', () => closeBtn.style.opacity = '1');
        closeBtn.addEventListener('mouseleave', () => closeBtn.style.opacity = '0.7');
        
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
        if (e.target.classList.contains('modal-overlay') || e.target.id === 'editModal' || e.target.id === 'approvalModal') {
            const modal = e.target;
            closeModal(modal);
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('[id$="Modal"]:not([style*="display: none"])');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function closeModal(modal) {
    modal.style.animation = 'modalSlideOut 0.3s ease-out forwards';
    setTimeout(() => {
        modal.style.display = 'none';
        modal.style.animation = '';
    }, 300);
}

// Enhanced form handling
function initializeForms() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(function(form) {
        const inputs = form.querySelectorAll('.form-control');
        
        // Add floating labels effect
        inputs.forEach(input => {
            addFloatingLabel(input);
            addInputValidation(input);
        });
        
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                showFormErrors(form);
            } else {
                addLoadingState(form.querySelector('button[type="submit"]'));
            }
        });
    });
}

function addFloatingLabel(input) {
    const wrapper = document.createElement('div');
    wrapper.className = 'input-wrapper';
    wrapper.style.position = 'relative';
    
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    
    input.addEventListener('focus', () => {
        wrapper.classList.add('focused');
    });
    
    input.addEventListener('blur', () => {
        if (!input.value) {
            wrapper.classList.remove('focused');
        }
    });
    
    if (input.value) {
        wrapper.classList.add('focused');
    }
}

function addInputValidation(input) {
    input.addEventListener('blur', function() {
        validateInput(this);
    });
    
    input.addEventListener('input', function() {
        if (this.classList.contains('error')) {
            validateInput(this);
        }
    });
}

function validateInput(input) {
    const isValid = input.checkValidity();
    
    if (isValid) {
        input.classList.remove('error');
        input.style.borderColor = 'var(--success)';
        setTimeout(() => {
            input.style.borderColor = '';
        }, 2000);
    } else {
        input.classList.add('error');
        input.style.borderColor = 'var(--error)';
    }
    
    return isValid;
}

function validateForm(form) {
    const required = form.querySelectorAll('[required]');
    let valid = true;
    
    required.forEach(function(field) {
        if (!validateInput(field)) {
            valid = false;
        }
    });
    
    return valid;
}

function showFormErrors(form) {
    const firstError = form.querySelector('.error');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
        
        // Add shake animation
        firstError.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            firstError.style.animation = '';
        }, 500);
    }
}

// Enhanced table interactions
function initializeTables() {
    const tables = document.querySelectorAll('.table');
    
    tables.forEach(table => {
        // Add row hover effects
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.zIndex = '10';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.zIndex = '';
            });
        });
        
        // Add sortable columns (if needed)
        addTableSorting(table);
    });
}

function addTableSorting(table) {
    const headers = table.querySelectorAll('th');
    
    headers.forEach((header, index) => {
        if (!header.classList.contains('no-sort')) {
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.style.position = 'relative';
            
            const sortIcon = document.createElement('i');
            sortIcon.className = 'fas fa-sort';
            sortIcon.style.cssText = 'margin-left: 0.5rem; opacity: 0.5; transition: opacity 0.2s;';
            header.appendChild(sortIcon);
            
            header.addEventListener('mouseenter', () => sortIcon.style.opacity = '1');
            header.addEventListener('mouseleave', () => sortIcon.style.opacity = '0.5');
        }
    });
}

// Sidebar enhancements
function initializeSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('sidebar-open');
        });
    }
    
    // Add active state management
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    const currentPath = window.location.pathname;
    
    menuLinks.forEach(link => {
        if (link.href.includes(currentPath) || 
            (currentPath.includes('/employees/') && link.href.includes('/employees/')) ||
            (currentPath.includes('/attendance/') && link.href.includes('/attendance/')) ||
            (currentPath.includes('/petty_cash/') && link.href.includes('/petty_cash/')) ||
            (currentPath.includes('/reports/') && link.href.includes('/reports/')) ||
            (currentPath.includes('/settings/') && link.href.includes('/settings/')) ||
            (currentPath.includes('/tasks/') && link.href.includes('/tasks/')) ||
            (currentPath.includes('/salary/') && link.href.includes('/salary/'))) {
            link.classList.add('active');
        }
    });
}

// Animated statistics
function initializeStats() {
    const statNumbers = document.querySelectorAll('.stat-info h3');
    
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent) || 0;
        if (finalValue > 0) {
            animateNumber(stat, finalValue);
        }
    });
}

function animateNumber(element, finalValue) {
    const duration = 2000; // 2 seconds
    const steps = 60;
    const increment = finalValue / steps;
    let current = 0;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= finalValue) {
            current = finalValue;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, duration / steps);
}

// Loading states
function addLoadingState(button) {
    if (button.classList.contains('loading')) return;
    
    const originalText = button.innerHTML;
    button.classList.add('loading');
    button.disabled = true;
    
    button.innerHTML = '<div class="loading"></div> Loading...';
    
    // Remove loading state after form submission or 3 seconds
    setTimeout(() => {
        removeLoadingState(button, originalText);
    }, 3000);
}

function removeLoadingState(button, originalText) {
    button.classList.remove('loading');
    button.disabled = false;
    button.innerHTML = originalText;
}

// Tooltip system
function initializeTooltips() {
    const elements = document.querySelectorAll('[data-tooltip]');
    
    elements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const text = e.target.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: var(--gray-900);
        color: var(--white);
        padding: 0.5rem 0.75rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        z-index: 1000;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
        white-space: nowrap;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    setTimeout(() => tooltip.style.opacity = '1', 10);
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
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
function ajaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        // Add loading indicator
        const loadingOverlay = showLoadingOverlay();
        
        xhr.onload = function() {
            hideLoadingOverlay(loadingOverlay);
            
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error('Request failed'));
            }
        };
        
        xhr.onerror = function() {
            hideLoadingOverlay(loadingOverlay);
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

// Dark mode toggle (optional feature)
function initializeDarkMode() {
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
    `;
    
    toggle.addEventListener('click', toggleDarkMode);
    document.body.appendChild(toggle);
}

function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
    
    const icon = document.querySelector('.dark-mode-toggle i');
    icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
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
    }
}

// Add CSS animations dynamically
const style = document.createElement('style');
style.textContent = `
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
`;
document.head.appendChild(style);

// Export functions for global use
window.EMS = {
    dismissAlert,
    closeModal,
    addLoadingState,
    removeLoadingState,
    formatCurrency,
    formatDate,
    ajaxRequest,
    showLoadingOverlay,
    hideLoadingOverlay
}; s