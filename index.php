<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use Biblio\Services\Bibliotheque;

session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $actionPost = $_POST['action'] ?? '';
        
        if ($actionPost === 'emprunter') {
            $bibliotheque->emprunter((int)$_POST['document_id'], (int)$_POST['membre_id']);
            $_SESSION['message'] = 'Document emprunté avec succès !';
        } elseif ($actionPost === 'retourner') {
            $bibliotheque->retourner((int)$_POST['document_id']);
            $_SESSION['message'] = 'Document retourné avec succès !';
        } elseif ($actionPost === 'reserver') {
            $bibliotheque->reserver((int)$_POST['document_id'], (int)$_POST['membre_id']);
            $_SESSION['message'] = 'Document réservé avec succès !';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: index.php?action=' . $action);
    exit;
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
                    <td><?= $doc->getDateAjout() ?></td>
                    <td><?= htmlspecialchars($doc->getResume()) ?></td>
                    <td><a href="?action=detail&id=<?= $doc->getId() ?>" class="btn btn-detail">Détail</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function pageDetail(Bibliotheque $biblio, int $id): void {
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
    <h2><?= htmlspecialchars($doc) ?></h2>
    <table>
        <tr><th>Type</th><td><?= ucfirst($doc->getType()) ?></td></tr>
        <tr><th>Titre</th><td><?= htmlspecialchars($doc->getNom()) ?></td></tr>
        <tr><th>Auteur</th><td><?= htmlspecialchars($doc->getAuteur()) ?></td></tr>
        <tr><th>Date d'ajout</th><td><?= $doc->getDateAjout() ?></td></tr>
        <tr><th>Résumé</th><td><?= htmlspecialchars($doc->getResume()) ?></td></tr>
        <tr><th>Disponibilité</th><td class="<?= $disponible ? 'disponible' : 'indisponible' ?>"><?= $disponible ? 'Disponible' : 'Emprunté' ?></td></tr>
        <?php if ($emprunteur): ?>
            <tr><th>Emprunté par</th><td><?= htmlspecialchars($emprunteur) ?></td></tr>
        <?php endif; ?>
        <tr><th>Créé le</th><td><?= $doc->getCreeLe() ?></td></tr>
        <tr><th>Modifié le</th><td><?= $doc->getModifieLe() ?: '-' ?></td></tr>
    </table>
    
    <?php if ($estEmpruntable): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="document_id" value="<?= $id ?>">
            <?php if ($disponible): ?>
                <select name="membre_id" required>
                    <option value="">Choisir un membre...</option>
                    <?php foreach ($membres as $membre): ?>
                        <option value="<?= $membre->getId() ?>"><?= htmlspecialchars($membre) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="action" value="emprunter" class="btn btn-emprunter">📖 Emprunter</button>
            <?php else: ?>
                <button type="submit" name="action" value="retourner" class="btn btn-retourner">🔄 Retourner</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    
    <?php if ($estReservable && $disponible): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="document_id" value="<?= $id ?>">
            <select name="membre_id" required>
                <option value="">Choisir un membre...</option>
                <?php foreach ($membres as $membre): ?>
                    <option value="<?= $membre->getId() ?>"><?= htmlspecialchars($membre) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="reserver" class="btn btn-reserver">⭐ Réserver</button>
        </form>
    <?php endif; ?>
    
    <div style="margin-top: 1rem"><a href="?action=accueil" class="btn">⬅ Retour</a></div>
    <?php
}

function pageMembres(Bibliotheque $biblio): void {
    $membres = $biblio->getMembres();
    ?>
    <table>
        <thead><tr><th>Prénom</th><th>Nom</th><th>Email</th><th>Date inscription</th><th>Emprunts en cours</th></tr></thead>
        <tbody>
            <?php foreach ($membres as $membre): ?>
                <?php $emprunts = $biblio->getEmpruntsMembre($membre->getId()); ?>
                <tr>
                    <td><?= htmlspecialchars($membre->getPrenom()) ?></td>
                    <td><?= htmlspecialchars($membre->getNom()) ?></td>
                    <td><?= htmlspecialchars($membre->getEmail()) ?></td>
                    <td><?= $membre->getDateInscription() ?></td>
                    <td>
                        <?php if ($emprunts): ?>
                            <ul>
                            <?php foreach ($emprunts as $e): ?>
                                <li><?= htmlspecialchars($e['document']->getNom()) ?> (depuis <?= $e['dateEmprunt'] ?>)</li>
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bibliothèque Municipale</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <nav>
            <a href="?action=accueil">🏠 Accueil</a>
            <a href="?action=membres">👥 Membres</a>
            <a href="?action=stats">📊 Statistiques</a>
        </nav>
        <div class="content">
            <h1>📚 Bibliothèque de Saint-Chapitres-en-POO</h1>
            <?php afficherAlerte(); ?>
            <?php
            switch ($action) {
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
</body>
</html>