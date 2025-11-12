<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
include 'bd.php';

// Vérifier si l'ID de l'activité est fourni
if (!isset($_GET['id'])) {
    header('Location: activite.php');
    exit;
}

$activite_id = $_GET['id'];

try {
    // Récupérer les détails de l'activité
    $requete_activite = $connexion->prepare("
        SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
               u.prenom as organisateur_prenom, u.nom as organisateur_nom, u.email as organisateur_email, u.ville as organisateur_ville,
               COUNT(p.id) as participants_actuels
        FROM activites a
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN utilisateurs u ON a.organisateur_id = u.id
        LEFT JOIN participations p ON a.id = p.activite_id
        WHERE a.id = ?
        GROUP BY a.id
    ");
    
    $requete_activite->execute([$activite_id]);
    $activite = $requete_activite->fetch(PDO::FETCH_ASSOC);
    
    if (!$activite) {
        header('Location: activite.php');
        exit;
    }
    
    // Vérifier si l'utilisateur est déjà inscrit
    $est_inscrit = false;
    if (isset($_SESSION['utilisateur'])) {
        $requete_inscription = $connexion->prepare("
            SELECT id FROM participations 
            WHERE utilisateur_id = ? AND activite_id = ?
        ");
        $requete_inscription->execute([$_SESSION['utilisateur']['id'], $activite_id]);
        $est_inscrit = $requete_inscription->fetch() !== false;
    }
    
    // Vérifier si l'activité est dans les favoris
    $est_favori = false;
    if (isset($_SESSION['utilisateur'])) {
        $requete_favori = $connexion->prepare("
            SELECT id FROM favoris 
            WHERE utilisateur_id = ? AND activite_id = ?
        ");
        $requete_favori->execute([$_SESSION['utilisateur']['id'], $activite_id]);
        $est_favori = $requete_favori->fetch() !== false;
    }
    
    // Récupérer les commentaires approuvés
    $requete_commentaires = $connexion->prepare("
        SELECT c.*, u.prenom, u.nom 
        FROM commentaires c
        JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.activite_id = ? AND c.statut = 'approuve'
        ORDER BY c.date_creation DESC
    ");
    $requete_commentaires->execute([$activite_id]);
    $commentaires = $requete_commentaires->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des détails de l'activité : " . $e->getMessage();
}

// Traitement de l'inscription/désinscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['utilisateur'])) {
    if (isset($_POST['s_inscrire'])) {
        try {
            // Vérifier s'il reste des places
            if ($activite['participants_actuels'] < $activite['participants_max']) {
                $requete_inscription = $connexion->prepare("
                    INSERT INTO participations (utilisateur_id, activite_id) 
                    VALUES (?, ?)
                ");
                $requete_inscription->execute([$_SESSION['utilisateur']['id'], $activite_id]);
                $est_inscrit = true;
                $succes = "Inscription réussie ! Vous êtes maintenant inscrit à cette activité.";
                
                // Recharger les données
                header('Location: activity_details.php?id=' . $activite_id);
                exit;
            } else {
                $erreur = "Désolé, cette activité est complète.";
            }
        } catch (PDOException $e) {
            $erreur = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    } elseif (isset($_POST['se_desinscrire'])) {
        try {
            $requete_desinscription = $connexion->prepare("
                DELETE FROM participations 
                WHERE utilisateur_id = ? AND activite_id = ?
            ");
            $requete_desinscription->execute([$_SESSION['utilisateur']['id'], $activite_id]);
            $est_inscrit = false;
            $succes = "Désinscription réussie !";
            
            // Recharger les données
            header('Location: activity_details.php?id=' . $activite_id);
            exit;
        } catch (PDOException $e) {
            $erreur = "Erreur lors de la désinscription : " . $e->getMessage();
        }
    } elseif (isset($_POST['toggle_favori'])) {
        try {
            if ($est_favori) {
                // Retirer des favoris
                $requete_supp_favori = $connexion->prepare("
                    DELETE FROM favoris 
                    WHERE utilisateur_id = ? AND activite_id = ?
                ");
                $requete_supp_favori->execute([$_SESSION['utilisateur']['id'], $activite_id]);
                $est_favori = false;
                $succes = "Activité retirée des favoris";
            } else {
                // Ajouter aux favoris
                $requete_ajout_favori = $connexion->prepare("
                    INSERT INTO favoris (utilisateur_id, activite_id) 
                    VALUES (?, ?)
                ");
                $requete_ajout_favori->execute([$_SESSION['utilisateur']['id'], $activite_id]);
                $est_favori = true;
                $succes = "Activité ajoutée aux favoris";
            }
            
            // Recharger les données
            header('Location: activity_details.php?id=' . $activite_id);
            exit;
        } catch (PDOException $e) {
            $erreur = "Erreur lors de la gestion des favoris : " . $e->getMessage();
        }
    }
}

// Fonction pour déterminer le statut
function getStatutActivite($activite) {
    $maintenant = new DateTime();
    $date_activite = new DateTime($activite['date_activite']);
    
    if ($date_activite < $maintenant) {
        return 'terminee';
    } elseif ($activite['participants_actuels'] >= $activite['participants_max']) {
        return 'complet';
    } elseif ($date_activite->format('Y-m-d') === $maintenant->format('Y-m-d')) {
        return 'aujourdhui';
    } else {
        return 'disponible';
    }
}

$statut = getStatutActivite($activite);
$date_activite = new DateTime($activite['date_activite']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activite['titre']); ?> - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="py-5" style="background: var(--light);">
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

            <div class="row">
                <div class="col-lg-8">
                    <div class="activity-detail-card">
                        <!-- En-tête -->
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge me-2" style="background-color: <?php echo $activite['categorie_couleur']; ?>">
                                        <?php echo $activite['categorie_icone']; ?> <?php echo htmlspecialchars($activite['categorie_nom']); ?>
                                    </span>
                                    
                                    <?php if ($statut == 'terminee'): ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-history me-1"></i>Terminée
                                        </span>
                                    <?php elseif ($statut == 'complet'): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times me-1"></i>Complet
                                        </span>
                                    <?php elseif ($statut == 'aujourdhui'): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-bolt me-1"></i>Aujourd'hui
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Disponible
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (isset($_SESSION['utilisateur'])): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="toggle_favori" class="btn btn-sm <?php echo $est_favori ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                            <i class="fas fa-heart me-1"></i>
                                            <?php echo $est_favori ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Contenu -->
                        <div class="activity-detail-content">
                            <h1 class="mb-4"><?php echo htmlspecialchars($activite['titre']); ?></h1>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-calendar me-2 text-primary"></i>Date :</strong> 
                                       <?php echo $date_activite->format('d/m/Y'); ?></p>
                                    <p><strong><i class="fas fa-clock me-2 text-primary"></i>Heure :</strong> 
                                       <?php echo $date_activite->format('H:i'); ?></p>
                                    <p><strong><i class="fas fa-hourglass me-2 text-primary"></i>Durée :</strong> 
                                       <?php echo $activite['duree']; ?> minutes</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-map-marker-alt me-2 text-primary"></i>Lieu :</strong> 
                                       <?php echo htmlspecialchars($activite['ville']); ?> - <?php echo htmlspecialchars($activite['lieu']); ?></p>
                                    <p><strong><i class="fas fa-users me-2 text-primary"></i>Participants :</strong> 
                                       <?php echo $activite['participants_actuels']; ?>/<?php echo $activite['participants_max']; ?></p>
                                    <p><strong><i class="fas fa-tag me-2 text-primary"></i>Prix :</strong> 
                                       <span class="h5 text-primary"><?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?></span></p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h4>Description</h4>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($activite['description'])); ?></p>
                            </div>

                            <div class="mb-4">
                                <h4>Organisateur</h4>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-circle p-3 me-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($activite['organisateur_prenom'] . ' ' . $activite['organisateur_nom']); ?></h5>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($activite['organisateur_ville']); ?>
                                        </p>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($activite['organisateur_email']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Commentaires -->
                            <div class="mb-4">
                                <h4>Avis des participants</h4>
                                <?php if (empty($commentaires)): ?>
                                    <p class="text-muted">Aucun avis pour le moment.</p>
                                <?php else: ?>
                                    <?php foreach ($commentaires as $commentaire): 
                                        $date_commentaire = new DateTime($commentaire['date_creation']);
                                    ?>
                                        <div class="comment-card mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong><?php echo htmlspecialchars($commentaire['prenom'] . ' ' . $commentaire['nom']); ?></strong>
                                                <small class="text-muted"><?php echo $date_commentaire->format('d/m/Y'); ?></small>
                                            </div>
                                            <div class="rating-stars mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $commentaire['note'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($commentaire['commentaire'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Participation</h5>
                        </div>
                        <div class="card-body text-center">
                            <h3 class="text-primary mb-3">
                                <?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?>
                            </h3>
                            
                            <?php if (!isset($_SESSION['utilisateur'])): ?>
                                <p class="text-muted mb-3">Connectez-vous pour participer à cette activité</p>
                                <a href="connexion.php?redirect=activity_details.php?id=<?php echo $activite_id; ?>" 
                                   class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                </a>
                            <?php else: ?>
                                <?php if ($statut == 'terminee'): ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-history me-2"></i>Activité terminée
                                    </button>
                                <?php elseif ($statut == 'complet'): ?>
                                    <button class="btn btn-danger w-100" disabled>
                                        <i class="fas fa-times me-2"></i>Complet
                                    </button>
                                <?php elseif ($est_inscrit): ?>
                                    <p class="text-success mb-3">
                                        <i class="fas fa-check-circle me-2"></i>Vous êtes inscrit à cette activité
                                    </p>
                                    <form method="POST">
                                        <button type="submit" name="se_desinscrire" class="btn btn-outline-danger w-100"
                                                onclick="return confirm('Êtes-vous sûr de vouloir vous désinscrire ?')">
                                            <i class="fas fa-times me-2"></i>Se désinscrire
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <button type="submit" name="s_inscrire" class="btn btn-primary w-100">
                                            <i class="fas fa-check me-2"></i>S'inscrire
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <hr class="my-4">
                            
                            <div class="text-start">
                                <h6>Informations importantes :</h6>
                                <ul class="small text-muted">
                                    <li>Prix par participant : <?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?></li>
                                    <li>Places disponibles : <?php echo $activite['participants_max'] - $activite['participants_actuels']; ?></li>
                                    <li>Date limite d'inscription : <?php echo $date_activite->format('d/m/Y'); ?></li>
                                    <li>Durée : <?php echo $activite['duree']; ?> minutes</li>
                                    <li>Lieu : <?php echo htmlspecialchars($activite['ville']); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Activités similaires -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0">Activités similaires</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                $requete_similaires = $connexion->prepare("
                                    SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone
                                    FROM activites a
                                    LEFT JOIN categories c ON a.categorie_id = c.id
                                    WHERE a.categorie_id = ? AND a.id != ? AND a.date_activite > NOW() AND a.statut = 'active'
                                    ORDER BY a.date_activite ASC
                                    LIMIT 3
                                ");
                                $requete_similaires->execute([$activite['categorie_id'], $activite_id]);
                                $activites_similaires = $requete_similaires->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                $activites_similaires = [];
                            }
                            ?>
                            
                            <?php if (empty($activites_similaires)): ?>
                                <p class="text-muted small">Aucune activité similaire pour le moment.</p>
                            <?php else: ?>
                                <?php foreach ($activites_similaires as $similaire): ?>
                                    <div class="mb-3 pb-3 border-bottom">
                                        <h6 class="mb-1 small"><?php echo htmlspecialchars($similaire['titre']); ?></h6>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo (new DateTime($similaire['date_activite']))->format('d/m/Y'); ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($similaire['ville']); ?>
                                        </small>
                                        <a href="activity_details.php?id=<?php echo $similaire['id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                            Voir détails
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>