<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="page_accueil.php">
            <div class="logo-container">
                <div class="logo-icon"></div>
                <div>
                    <div class="fosha-logo">FOSHA</div>
                    <div class="fosha-tagline">Vivez l'expérience</div>
                </div>
            </div>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="page_accueil.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="activite.php">Activités</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="page_organisateur.php">Devenir organisateur</a>
                </li>
                <?php if (isset($_SESSION['utilisateur']) && $_SESSION['utilisateur']['est_admin']): ?>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="page_admin.php">
                            <i class="fas fa-cog me-1"></i>Administration
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2">
                <?php if (isset($_SESSION['utilisateur'])): ?>
                    <!-- Si l'utilisateur est connecté -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo $_SESSION['utilisateur']['prenom']; ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="page_utilisateur.php">
                                <i class="fas fa-user me-2"></i>Mon compte
                            </a></li>
                            <li><a class="dropdown-item" href="mes_participations.php">
                                <i class="fas fa-calendar-check me-2"></i>Mes participations
                            </a></li>
                            <li><a class="dropdown-item" href="commentaires.php">
                                <i class="fas fa-comments me-2"></i>Mes commentaires
                            </a></li>
                            <?php if ($_SESSION['utilisateur']['est_organisateur']): ?>
                                <li><a class="dropdown-item" href="page_organisateur_dashboard.php">
                                    <i class="fas fa-user-tie me-2"></i>Tableau de bord
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="deconnexion.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- Si l'utilisateur n'est pas connecté -->
                    <a href="connexion.php" class="btn btn-outline-primary">Connexion</a>
                    <a href="inscription.php" class="btn btn-primary">Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
</body>
</html>