<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 24px;
        }
        .login-btn {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <!-- Login Button -->
    <a href="auth/login.php" class="btn btn-primary login-btn">
        <i class="bi bi-box-arrow-in-right"></i> Login
    </a>
    
    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 mb-4">
                <i class="bi bi-box-seam"></i> Asset Management System
            </h1>
            <p class="lead mb-4">
                Comprehensive asset tracking and management solution for modern enterprises
            </p>
            <a href="auth/signup.php" class="btn btn-light btn-lg">
                Get Started <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Key Features</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">
                                <i class="bi bi-barcode text-primary"></i>
                            </div>
                            <h4>Asset Tracking</h4>
                            <p>Track all assets with unique IDs, barcodes, and serial numbers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">
                                <i class="bi bi-people text-success"></i>
                            </div>
                            <h4>Role-Based Access</h4>
                            <p>Four distinct dashboards for different user roles and permissions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">
                                <i class="bi bi-graph-up text-warning"></i>
                            </div>
                            <h4>Financial Management</h4>
                            <p>Track costs, depreciation, and maintenance expenses</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">
                                <i class="bi bi-arrow-left-right text-info"></i>
                            </div>
                            <h4>Check In/Out</h4>
                            <p>Streamlined asset movement tracking with audit trails</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">
                                <i class="bi bi-tools text-danger"></i>
                            </div>
                            <h4>Maintenance Tracking</h4>
                            <p>Schedule and track maintenance with status updates</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">
                                <i class="bi bi-clipboard-data text-secondary"></i>
                            </div>
                            <h4>Comprehensive Reporting</h4>
                            <p>Generate detailed reports for audits and decision making</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Asset Management System</h5>
                    <p>Version 1.0</p>
                </div>
                <div class="col-md-6 text-end">
                    <p>&copy; 2026 Data Jasa Plus Sdn Bhd. All rights reserved.</p>
                    <p>Designed following enterprise data dictionary standards</p>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>