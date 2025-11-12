<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['utilisateur'])) {
    header('Location: connexion.php');
    exit;
}

// Inclure la connexion à la base de données
include 'bd.php';

// Traitement pour devenir organisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['devenir_organisateur'])) {
    $motivation = trim($_POST['motivation']);
    
    if (empty($motivation)) {
        $erreur = "Veuillez expliquer vos motivations pour devenir organisateur";
    } else {
        try {
            $requete_update = $connexion->prepare("
                UPDATE utilisateurs 
                SET est_organisateur = 1 
                WHERE id = ?
            ");
            $requete_update->execute([$_SESSION['utilisateur']['id']]);
            
            // Mettre à jour la session
            $_SESSION['utilisateur']['est_organisateur'] = 1;
            $succes = "Félicitations ! Vous êtes maintenant organisateur sur FOSHA Maroc.";
            
        } catch (PDOException $e) {
            $erreur = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenir Organisateur - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Devenir Organisateur</h1>
            <p class="lead">Partagez vos passions et créez des expériences uniques au Maroc</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <!-- Messages -->
            <?php if (isset($succes)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $succes; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <div class="mt-2">
                        <a href="create_activity.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-2"></i>Créer votre première activité
                        </a>
                        <a href="page_organisateur_dashboard.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($erreur)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $erreur; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <?php if ($_SESSION['utilisateur']['est_organisateur']): ?>
                        <!-- Si déjà organisateur -->
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <div class="mb-4">
                                    <i class="fas fa-check-circle fa-5x text-success"></i>
                                </div>
                                <h3 class="mb-3">Vous êtes déjà organisateur !</h3>
                                <p class="text-muted mb-4">
                                    Vous pouvez dès maintenant créer et gérer vos activités sur FOSHA Maroc.
                                </p>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                    <a href="create_activity.php" class="btn btn-primary me-md-2">
                                        <i class="fas fa-plus me-2"></i>Créer une activité
                                    </a>
                                    <a href="page_organisateur_dashboard.php" class="btn btn-outline-primary">
                                        <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                                    </a>
                                    <a href="page_utilisateur.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-user me-2"></i>Mon compte
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Formulaire pour devenir organisateur -->
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-user-tie me-2"></i>Devenir Organisateur FOSHA Maroc
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h5><i class="fas fa-star text-warning me-2"></i>Avantages :</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Créer vos propres activités</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Gérer les participants</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Monétiser vos compétences</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Élargir votre réseau</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Promouvoir la culture marocaine</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Accéder aux statistiques détaillées</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fas fa-shield-alt text-primary me-2"></i>Engagements :</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Respecter les participants</li>
                                            <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Honorer les créneaux réservés</li>
                                            <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Fournir un service de qualité</li>
                                            <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Respecter les CGU</li>
                                            <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Maintenir une bonne communication</li>
                                            <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Assurer la sécurité des participants</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils pour réussir :</h6>
                                    <ul class="mb-0">
                                        <li>Choisissez des activités qui vous passionnent vraiment</li>
                                        <li>Soyez précis dans vos descriptions</li>
                                        <li>Fixez des prix justes et transparents</li>
                                        <li>Communiquez régulièrement avec vos participants</li>
                                        <li>Recueillez les retours pour vous améliorer</li>
                                    </ul>
                                </div>
                                
                                <hr>
                                
                                <form method="POST">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Pourquoi souhaitez-vous devenir organisateur ? *</label>
                                        <textarea class="form-control" name="motivation" rows="4" 
                                                  placeholder="Partagez-nous vos motivations, vos passions, le type d'activités que vous aimeriez organiser..." required><?php echo isset($_POST['motivation']) ? htmlspecialchars($_POST['motivation']) : ''; ?></textarea>
                                        <div class="form-text">Cette information nous aide à mieux vous connaître et vous accompagner.</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Quel type d'activités souhaitez-vous organiser ?</label>
                                        <div class="row">
                                            <?php
                                            $types_activites = [
                                                'Randonnées et nature',
                                                'Ateliers culinaires',
                                                'Activités sportives', 
                                                'Visites culturelles',
                                                'Ateliers créatifs',
                                                'Événements sociaux',
                                                'Activités bien-être',
                                                'Aventures et sports extrêmes'
                                            ];
                                            foreach (array_chunk($types_activites, 2) as $chunk): ?>
                                                <div class="col-md-6">
                                                    <?php foreach ($chunk as $type): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="types_activites[]" value="<?php echo $type; ?>" id="<?php echo strtolower(str_replace(' ', '_', $type)); ?>">
                                                            <label class="form-check-label" for="<?php echo strtolower(str_replace(' ', '_', $type)); ?>">
                                                                <?php echo $type; ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="accept_terms" required>
                                        <label class="form-check-label" for="accept_terms">
                                            J'accepte les <a href="#" class="text-primary">conditions générales d'utilisation</a> et m'engage à respecter la charte des organisateurs FOSHA Maroc.
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="devenir_organisateur" class="btn btn-primary btn-lg py-3">
                                            <i class="fas fa-user-tie me-2"></i>Devenir Organisateur FOSHA
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Témoignages -->
                        <div class="card mt-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-comments me-2"></i>Témoignages d'organisateurs
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-light mb-3">
                                            <div class="card-body">
                                                <p class="card-text">"Devenir organisateur sur FOSHA m'a permis de partager ma passion pour la randonnée et de faire découvrir les magnifiques paysages de l'Atlas à des personnes passionnées."</p>
                                                <footer class="blockquote-footer mt-2">Fatima A., <cite title="Source Title">Organisatrice à Marrakech</cite></footer>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light mb-3">
                                            <div class="card-body">
                                                <p class="card-text">"Grâce à FOSHA, j'ai pu transformer mon savoir-faire culinaire en une activité rémunératrice. Les ateliers de cuisine marocaine rencontrent un vrai succès !"</p>
                                                <footer class="blockquote-footer mt-2">Mehdi B., <cite title="Source Title">Organisateur à Casablanca</cite></footer>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>