<?php

namespace unapi\gsm\megafon;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use unapi\anticaptcha\common\AnticaptchaInterface;
use unapi\anticaptcha\common\dto\CaptchaSolvedInterface;
use unapi\gsm\megafon\exceptions\MegafonRuntimeException;
use unapi\interfaces\ServiceInterface;
use unapi\gsm\megafon\exceptions\MegafonUnathorizedException;

use function GuzzleHttp\json_decode;

class MegafonCabinetService implements ServiceInterface, LoggerAwareInterface
{
    /** @var ClientInterface */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var AnticaptchaInterface */
    private $anticaptcha;

    /**
     * @param array $config Service configuration settings.
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['client'])) {
            $this->client = new MegafonCabinetClient();
        } elseif ($config['client'] instanceof ClientInterface) {
            $this->client = $config['client'];
        } else {
            throw new \InvalidArgumentException('Client must be instance of ClientInterface');
        }

        if (!isset($config['logger'])) {
            $this->logger = new NullLogger();
        } elseif ($config['logger'] instanceof LoggerInterface) {
            $this->setLogger($config['logger']);
        } else {
            throw new \InvalidArgumentException('Logger must be instance of LoggerInterface');
        }

        if (!isset($config['anticaptcha'])) {
            throw new \InvalidArgumentException('Anticaptcha required');
        } elseif ($config['anticaptcha'] instanceof AnticaptchaInterface) {
            $this->anticaptcha = $config['anticaptcha'];
        } else {
            throw new \InvalidArgumentException('Anticaptcha must be instance of AnticaptchaInterface');
        }
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @return AnticaptchaInterface
     */
    public function getAnticaptcha(): AnticaptchaInterface
    {
        return $this->anticaptcha;
    }

    /**
     * @param string $login
     * @param string $password
     * @param string $captcha
     * @return PromiseInterface
     */
    public function auth(string $login, string $password, string $captcha = ''): PromiseInterface
    {
        return $this->getClient()->requestAsync('POST', '/mlk/login', [
            'form_params' => array_filter([
                'login' => $login,
                'password' => $password,
                'captcha' => $captcha,
            ])
        ])->then(function (ResponseInterface $response) use ($login, $password) {
            $this->getLogger()->info($data = $response->getBody()->getContents());
            $answer = json_decode($data);
            if (isset($answer->code) && !isset($answer->msisdn)) {
                if (($answer->code == 'a211') || ($answer->code == 'a212')) {
                    $this->getLogger()->info('Solving captcha.');
                    return $this->getAnticaptcha()->getAnticaptchaPromise($this->getClient(), $response)
                        ->then(function (CaptchaSolvedInterface $solved) use ($login, $password) {
                            return $this->auth($login, $password, $solved->getCode());
                        });
                } else {
                    throw new MegafonUnathorizedException($data, $response->getStatusCode());
                }
            }

            return new FulfilledPromise($answer);
        });
    }

    /**
     * @return PromiseInterface
     */
    public function getBalance(): PromiseInterface
    {
        return $this->getClient()->requestAsync('GET', '/mlk/api/main/info')
            ->then(function (ResponseInterface $response) {
                $this->getLogger()->info($data = $response->getBody()->getContents());
                $answer = json_decode($data);
                if (isset($answer->balance))
                    return new FulfilledPromise($answer);

                throw new MegafonRuntimeException('Balance not found', $response->getStatusCode());
            });
    }

    /**
     * @return PromiseInterface
     */
    public function getServices(): PromiseInterface
    {
        return $this->getClient()->requestAsync('GET', '/mlk/api/options/list/current')
            ->then(function (ResponseInterface $response) {
                $this->getLogger()->info($data = $response->getBody()->getContents());
                $answer = json_decode($data);
                return new FulfilledPromise($answer);
            });
    }

    /**
     * @param string $serviceId
     * @return PromiseInterface
     */
    public function disableService(string $serviceId): PromiseInterface
    {
        return $this->getClient()->requestAsync('DELETE', '/mlk/api/options/' . $serviceId)
            ->then(function () {
                return new FulfilledPromise(true);
            });
    }

    /**
     * @param $serviceId
     * @return PromiseInterface
     */
    public function enableService(string $serviceId): PromiseInterface
    {
        return $this->getClient()->requestAsync('POST', '/mlk/api/options/' . $serviceId)
            ->then(function () {
                return new FulfilledPromise(true);
            });
    }

    /**
     * @param string $currentPassword
     * @param string $newPassword
     * @return PromiseInterface
     */
    public function changePassword(string $currentPassword, string $newPassword): PromiseInterface
    {
        return $this->getClient()->requestAsync('POST', '/mlk/api/profile/password', [
            'form_params' => [
                'currentPassword' => $currentPassword,
                'newPassword' => $newPassword,
            ]
        ])->then(function (ResponseInterface $response) {
            $this->getLogger()->info($data = $response->getBody()->getContents());
            $answer = json_decode($data);

            if (isset($answer->ok) && $answer->ok)
                return new FulfilledPromise(true);

            if (isset($answer->message))
                return new RejectedPromise($answer->message);

            return new RejectedPromise('Unknown error');
        });
    }

    /**
     * @return PromiseInterface
     */
    public function getTariffs(): PromiseInterface
    {
        return $this->getClient()->requestAsync('GET', '/mlk/api/tariff/list')
            ->then(function (ResponseInterface $response) {
                $this->getLogger()->info($data = $response->getBody()->getContents());
                $answer = json_decode($data);
                return new FulfilledPromise($answer);
            });
    }

    /**
     * @param string $tariffId
     * @return PromiseInterface
     */
    public function getTariff(string $tariffId): PromiseInterface
    {
        return $this->getClient()->requestAsync('GET', '/mlk/api/tariff/' . $tariffId)
            ->then(function (ResponseInterface $response) {
                $this->getLogger()->info($data = $response->getBody()->getContents());
                $answer = json_decode($data);
                return new FulfilledPromise($answer);
            });
    }

    /**
     * @param string $tariffId
     * @return PromiseInterface
     */
    public function changeTariff(string $tariffId): PromiseInterface
    {
        return $this->getClient()->requestAsync('POST', '/mlk/api/tariff/current', [
            'query' => [
                'tariffId' => $tariffId,
            ]
        ])->then(function (ResponseInterface $response) {
            $this->getLogger()->info($data = $response->getBody()->getContents());
            $answer = json_decode($data);

            if ($response->getStatusCode() === 200)
                return new FulfilledPromise(true);

            if (isset($answer->message))
                return new RejectedPromise($answer->message);

            return new RejectedPromise('Unknown error');
        });
    }
}