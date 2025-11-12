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

// Récupérer les informations de l'utilisateur
$utilisateur = $_SESSION['utilisateur'];

// Compter les participations de l'utilisateur
try {
    $requete_participations = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM participations 
        WHERE utilisateur_id = ?
    ");
    $requete_participations->execute([$utilisateur['id']]);
    $participations = $requete_participations->fetch(PDO::FETCH_ASSOC);
    
    // Compter les activités créées (si organisateur)
    $requete_activites = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM activites 
        WHERE organisateur_id = ?
    ");
    $requete_activites->execute([$utilisateur['id']]);
    $activites_crees = $requete_activites->fetch(PDO::FETCH_ASSOC);
    
    // Compter les favoris
    $requete_favoris = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM favoris 
        WHERE utilisateur_id = ?
    ");
    $requete_favoris->execute([$utilisateur['id']]);
    $favoris = $requete_favoris->fetch(PDO::FETCH_ASSOC);
    
    // Compter les activités à venir
    $requete_avenir = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM participations p
        JOIN activites a ON p.activite_id = a.id
        WHERE p.utilisateur_id = ? AND a.date_activite > NOW()
    ");
    $requete_avenir->execute([$utilisateur['id']]);
    $activites_avenir = $requete_avenir->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - FOSHA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Mon Compte</h1>
            <p class="lead">Bienvenue, <?php echo $utilisateur['prenom']; ?> !</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <!-- Afficher les erreurs -->
            <?php if (isset($erreur)): ?>
                <div class="alert alert-danger"><?php echo $erreur; ?></div>
            <?php endif; ?>

            <!-- Statistiques personnelles -->
            <div class="row mb-5">
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon users">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $participations['total'] ?? 0; ?></h3>
                        <p class="stats-label">Participations</p>
                    </div>
                </div>
                
                <?php if ($utilisateur['est_organisateur']): ?>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon activities">
                            <i class="fas fa-hiking"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $activites_crees['total'] ?? 0; ?></h3>
                        <p class="stats-label">Activités créées</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon organizers">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $favoris['total'] ?? 0; ?></h3>
                        <p class="stats-label">Favoris</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon registrations">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $activites_avenir['total'] ?? 0; ?></h3>
                        <p class="stats-label">À venir</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Informations du profil -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informations personnelles</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Prénom :</strong>
                                    <p><?php echo htmlspecialchars($utilisateur['prenom']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Nom :</strong>
                                    <p><?php echo htmlspecialchars($utilisateur['nom']); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Email :</strong>
                                    <p><?php echo htmlspecialchars($utilisateur['email']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Ville :</strong>
                                    <p><?php echo htmlspecialchars($utilisateur['ville']); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Statut :</strong>
                                    <p>
                                        <?php if ($utilisateur['est_organisateur']): ?>
                                            <span class="badge bg-success me-2">Organisateur</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary me-2">Participant</span>
                                        <?php endif; ?>
                                        <?php if ($utilisateur['est_admin']): ?>
                                            <span class="badge bg-warning">Administrateur</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Membre depuis :</strong>
                                    <p><?php echo date('d/m/Y'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activités récentes -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Mes prochaines activités</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                $requete_activites_avenir = $connexion->prepare("
                                    SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone
                                    FROM participations p
                                    JOIN activites a ON p.activite_id = a.id
                                    LEFT JOIN categories c ON a.categorie_id = c.id
                                    WHERE p.utilisateur_id = ? AND a.date_activite > NOW()
                                    ORDER BY a.date_activite ASC
                                    LIMIT 3
                                ");
                                $requete_activites_avenir->execute([$utilisateur['id']]);
                                $activites_utilisateur = $requete_activites_avenir->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                $activites_utilisateur = [];
                            }
                            ?>
                            
                            <?php if (empty($activites_utilisateur)): ?>
                                <p class="text-muted text-center py-3">Aucune activité à venir</p>
                                <div class="text-center">
                                    <a href="activite.php" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Découvrir des activités
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activites_utilisateur as $activite): 
                                        $date_activite = new DateTime($activite['date_activite']);
                                    ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activite['titre']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo $date_activite->format('d/m/Y à H:i'); ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($activite['ville']); ?>
                                                    </small>
                                                </div>
                                                <a href="activity_details.php?id=<?php echo $activite['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    Voir
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="mes_participations.php" class="btn btn-outline-primary btn-sm">
                                        Voir toutes mes participations
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Actions rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="activite.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Explorer les activités
                                </a>
                                
                                <?php if ($utilisateur['est_organisateur']): ?>
                                    <a href="create_activity.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Créer une activité
                                    </a>
                                    <a href="page_organisateur_dashboard.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-user-tie me-1"></i>Tableau de bord organisateur
                                    </a>
                                <?php else: ?>
                                    <a href="page_organisateur.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-user-tie me-1"></i>Devenir organisateur
                                    </a>
                                <?php endif; ?>
                                
                                <a href="commentaires.php" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-comments me-1"></i>Mes commentaires
                                </a>
                                
                                <a href="deconnexion.php" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Statistiques</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Participations totales
                                    <span class="badge bg-primary rounded-pill"><?php echo $participations['total'] ?? 0; ?></span>
                                </div>
                                <?php if ($utilisateur['est_organisateur']): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Activités créées
                                    <span class="badge bg-success rounded-pill"><?php echo $activites_crees['total'] ?? 0; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Activités favorites
                                    <span class="badge bg-danger rounded-pill"><?php echo $favoris['total'] ?? 0; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Activités à venir
                                    <span class="badge bg-info rounded-pill"><?php echo $activites_avenir['total'] ?? 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>
</body>
</html>