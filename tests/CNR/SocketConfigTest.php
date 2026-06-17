<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\SocketConfig as SC;
use PHPUnit\Framework\TestCase;

final class SocketConfigTest extends TestCase
{
    /**
     * test getPOSTData method
     */
    public function testGetPostData(): void
    {
        $d = (new SC())->getPOSTData();
        $this->assertEmpty($d);
    }
}
