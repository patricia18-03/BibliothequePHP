<?php
declare(strict_types=1);

namespace Biblio\Models;

use Biblio\Interfaces\Empruntable;
use Biblio\Interfaces\Reservable;
use Biblio\Services\Database;

class Livre extends Document implements Empruntable, Reservable
{
    private ?Membre $emprunteur = null;
    private ?Membre $reservant = null;
    private bool $estReserve = false;
    
    public function __construct(
        ?int $id,
        string $nom,
        string $auteur,
        string $dateAjout,
        private int $pages,
        private string $genre
    ) {
        parent::__construct($id, $nom, $auteur, $dateAjout);
        
        if ($pages <= 0) {
            throw new \InvalidArgumentException('Le nombre de pages doit être supérieur à 0');
        }
        if (empty(trim($genre))) {
            throw new \InvalidArgumentException('Le genre ne peut pas être vide');
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
        return 'livre';
    }
    
    public function getResume(): string
    {
        return sprintf("%s — %s, %d pages", $this->nom, $this->genre, $this->pages);
    }
    
    public function getPages(): int
    {
        return $this->pages;
    }
    
    public function getGenre(): string
    {
        return $this->genre;
    }
    
    public function emprunter(Membre $membre): void
    {
        if (!$this->estDisponible()) {
            throw new \RuntimeException('Le livre n\'est pas disponible');
        }
        
        $db = Database::get();
        $db->inserer('emprunts', [
            'document_id' => $this->id,
            'membre_id' => $membre->getId(),
            'dateEmprunt' => date('Y-m-d H:i:s'),
            'dateRetour' => null
        ]);
        
        $this->emprunteur = $membre;
        $this->log("Livre emprunté par {$membre->getPrenom()} {$membre->getNom()}");
        $this->marquerModification();
    }
    
    public function retourner(): void
    {
        if ($this->estDisponible()) {
            throw new \RuntimeException('Le livre n\'était pas emprunté');
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
        $this->log("Livre retourné");
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
    
    public function reserver(Membre $membre): void
    {
        if ($this->estReserve) {
            throw new \RuntimeException('Le livre est déjà réservé');
        }
        
        $this->reservant = $membre;
        $this->estReserve = true;
        $this->log("Livre réservé par {$membre->getPrenom()} {$membre->getNom()}");
        $this->marquerModification();
    }
    
    public function annulerReservation(): void
    {
        if (!$this->estReserve) {
            throw new \RuntimeException('Le livre n\'est pas réservé');
        }
        
        $this->reservant = null;
        $this->estReserve = false;
        $this->log("Réservation annulée");
        $this->marquerModification();
    }
    
    public function estReserve(): bool
    {
        return $this->estReserve;
    }
    
    public function getReservant(): ?Membre
    {
        return $this->reservant;
    }
}