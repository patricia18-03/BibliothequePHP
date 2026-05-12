<?php
declare(strict_types=1);

namespace Biblio\Models;

use Biblio\Traits\Horodatable;

class Membre
{
    use Horodatable;
    
    public function __construct(
        private int $id,
        private string $nom,
        private string $prenom,
        private string $email,
        private string $dateInscription
    ) {
        if (empty(trim($nom))) {
            throw new \InvalidArgumentException('Le nom ne peut pas être vide');
        }
        if (empty(trim($prenom))) {
            throw new \InvalidArgumentException('Le prénom ne peut pas être vide');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'email n\'est pas valide');
        }
        
        $this->marquerCreation();
    }
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getNom(): string
    {
        return $this->nom;
    }
    
    public function getPrenom(): string
    {
        return $this->prenom;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getDateInscription(): string
    {
        return $this->dateInscription;
    }
    
    public function __toString(): string
    {
        return sprintf("%s %s (%s)", $this->prenom, $this->nom, $this->email);
    }
}