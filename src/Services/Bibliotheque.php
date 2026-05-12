<?php
declare(strict_types=1);

namespace Biblio\Services;

use Biblio\Models\Document;
use Biblio\Models\Livre;
use Biblio\Models\BD;
use Biblio\Models\Magazine;
use Biblio\Models\Membre;
use Biblio\Interfaces\Empruntable;
use Biblio\Interfaces\Reservable;

class Bibliotheque
{
    /** @var Document[] */
    private array $documents = [];
    
    /** @var Membre[] */
    private array $membres = [];
    
    public function __construct()
    {
        $this->charger();
    }
    
    private function charger(): void
    {
        $db = Database::get();
        
        // Chargement des documents
        $documentsData = $db->lireTous('documents');
        foreach ($documentsData as $row) {
            $proprietes = json_decode($row['proprietes_specifiques'] ?? '{}', true);
            
            $document = match ($row['type']) {
                'livre' => new Livre(
                    $row['id'],
                    $row['nom'],
                    $row['auteur'],
                    $row['dateAjout'],
                    $proprietes['pages'] ?? 0,
                    $proprietes['genre'] ?? ''
                ),
                'bd' => new BD(
                    $row['id'],
                    $row['nom'],
                    $row['auteur'],
                    $row['dateAjout'],
                    $proprietes['dessinateur'] ?? '',
                    $proprietes['tome'] ?? 1
                ),
                'magazine' => new Magazine(
                    $row['id'],
                    $row['nom'],
                    $row['auteur'],
                    $row['dateAjout'],
                    $proprietes['numero'] ?? 1,
                    $proprietes['periodicite'] ?? 'mensuel'
                ),
                default => throw new \RuntimeException("Type inconnu : {$row['type']}")
            };
            
            $this->documents[$row['id']] = $document;
        }
        
        // Chargement des membres
        $membresData = $db->lireTous('membres');
        foreach ($membresData as $row) {
            $this->membres[$row['id']] = new Membre(
                $row['id'],
                $row['nom'],
                $row['prenom'],
                $row['email'],
                $row['dateInscription']
            );
        }
    }
    
    public function getDocuments(): array
    {
        return array_values($this->documents);
    }
    
    public function getMembres(): array
    {
        return array_values($this->membres);
    }
    
    public function getDocumentParId(int $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }
    
    public function getMembreParId(int $id): ?Membre
    {
        return $this->membres[$id] ?? null;
    }
    
    public function chercherDocuments(string $terme): array
    {
        return array_filter($this->documents, function($doc) use ($terme) {
            return $doc->correspondA($terme);
        });
    }
    
    public function filtrerParType(string $type): array
    {
        return array_filter($this->documents, function($doc) use ($type) {
            return $doc->getType() === $type;
        });
    }
    
    public function emprunter(int $idDocument, int $idMembre): void
    {
        $document = $this->getDocumentParId($idDocument);
        $membre = $this->getMembreParId($idMembre);
        
        if (!$document) {
            throw new \InvalidArgumentException('Document non trouvé');
        }
        if (!$membre) {
            throw new \InvalidArgumentException('Membre non trouvé');
        }
        
        if (!$document instanceof Empruntable) {
            throw new \RuntimeException('Ce document ne peut pas être emprunté');
        }
        
        $document->emprunter($membre);
    }
    
    public function retourner(int $idDocument): void
    {
        $document = $this->getDocumentParId($idDocument);
        
        if (!$document) {
            throw new \InvalidArgumentException('Document non trouvé');
        }
        
        if (!$document instanceof Empruntable) {
            throw new \RuntimeException('Ce document ne peut pas être retourné');
        }
        
        $document->retourner();
    }
    
    public function reserver(int $idDocument, int $idMembre): void
    {
        $document = $this->getDocumentParId($idDocument);
        $membre = $this->getMembreParId($idMembre);
        
        if (!$document) {
            throw new \InvalidArgumentException('Document non trouvé');
        }
        if (!$membre) {
            throw new \InvalidArgumentException('Membre non trouvé');
        }
        
        if (!$document instanceof Reservable) {
            throw new \RuntimeException('Ce document ne peut pas être réservé');
        }
        
        $document->reserver($membre);
    }
    
    public function getStatistiques(): array
    {
        $stats = [
            'total_documents' => count($this->documents),
            'total_membres' => count($this->membres),
            'livres' => 0,
            'bd' => 0,
            'magazines' => 0,
            'emprunts_en_cours' => 0,
            'documents_disponibles' => 0
        ];
        
        foreach ($this->documents as $doc) {
            $type = $doc->getType();
            $stats[$type . 's'] = ($stats[$type . 's'] ?? 0) + 1;
            
            if ($doc instanceof Empruntable && $doc->estDisponible()) {
                $stats['documents_disponibles']++;
            }
        }
        
        $db = Database::get();
        $emprunts = $db->lireTous('emprunts');
        foreach ($emprunts as $emprunt) {
            if ($emprunt['dateRetour'] === null) {
                $stats['emprunts_en_cours']++;
            }
        }
        
        return $stats;
    }
    
    public function getEmpruntsMembre(int $idMembre): array
    {
        $db = Database::get();
        $emprunts = $db->lireTous('emprunts');
        $result = [];
        
        foreach ($emprunts as $emprunt) {
            if ($emprunt['membre_id'] === $idMembre && $emprunt['dateRetour'] === null) {
                $document = $this->getDocumentParId($emprunt['document_id']);
                if ($document) {
                    $result[] = [
                        'document' => $document,
                        'dateEmprunt' => $emprunt['dateEmprunt']
                    ];
                }
            }
        }
        
        return $result;
    }
}