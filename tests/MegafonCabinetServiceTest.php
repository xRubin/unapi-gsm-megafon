<?php

use PHPUnit\Framework\TestCase;
use unapi\gsm\megafon\MegafonCabinetClient;
use unapi\gsm\megafon\MegafonCabinetService;
use unapi\anticaptcha\common\AnticaptchaInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;

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

    public function testServices()
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'msisdn' => '9250000000',
            ])),
            new Response(200, [], json_encode([
                'free' => [
                    [
                        'optionName' => 'MMS',
                        'optionId' => '21031',
                        'optionType' => 'service',
                        'status' => '1',
                        'fees' => ['0 ₽ в месяц'],
                        'monthRate' => 0,
                        'dailyMonthRate' => '0',
                        'daylyMonthRate' => '0',
                        'closeMode' => '1',
                        'activateMode' => '1',
                        'turnOnchargeRate' => 0,
                        'operDate' => '08.08.2017 12:21:53',
                        'monthly' => false,
                        'canReActivate' => false,
                        'activationCount' => 0,
                        'group' => 'Сообщения',
                        'groupId' => 'message',
                        'roamingGroupOrder' => 1000,
                        'link' => 'https://www.megafon.ru/ad/l_mms_lk',
                        'shortDescription' => 'MMS — это мультимедийные сообщения в вашем телефоне.',
                        'subGroup' => 'MMS',
                        'mainOption' => '0',
                        'order' => 2147483647,
                        'subCategoryOrder' => 2,
                        'activatedInFuture' => false,
                        'orderedOffOptionInFuture' => false,
                    ]
                ],
            ]))
        ]));

        $service = new MegafonCabinetService([
            'client' => new MegafonCabinetClient(['handler' => $handler]),
            'anticaptcha' => $this->createMock(AnticaptchaInterface::class)
        ]);

        $service->auth('9250000000', 'password')->wait();

        $response = $service->getServices()->wait();
        $this->assertInternalType('array', $response->free);
    }
}