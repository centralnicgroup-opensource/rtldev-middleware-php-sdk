<?php

//declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\SocketConfig as SC;

final class SocketConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * test getPOSTData method
     */
    public function testGetPostData(): void
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
            "ipfilter" => "s_remoteaddr"
        ]))->getPOSTData();
        $this->assertEmpty($d);
    }
}
