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

    public function testAuth(): void
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => 'user@mail.ru',
            'password' => 'user123',
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        $crawler = $auth->auth($requestData);

        $client = self::getClient();

        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        $linkLogout = $crawler->selectLink('Выйти')->link();
        $crawler = $client->click($linkLogout);

        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        $crawler = $client->followRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());

        $linkLogin = $crawler->selectLink('Вход')->link();
        $crawler = $client->click($linkLogin);

        self::assertEquals('/login', $client->getRequest()->getPathInfo());

        $invalidUser = [
            'username' => 'user228@mail.ru',
            'password' => 'user123',
        ];

        $requestData = $this->serializer->serialize($invalidUser, 'json');
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $requestData = json_decode($requestData, true);

        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = $requestData['username'];
        $form['password'] = $requestData['password'];
        $client->submit($form);

        self::assertFalse($client->getResponse()->isRedirect('/courses/'));
        $crawler = $client->followRedirect();

        $error = $crawler->filter('#errors');
        self::assertEquals('Проверьте правильность введёного логина и пароля', $error->text());
    }

    public function testRegister(): void
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        $auth->getBillingClient();

        $client = self::getClient();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseOk();

        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[username]'] = 'newuser@mail.ru';
        $form['registration[password][first]'] = 'user123';
        $form['registration[password][second]'] = 'user123';

        $crawler = $client->submit($form);

        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(0, $errors);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        $linkLogout = $crawler->selectLink('Выйти')->link();
        $crawler = $client->click($linkLogout);
        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        $crawler = $client->followRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());

        $client = static::getClient();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseOk();

        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[username]'] = '';
        $form['registration[password][first]'] = '';
        $form['registration[password][second]'] = '';

        $crawler = $client->submit($form);

        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(2, $errors);

        $errorsMessage = $errors->each(function (Crawler $node) {
            return $node->text();
        });

        self::assertEquals('Введите Email', $errorsMessage[0]);
        self::assertEquals('Введите пароль', $errorsMessage[1]);

        $form['registration[username]'] = 'intaro';
        $form['registration[password][first]'] = '123';
        $form['registration[password][second]'] = '123';

        $crawler = $client->submit($form);

        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(2, $errors);

        $errorsMessage = $errors->each(function (Crawler $node, $i) {
            return $node->text();
        });

        self::assertEquals('Неверно указан Email', $errorsMessage[0]);
        self::assertEquals('Ваш пароль менее 6 символов', $errorsMessage[1]);

        $form['registration[username]'] = 'intaro@intaro.ru';
        $form['registration[password][first]'] = '123456';
        $form['registration[password][second]'] = '123';

        $crawler = $client->submit($form);

        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(1, $errors);

        $errorsValues = $errors->each(function (Crawler $node, $i) {
            return $node->text();
        });

        self::assertEquals('Пароли должны совпадать', $errorsValues[0]);
    }
}
