<?php

namespace Tests\Unit;

use App\Support\CaseInsensitiveLike;
use PHPUnit\Framework\TestCase;

class CaseInsensitiveLikeTest extends TestCase
{
    public function test_pattern_lowercases_search_term(): void
    {
        $this->assertSame('%omar monem%', CaseInsensitiveLike::pattern('Omar Monem'));
    }

    public function test_pattern_escapes_wildcards(): void
    {
        $this->assertSame('%100\%%', CaseInsensitiveLike::pattern('100%'));
        $this->assertSame('%a\_b%', CaseInsensitiveLike::pattern('a_b'));
    }

    public function test_pattern_trims_whitespace(): void
    {
        $this->assertSame('%helmet%', CaseInsensitiveLike::pattern('  Helmet  '));
    }
}
