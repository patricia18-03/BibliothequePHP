<?php
declare(strict_types=1);

namespace Biblio\Models;

use Biblio\Traits\Horodatable;
use Biblio\Traits\Loggable;

abstract class Document
{
    use Horodatable;
    use Loggable;
    
    private static int $compteurInstances = 0;
    
    public function __construct(
        protected ?int $id,
        protected string $nom,
        protected string $auteur,
        protected string $dateAjout
    ) {
        if (empty(trim($nom))) {
            throw new \InvalidArgumentException('Le nom du document ne peut pas être vide');
        }
        if (empty(trim($auteur))) {
            throw new \InvalidArgumentException('Le nom de l\'auteur ne peut pas être vide');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAjout)) {
            throw new \InvalidArgumentException('La date d\'ajout doit être au format YYYY-MM-DD');
        }
        
        self::$compteurInstances++;
        $this->marquerCreation();
        $this->log("Document créé : {$this->getType()} - {$nom}");
    }
    
    public static function getCompteurInstances(): int
    {
        return self::$compteurInstances;
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getNom(): string
    {
        return $this->nom;
    }
    
    public function getAuteur(): string
    {
        return $this->auteur;
    }
    
    public function getDateAjout(): string
    {
        return $this->dateAjout;
    }
    
    public function correspondA(string $terme): bool
    {
        $terme = strtolower($terme);
        return str_contains(strtolower($this->nom), $terme) || 
               str_contains(strtolower($this->auteur), $terme);
    }
    
    public function __toString(): string
    {
        return sprintf("[%s] %s — %s", ucfirst($this->getType()), $this->nom, $this->auteur);
    }
    
    abstract public function getType(): string;
    abstract public function getResume(): string;
}