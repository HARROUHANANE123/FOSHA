<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
include 'bd.php';

// Récupérer les statistiques
try {
    // Compter les utilisateurs
    $requete_utilisateurs = $connexion->query("SELECT COUNT(*) as total FROM utilisateurs");
    $total_utilisateurs = $requete_utilisateurs->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les activités
    $requete_activites = $connexion->query("SELECT COUNT(*) as total FROM activites");
    $total_activites = $requete_activites->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les organisateurs
    $requete_organisateurs = $connexion->query("SELECT COUNT(*) as total FROM utilisateurs WHERE est_organisateur = 1");
    $total_organisateurs = $requete_organisateurs->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les activités à venir (limité à 6)
    $requete_activites_avenir = $connexion->prepare("
        SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
               u.prenom as organisateur_prenom, u.nom as organisateur_nom,
               COUNT(p.id) as participants_actuels
        FROM activites a
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN utilisateurs u ON a.organisateur_id = u.id
        LEFT JOIN participations p ON a.id = p.activite_id
        WHERE a.date_activite > NOW() AND a.statut = 'active'
        GROUP BY a.id
        ORDER BY a.date_activite ASC
        LIMIT 6
    ");
    $requete_activites_avenir->execute();
    $activites_avenir = $requete_activites_avenir->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les catégories
    $requete_categories = $connexion->query("SELECT * FROM categories");
    $categories = $requete_categories->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $total_utilisateurs = 150;
    $total_activites = 45;
    $total_organisateurs = 25;
    $activites_avenir = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOSHA - Vivez l'expérience</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Section Hero -->
    <section class="hero-section">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Découvrez le Maroc comme jamais auparavant</h1>
                    <p class="hero-subtitle">Rejoignez la communauté FOSHA et vivez des expériences authentiques à travers tout le Royaume. Des activités uniques créées par des passionnés locaux.</p>
                    <div class="hero-buttons">
                        <a href="activite.php" class="btn btn-light btn-hero me-3">
                            <i class="fas fa-search me-2"></i>Explorer les activités
                        </a>
                        <a href="inscription.php" class="btn btn-outline-light btn-hero">
                            <i class="fas fa-user-plus me-2"></i>Créer un compte
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-image mt-5 mt-lg-0">
                        <div class="activity-image-large bg-primary rounded-3 shadow-lg">
                            <i class="fas fa-mountain-sun"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Statistiques -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="stat-number"><?php echo $total_utilisateurs; ?>+</div>
                    <div class="stat-label">Marocains actifs</div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number"><?php echo $total_activites; ?>+</div>
                    <div class="stat-label">Expériences uniques</div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number"><?php echo $total_organisateurs; ?>+</div>
                    <div class="stat-label">Passionnés locaux</div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">12+</div>
                    <div class="stat-label">Villes marocaines</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Activités Populaires -->
    <section class="py-5" style="background: var(--light);">
        <div class="container">
            <h2 class="section-title">Expériences à venir</h2>
            <p class="section-subtitle">Découvrez les prochaines activités près de chez vous</p>
            
            <?php if (empty($activites_avenir)): ?>
                <div class="row g-4">
                    <div class="col-12 text-center">
                        <p class="text-muted">Aucune activité disponible pour le moment.</p>
                        <?php if (isset($_SESSION['utilisateur']) && $_SESSION['utilisateur']['est_organisateur']): ?>
                            <a href="create_activity.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Créer la première activité
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($activites_avenir as $activite): 
                        $date_activite = new DateTime($activite['date_activite']);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card activity-card h-100">
                                <div class="card-header position-relative">
                                    <span class="badge" style="background-color: <?php echo $activite['categorie_couleur']; ?>">
                                        <?php echo $activite['categorie_icone']; ?> <?php echo htmlspecialchars($activite['categorie_nom']); ?>
                                    </span>
                                    <small class="text-muted position-absolute end-0 top-0 me-3 mt-2">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $activite['participants_actuels']; ?>/<?php echo $activite['participants_max']; ?>
                                    </small>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($activite['titre']); ?></h5>
                                    <p class="card-text flex-grow-1 text-muted small">
                                        <?php echo nl2br(htmlspecialchars(substr($activite['description'], 0, 100) . '...')); ?>
                                    </p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($activite['ville']); ?>
                                            </small>
                                            <strong class="text-primary">
                                                <?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?>
                                            </strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo $date_activite->format('d/m/Y'); ?>
                                            </small>
                                            <a href="activity_details.php?id=<?php echo $activite['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Voir détails
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-5">
                <a href="activite.php" class="btn btn-outline-primary btn-lg">
                    Voir toutes les activités <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Section Catégories -->
    <section class="py-5 bg-white">
        <div class="container">
            <h2 class="section-title">Explorez par catégorie</h2>
            <p class="section-subtitle">Trouvez des activités qui correspondent à vos passions</p>
            
            <div class="row g-4">
                <?php foreach ($categories as $categorie): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="activite.php?categorie=<?php echo $categorie['id']; ?>" class="text-decoration-none">
                            <div class="category-card text-center">
                                <div class="category-icon" style="font-size: 2.5rem;"><?php echo $categorie['icone']; ?></div>
                                <div class="category-name mt-2"><?php echo htmlspecialchars($categorie['nom']); ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>  
    </section>

    <!-- Section Fonctionnalités -->
    <section class="py-5" style="background: var(--light);">
        <div class="container">
            <h2 class="section-title">Comment ça marche</h2>
            <p class="section-subtitle">Découvrez FOSHA en 3 étapes simples</p>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="step-number">1</div>
                        <h4 class="feature-title">Créez votre compte</h4>
                        <p class="feature-text">Inscrivez-vous gratuitement en 2 minutes et rejoignez notre communauté de passionnés marocains.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="step-number">2</div>
                        <h4 class="feature-title">Découvrez des activités</h4>
                        <p class="feature-text">Parcourez des activités authentiques près de chez vous ou créez les vôtres.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="step-number">3</div>
                        <h4 class="feature-title">Vivez l'expérience</h4>
                        <p class="feature-text">Participez à des activités uniques et créez des souvenirs inoubliables au Maroc.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>