<?php
declare(strict_types=1);

namespace Biblio\Traits;

trait Horodatable
{
    private ?string $creeLe = null;
    private ?string $modifieLe = null;

    public function marquerCreation(): void
    {
        $this->creeLe = date('Y-m-d H:i:s');
    }

    public function marquerModification(): void
    {
        $this->modifieLe = date('Y-m-d H:i:s');
    }

    public function getCreeLe(): ?string
    {
        return $this->creeLe;
    }

    public function getModifieLe(): ?string
    {
        return $this->modifieLe;
    }
}