<?php

namespace Tests\Unit;

use App\Shared\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_e164_and_uk_national_forms(): void
    {
        $this->assertSame('+447700900123', PhoneNormalizer::normalize('+44 7700 900123'));
        $this->assertSame('+447700900123', PhoneNormalizer::normalize('00447700900123'));
        $this->assertSame('+447700900123', PhoneNormalizer::normalize('07700900123'));
        $this->assertSame('+447700900123', PhoneNormalizer::normalize('7700900123'));
        $this->assertTrue(PhoneNormalizer::isValid('07700900123'));
        $this->assertTrue(PhoneNormalizer::isValid('+447700900123'));
    }
}
