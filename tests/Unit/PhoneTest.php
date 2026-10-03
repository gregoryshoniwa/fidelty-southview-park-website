<?php

namespace Tests\Unit;

use App\Services\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalises_zimbabwe_numbers(): void
    {
        $this->assertSame('+263771234567', Phone::normalise('077 123 4567'));
        $this->assertSame('+263771234567', Phone::normalise('+263 77 123 4567'));
        $this->assertSame('+263771234567', Phone::normalise('00263771234567'));
        $this->assertSame('+263771234567', Phone::normalise('771234567'));
        $this->assertNull(Phone::normalise('12'));
        $this->assertNull(Phone::normalise(''));
    }
}
