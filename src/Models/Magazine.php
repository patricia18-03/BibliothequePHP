<?php
declare(strict_types=1);

namespace Biblio\Models;

class Magazine extends Document
{
    private static array $periodicitesValides = ['hebdomadaire', 'mensuel', 'trimestriel'];
    
    public function __construct(
        ?int $id,
        string $nom,
        string $auteur,
        string $dateAjout,
        private int $numero,
        private string $periodicite
    ) {
        parent::__construct($id, $nom, $auteur, $dateAjout);
        
        if ($numero < 1) {
            throw new \InvalidArgumentException('Le numéro doit être >= 1');
        }
        if (!in_array($periodicite, self::$periodicitesValides)) {
            throw new \InvalidArgumentException('La périodicité doit être : hebdomadaire, mensuel ou trimestriel');
        }
    }
    
    public function getType(): string
    {
        return 'magazine';
    }
    
    public function getResume(): string
    {
        return sprintf("%s n°%d — %s", $this->nom, $this->numero, $this->periodicite);
    }
    
    public function getNumero(): int
    {
        return $this->numero;
    }
    
    public function getPeriodicite(): string
    {
        return $this->periodicite;
    }
}