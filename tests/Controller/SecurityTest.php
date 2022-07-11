<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use App\Tests\Authorization\Auth;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

class SecurityTest extends AbstractTest
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testValidAuth(): void
    {
        $crawler = $this->login('user@mail.ru', 'user123');

        $client = self::getClient();

        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        $linkLogout = $crawler->selectLink('Выйти')->link();
        $crawler = $client->click($linkLogout);

        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        $crawler = $client->followRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());
    }

    public function testInvalidAuth(): void
    {
        $crawler = $this->login('user228@mail.ru', '12345678');


    }

    /*public function testRegister(): void
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        $auth->getBillingClient();
        //____________________________Валидные значения при регистрации____________________________
        // Переходим на страницу регистрации
        $client = static::getClient();
        $crawler = $client->request('GET', '/register');

        // Проверка статуса ответа
        $this->assertResponseOk();

        // Работа с формой
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[username]'] = 'intaro@intaro.ru';
        $form['registration[password][first]'] = 'intaro123';
        $form['registration[password][second]'] = 'intaro123';

        // Отправляем форму
        $crawler = $client->submit($form);

        // Проверяем список с ошибками (если есть)
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(0, $errors);

        // Редирект на /course/
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/course/', $client->getRequest()->getPathInfo());

        $linkLogout = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($linkLogout);
        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        // Редиректит на страницу /
        $crawler = $client->followRedirect();
        $this->assertResponseRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());
        // Редиректит на страницу /course/
        $crawler = $client->followRedirect();
        self::assertEquals('/course/', $client->getRequest()->getPathInfo());
        // Редиректит на страницу /login
        $crawler = $client->followRedirect();
        self::assertEquals('/login', $client->getRequest()->getPathInfo());

        // Переходим на страницу регистрации
        $client = static::getClient();
        $crawler = $client->request('GET', '/register');

        // Проверка статуса ответа
        $this->assertResponseOk();

        // Заполнение формы с пустыми значениями
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[username]'] = '';
        $form['registration[password][first]'] = '';
        $form['registration[password][second]'] = '';

        $crawler = $client->submit($form);

        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(2, $errors);

        // Текст ошибок
        $errorsMessage= $errors->each(function (Crawler $node) {
            return $node->text();
        });

        // Проверка сообщений
        self::assertEquals('Введите Email', $errorsMessage[0]);
        self::assertEquals('Введите пароль', $errorsMessage[1]);

        // Заполнение формы с неправильным email и коротким паролем
        $form['registration[username]'] = 'intaro';
        $form['registration[password][first]'] = '123';
        $form['registration[password][second]'] = '123';

        // Отправляем форму
        $crawler = $client->submit($form);

        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(2, $errors);

        // Получение текста ошибок
        $errorsMessage = $errors->each(function (Crawler $node, $i) {
            return $node->text();
        });

        // Проверка сообщений
        self::assertEquals('Неверно указан Email', $errorsMessage[0]);
        self::assertEquals('Ваш пароль менее 6 символов', $errorsMessage[1]);

        $form['registration[username]'] = 'intaro@intaro.ru';
        $form['registration[password][first]'] = '123456';
        $form['registration[password][second]'] = '123';

        // Отправляем форму
        $crawler = $client->submit($form);

        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(1, $errors);

        // Получение текста ошибок
        $errorsValues = $errors->each(function (Crawler $node, $i) {
            return $node->text();
        });

        // Проверка сообщений
        self::assertEquals('Пароли должны совпадать', $errorsValues[0]);
    }*/

    private function login(string $username, string $password): Crawler
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => $username,
            'password' => $password,
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        return $auth->auth($requestData);
    }
}
