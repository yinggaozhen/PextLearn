<?php

namespace tests;

/**
 * Class P001Test
 * @package tests
 * @a
 */
class P001Test extends Base
{
    /**
     * yaf extension is not available
     *
     * @throws \Exception
     */
    public function test()
    {
        $this->assertFalse(extension_loaded("yaf"));
    }
}
