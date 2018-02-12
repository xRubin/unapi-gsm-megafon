<?php

use unapi\gsm\megafon\MegafonCabinetClient;
use unapi\gsm\megafon\MegafonCabinetService;
use unapi\anticaptcha\common\AnticaptchaInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\TestCase;

use function GuzzleHttp\json_encode;

class MegafonCabinetServiceTest extends TestCase
{
    public function testAuthAndBalance()
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'msisdn' => '9250000000',
                'operCode' => '100',
                'operKey' => 'stf',
                'name' => 'Тестов Тест Иванович',
                'avatarId' => 'da4e93b1-288f-461a-856d-4c005083105f',
                'status' => [
                    'id' => 1,
                    'text' => 'Активен'
                ],
                'widgetKey' => '67ee2c35-d789-4d91-9fed-b36087f3b321',
                'accessLevel' => 'ACCOUNT_OWNER',
                'billingType' => 'LEGACY',
            ])),
            new Response(200, [], json_encode([
                'msisdn' => '9250000000',
                'balance' => 42.5,
                'bonusBalance' => 0,
                'originalBalance' => 42.5,
                'ratePlan' => [
                    'id' => '',
                    'name' => ''
                ],
                'services' => [
                    'paid' => 0,
                    'total' => 6
                ],
                'operKey' => [
                    'code' => '100',
                    'key' => 'stf',
                    'name' => 'Столичный филиал',
                ],
                'showFamily' => true,
                'showChat' => true,
                'showMfTv' => true,
                'enableRemaindersOnMain' => true,
            ]))
        ]));

        $service = new MegafonCabinetService([
            'client' => new MegafonCabinetClient(['handler' => $handler]),
            'anticaptcha' => $this->createMock(AnticaptchaInterface::class)
        ]);

        $response = $service->auth('9250000000', 'password')->wait();
        $this->assertObjectHasAttribute('name', $response);
        $this->assertEquals('Тестов Тест Иванович', $response->name);
        $this->assertEquals(1, $response->status->id);

        $response = $service->getBalance()->wait();
        $this->assertInternalType('float', $response->balance);
        $this->assertEquals(42.5, $response->balance, 'Balance error', 0.01);
    }
}