<?php
declare(strict_types=1);

namespace Biblio\Interfaces;

use Biblio\Models\Membre;

interface Empruntable
{
    public function emprunter(Membre $membre): void;
    public function retourner(): void;
    public function estDisponible(): bool;
    public function getEmprunteur(): ?Membre;
}