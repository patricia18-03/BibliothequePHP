<?php
declare(strict_types=1);

namespace Biblio\Services;

class Auth
{
    private static ?Auth $instance = null;
    private ?array $currentUser = null;
    
    private function __construct() 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->currentUser = $_SESSION['user'] ?? null;
    }
    
    public static function get(): Auth
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function login(string $username, string $password): bool
    {
        $db = Database::get();
        $users = $db->lireTous('users');
        
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $this->currentUser = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'nom' => $user['nom'],
                    'prenom' => $user['prenom'],
                    'email' => $user['email']
                ];
                $_SESSION['user'] = $this->currentUser;
                return true;
            }
        }
        return false;
    }
    
    public function register(string $username, string $password, string $nom, string $prenom, string $email): bool
    {
        $db = Database::get();
        
        // Vérifier si l'utilisateur existe déjà
        $users = $db->lireTous('users');
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return false;
            }
            if ($user['email'] === $email) {
                return false;
            }
        }
        
        // Créer le nouvel utilisateur (par défaut rôle 'user')
        $db->inserer('users', [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'date_creation' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    public function logout(): void
    {
        $this->currentUser = null;
        unset($_SESSION['user']);
        session_destroy();
        // Redémarrer une session pour les messages
        session_start();
    }
    
    public function isLoggedIn(): bool
    {
        return $this->currentUser !== null;
    }
    
    public function isAdmin(): bool
    {
        return $this->currentUser && $this->currentUser['role'] === 'admin';
    }
    
    public function getCurrentUser(): ?array
    {
        return $this->currentUser;
    }
    
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: index.php?action=login');
            exit;
        }
    }
    
    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: index.php?action=accueil&error=non_admin');
            exit;
        }
    }
}