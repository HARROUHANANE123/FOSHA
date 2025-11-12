<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et est organisateur
if (!isset($_SESSION['utilisateur']) || !$_SESSION['utilisateur']['est_organisateur']) {
    header('Location: page_organisateur.php');
    exit;
}

// Inclure la connexion à la base de données
include 'bd.php';

// Récupérer les catégories
try {
    $requete_categories = $connexion->query("SELECT * FROM categories WHERE statut = 'active' ORDER BY nom");
    $categories = $requete_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des catégories : " . $e->getMessage();
    $categories = [];
}

// Traitement du formulaire de création d'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $titre = trim($_POST['title']);
    $description = trim($_POST['description']);
    $categorie_id = $_POST['category_id'];
    $lieu = trim($_POST['location']);
    $ville = $_POST['ville'];
    $date_activite = $_POST['activity_date'];
    $duree = $_POST['duree'];
    $participants_max = $_POST['max_participants'];
    $prix = $_POST['price'];
    
    // Valider les données
    $erreurs = [];
    
    if (empty($titre)) {
        $erreurs[] = "Le titre est obligatoire";
    }
    
    if (empty($description)) {
        $erreurs[] = "La description est obligatoire";
    }
    
    if (empty($categorie_id)) {
        $erreurs[] = "La catégorie est obligatoire";
    }
    
    if (empty($lieu)) {
        $erreurs[] = "Le lieu est obligatoire";
    }
    
    if (empty($ville)) {
        $erreurs[] = "La ville est obligatoire";
    }
    
    if (empty($date_activite)) {
        $erreurs[] = "La date et l'heure sont obligatoires";
    }
    
    if (empty($participants_max) || $participants_max < 1) {
        $erreurs[] = "Le nombre maximum de participants doit être au moins 1";
    }
    
    if (empty($duree) || $duree < 30) {
        $erreurs[] = "La durée doit être d'au moins 30 minutes";
    }
    
    // Vérifier que la date est dans le futur
    $date_saisie = new DateTime($date_activite);
    $maintenant = new DateTime();
    if ($date_saisie <= $maintenant) {
        $erreurs[] = "La date de l'activité doit être dans le futur";
    }
    
    if (empty($erreurs)) {
        try {
            // Insérer la nouvelle activité
            $requete_creation = $connexion->prepare("
                INSERT INTO activites (titre, description, categorie_id, lieu, ville, date_activite, duree, participants_max, prix, organisateur_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $requete_creation->execute([
                $titre,
                $description,
                $categorie_id,
                $lieu,
                $ville,
                $date_activite,
                $duree,
                $participants_max,
                $prix,
                $_SESSION['utilisateur']['id']
            ]);
            
            $activite_id = $connexion->lastInsertId();
            $succes_creation = "Activité créée avec succès !";
            
            // Rediriger vers la page de détails de l'activité après 2 secondes
            header('Refresh: 2; URL=activity_details.php?id=' . $activite_id);
            
        } catch (PDOException $e) {
            $erreur_creation = "Erreur lors de la création de l'activité : " . $e->getMessage();
        }
    } else {
        $erreur_creation = implode("<br>", $erreurs);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une activité - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="py-5" style="background: var(--light);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="auth-card p-4 p-md-5">
                        <div class="text-center mb-5">
                            <h2 class="fw-bold mb-3">Créer une activité</h2>
                            <p class="text-muted">Partagez votre passion et organisez une expérience unique au Maroc</p>
                        </div>

                        <!-- Afficher les messages -->
                        <?php if (isset($succes_creation)): ?>
                            <div class="alert alert-success">
                                <?php echo $succes_creation; ?>
                                <br>
                                <small>Redirection vers votre activité...</small>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($erreur_creation)): ?>
                            <div class="alert alert-danger"><?php echo $erreur_creation; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <!-- Titre et Catégorie -->
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Titre de l'activité *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                           placeholder="Ex: Randonnée dans l'Atlas - Vallée d'Ourika" required>
                                    <div class="form-text">Donnez un titre attractif et descriptif à votre activité</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="category_id" class="form-label">Catégorie *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Choisir une catégorie</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?php echo $categorie['id']; ?>" 
                                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $categorie['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($categorie['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Description détaillée *</label>
                                <textarea class="form-control" id="description" name="description" rows="5" 
                                          placeholder="Décrivez votre activité en détail : ce que les participants vont vivre, le déroulement, ce qui est inclus, etc..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">Soyez précis et enthousiaste ! Les bonnes descriptions attirent plus de participants.</div>
                            </div>

                            <!-- Lieu et Ville -->
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="location" class="form-label">Lieu précis *</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" 
                                           placeholder="Ex: Vallée d'Ourika, Parc de la Ligue Arabe, Plage d'Agadir..." required>
                                    <div class="form-text">Indiquez l'adresse exacte ou le lieu de rendez-vous</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ville" class="form-label">Ville *</label>
                                    <select class="form-select" id="ville" name="ville" required>
                                        <option value="">Choisir une ville</option>
                                        <option value="Casablanca" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Casablanca') ? 'selected' : ''; ?>>Casablanca</option>
                                        <option value="Marrakech" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Marrakech') ? 'selected' : ''; ?>>Marrakech</option>
                                        <option value="Rabat" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Rabat') ? 'selected' : ''; ?>>Rabat</option>
                                        <option value="Fès" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Fès') ? 'selected' : ''; ?>>Fès</option>
                                        <option value="Tanger" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Tanger') ? 'selected' : ''; ?>>Tanger</option>
                                        <option value="Agadir" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Agadir') ? 'selected' : ''; ?>>Agadir</option>
                                        <option value="Meknès" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Meknès') ? 'selected' : ''; ?>>Meknès</option>
                                        <option value="Oujda" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Oujda') ? 'selected' : ''; ?>>Oujda</option>
                                        <option value="Essaouira" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Essaouira') ? 'selected' : ''; ?>>Essaouira</option>
                                        <option value="Autre" <?php echo (isset($_POST['ville']) && $_POST['ville'] == 'Autre') ? 'selected' : ''; ?>>Autre</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Date, Durée et Participants -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="activity_date" class="form-label">Date et heure *</label>
                                    <input type="datetime-local" class="form-control" id="activity_date" name="activity_date" 
                                           value="<?php echo isset($_POST['activity_date']) ? htmlspecialchars($_POST['activity_date']) : ''; ?>" 
                                           min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="duree" class="form-label">Durée (minutes) *</label>
                                    <input type="number" class="form-control" id="duree" name="duree" 
                                           value="<?php echo isset($_POST['duree']) ? htmlspecialchars($_POST['duree']) : '120'; ?>" 
                                           min="30" step="15" required>
                                    <div class="form-text">Durée estimée de l'activité</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="max_participants" class="form-label">Participants max *</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="<?php echo isset($_POST['max_participants']) ? htmlspecialchars($_POST['max_participants']) : '10'; ?>" 
                                           min="1" required>
                                </div>
                            </div>

                            <!-- Prix -->
                            <div class="mb-4">
                                <label for="price" class="form-label">Prix par participant (MAD)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '0'; ?>" 
                                       min="0" step="10">
                                <div class="form-text">Laissez 0 pour une activité gratuite. Prix en Dirham Marocain (MAD).</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-3 fw-semibold">
                                    <i class="fas fa-plus me-2"></i>Créer l'activité
                                </button>
                                <a href="activite.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Retour aux activités
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>