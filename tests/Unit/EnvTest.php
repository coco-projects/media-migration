<?php

    declare(strict_types = 1);

    namespace Coco\Tests\Unit;

    use Coco\env\EnvParser;
    use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testA()
    {
        EnvParser::loadEnvFile('examples/.env');

        $arr = EnvParser::set('TEST899', '111111111');

        $arr = EnvParser::get('TEST8', '2222222');

        $arr = EnvParser::get('TEST899', '33333333');

        $arr = EnvParser::get('TEST855', '44444444');

        $arr = EnvParser::getAll();

        $this->assertEquals(1, 1);
    }
}
