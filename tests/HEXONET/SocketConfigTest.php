<?php

//declare(strict_types=1);

namespace CNICTEST\HEXONET;

use CNIC\HEXONET\SocketConfig as SC;

final class SocketConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * test getPOSTData method
     */
    public function testGetPOSTData(): void
    {
        $d = (new SC([
            "login" => [
                "name" => "s_login",
                "sep" => "!"
            ],
            "password" => "s_pw",
            "session" => "s_session",
            "subuser" => "s_user",
            "command" => "COMMAND",
            "ipfilter" => "s_remoteaddr",
            "entity" => "s_entity"
        ]))->getPOSTData();
        $this->assertEmpty($d);
    }
}
