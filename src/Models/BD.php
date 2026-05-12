<?php
declare(strict_types=1);

namespace Biblio\Models;

use Biblio\Interfaces\Empruntable;
use Biblio\Services\Database;

class BD extends Document implements Empruntable
{
    private ?Membre $emprunteur = null;
    
    public function __construct(
        ?int $id,
        string $nom,
        string $auteur,
        string $dateAjout,
        private string $dessinateur,
        private int $tome
    ) {
        parent::__construct($id, $nom, $auteur, $dateAjout);
        
        if (empty(trim($dessinateur))) {
            throw new \InvalidArgumentException('Le nom du dessinateur ne peut pas être vide');
        }
        if ($tome < 1) {
            throw new \InvalidArgumentException('Le numéro de tome doit être >= 1');
        }
        
        $this->chargerEtat();
    }
    
    private function chargerEtat(): void
    {
        if ($this->id === null) return;
        
        $db = Database::get();
        $emprunts = $db->lireTous('emprunts');
        
        foreach ($emprunts as $emprunt) {
            if ($emprunt['document_id'] === $this->id && $emprunt['dateRetour'] === null) {
                $membreData = $db->lireParId('membres', $emprunt['membre_id']);
                if ($membreData) {
                    $this->emprunteur = new Membre(
                        $membreData['id'],
                        $membreData['nom'],
                        $membreData['prenom'],
                        $membreData['email'],
                        $membreData['dateInscription']
                    );
                }
                break;
            }
        }
    }
    
    public function getType(): string
    {
        return 'bd';
    }
    
    public function getResume(): string
    {
        return sprintf("%s — %s / %s — tome %d", $this->nom, $this->auteur, $this->dessinateur, $this->tome);
    }
    
    public function getDessinateur(): string
    {
        return $this->dessinateur;
    }
    
    public function getTome(): int
    {
        return $this->tome;
    }
    
    public function emprunter(Membre $membre): void
    {
        if (!$this->estDisponible()) {
            throw new \RuntimeException('La BD n\'est pas disponible');
        }
        
        $db = Database::get();
        $db->inserer('emprunts', [
            'document_id' => $this->id,
            'membre_id' => $membre->getId(),
            'dateEmprunt' => date('Y-m-d H:i:s'),
            'dateRetour' => null
        ]);
        
        $this->emprunteur = $membre;
        $this->log("BD empruntée par {$membre->getPrenom()} {$membre->getNom()}");
        $this->marquerModification();
    }
    
    public function retourner(): void
    {
        if ($this->estDisponible()) {
            throw new \RuntimeException('La BD n\'était pas empruntée');
        }
        
        $db = Database::get();
        $emprunts = $db->lireTous('emprunts');
        foreach ($emprunts as $emprunt) {
            if ($emprunt['document_id'] === $this->id && $emprunt['dateRetour'] === null) {
                $db->mettreAJour('emprunts', $emprunt['id'], ['dateRetour' => date('Y-m-d H:i:s')]);
                break;
            }
        }
        
        $this->emprunteur = null;
        $this->log("BD retournée");
        $this->marquerModification();
    }
    
    public function estDisponible(): bool
    {
        return $this->emprunteur === null;
    }
    
    public function getEmprunteur(): ?Membre
    {
        return $this->emprunteur;
    }
}