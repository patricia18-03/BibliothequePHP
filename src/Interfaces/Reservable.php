<?php
declare(strict_types=1);

namespace Biblio\Interfaces;

use Biblio\Models\Membre;

interface Reservable
{
    public function reserver(Membre $membre): void;
    public function annulerReservation(): void;
    public function estReserve(): bool;
    public function getReservant(): ?Membre;
}