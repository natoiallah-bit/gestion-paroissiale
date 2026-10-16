<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_les_montants_sont_affiches_en_fcfa(): void
    {
        $this->assertSame('12 500 F', fcfa(12500));
        $this->assertSame('0 F', fcfa(null));
        $this->assertSame('-3 000 F', fcfa(-3000));
    }
}
