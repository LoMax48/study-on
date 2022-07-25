<?php

namespace App\Tests\Mock;

use App\Dto\CourseDto;
use App\Dto\TransactionDto;
use App\Exception\BillingUnavailableException;
use App\Exception\ClientException;
use App\Dto\UserDto;
use App\Security\User;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class BillingClientMock extends BillingClient
{
    private UserDto $user;
    private UserDto $userAdmin;
    private array $courses;
    private array $transactions;

    public function __construct(DecodingJwt $decodingJwt, SerializerInterface $serializer)
    {
        parent::__construct($decodingJwt, $serializer);

        $this->user = new UserDto();
        $this->user->username = 'user@mail.ru';
        $this->user->password = 'user123';
        $this->user->roles = ['ROLE_USER'];
        $this->user->balance = 1000;

        $this->userAdmin = new UserDto();
        $this->userAdmin->username = 'admin@mail.ru';
        $this->userAdmin->password = 'admin123';
        $this->userAdmin->roles = ['ROLE_USER', 'ROLE_SUPER_ADMIN'];
        $this->userAdmin->balance = 9999;

        $courseTypes = [
            1 => 'rent',
            2 => 'free',
            3 => 'buy',
        ];

        $this->courses = [
            [
                'code' => 'PPBIB',
                'title' => 'Программирование на Python (базовый)',
                'type' => $courseTypes[2],
                'price' => 2000,
            ],
            [
                'code' => 'PPBI',
                'title' => 'Программирование на Python (продвинутый)',
                'type' => $courseTypes[1],
                'price' => 2000,
            ],
            [
                'code' => 'PPBI2',
                'title' => 'Программирование на Python 2',
                'type' => $courseTypes[3],
                'price' => 2000,
            ],
            [
                'code' => 'MSCB',
                'title' => 'Математическая статистика (базовый)',
                'type' => $courseTypes[2],
                'price' => 1000,
            ],
            [
                'code' => 'MSC',
                'title' => 'Математическая статистика',
                'type' => $courseTypes[3],
                'price' => 1000,
            ],
            [
                'code' => 'CAMPB',
                'title' => 'Курс подготовки вожатых (базовый)',
                'type' => $courseTypes[2],
                'price' => 3000,
            ],
            [
                'code' => 'CAMP',
                'title' => 'Курс подготовки вожатых (продвинутый)',
                'type' => $courseTypes[1],
                'price' => 3000,
            ],
        ];

        $transactionTypes = [
            1 => 'payment',
            2 => 'deposit',
        ];

        $this->transactions = [
            [
                'type' => $transactionTypes[2],
                'amount' => 10000,
                'operation_time' => new \DateTimeImmutable('2022-06-01 00:00:00'),
            ],
            [
                'type' => $transactionTypes[1],
                'amount' => 1000,
                'operation_time' => new \DateTimeImmutable('2022-06-05 00:00:00'),
                'course_code' => 'MSC',
            ],
            [
                'type' => $transactionTypes[1],
                'amount' => 1000,
                'operation_time' => new \DateTimeImmutable('2022-06-08 00:00:00'),
                'course_code' => 'PPBI',
                'expires_time' => (
                    new \DateTimeImmutable('2022-06-08 00:00:00'))->add(new \DateInterval('P1W')),
            ],
            [
                'type' => $transactionTypes[1],
                'amount' => 1000,
                'operation_time' => new \DateTimeImmutable(),
                'course_code' => 'PPBI',
                'expires_time' => (new \DateTimeImmutable())->add(new \DateInterval('P1W'))
            ],
        ];

        $this->user->balance = $this->transactions[0]['amount'] -
            ($this->transactions[1]['amount'] + $this->transactions[2]['amount'] + $this->transactions[3]['amount']);
    }

    public function auth($request): User
    {
        $userDto = $this->serializer->deserialize($request, UserDto::class, 'json');

        if (
            $userDto->username === $this->user->username &&
            $userDto->password === $this->user->password
        ) {
            $userDto->token = $this->generateToken('ROLE_USER', $this->user->username);
            $userDto->roles = ['ROLE_USER'];
            $userDto->refreshToken = '911';

            return User::fromDto($userDto, $this->decodingJwt);
        }
        if (
            $userDto->username === $this->userAdmin->username &&
            $userDto->password === $this->userAdmin->password
        ) {
            $userDto->token = $this->generateToken('ROLE_SUPER_ADMIN', $this->userAdmin->username);
            $userDto->roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'];
            $userDto->refreshToken = '911';

            return User::fromDto($userDto, $this->decodingJwt);
        }
        throw new BillingUnavailableException('Проверьте правильность введёного логина и пароля');
    }

    public function register(UserDto $user): UserDto
    {
        if (
            $user->username === $this->user->username ||
            $user->username === $this->userAdmin->username
        ) {
            throw new ClientException('Данный пользователь уже существует');
        }
        $token = $this->generateToken('ROLE_USER', $user->username);
        $user->token = $token;
        $user->balance = 0;
        $user->roles = ["ROLE_USER"];
        $user->refreshToken = '912';
        return $user;
    }

    public function generateToken(string $role, string $username): string
    {
        $roles = null;
        if ($role === 'ROLE_USER') {
            $roles = ['ROLE_USER'];
        } elseif ($role === 'ROLE_SUPER_ADMIN') {
            $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'];
        }
        $data = [
            'username' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];
        $query = base64_encode(json_encode($data));
        return 'header.' . $query . '.signature';
    }

    public function getCurrentUser(User $user, DecodingJwt $decodingJwt)
    {
        $decodingJwt->decode($user->getApiToken());
        if ($decodingJwt->getUsername() === $this->user->username) {
            $data = [
                'username' => $decodingJwt->getUsername() ,
                'roles' => $decodingJwt->getRoles(),
                'balance' => $this->user->balance,
            ];
            return $this->serializer->serialize($data, 'json');
        }
        if ($decodingJwt->getUsername() === $this->userAdmin->username) {
            $data = [
                'username' => $decodingJwt->getUsername(),
                'roles' => $decodingJwt->getRoles(),
                'balance' => $this->userAdmin->balance,
            ];
            return $this->serializer->serialize($data, 'json');
        }

        $data = [
            'username' => $decodingJwt->getUsername(),
            'roles' => $decodingJwt->getRoles(),
            'balance' => 0,
        ];
        return $this->serializer->serialize($data, 'json');
    }

    public function getAllCourses(): array
    {
        $coursesDto = [];
        foreach ($this->courses as $course) {
            $courseDto = new CourseDto();

            $courseDto->code = $course['code'];
            $courseDto->type = $course['type'];
            $courseDto->price = $course['price'];
            $courseDto->title = $course['title'];

            $coursesDto[] = $courseDto;
        }

        return $coursesDto;
    }

    public function transactionHistory(User $user, string $request = ''): array
    {
        if (!$user) {
            throw new AccessDeniedException();
        }

        if ($request === '') {
            $decodingJwt = new DecodingJwt();
            $decodingJwt->decode($user->getApiToken());

            if ($decodingJwt->getUsername() === $this->user->username) {
                return $this->transactions;
            }

            if ($decodingJwt->getUsername() === $this->userAdmin->username) {
                return [];
            }
        }

        $requestFilters = explode('&', $request);

        $filters = [];
        foreach ($requestFilters as $requestFilter) {
            $temp = explode('=', $requestFilter);

            $filters[$temp[0]] = $temp[1];
        }

        $filteredTransactions = $this->transactions;

        if (isset($filters['type'])) {
            $filteredTransactions = array_filter($filteredTransactions, function ($transaction) use ($filters) {
                return $transaction['type'] === $filters['type'];
            });
        }
        if (isset($filters['course_code'])) {
            $filteredTransactions = array_filter($filteredTransactions, function ($transaction) use ($filters) {
                return $transaction['course_code'] === $filters['course_code'];
            });
        }
        if (isset($filters['skip_expired'])) {
            $filteredTransactions = array_filter($filteredTransactions, function ($transaction) use ($filters) {
                return !isset($transaction['expires_time']) || $transaction['expires_time'] > new \DateTimeImmutable();
            });
        }

        $responseTransactions = [];
        foreach ($filteredTransactions as $filteredTransaction) {
            $newTransaction = new TransactionDto();

            $newTransaction->type = $filteredTransaction['type'];
            $newTransaction->amount = $filteredTransaction['amount'];
            $newTransaction->course = $filteredTransaction['course_code'];
            $newTransaction->operationTime = $filteredTransaction['operation_time']->format('Y-m-d H:i:s');
            if (isset($filteredTransaction['expires_time'])) {
                $newTransaction->expiresTime = $filteredTransaction['expires_time']->format('Y-m-d H:i:s');
            }

            $responseTransactions[] = $newTransaction;
        }

        return $responseTransactions;
    }

    public function getCourse(string $courseCode): CourseDto
    {
        $targetCourse = null;
        foreach ($this->courses as $course) {
            if ($course['code'] === $courseCode) {
                $targetCourse = $course;
            }
        }

        if (!$targetCourse) {
            throw new BillingUnavailableException('Данный курс не найден');
        }

        $courseDto = new CourseDto();
        $courseDto->code = $targetCourse['code'];
        $courseDto->type = $targetCourse['type'];
        $courseDto->price = $targetCourse['price'];
        $courseDto->title = $targetCourse['title'];

        return $courseDto;
    }

    public function newCourse(User $user, CourseDto $courseDto): array
    {
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw new AccessDeniedException();
        }

        if (in_array($courseDto->code, array_column($this->courses, 'code'), true)) {
            throw new BillingUnavailableException('Курс с данным кодом уже существует');
        }

        $newCourse = [
            'code' => $courseDto->code,
            'title' => $courseDto->title,
            'type' => $courseDto->type,
            'price' => $courseDto->price,
        ];

        return ['success' => true];
    }

    public function editCourse(User $user, string $courseCode, CourseDto $courseDto): array
    {
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw new AccessDeniedException();
        }

        if (!in_array($courseCode, array_column($this->courses, 'code'), true)) {
            throw new BillingUnavailableException('Курс с данным кодом не найден');
        }

        foreach ($this->courses as &$course) {
            if ($course['code'] === $courseCode) {
                $course['code'] = $courseDto->code;
                $course['type'] = $courseDto->type;
                $course['title'] = $courseDto->title;
                $course['price'] = $courseDto->price;
            }
        }

        return ['success' => true];
    }
}
