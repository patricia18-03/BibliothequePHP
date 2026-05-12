<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use Biblio\Services\Bibliotheque;
use Biblio\Services\Auth;

session_start();

$auth = Auth::get();
$bibliotheque = new Bibliotheque();
$action = $_GET['action'] ?? 'login';
$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);

// Si l'utilisateur n'est pas connecté, forcer la page de login
if (!$auth->isLoggedIn() && $action !== 'login' && $action !== 'register') {
    $action = 'login';
}

function afficherAlerte(): void {
    global $message, $error;
    if ($message) {
        echo '<div class="alert alert-success">✓ ' . htmlspecialchars($message) . '</div>';
    } elseif ($error) {
        echo '<div class="alert alert-error">⚠ ' . htmlspecialchars($error) . '</div>';
    }
}

// ============ GESTION DES ACTIONS POST ============

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $actionPost = $_POST['action'] ?? '';

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
            header('Location: index.php?action=login');
            exit;
        }

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

        if ($auth->isAdmin()) {
            $adminAction = $_POST['action_admin'] ?? '';

            if ($adminAction === 'ajouter_document') {
                $type = $_POST['type_document'];
                $nom = $_POST['nom'];
                $auteur = $_POST['auteur'];
                $dateAjout = $_POST['dateAjout'];
                $proprietes = array();
                if ($type === 'livre') {
                    $proprietes = array('pages' => (int)$_POST['pages'], 'genre' => $_POST['genre']);
                } elseif ($type === 'bd') {
                    $proprietes = array('dessinateur' => $_POST['dessinateur'], 'tome' => (int)$_POST['tome']);
                } elseif ($type === 'magazine') {
                    $proprietes = array('numero' => (int)$_POST['numero'], 'periodicite' => $_POST['periodicite']);
                }
                $db = Biblio\Services\Database::get();
                $db->inserer('documents', array(
                    'type' => $type, 'nom' => $nom, 'auteur' => $auteur,
                    'dateAjout' => $dateAjout, 'proprietes_specifiques' => json_encode($proprietes)
                ));
                $_SESSION['message'] = 'Document ajouté avec succès !';
                header('Location: index.php?action=admin'); 
                exit;
            }

            if ($adminAction === 'supprimer_document') {
                $db = Biblio\Services\Database::get();
                $db->supprimer('documents', (int)$_POST['document_id']);
                $_SESSION['message'] = 'Document supprimé !';
                header('Location: index.php?action=admin'); 
                exit;
            }

            if ($adminAction === 'ajouter_membre') {
                $db = Biblio\Services\Database::get();
                $db->inserer('membres', array(
                    'nom' => $_POST['nom'], 'prenom' => $_POST['prenom'],
                    'email' => $_POST['email'], 'dateInscription' => date('Y-m-d')
                ));
                $_SESSION['message'] = 'Membre ajouté avec succès !';
                header('Location: index.php?action=admin'); 
                exit;
            }

            if ($adminAction === 'supprimer_membre') {
                $db = Biblio\Services\Database::get();
                $db->supprimer('membres', (int)$_POST['membre_id']);
                $_SESSION['message'] = 'Membre supprimé !';
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

function typeBadge(string $type): string {
    $icons = array('livre' => '📖', 'bd' => '🎨', 'magazine' => '📰');
    $icon = isset($icons[$type]) ? $icons[$type] : '📄';
    return '<span class="type-badge type-' . htmlspecialchars($type) . '">' . $icon . ' ' . ucfirst($type) . '</span>';
}

function pageLogin(): void { 
    ?>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-header">
                <h1>📚 Bibliothèque de Saint-Chapitres-en-POO</h1>
                <p>Accès à votre espace personnel</p>
            </div>

            <div class="auth-tabs">
                <button class="auth-tab active" onclick="showAuthForm('login', this)">🔐 Connexion</button>
                <button class="auth-tab" onclick="showAuthForm('register', this)">📝 Inscription</button>
            </div>

            <div class="auth-body">
                <div id="login-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label>👤 Nom d'utilisateur</label>
                            <input type="text" name="username" required placeholder="Entrez votre identifiant" autocomplete="username">
                        </div>
                        <div class="form-group">
                            <label>🔑 Mot de passe</label>
                            <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn-auth btn-login-action">Se connecter</button>
                    </form>
                    <div class="auth-hint">
                        <p>👑 Comptes de test :</p>
                        <p><strong>Admin :</strong> admin / admin123</p>
                        <p><strong>Utilisateur :</strong> user / user123</p>
                    </div>
                </div>

                <div id="register-form" style="display:none">
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
                            <input type="password" name="password" required placeholder="Minimum 4 caractères" minlength="4">
                        </div>
                        <button type="submit" class="btn-auth btn-register">Créer mon compte</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showAuthForm(form, btn) {
        var loginForm = document.getElementById('login-form');
        var registerForm = document.getElementById('register-form');
        var tabs = document.querySelectorAll('.auth-tab');
        
        if (form === 'login') {
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
        } else {
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
        }
        
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        btn.classList.add('active');
    }
    </script>
    <?php
}

function pageAccueil(Bibliotheque $biblio): void {
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $recherche = isset($_GET['q']) ? $_GET['q'] : null;

    if ($recherche) {
        $documents = $biblio->chercherDocuments($recherche);
    } elseif ($type && in_array($type, array('livre', 'bd', 'magazine'))) {
        $documents = $biblio->filtrerParType($type);
    } else {
        $documents = $biblio->getDocuments();
    }
    
    $countDocs = count($documents);
    $suffixe = $countDocs > 1 ? 's' : '';
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h1>📚 Le fonds documentaire</h1>
            <p><?php echo $countDocs; ?> document<?php echo $suffixe; ?> trouvé<?php echo $suffixe; ?></p>
        </div>
    </div>

    <div class="filter-bar">
        <span class="filter-label">Filtrer :</span>
        <a href="?action=accueil" class="filtre-btn <?php echo (!$type && !$recherche) ? 'active' : ''; ?>">Tout</a>
        <a href="?action=accueil&type=livre" class="filtre-btn <?php echo ($type === 'livre') ? 'active' : ''; ?>">📖 Livres</a>
        <a href="?action=accueil&type=bd" class="filtre-btn <?php echo ($type === 'bd') ? 'active' : ''; ?>">🎨 Bandes dessinées</a>
        <a href="?action=accueil&type=magazine" class="filtre-btn <?php echo ($type === 'magazine') ? 'active' : ''; ?>">📰 Magazines</a>
    </div>

    <form method="GET" class="search-bar">
        <input type="hidden" name="action" value="accueil">
        <input type="text" name="q" placeholder="Rechercher par titre ou auteur…" value="<?php echo htmlspecialchars($recherche ?? ''); ?>">
        <button type="submit">🔍 Rechercher</button>
    </form>

    <div class="table-wrapper">
        <?php if (empty($documents)): ?>
            <div class="no-results">
                <p>Aucun document trouvé pour votre recherche.</p>
            </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Titre</th>
                    <th>Auteur</th>
                    <th>Date d'ajout</th>
                    <th>Résumé</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?php echo typeBadge($doc->getType()); ?></td>
                    <td style="font-weight:500"><?php echo htmlspecialchars($doc->getNom()); ?></td>
                    <td style="color:var(--ink-muted)"><?php echo htmlspecialchars($doc->getAuteur()); ?></td>
                    <td style="color:var(--ink-faint);font-size:0.82rem"><?php echo htmlspecialchars($doc->getDateAjout()); ?></td>
                    <td style="color:var(--ink-muted);font-size:0.82rem"><?php echo htmlspecialchars($doc->getResume()); ?></td>
                    <td><a href="?action=detail&id=<?php echo $doc->getId(); ?>" class="btn btn-detail">Voir →</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function pageDetail(Bibliotheque $biblio, int $id): void {
    global $auth;
    $doc = $biblio->getDocumentParId($id);
    if (!$doc) { 
        echo '<p class="alert alert-error">Document introuvable.</p>'; 
        return; 
    }

    $membres = $biblio->getMembres();
    $estEmpruntable = $doc instanceof \Biblio\Interfaces\Empruntable;
    $estReservable = $doc instanceof \Biblio\Interfaces\Reservable;
    $disponible = $estEmpruntable ? $doc->estDisponible() : false;
    $emprunteur = $estEmpruntable ? $doc->getEmprunteur() : null;
    ?>
    <a href="?action=accueil" class="back-link">← Retour au catalogue</a>

    <div class="detail-card">
        <div class="detail-hero">
            <div style="margin-bottom:0.75rem"><?php echo typeBadge($doc->getType()); ?></div>
            <h2><?php echo htmlspecialchars((string)$doc); ?></h2>
            <p><?php echo htmlspecialchars($doc->getAuteur()); ?></p>
        </div>

        <div class="detail-body">
            <table class="detail-table">
                <tr><th>Titre</th><td><?php echo htmlspecialchars($doc->getNom()); ?></td></tr>
                <tr><th>Auteur</th><td><?php echo htmlspecialchars($doc->getAuteur()); ?></td></tr>
                <tr><th>Date d'ajout</th><td><?php echo htmlspecialchars($doc->getDateAjout()); ?></td></tr>
                <tr><th>Résumé</th><td><?php echo htmlspecialchars($doc->getResume()); ?><td></tr>
                <tr>
                    <th>Disponibilité</th>
                    <td>
                        <?php if ($disponible): ?>
                            <span class="status-badge disponible">✅ Disponible</span>
                        <?php else: ?>
                            <span class="status-badge indisponible">❌ Emprunté</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($emprunteur): ?>
                <tr><th>Emprunté par</th><td><?php echo htmlspecialchars((string)$emprunteur); ?></td></tr>
                <?php endif; ?>
                <tr><th>Fiche créée le</th><td style="color:var(--ink-muted)"><?php echo htmlspecialchars($doc->getCreeLe() ?? '—'); ?></td></tr>
                <tr><th>Modifiée le</th><td style="color:var(--ink-muted)"><?php echo htmlspecialchars($doc->getModifieLe() ?? '—'); ?></td></tr>
            </table>
        </div>

        <?php if ($auth->isLoggedIn()): ?>
        <div class="detail-actions">
            <?php if ($estEmpruntable): ?>
                <form method="POST" class="action-group">
                    <input type="hidden" name="document_id" value="<?php echo $id; ?>">
                    <?php if ($disponible): ?>
                        <select name="membre_id" required>
                            <option value="">Choisir un membre…</option>
                            <?php foreach ($membres as $membre): ?>
                                <option value="<?php echo $membre->getId(); ?>"><?php echo htmlspecialchars((string)$membre); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="action" value="emprunter" class="btn btn-emprunter">📖 Emprunter</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="retourner" class="btn btn-retourner">🔄 Retourner</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ($estReservable && $disponible): ?>
                <form method="POST" class="action-group">
                    <input type="hidden" name="document_id" value="<?php echo $id; ?>">
                    <select name="membre_id" required>
                        <option value="">Choisir un membre…</option>
                        <?php foreach ($membres as $membre): ?>
                            <option value="<?php echo $membre->getId(); ?>"><?php echo htmlspecialchars((string)$membre); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="reserver" class="btn btn-reserver">⭐ Réserver</button>
                </form>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="detail-actions">
            <div class="alert alert-info" style="margin:0">
                🔐 <a href="?action=login" style="color:inherit;font-weight:600">Connectez-vous</a> pour emprunter ou réserver ce document.
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

function pageMembres(Bibliotheque $biblio): void {
    $membres = $biblio->getMembres();
    $countMembres = count($membres);
    $suffixe = $countMembres > 1 ? 's' : '';
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h1>👥 Les membres</h1>
            <p><?php echo $countMembres; ?> membre<?php echo $suffixe; ?> inscrit<?php echo $suffixe; ?></p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Prénom & Nom</th>
                    <th>Email</th>
                    <th>Inscription</th>
                    <th>Emprunts en cours</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($membres as $membre): ?>
                    <?php $emprunts = $biblio->getEmpruntsMembre($membre->getId()); ?>
                    <tr class="member-row">
                        <td style="font-weight:500"><?php echo htmlspecialchars($membre->getPrenom() . ' ' . $membre->getNom()); ?></td>
                        <td style="color:var(--ink-muted);font-size:0.85rem"><?php echo htmlspecialchars($membre->getEmail()); ?></td>
                        <td style="color:var(--ink-faint);font-size:0.82rem"><?php echo htmlspecialchars($membre->getDateInscription()); ?></td>
                        <td>
                            <?php if ($emprunts): ?>
                                <ul>
                                <?php foreach ($emprunts as $e): ?>
                                    <li><?php echo htmlspecialchars($e['document']->getNom()); ?> <span style="color:var(--ink-faint)">(depuis <?php echo htmlspecialchars($e['dateEmprunt']); ?>)</span></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span style="color:var(--ink-faint);font-size:0.82rem">Aucun emprunt</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function pageStats(Bibliotheque $biblio): void {
    $stats = $biblio->getStatistiques();
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h1>📊 Tableau de bord</h1>
            <p>Vue d'ensemble de la bibliothèque</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-value" data-target="<?php echo $stats['total_documents'] ?? 0; ?>">0</div>
            <div class="stat-label">Documents au total</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value" data-target="<?php echo $stats['total_membres'] ?? 0; ?>">0</div>
            <div class="stat-label">Membres inscrits</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📖</div>
            <div class="stat-value" data-target="<?php echo $stats['livres'] ?? 0; ?>">0</div>
            <div class="stat-label">Livres</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎨</div>
            <div class="stat-value" data-target="<?php echo $stats['bd'] ?? 0; ?>">0</div>
            <div class="stat-label">Bandes dessinées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📰</div>
            <div class="stat-value" data-target="<?php echo $stats['magazines'] ?? 0; ?>">0</div>
            <div class="stat-label">Magazines</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔄</div>
            <div class="stat-value" data-target="<?php echo $stats['emprunts_en_cours'] ?? 0; ?>">0</div>
            <div class="stat-label">Emprunts en cours</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value" data-target="<?php echo $stats['documents_disponibles'] ?? 0; ?>">0</div>
            <div class="stat-label">Documents disponibles</div>
        </div>
    </div>

    <script>
    (function() {
        var statValues = document.querySelectorAll('.stat-value[data-target]');
        for (var i = 0; i < statValues.length; i++) {
            var el = statValues[i];
            var target = parseInt(el.getAttribute('data-target'));
            var duration = 1000;
            var start = null;
            
            function animateCount(timestamp) {
                if (!start) start = timestamp;
                var progress = Math.min((timestamp - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(eased * target);
                if (progress < 1) {
                    requestAnimationFrame(animateCount);
                }
            }
            
            setTimeout(function() {
                requestAnimationFrame(animateCount);
            }, i * 100);
        }
    })();
    </script>
    <?php
}

function pageAdmin(Bibliotheque $biblio): void {
    global $auth;
    if (!$auth->isAdmin()) {
        echo '<div class="alert alert-error">⛔ Accès réservé aux administrateurs.</div>';
        return;
    }
    $documents = $biblio->getDocuments();
    $membres = $biblio->getMembres();
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h1>⚙️ Administration</h1>
            <p>Gestion des documents et des membres</p>
        </div>
    </div>

    <div class="admin-tabs">
        <button class="admin-tab active" onclick="showAdminTab('documents', this)">📚 Documents</button>
        <button class="admin-tab" onclick="showAdminTab('membres', this)">👥 Membres</button>
    </div>

    <!-- TAB DOCUMENTS -->
    <div id="tab-documents">
        <div class="admin-section">
            <div class="admin-section-header">
                <h3>➕ Ajouter un document</h3>
            </div>
            <div class="admin-form-body">
                <form method="POST">
                    <input type="hidden" name="action_admin" value="ajouter_document">
                    <div class="form-row">
                        <select name="type_document" required onchange="showSpecificFields(this.value)">
                            <option value="">Type de document…</option>
                            <option value="livre">📖 Livre</option>
                            <option value="bd">🎨 Bande dessinée</option>
                            <option value="magazine">📰 Magazine</option>
                        </select>
                        <input type="text" name="nom" placeholder="Titre" required>
                        <input type="text" name="auteur" placeholder="Auteur" required>
                        <input type="date" name="dateAjout" value="<?php echo date('Y-m-d'); ?>" required>
                        <button type="submit" class="btn btn-success">➕ Ajouter</button>
                    </div>
                    <div id="champs-livre" class="specific-fields" style="display:none">
                        <input type="number" name="pages" placeholder="Nombre de pages" min="1">
                        <input type="text" name="genre" placeholder="Genre littéraire">
                    </div>
                    <div id="champs-bd" class="specific-fields" style="display:none">
                        <input type="text" name="dessinateur" placeholder="Dessinateur">
                        <input type="number" name="tome" placeholder="Numéro de tome" min="1">
                    </div>
                    <div id="champs-magazine" class="specific-fields" style="display:none">
                        <input type="number" name="numero" placeholder="Numéro de parution" min="1">
                        <select name="periodicite">
                            <option value="mensuel">Mensuel</option>
                            <option value="hebdomadaire">Hebdomadaire</option>
                            <option value="trimestriel">Trimestriel</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Type</th><th>Titre</th><th>Auteur</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td style="color:var(--ink-faint);font-size:0.8rem"><?php echo $doc->getId(); ?></td>
                        <td><?php echo typeBadge($doc->getType()); ?></td>
                        <td style="font-weight:500"><?php echo htmlspecialchars($doc->getNom()); ?></td>
                        <td style="color:var(--ink-muted)"><?php echo htmlspecialchars($doc->getAuteur()); ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Supprimer ce document définitivement ?')">
                                <input type="hidden" name="action_admin" value="supprimer_document">
                                <input type="hidden" name="document_id" value="<?php echo $doc->getId(); ?>">
                                <button type="submit" class="btn btn-danger btn-small">🗑️ Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB MEMBRES -->
    <div id="tab-membres" style="display:none">
        <div class="admin-section">
            <div class="admin-section-header">
                <h3>➕ Ajouter un membre</h3>
            </div>
            <div class="admin-form-body">
                <form method="POST">
                    <input type="hidden" name="action_admin" value="ajouter_membre">
                    <div class="form-row">
                        <input type="text" name="nom" placeholder="Nom" required>
                        <input type="text" name="prenom" placeholder="Prénom" required>
                        <input type="email" name="email" placeholder="Email" required>
                        <button type="submit" class="btn btn-success">➕ Ajouter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Nom complet</th><th>Email</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($membres as $membre): ?>
                    <tr>
                        <td style="color:var(--ink-faint);font-size:0.8rem"><?php echo $membre->getId(); ?></td>
                        <td style="font-weight:500"><?php echo htmlspecialchars($membre->getPrenom() . ' ' . $membre->getNom()); ?></td>
                        <td style="color:var(--ink-muted)"><?php echo htmlspecialchars($membre->getEmail()); ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Supprimer ce membre ?')">
                                <input type="hidden" name="action_admin" value="supprimer_membre">
                                <input type="hidden" name="membre_id" value="<?php echo $membre->getId(); ?>">
                                <button type="submit" class="btn btn-danger btn-small">🗑️ Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function showAdminTab(tab, btn) {
        var tabDocs = document.getElementById('tab-documents');
        var tabMembres = document.getElementById('tab-membres');
        var tabs = document.querySelectorAll('.admin-tab');
        
        if (tab === 'documents') {
            tabDocs.style.display = 'block';
            tabMembres.style.display = 'none';
        } else {
            tabDocs.style.display = 'none';
            tabMembres.style.display = 'block';
        }
        
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        btn.classList.add('active');
    }

    function showSpecificFields(type) {
        var champsLivre = document.getElementById('champs-livre');
        var champsBd = document.getElementById('champs-bd');
        var champsMagazine = document.getElementById('champs-magazine');
        
        champsLivre.style.display = 'none';
        champsBd.style.display = 'none';
        champsMagazine.style.display = 'none';
        
        if (type === 'livre') {
            champsLivre.style.display = 'flex';
        } else if (type === 'bd') {
            champsBd.style.display = 'flex';
        } else if (type === 'magazine') {
            champsMagazine.style.display = 'flex';
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque de Saint-Chapitres</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav>
    <span class="nav-brand">📚 Biblio<span>·</span>Saint-Chapitres</span>
    <div class="nav-links">
        <?php if ($auth->isLoggedIn()): ?>
            <a href="?action=accueil">🏠 Catalogue</a>
            <a href="?action=membres">👥 Membres</a>
            <a href="?action=stats">📊 Statistiques</a>
            <?php if ($auth->isAdmin()): ?>
                <div class="nav-divider"></div>
                <a href="?action=admin">⚙️ Administration</a>
            <?php endif; ?>
            <div class="nav-divider"></div>
            <span class="user-info">👋 <?php echo htmlspecialchars($auth->getCurrentUser()['prenom']); ?></span>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn-logout">🚪 Déconnexion</button>
            </form>
        <?php else: ?>
            <a href="?action=login">🔐 Connexion</a>
        <?php endif; ?>
    </div>
</nav>

<?php
$fullWidthPages = array('login', 'register');
if (in_array($action, $fullWidthPages)):
    afficherAlerte();
    pageLogin();
else: ?>
<div class="page-wrapper">
    <?php afficherAlerte(); ?>
    <?php
    switch ($action) {
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
        case 'accueil':
            pageAccueil($bibliotheque);
            break;
        default:
            pageAccueil($bibliotheque);
            break;
    }
    ?>
</div>
<?php endif; ?>

<a href="#" class="scroll-top" id="scrollTop" title="Retour en haut">↑</a>

<script>
var scrollBtn = document.getElementById('scrollTop');
window.addEventListener('scroll', function() {
    if (window.scrollY > 400) {
        scrollBtn.classList.add('show');
    } else {
        scrollBtn.classList.remove('show');
    }
});
scrollBtn.addEventListener('click', function(e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Highlight active nav
var currentAction = new URLSearchParams(window.location.search).get('action') || 'login';
var navLinks = document.querySelectorAll('nav a');
for (var i = 0; i < navLinks.length; i++) {
    var href = navLinks[i].getAttribute('href') || '';
    if (href.includes('action=' + currentAction)) {
        navLinks[i].classList.add('active');
    }
}
</script>
</body>
</html>