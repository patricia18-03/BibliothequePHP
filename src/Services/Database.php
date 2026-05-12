<?php
declare(strict_types=1);

namespace Biblio\Services;

class Database
{
    private static ?self $instance = null;
    private \PDO $pdo;
    
    private function __construct()
    {
        $dbFile = __DIR__ . '/../../data.sqlite';
        $isNew = !file_exists($dbFile);
        
        $this->pdo = new \PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        if ($isNew) {
            $this->creerTables();
            $this->seeder();
        }
    }
    
    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function creerTables(): void
    {
        $sqls = [
            "CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                nom TEXT NOT NULL,
                auteur TEXT NOT NULL,
                dateAjout TEXT NOT NULL,
                proprietes_specifiques TEXT
            )",
            "CREATE TABLE IF NOT EXISTS membres (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL,
                prenom TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                dateInscription TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS emprunts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER NOT NULL,
                membre_id INTEGER NOT NULL,
                dateEmprunt TEXT NOT NULL,
                dateRetour TEXT,
                type_operation TEXT DEFAULT 'emprunt',
                FOREIGN KEY (document_id) REFERENCES documents(id),
                FOREIGN KEY (membre_id) REFERENCES membres(id)
            )"
        ];
        
        foreach ($sqls as $sql) {
            $this->pdo->exec($sql);
        }
    }
    
    private function seeder(): void
    {
        // Livres
        $this->inserer('documents', ['type' => 'livre', 'nom' => 'Germinal', 'auteur' => 'Émile Zola', 'dateAjout' => '2024-01-15', 'proprietes_specifiques' => json_encode(['pages' => 591, 'genre' => 'Roman'])]);
        $this->inserer('documents', ['type' => 'livre', 'nom' => 'Le Petit Prince', 'auteur' => 'Antoine de Saint-Exupéry', 'dateAjout' => '2024-01-20', 'proprietes_specifiques' => json_encode(['pages' => 96, 'genre' => 'Conte'])]);
        
        // BD
        $this->inserer('documents', ['type' => 'bd', 'nom' => 'Tintin au Tibet', 'auteur' => 'Hergé', 'dateAjout' => '2024-02-01', 'proprietes_specifiques' => json_encode(['dessinateur' => 'Hergé', 'tome' => 20])]);
        $this->inserer('documents', ['type' => 'bd', 'nom' => 'Astérix chez les Bretons', 'auteur' => 'René Goscinny', 'dateAjout' => '2024-02-05', 'proprietes_specifiques' => json_encode(['dessinateur' => 'Albert Uderzo', 'tome' => 8])]);
        
        // Magazines
        $this->inserer('documents', ['type' => 'magazine', 'nom' => 'Géo', 'auteur' => 'Prisma Media', 'dateAjout' => '2024-03-01', 'proprietes_specifiques' => json_encode(['numero' => 512, 'periodicite' => 'mensuel'])]);
        $this->inserer('documents', ['type' => 'magazine', 'nom' => 'Science & Vie', 'auteur' => 'Reworld Media', 'dateAjout' => '2024-03-10', 'proprietes_specifiques' => json_encode(['numero' => 1278, 'periodicite' => 'mensuel'])]);
        
        // Membres
        $this->inserer('membres', ['nom' => 'Martin', 'prenom' => 'Alice', 'email' => 'alice.martin@email.com', 'dateInscription' => '2024-01-10']);
        $this->inserer('membres', ['nom' => 'Bernard', 'prenom' => 'Bob', 'email' => 'bob.bernard@email.com', 'dateInscription' => '2024-01-15']);
        $this->inserer('membres', ['nom' => 'Durand', 'prenom' => 'Charlie', 'email' => 'charlie.durand@email.com', 'dateInscription' => '2024-02-01']);
        
        // Emprunts en cours
        $this->inserer('emprunts', ['document_id' => 1, 'membre_id' => 1, 'dateEmprunt' => date('Y-m-d H:i:s', strtotime('-5 days')), 'dateRetour' => null]);
        $this->inserer('emprunts', ['document_id' => 3, 'membre_id' => 2, 'dateEmprunt' => date('Y-m-d H:i:s', strtotime('-3 days')), 'dateRetour' => null]);
    }
    
    public function lireTous(string $table): array
    {
        $tablesAutorisees = ['documents', 'membres', 'emprunts'];
        if (!in_array($table, $tablesAutorisees)) {
            throw new \InvalidArgumentException("Table non autorisée : $table");
        }
        
        $stmt = $this->pdo->query("SELECT * FROM $table");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function lireParId(string $table, int $id): ?array
    {
        $tablesAutorisees = ['documents', 'membres', 'emprunts'];
        if (!in_array($table, $tablesAutorisees)) {
            throw new \InvalidArgumentException("Table non autorisée : $table");
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function inserer(string $table, array $donnees): int
    {
        $tablesAutorisees = ['documents', 'membres', 'emprunts'];
        if (!in_array($table, $tablesAutorisees)) {
            throw new \InvalidArgumentException("Table non autorisée : $table");
        }
        
        $champs = implode(', ', array_keys($donnees));
        $placeholders = ':' . implode(', :', array_keys($donnees));
        $stmt = $this->pdo->prepare("INSERT INTO $table ($champs) VALUES ($placeholders)");
        $stmt->execute($donnees);
        return (int)$this->pdo->lastInsertId();
    }
    
    public function mettreAJour(string $table, int $id, array $donnees): void
    {
        $tablesAutorisees = ['documents', 'membres', 'emprunts'];
        if (!in_array($table, $tablesAutorisees)) {
            throw new \InvalidArgumentException("Table non autorisée : $table");
        }
        
        $set = [];
        foreach ($donnees as $key => $value) {
            $set[] = "$key = :$key";
        }
        $setString = implode(', ', $set);
        $donnees['id'] = $id;
        $stmt = $this->pdo->prepare("UPDATE $table SET $setString WHERE id = :id");
        $stmt->execute($donnees);
    }
    
    public function supprimer(string $table, int $id): void
    {
        $tablesAutorisees = ['documents', 'membres', 'emprunts'];
        if (!in_array($table, $tablesAutorisees)) {
            throw new \InvalidArgumentException("Table non autorisée : $table");
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$id]);
    }
}