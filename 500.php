<?php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - Employee Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        
        .error-content {
            max-width: 600px;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .error-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .error-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .error-btn {
            padding: 1rem 2rem;
            border: 2px solid white;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        
        .error-btn:hover {
            background: white;
            color: #e74c3c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 2rem;
            opacity: 0.8;
            animation: shake 2s ease-in-out infinite;
        }
        
        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            25% {
                transform: translateX(-5px);
            }
            75% {
                transform: translateX(5px);
            }
        }
        
        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .error-btn {
                width: 200px;
            }
        }
        
        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            top: 20%;
            left: 10%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            top: 60%;
            right: 10%;
            width: 120px;
            height: 120px;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            bottom: 20%;
            left: 20%;
            width: 60px;
            height: 60px;
            animation-delay: 4s;
        }
        
        .shape:nth-child(4) {
            top: 40%;
            left: 50%;
            width: 40px;
            height: 40px;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
        
        .error-details {
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 1rem;
            margin: 2rem 0;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border-left: 4px solid rgba(255,255,255,0.5);
        }
        
        .error-tips {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .error-tips h3 {
            margin-bottom: 1rem;
            color: #fff;
        }
        
        .error-tips ul {
            list-style: none;
            padding: 0;
        }
        
        .error-tips li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .error-tips li:last-child {
            border-bottom: none;
        }
        
        .error-tips i {
            margin-right: 0.5rem;
            color: #ffeb3b;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <div class="error-code">500</div>
            
            <h1 class="error-title">Internal Server Error</h1>
            
            <p class="error-message">
                Something went wrong on our end. We're experiencing technical difficulties 
                and our team has been notified. Please try again in a few moments.
            </p>
            
            <div class="error-tips">
                <h3><i class="fas fa-lightbulb"></i> What you can try:</h3>
                <ul>
                    <li><i class="fas fa-redo"></i> Refresh the page</li>
                    <li><i class="fas fa-clock"></i> Wait a few minutes and try again</li>
                    <li><i class="fas fa-arrow-left"></i> Go back to the previous page</li>
                    <li><i class="fas fa-home"></i> Return to the homepage</li>
                </ul>
            </div>
            
            <div class="error-details">
                <strong>Error ID:</strong> <?php echo uniqid('ERR-'); ?><br>
                <strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                <strong>Server:</strong> <?php echo $_SERVER['SERVER_NAME'] ?? 'Unknown'; ?>
            </div>
            
            <div class="error-actions">
                <a href="index.php" class="error-btn">
                    <i class="fas fa-home"></i> Go Home
                </a>
                
                <a href="javascript:location.reload()" class="error-btn">
                    <i class="fas fa-redo"></i> Refresh Page
                </a>
                
                <a href="javascript:history.back()" class="error-btn">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
            </div>
        </div>
    </div>
</body>
</html>