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

$utilisateur = $_SESSION['utilisateur'];

// Récupérer les participations de l'utilisateur
try {
    $requete_participations = $connexion->prepare("
        SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
               u.prenom as organisateur_prenom, u.nom as organisateur_nom,
               p.date_inscription,
               COUNT(part.id) as participants_actuels
        FROM participations p
        JOIN activites a ON p.activite_id = a.id
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN utilisateurs u ON a.organisateur_id = u.id
        LEFT JOIN participations part ON a.id = part.activite_id
        WHERE p.utilisateur_id = ?
        GROUP BY a.id, p.date_inscription
        ORDER BY a.date_activite DESC
    ");
    $requete_participations->execute([$utilisateur['id']]);
    $participations = $requete_participations->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les statistiques
    $total_participations = count($participations);
    $activites_avenir = array_filter($participations, function($activite) {
        $date_activite = new DateTime($activite['date_activite']);
        $maintenant = new DateTime();
        return $date_activite > $maintenant;
    });
    $activites_passees = array_filter($participations, function($activite) {
        $date_activite = new DateTime($activite['date_activite']);
        $maintenant = new DateTime();
        return $date_activite < $maintenant;
    });
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des participations : " . $e->getMessage();
    $participations = [];
    $total_participations = 0;
    $activites_avenir = [];
    $activites_passees = [];
}

// Traitement de la désinscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['se_desinscrire'])) {
    $activite_id = $_POST['activite_id'];
    
    try {
        $requete_desinscription = $connexion->prepare("
            DELETE FROM participations 
            WHERE utilisateur_id = ? AND activite_id = ?
        ");
        $requete_desinscription->execute([$utilisateur['id'], $activite_id]);
        
        $succes = "Désinscription réussie !";
        
        // Recharger la page pour actualiser les données
        header('Location: mes_participations.php');
        exit;
        
    } catch (PDOException $e) {
        $erreur = "Erreur lors de la désinscription : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Participations - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Mes Participations</h1>
            <p class="lead">Retrouvez toutes les activités auxquelles vous êtes inscrit</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <!-- Messages -->
            <?php if (isset($succes)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $succes; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($erreur)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $erreur; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon users">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $total_participations; ?></h3>
                        <p class="stats-label">Total des participations</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon activities">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="stats-number"><?php echo count($activites_avenir); ?></h3>
                        <p class="stats-label">Activités à venir</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon organizers">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="stats-number"><?php echo count($activites_passees); ?></h3>
                        <p class="stats-label">Activités terminées</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if (empty($participations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-check fa-4x text-muted mb-4"></i>
                            <h3 class="text-muted">Aucune participation</h3>
                            <p class="text-muted mb-4">Vous n'êtes inscrit à aucune activité pour le moment.</p>
                            <a href="activite.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-search me-2"></i>Découvrir les activités
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Navigation par onglets -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">Vos activités</h5>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <div class="btn-group">
                                            <a href="?section=avenir" class="btn btn-outline-primary btn-sm <?php echo (!isset($_GET['section']) || $_GET['section'] == 'avenir') ? 'active' : ''; ?>">
                                                À venir (<?php echo count($activites_avenir); ?>)
                                            </a>
                                            <a href="?section=terminees" class="btn btn-outline-secondary btn-sm <?php echo (isset($_GET['section']) && $_GET['section'] == 'terminees') ? 'active' : ''; ?>">
                                                Terminées (<?php echo count($activites_passees); ?>)
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php 
                        $section_active = $_GET['section'] ?? 'avenir';
                        ?>

                        <!-- Activités à venir -->
                        <?php if ($section_active == 'avenir'): ?>
                            <?php if (empty($activites_avenir)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Aucune activité à venir</h5>
                                    <p class="text-muted">Explorez nos activités pour trouver votre prochaine expérience.</p>
                                    <a href="activite.php" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Découvrir les activités
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach ($activites_avenir as $activite): 
                                        $date_activite = new DateTime($activite['date_activite']);
                                        $date_inscription = new DateTime($activite['date_inscription']);
                                    ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card activity-card h-100">
                                                <div class="card-header position-relative">
                                                    <span class="badge" style="background-color: <?php echo $activite['categorie_couleur']; ?>">
                                                        <?php echo $activite['categorie_icone']; ?> <?php echo htmlspecialchars($activite['categorie_nom']); ?>
                                                    </span>
                                                    <span class="badge bg-success position-absolute end-0 top-0 me-3 mt-2">
                                                        À venir
                                                    </span>
                                                </div>
                                                <div class="card-body d-flex flex-column">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($activite['titre']); ?></h5>
                                                    <p class="card-text flex-grow-1 text-muted small">
                                                        <?php echo nl2br(htmlspecialchars(substr($activite['description'], 0, 100) . '...')); ?>
                                                    </p>
                                                    
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            Organisé par <?php echo htmlspecialchars($activite['organisateur_prenom'] . ' ' . $activite['organisateur_nom']); ?>
                                                        </small>
                                                    </div>
                                                    
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
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo $date_activite->format('d/m/Y'); ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo $date_activite->format('H:i'); ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="d-grid gap-2">
                                                            <a href="activity_details.php?id=<?php echo $activite['id']; ?>" 
                                                               class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye me-1"></i>Voir détails
                                                            </a>
                                                            
                                                            <form method="POST">
                                                                <input type="hidden" name="activite_id" value="<?php echo $activite['id']; ?>">
                                                                <button type="submit" name="se_desinscrire" 
                                                                        class="btn btn-outline-danger btn-sm w-100"
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir vous désinscrire de cette activité ?')">
                                                                    <i class="fas fa-times me-1"></i>Se désinscrire
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Activités terminées -->
                        <?php if ($section_active == 'terminees'): ?>
                            <?php if (empty($activites_passees)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Aucune activité terminée</h5>
                                    <p class="text-muted">Vos activités terminées apparaîtront ici.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach ($activites_passees as $activite): 
                                        $date_activite = new DateTime($activite['date_activite']);
                                    ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card activity-card h-100">
                                                <div class="card-header position-relative">
                                                    <span class="badge" style="background-color: <?php echo $activite['categorie_couleur']; ?>">
                                                        <?php echo $activite['categorie_icone']; ?> <?php echo htmlspecialchars($activite['categorie_nom']); ?>
                                                    </span>
                                                    <span class="badge bg-secondary position-absolute end-0 top-0 me-3 mt-2">
                                                        Terminée
                                                    </span>
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
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo $date_activite->format('d/m/Y'); ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="d-grid">
                                                            <a href="activity_details.php?id=<?php echo $activite['id']; ?>" 
                                                               class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye me-1"></i>Voir détails
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>
</body>
</html>