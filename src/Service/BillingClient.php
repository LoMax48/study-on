<?php

namespace App\Service;

use App\Dto\CourseDto;
use App\Dto\PayDto;
use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use App\Exception\ClientException;
use App\Security\User;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpClient\CurlHttpClient;

class BillingClient
{
    private string $startUri;
    private DecodingJwt $decodingJwt;
    private SerializerInterface $serializer;

    public function __construct(DecodingJwt $decodingJwt, SerializerInterface $serializer)
    {
        $this->startUri = $_ENV['BILLING'];
        $this->decodingJwt = $decodingJwt;
        $this->serializer = $serializer;
    }

    public function refreshToken(string $refreshToken): UserDto
    {
        $query = curl_init($this->startUri . '/api/v1/token/refresh');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $refreshToken);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($refreshToken)
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте авторизоваться позднее');
        }

        return $this->serializer->deserialize($response, UserDto::class, 'json');
    }

    public function auth(string $request): User
    {
        $query = curl_init($this->startUri . '/api/v1/auth');
        curl_setopt($query, CURLOPT_POST, true);
        curl_setopt($query, CURLOPT_POSTFIELDS, $request);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($request),
        ]);

        $authResponse = curl_exec($query);

        if (!$authResponse) {
            throw new BillingUnavailableException(
                'Сервис временно недоступен. Попробуйте авторизоваться позднее.'
            );
        }
        curl_close($query);

        $result = json_decode($authResponse, true);
        if (isset($result['code']) && $result['code'] === 401) {
            throw new BillingUnavailableException('Проверьте корректность введённых данных.');
        }

        $this->decodingJwt->decode($result['token']);

        $user = new User();
        $user->setEmail($this->decodingJwt->getUsername());
        $user->setApiToken($result['token']);
        $user->setRoles($this->decodingJwt->getRoles());

        return $user;
    }

    public function register(UserDto $user): UserDto
    {
        $client = new CurlHttpClient();
        $serializedData = $this->serializer->serialize($user, 'json');

        $query = curl_init($this->startUri . '/api/v1/register');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $serializedData);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($serializedData),
        ]);
        $registerResponse = curl_exec($query);

        if (!$registerResponse) {
            throw new BillingUnavailableException(
                'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.'
            );
        }

        $result = json_decode($registerResponse, true, 512, JSON_THROW_ON_ERROR);

        if (isset($result['code'])) {
            if ($result['code'] === 403) {
                throw new ClientException($result['message']);
            }

            throw new BillingUnavailableException(
                'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.'
            );
        }

        $userDto = $this->serializer->deserialize($registerResponse, UserDto::class, 'json');

        return $userDto;
    }

    public function getCurrentUser(User $user, DecodingJwt $decodingJwt)
    {
        $decodingJwt->decode($user->getApiToken());

        $query = curl_init($this->startUri . '/api/v1/users/current');
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException(
                'Сервис временно недоступен. Повторите попытку позднее.'
            );
        }

        $result = json_decode($response, true);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $response;
    }

    public function getAllCourses(): array
    {
        $query = curl_init($this->startUri . '/api/v1/courses');
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($query);
        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, 'array<App\Dto\CourseDto>', 'json');
    }

    public function transactionHistory(User $user, string $request = ''): array
    {
        $query = curl_init($this->startUri . '/api/v1/transactions/?' . $request);
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. Попробуйте позднее');
        }
        curl_close($query);

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, 'array<App\Dto\TransactionDto>', 'json');
    }

    public function getCourse(string $courseCode): CourseDto
    {
        $query = curl_init($this->startUri . '/api/v1/courses/' . $courseCode);
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. Попробуйте позднее');
        }
        curl_close($query);

        return $this->serializer->deserialize($response, CourseDto::class, 'json');
    }

    public function paymentCourse(User $user, string $codeCourse): PayDto
    {
        $query = curl_init($this->startUri . '/api/v1/courses/' . $codeCourse . '/pay');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, PayDto::class, 'json');
    }

    public function newCourse(User $user, CourseDto $courseDto): array
    {
        $response = $this->serializer->serialize($courseDto, 'json');

        $query = curl_init($this->startUri . '/api/v1/courses/new');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $response);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken(),
            'Content-Length: ' . strlen($response)
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] !== 201) {
            throw new BillingUnavailableException($result['message']);
        }

        return $result;
    }

    public function editCourse(User $user, string $courseCode, CourseDto $courseDto): array
    {
        $response = $this->serializer->serialize($courseDto, 'json');

        $query = curl_init($this->startUri . '/api/v1/courses/' . $courseCode . '/edit');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $response);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken(),
            'Content-Length: ' . strlen($response)
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] !== 200) {
            throw new BillingUnavailableException($result['message']);
        }

        return $result;
    }
}
