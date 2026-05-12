<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use Biblio\Services\Bibliotheque;
use Biblio\Services\Auth;

session_start();

$auth = Auth::get();
$bibliotheque = new Bibliotheque();
$action = $_GET['action'] ?? 'accueil';
$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);

function afficherAlerte(): void {
    global $message, $error;
    if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif;
}

// ============ GESTION DES ACTIONS POST ============

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $actionPost = $_POST['action'] ?? '';
        
        // Actions d'authentification
        if ($actionPost === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if ($auth->login($username, $password)) {
                $_SESSION['message'] = 'Bienvenue ' . htmlspecialchars($username) . ' !';
                header('Location: index.php?action=accueil');
            } else {
                $_SESSION['error'] = 'Identifiants incorrects';
                header('Location: index.php?action=login');
            }
            exit;
        }
        
        if ($actionPost === 'register') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $nom = $_POST['nom'] ?? '';
            $prenom = $_POST['prenom'] ?? '';
            $email = $_POST['email'] ?? '';
            
            if ($auth->register($username, $password, $nom, $prenom, $email)) {
                $_SESSION['message'] = 'Inscription réussie ! Connectez-vous.';
                header('Location: index.php?action=login');
            } else {
                $_SESSION['error'] = 'Nom d\'utilisateur ou email déjà existant';
                header('Location: index.php?action=register');
            }
            exit;
        }
        
        if ($actionPost === 'logout') {
            $auth->logout();
            $_SESSION['message'] = 'Vous êtes déconnecté';
            header('Location: index.php?action=accueil');
            exit;
        }
        
        // Actions d'emprunt (utilisateur normal)
        if ($actionPost === 'emprunter') {
            $auth->requireLogin();
            $bibliotheque->emprunter((int)$_POST['document_id'], (int)$_POST['membre_id']);
            $_SESSION['message'] = 'Document emprunté avec succès !';
            header('Location: index.php?action=detail&id=' . $_POST['document_id']);
            exit;
        }
        
        if ($actionPost === 'retourner') {
            $auth->requireLogin();
            $bibliotheque->retourner((int)$_POST['document_id']);
            $_SESSION['message'] = 'Document retourné avec succès !';
            header('Location: index.php?action=detail&id=' . $_POST['document_id']);
            exit;
        }
        
        if ($actionPost === 'reserver') {
            $auth->requireLogin();
            $bibliotheque->reserver((int)$_POST['document_id'], (int)$_POST['membre_id']);
            $_SESSION['message'] = 'Document réservé avec succès !';
            header('Location: index.php?action=detail&id=' . $_POST['document_id']);
            exit;
        }
        
        // Actions ADMIN (CRUD)
        if ($auth->isAdmin()) {
            $adminAction = $_POST['action_admin'] ?? '';
            
            if ($adminAction === 'ajouter_document') {
                $type = $_POST['type_document'];
                $nom = $_POST['nom'];
                $auteur = $_POST['auteur'];
                $dateAjout = $_POST['dateAjout'];
                $proprietes = [];
                
                if ($type === 'livre') {
                    $proprietes = ['pages' => (int)$_POST['pages'], 'genre' => $_POST['genre']];
                } elseif ($type === 'bd') {
                    $proprietes = ['dessinateur' => $_POST['dessinateur'], 'tome' => (int)$_POST['tome']];
                } elseif ($type === 'magazine') {
                    $proprietes = ['numero' => (int)$_POST['numero'], 'periodicite' => $_POST['periodicite']];
                }
                
                $db = Biblio\Services\Database::get();
                $db->inserer('documents', [
                    'type' => $type,
                    'nom' => $nom,
                    'auteur' => $auteur,
                    'dateAjout' => $dateAjout,
                    'proprietes_specifiques' => json_encode($proprietes)
                ]);
                $_SESSION['message'] = 'Document ajouté avec succès !';
                header('Location: index.php?action=admin');
                exit;
            }
            
            if ($adminAction === 'supprimer_document') {
                $db = Biblio\Services\Database::get();
                $db->supprimer('documents', (int)$_POST['document_id']);
                $_SESSION['message'] = 'Document supprimé avec succès !';
                header('Location: index.php?action=admin');
                exit;
            }
            
            if ($adminAction === 'ajouter_membre') {
                $db = Biblio\Services\Database::get();
                $db->inserer('membres', [
                    'nom' => $_POST['nom'],
                    'prenom' => $_POST['prenom'],
                    'email' => $_POST['email'],
                    'dateInscription' => date('Y-m-d')
                ]);
                $_SESSION['message'] = 'Membre ajouté avec succès !';
                header('Location: index.php?action=admin');
                exit;
            }
            
            if ($adminAction === 'supprimer_membre') {
                $db = Biblio\Services\Database::get();
                $db->supprimer('membres', (int)$_POST['membre_id']);
                $_SESSION['message'] = 'Membre supprimé avec succès !';
                header('Location: index.php?action=admin');
                exit;
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php?action=' . $action);
        exit;
    }
}

// ============ FONCTIONS D'AFFICHAGE ============

function pageLogin(): void {
    ?>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-tabs">
                <button class="auth-tab active" onclick="showAuthForm('login')">🔐 Connexion</button>
                <button class="auth-tab" onclick="showAuthForm('register')">📝 Inscription</button>
            </div>
            
            <div id="login-form" class="auth-form active">
                <h2>Connexion</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>👤 Nom d'utilisateur</label>
                        <input type="text" name="username" required placeholder="admin ou user">
                    </div>
                    <div class="form-group">
                        <label>🔑 Mot de passe</label>
                        <input type="password" name="password" required placeholder="••••••">
                    </div>
                    <button type="submit" class="btn btn-login">Se connecter</button>
                </form>
                <div class="auth-info">
                    <p>👑 Admin: admin / admin123</p>
                    <p>👤 User: user / user123</p>
                </div>
            </div>
            
            <div id="register-form" class="auth-form" style="display:none">
                <h2>Inscription</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label>👤 Nom d'utilisateur</label>
                            <input type="text" name="username" required placeholder="username">
                        </div>
                        <div class="form-group">
                            <label>📧 Email</label>
                            <input type="email" name="email" required placeholder="email@exemple.com">
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label>📛 Nom</label>
                            <input type="text" name="nom" required placeholder="Votre nom">
                        </div>
                        <div class="form-group">
                            <label>👶 Prénom</label>
                            <input type="text" name="prenom" required placeholder="Votre prénom">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>🔑 Mot de passe</label>
                        <input type="password" name="password" required placeholder="••••••" minlength="4">
                    </div>
                    <button type="submit" class="btn btn-register">S'inscrire</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showAuthForm(form) {
        document.getElementById('login-form').style.display = form === 'login' ? 'block' : 'none';
        document.getElementById('register-form').style.display = form === 'register' ? 'block' : 'none';
        document.querySelectorAll('.auth-tab').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }
    </script>
    <?php
}

function pageAccueil(Bibliotheque $biblio): void {
    $type = $_GET['type'] ?? null;
    $recherche = $_GET['q'] ?? null;
    
    if ($recherche) {
        $documents = $biblio->chercherDocuments($recherche);
    } elseif ($type && in_array($type, ['livre', 'bd', 'magazine'])) {
        $documents = $biblio->filtrerParType($type);
    } else {
        $documents = $biblio->getDocuments();
    }
    ?>
    <div class="filtres">
        <a href="?action=accueil" class="filtre-btn <?= !$type && !$recherche ? 'active' : '' ?>">Tous</a>
        <a href="?action=accueil&type=livre" class="filtre-btn <?= $type === 'livre' ? 'active' : '' ?>">Livres</a>
        <a href="?action=accueil&type=bd" class="filtre-btn <?= $type === 'bd' ? 'active' : '' ?>">BD</a>
        <a href="?action=accueil&type=magazine" class="filtre-btn <?= $type === 'magazine' ? 'active' : '' ?>">Magazines</a>
    </div>
    <div class="recherche">
        <form method="GET">
            <input type="hidden" name="action" value="accueil">
            <input type="text" name="q" placeholder="Rechercher par titre ou auteur..." value="<?= htmlspecialchars($recherche ?? '') ?>">
            <button type="submit">🔍 Rechercher</button>
        </form>
    </div>
    <table>
        <thead>
            <tr><th>Type</th><th>Titre</th><th>Auteur</th><th>Date ajout</th><th>Résumé</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= ucfirst($doc->getType()) ?></td>
                    <td><?= htmlspecialchars($doc->getNom()) ?></td>
                    <td><?= htmlspecialchars($doc->getAuteur()) ?></td>
                    <td><?= htmlspecialchars($doc->getDateAjout()) ?></td>
                    <td><?= htmlspecialchars($doc->getResume()) ?></td>
                    <td><a href="?action=detail&id=<?= $doc->getId() ?>" class="btn btn-detail">Détail</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function pageDetail(Bibliotheque $biblio, int $id): void {
    global $auth;
    $doc = $biblio->getDocumentParId($id);
    if (!$doc) {
        echo "<p>Document non trouvé</p>";
        return;
    }
    
    $membres = $biblio->getMembres();
    $estEmpruntable = $doc instanceof \Biblio\Interfaces\Empruntable;
    $estReservable = $doc instanceof \Biblio\Interfaces\Reservable;
    $disponible = $estEmpruntable ? $doc->estDisponible() : false;
    $emprunteur = $estEmpruntable ? $doc->getEmprunteur() : null;
    ?>
    <h2><?= htmlspecialchars((string)$doc) ?></h2>
    <table>
        <tr><th>Type</th><td><?= ucfirst($doc->getType()) ?></td></tr>
        <tr><th>Titre</th><td><?= htmlspecialchars($doc->getNom()) ?></td></tr>
        <tr><th>Auteur</th><td><?= htmlspecialchars($doc->getAuteur()) ?></td></tr>
        <tr><th>Date d'ajout</th><td><?= htmlspecialchars($doc->getDateAjout()) ?></td></tr>
        <tr><th>Résumé</th><td><?= htmlspecialchars($doc->getResume()) ?></td></tr>
        <tr><th>Disponibilité</th><td class="<?= $disponible ? 'disponible' : 'indisponible' ?>"><?= $disponible ? 'Disponible' : 'Emprunté' ?></td></tr>
        <?php if ($emprunteur): ?>
            <tr><th>Emprunté par</th><td><?= htmlspecialchars((string)$emprunteur) ?></td></tr>
        <?php endif; ?>
        <tr><th>Créé le</th><td><?= htmlspecialchars($doc->getCreeLe() ?? '-') ?></td></tr>
        <tr><th>Modifié le</th><td><?= htmlspecialchars($doc->getModifieLe() ?? '-') ?></td></tr>
    </table>
    
    <?php if ($auth->isLoggedIn()): ?>
        <div style="margin: 1rem 0;">
            <?php if ($estEmpruntable): ?>
                <form method="POST" style="display: inline-block; margin-right: 10px;">
                    <input type="hidden" name="document_id" value="<?= $id ?>">
                    <?php if ($disponible): ?>
                        <select name="membre_id" required>
                            <option value="">Choisir un membre...</option>
                            <?php foreach ($membres as $membre): ?>
                                <option value="<?= $membre->getId() ?>"><?= htmlspecialchars((string)$membre) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="action" value="emprunter" class="btn btn-emprunter">📖 Emprunter</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="retourner" class="btn btn-retourner">🔄 Retourner</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
            
            <?php if ($estReservable && $disponible): ?>
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="document_id" value="<?= $id ?>">
                    <select name="membre_id" required>
                        <option value="">Choisir un membre...</option>
                        <?php foreach ($membres as $membre): ?>
                            <option value="<?= $membre->getId() ?>"><?= htmlspecialchars((string)$membre) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="reserver" class="btn btn-reserver">⭐ Réserver</button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            🔐 <a href="?action=login">Connectez-vous</a> pour emprunter ou réserver des documents.
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 1rem">
        <a href="?action=accueil" class="btn">⬅ Retour à l'accueil</a>
    </div>
    <?php
}

function pageMembres(Bibliotheque $biblio): void {
    $membres = $biblio->getMembres();
    ?>
    <h2>👥 Liste des membres</h2>
    <table>
        <thead>
            <tr><th>Prénom</th><th>Nom</th><th>Email</th><th>Date inscription</th><th>Emprunts en cours</th></tr>
        </thead>
        <tbody>
            <?php foreach ($membres as $membre): ?>
                <?php $emprunts = $biblio->getEmpruntsMembre($membre->getId()); ?>
                <tr>
                    <td><?= htmlspecialchars($membre->getPrenom()) ?></td>
                    <td><?= htmlspecialchars($membre->getNom()) ?></td>
                    <td><?= htmlspecialchars($membre->getEmail()) ?></td>
                    <td><?= htmlspecialchars($membre->getDateInscription()) ?></td>
                    <td>
                        <?php if ($emprunts): ?>
                            <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($emprunts as $e): ?>
                                <li><?= htmlspecialchars($e['document']->getNom()) ?> (depuis <?= htmlspecialchars($e['dateEmprunt']) ?>)</li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            Aucun emprunt
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function pageStats(Bibliotheque $biblio): void {
    $stats = $biblio->getStatistiques();
    ?>
    <h2>📊 Statistiques de la bibliothèque</h2>
    <div class="stats-grid">
        <div class="stat-card"><h3><?= $stats['total_documents'] ?></h3><p>Total documents</p></div>
        <div class="stat-card"><h3><?= $stats['total_membres'] ?></h3><p>Membres inscrits</p></div>
        <div class="stat-card"><h3><?= $stats['livres'] ?></h3><p>Livres</p></div>
        <div class="stat-card"><h3><?= $stats['bd'] ?></h3><p>BD</p></div>
        <div class="stat-card"><h3><?= $stats['magazines'] ?></h3><p>Magazines</p></div>
        <div class="stat-card"><h3><?= $stats['emprunts_en_cours'] ?></h3><p>Emprunts en cours</p></div>
        <div class="stat-card"><h3><?= $stats['documents_disponibles'] ?></h3><p>Documents disponibles</p></div>
    </div>
    <?php
}

function pageAdmin(Bibliotheque $biblio): void {
    global $auth;
    if (!$auth->isAdmin()) {
        echo "<p>⛔ Accès réservé aux administrateurs.</p>";
        return;
    }
    
    $documents = $biblio->getDocuments();
    $membres = $biblio->getMembres();
    ?>
    <div class="admin-container">
        <h2>⚙️ Administration</h2>
        
        <div class="admin-tabs">
            <button class="admin-tab active" onclick="showAdminTab('documents')">📚 Documents</button>
            <button class="admin-tab" onclick="showAdminTab('membres')">👥 Membres</button>
        </div>
        
        <div id="admin-documents" class="admin-tab-content active">
            <div class="admin-form">
                <h3>➕ Ajouter un document</h3>
                <form method="POST" class="form-ajout">
                    <input type="hidden" name="action_admin" value="ajouter_document">
                    <div class="form-row">
                        <select name="type_document" required onchange="showSpecificFields(this.value)">
                            <option value="">-- Type --</option>
                            <option value="livre">📖 Livre</option>
                            <option value="bd">🎨 BD</option>
                            <option value="magazine">📰 Magazine</option>
                        </select>
                        <input type="text" name="nom" placeholder="Titre" required>
                        <input type="text" name="auteur" placeholder="Auteur" required>
                        <input type="date" name="dateAjout" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div id="champs-livre" class="specific-fields" style="display:none">
                        <input type="number" name="pages" placeholder="Pages" min="1">
                        <input type="text" name="genre" placeholder="Genre">
                    </div>
                    <div id="champs-bd" class="specific-fields" style="display:none">
                        <input type="text" name="dessinateur" placeholder="Dessinateur">
                        <input type="number" name="tome" placeholder="Tome" min="1">
                    </div>
                    <div id="champs-magazine" class="specific-fields" style="display:none">
                        <input type="number" name="numero" placeholder="Numéro" min="1">
                        <select name="periodicite">
                            <option value="mensuel">Mensuel</option>
                            <option value="hebdomadaire">Hebdomadaire</option>
                            <option value="trimestriel">Trimestriel</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success">➕ Ajouter</button>
                </form>
            </div>
            
            <h3>📋 Documents existants</h3>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Type</th><th>Titre</th><th>Auteur</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= $doc->getId() ?></td>
                        <td><?= ucfirst($doc->getType()) ?></td>
                        <td><?= htmlspecialchars($doc->getNom()) ?></td>
                        <td><?= htmlspecialchars($doc->getAuteur()) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Supprimer ?')">
                                <input type="hidden" name="action_admin" value="supprimer_document">
                                <input type="hidden" name="document_id" value="<?= $doc->getId() ?>">
                                <button type="submit" class="btn btn-danger btn-small">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="admin-membres" class="admin-tab-content" style="display:none">
            <div class="admin-form">
                <h3>➕ Ajouter un membre</h3>
                <form method="POST" class="form-ajout">
                    <input type="hidden" name="action_admin" value="ajouter_membre">
                    <input type="text" name="nom" placeholder="Nom" required>
                    <input type="text" name="prenom" placeholder="Prénom" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <button type="submit" class="btn btn-success">➕ Ajouter</button>
                </form>
            </div>
            
            <h3>👥 Membres existants</h3>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($membres as $membre): ?>
                    <tr>
                        <td><?= $membre->getId() ?></td>
                        <td><?= htmlspecialchars($membre->getNom()) ?></td>
                        <td><?= htmlspecialchars($membre->getPrenom()) ?></td>
                        <td><?= htmlspecialchars($membre->getEmail()) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Supprimer ?')">
                                <input type="hidden" name="action_admin" value="supprimer_membre">
                                <input type="hidden" name="membre_id" value="<?= $membre->getId() ?>">
                                <button type="submit" class="btn btn-danger btn-small">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function showAdminTab(tab) {
        document.getElementById('admin-documents').style.display = tab === 'documents' ? 'block' : 'none';
        document.getElementById('admin-membres').style.display = tab === 'membres' ? 'block' : 'none';
        document.querySelectorAll('.admin-tab').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }
    
    function showSpecificFields(type) {
        document.querySelectorAll('.specific-fields').forEach(div => div.style.display = 'none');
        if (type === 'livre') document.getElementById('champs-livre').style.display = 'flex';
        if (type === 'bd') document.getElementById('champs-bd').style.display = 'flex';
        if (type === 'magazine') document.getElementById('champs-magazine').style.display = 'flex';
    }
    </script>
    <?php
}

// ============ AFFICHAGE ============
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bibliothèque Municipale</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body>
    <div class="container">
        <nav>
            <a href="?action=accueil">🏠 Accueil</a>
            <a href="?action=membres">👥 Membres</a>
            <a href="?action=stats">📊 Statistiques</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="?action=admin">⚙️ Admin</a>
            <?php endif; ?>
            <?php if ($auth->isLoggedIn()): ?>
                <span class="user-info">👋 <?= htmlspecialchars($auth->getCurrentUser()['prenom']) ?></span>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn-logout">🚪 Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="?action=login" class="btn-login-link">🔐 Connexion</a>
            <?php endif; ?>
        </nav>
        <div class="content">
            <h1>📚 Bibliothèque de Saint-Chapitres-en-POO</h1>
            <?php afficherAlerte(); ?>
            <?php
            switch ($action) {
                case 'login':
                    if (!$auth->isLoggedIn()) pageLogin(); else header('Location: index.php?action=accueil');
                    break;
                case 'register':
                    if (!$auth->isLoggedIn()) pageLogin(); else header('Location: index.php?action=accueil');
                    break;
                case 'admin':
                    pageAdmin($bibliotheque);
                    break;
                case 'detail':
                    pageDetail($bibliotheque, (int)($_GET['id'] ?? 0));
                    break;
                case 'membres':
                    pageMembres($bibliotheque);
                    break;
                case 'stats':
                    pageStats($bibliotheque);
                    break;
                default:
                    pageAccueil($bibliotheque);
                    break;
            }
            ?>
        </div>
    </div>
    
    <a href="#" class="scroll-top" id="scrollTop" title="Retour en haut">↑</a>
</body>
</html>