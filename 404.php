<?php
// Set the proper 404 status code
http_response_code(404);

// Include database connection if needed for navigation
include 'config/database.php';

// Get the requested URL for error logging
$requested_url = $_SERVER['REQUEST_URI'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

// Optional: Log 404 errors to database or file
try {
    // Uncomment below to log 404 errors
    /*
    $stmt = $pdo->prepare("INSERT INTO error_logs (error_type, requested_url, user_agent, ip_address, created_at) VALUES (?, ?,?, ?, NOW())");
    $stmt->execute(['404', $requested_url, $user_agent, $ip_address]);
    */
} catch (PDOException $e) {
    // Handle silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - EMS Pro</title>
    
    <!-- Enhanced CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Meta tags -->
    <meta name="description" content="Page not found - EMS Pro Employee Management System">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <style>
        :root {
            /* Indigo Color Palette */
            --indigo-50: #eef2ff;
            --indigo-100: #e0e7ff;
            --indigo-200: #c7d2fe;
            --indigo-300: #a5b4fc;
            --indigo-400: #818cf8;
            --indigo-500: #6366f1;
            --indigo-600: #4f46e5;
            --indigo-700: #4338ca;
            --indigo-800: #3730a3;
            --indigo-900: #312e81;
            --white: #ffffff;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--indigo-900) 0%, var(--indigo-800) 30%, var(--indigo-700) 60%, var(--indigo-600) 100%);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 2rem;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(99, 102, 241, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(165, 180, 252, 0.1) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
        }

        @keyframes backgroundMove {
            0%, 100% { 
                background-position: 0% 0%, 100% 100%, 50% 50%;
            }
            50% { 
                background-position: 100% 100%, 0% 0%, 60% 40%;
            }
        }

        .error-content {
            text-align: center;
            color: var(--white);
            max-width: 600px;
            position: relative;
            z-index: 2;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-animation {
            position: relative;
            margin-bottom: 2rem;
        }

        .error-number {
            font-size: clamp(8rem, 20vw, 12rem);
            font-weight: 900;
            line-height: 1;
            background: linear-gradient(45deg, var(--white), var(--indigo-300));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 20px rgba(255, 255, 255, 0.3);
            animation: pulse404 2s ease-in-out infinite;
            position: relative;
        }

        @keyframes pulse404 {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .error-number::before {
            content: '404';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(165, 180, 252, 0.4), transparent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }

        .floating-icons {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .floating-icon {
            position: absolute;
            font-size: 2rem;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
            color: var(--indigo-300);
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 15%; animation-delay: 1s; }
        .floating-icon:nth-child(3) { top: 60%; left: 5%; animation-delay: 2s; }
        .floating-icon:nth-child(4) { bottom: 20%; right: 10%; animation-delay: 3s; }
        .floating-icon:nth-child(5) { top: 40%; left: 80%; animation-delay: 4s; }
        .floating-icon:nth-child(6) { bottom: 40%; left: 20%; animation-delay: 5s; }

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                opacity: 0.1;
            }
            50% { 
                transform: translateY(-20px) rotate(180deg); 
                opacity: 0.3;
            }
        }

        .error-title {
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--white), var(--indigo-200));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-subtitle {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            margin-bottom: 1.5rem;
            opacity: 0.9;
            line-height: 1.6;
            color: var(--indigo-100);
        }

        .error-description {
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            opacity: 0.8;
            line-height: 1.8;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            color: var(--indigo-100);
        }

        .error-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
            margin-bottom: 2rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            font-size: 1rem;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.6s ease;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.95);
            color: var(--indigo-700);
            border: 2px solid transparent;
            box-shadow: 0 4px 20px rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(255, 255, 255, 0.4);
            background: var(--white);
        }

        .btn-secondary {
            background: transparent;
            color: var(--white);
            border: 2px solid rgba(255, 255, 255, 0.7);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--white);
            transform: translateY(-3px);
        }

        .search-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .search-input {
            flex: 1;
            padding: 0.875rem 1.25rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.9);
            color: var(--indigo-900);
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--indigo-300);
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.3);
        }

        .search-input::placeholder {
            color: var(--indigo-400);
        }

        .particles-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .error-code-display {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--white);
            font-size: 0.875rem;
            z-index: 10;
        }

        .breadcrumb {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--white);
            font-size: 0.875rem;
            z-index: 10;
        }

        @media (max-width: 768px) {
            .error-container {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
                max-width: 300px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .search-form {
                flex-direction: column;
            }

            .error-code-display,
            .breadcrumb {
                position: relative;
                top: auto;
                left: auto;
                right: auto;
                margin: 1rem auto;
                max-width: fit-content;
            }
        }

        /* Loading animation for page transitions */
        .page-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--indigo-900), var(--indigo-600));
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
        }

        .page-transition.active {
            opacity: 1;
            visibility: visible;
        }

        .transition-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <i class="fas fa-home"></i>
        <span>Home / Error 404</span>
    </div>

    <!-- Error Code Display -->
    <div class="error-code-display">
        <strong>Error Code:</strong> HTTP 404<br>
        <small>Requested: <?php echo htmlspecialchars($requested_url); ?></small>
    </div>

    <!-- Particle Background Canvas -->
    <canvas class="particles-bg" id="particlesCanvas"></canvas>

    <!-- Page Transition Overlay -->
    <div class="page-transition" id="pageTransition">
        <div class="transition-spinner"></div>
    </div>

    <div class="error-container">
        <!-- Floating Background Icons -->
        <div class="floating-icons">
            <i class="fas fa-search floating-icon"></i>
            <i class="fas fa-question-circle floating-icon"></i>
            <i class="fas fa-exclamation-triangle floating-icon"></i>
            <i class="fas fa-home floating-icon"></i>
            <i class="fas fa-cog floating-icon"></i>
            <i class="fas fa-compass floating-icon"></i>
        </div>

        <div class="error-content">
            <div class="error-animation">
                <div class="error-number">404</div>
            </div>

            <h1 class="error-title">Oops! Page Not Found</h1>
            <p class="error-subtitle">Houston, we have a problem!</p>
            <p class="error-description">
                The page you're looking for seems to have wandered off into cyberspace. 
                It might have been moved, deleted, or you entered the wrong URL. 
                Don't worry though, we'll help you find your way back!
            </p>

            <div class="error-actions">
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i>
                        Go Back
                    </button>
                </div>

                
            </div>
        </div>
    </div>

    <script>
        // Enhanced 404 Page JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            initializeParticleBackground();
            initializeAnimations();
            initializeErrorLogging();
            
            // Auto-focus search input
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                setTimeout(() => searchInput.focus(), 1000);
            }
        });

        // Particle background animation with indigo theme
        function initializeParticleBackground() {
            const canvas = document.getElementById('particlesCanvas');
            const ctx = canvas.getContext('2d');
            let particles = [];

            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }

            function createParticles() {
                particles = [];
                const particleCount = Math.floor((canvas.width * canvas.height) / 20000);
                
                for (let i = 0; i < particleCount; i++) {
                    particles.push({
                        x: Math.random() * canvas.width,
                        y: Math.random() * canvas.height,
                        size: Math.random() * 2 + 1,
                        speedX: (Math.random() - 0.5) * 0.3,
                        speedY: (Math.random() - 0.5) * 0.3,
                        opacity: Math.random() * 0.3 + 0.1,
                        color: `rgba(165, 180, 252, ${Math.random() * 0.5 + 0.1})` // Indigo-300 with varying opacity
                    });
                }
            }

            function animateParticles() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                particles.forEach(particle => {
                    particle.x += particle.speedX;
                    particle.y += particle.speedY;
                    
                    // Wrap around screen
                    if (particle.x > canvas.width) particle.x = 0;
                    if (particle.x < 0) particle.x = canvas.width;
                    if (particle.y > canvas.height) particle.y = 0;
                    if (particle.y < 0) particle.y = canvas.height;
                    
                    // Draw particle
                    ctx.beginPath();
                    ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                    ctx.fillStyle = particle.color;
                    ctx.fill();
                    
                    // Draw connections with indigo theme
                    particles.forEach(otherParticle => {
                        const dx = particle.x - otherParticle.x;
                        const dy = particle.y - otherParticle.y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance < 80) {
                            ctx.beginPath();
                            ctx.moveTo(particle.x, particle.y);
                            ctx.lineTo(otherParticle.x, otherParticle.y);
                            ctx.strokeStyle = `rgba(165, 180, 252, ${0.1 * (1 - distance / 80)})`;
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    });
                });
                
                requestAnimationFrame(animateParticles);
            }

            resizeCanvas();
            createParticles();
            animateParticles();

            window.addEventListener('resize', () => {
                resizeCanvas();
                createParticles();
            });
        }

        // Enhanced animations
        function initializeAnimations() {
            // Add typing effect to error description
            typeWriter();
        }

        function typeWriter() {
            const description = document.querySelector('.error-description');
            const text = description.textContent;
            description.textContent = '';
            description.style.opacity = '1';
            
            let i = 0;
            function type() {
                if (i < text.length) {
                    description.textContent += text.charAt(i);
                    i++;
                    setTimeout(type, 30);
                }
            }
            
            setTimeout(type, 1000);
        }

        // Error logging for analytics
        function initializeErrorLogging() {
            // Log 404 error for analytics (optional)
            if (navigator.sendBeacon) {
                const data = new FormData();
                data.append('error', '404');
                data.append('url', window.location.href);
                data.append('referrer', document.referrer);
                data.append('timestamp', Date.now());
                
                // Uncomment to enable error logging
                // navigator.sendBeacon('api/log_error.php', data);
            }
        }

        // Page transition handler
        function handlePageTransition(event, url) {
            event.preventDefault();
            
            const transition = document.getElementById('pageTransition');
            transition.classList.add('active');
            
            setTimeout(() => {
                window.location.href = url;
            }, 500);
        }

        // Go back function
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'index.php';
            }
        }

        // Search handler
        function handleSearch(event) {
            event.preventDefault();
            
            const searchInput = document.getElementById('searchInput');
            const query = searchInput.value.trim();
            
            if (query) {
                // Show loading state
                const submitBtn = event.target.querySelector('button[type="submit"]');
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                submitBtn.disabled = true;
                
                // Simulate search and redirect
                setTimeout(() => {
                    const searchUrl = `index.php?search=${encodeURIComponent(query)}`;
                    window.location.href = searchUrl;
                }, 800);
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'Escape' to go back
            if (e.key === 'Escape') {
                goBack();
            }
            
            // Press '/' to focus search
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // Add mouse trail effect with indigo theme
        document.addEventListener('mousemove', function(e) {
            createMouseTrail(e.clientX, e.clientY);
        });

        function createMouseTrail(x, y) {
            const trail = document.createElement('div');
            trail.style.cssText = `
                position: fixed;
                left: ${x}px;
                top: ${y}px;
                width: 4px;
                height: 4px;
                background: rgba(165, 180, 252, 0.6);
                border-radius: 50%;
                pointer-events: none;
                z-index: 1000;
                animation: trailFade 0.8s ease-out forwards;
                transform: translate(-50%, -50%);
            `;
            
            document.body.appendChild(trail);
            
            setTimeout(() => trail.remove(), 800);
        }

        // Add CSS for mouse trail animation
        const trailStyle = document.createElement('style');
        trailStyle.textContent = `
            @keyframes trailFade {
                from {
                    opacity: 1;
                    transform: translate(-50%, -50%) scale(1);
                }
                to {
                    opacity: 0;
                    transform: translate(-50%, -50%) scale(0.5);
                }
            }
        `;
        document.head.appendChild(trailStyle);

     
    </script>
</body>
</html>
