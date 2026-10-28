<?php

namespace Tests\Unit;

use App\Support\Ressources;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_montants_en_fcfa(): void
    {
        $this->assertSame('12 500 F', fcfa(12500));
        $this->assertSame('0 F', fcfa(null));
        $this->assertSame('1 000 000 F', fcfa(1000000));
    }

    public function test_annee_pastorale_de_septembre_a_aout(): void
    {
        $this->assertSame('2026-2027', Ressources::anneePastorale(new \DateTime('2026-09-01')));
        $this->assertSame('2026-2027', Ressources::anneePastorale(new \DateTime('2027-06-30')));
        $this->assertSame('2025-2026', Ressources::anneePastorale(new \DateTime('2026-08-31')));
    }
}
