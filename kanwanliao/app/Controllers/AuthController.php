<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use App\Validation;
use Throwable;

/**
 * 注册 / 登录 / 退出。
 *
 * 安全取舍：
 * - 登录失败一律同一句提示，不区分「用户不存在」和「密码不对」，避免探测账号。
 * - 账号不存在时也做一次哈希校验，减少通过响应时间判断账号是否存在。
 * - 失败按来源地址和账号分别限流，脚本撞库会被挡住。
 * - 成功登录换会话 id；退出同样换 id，旧会话立即失效。
 */
final class AuthController extends ForumController
{
    /** @param array<string, string> $params */
    public function showLogin(Request $request, array $params): Response
    {
        if ($this->loggedIn()) {
            return Response::redirect('/');
        }
        if ($this->services === null || !$this->services->canAuthenticate()) {
            return $this->unavailable();
        }
        return $this->render('login', [
            'pageTitle' => '登录',
            'navKey' => 'login',
            'errors' => [],
            'old' => ['identity' => ''],
        ]);
    }

    /** @param array<string, string> $params */
    public function login(Request $request, array $params): Response
    {
        if ($this->loggedIn()) {
            return Response::redirect('/');
        }
        $services = $this->services;
        if ($services === null || !$services->canAuthenticate() || $services->accounts === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $identity = $request->field('identity', 191);
        $password = is_string($request->post['password'] ?? null) ? (string) $request->post['password'] : '';

        // Two buckets: a single address cannot spray many accounts, and a
        // single account cannot be hammered from many addresses.
        $byIp = $this->rateLimit($request, 'login_ip', $request->ip, 20, 600);
        if ($byIp !== null) {
            return $byIp;
        }
        $byAccount = $this->rateLimit($request, 'login_account', mb_strtolower($identity), 10, 900);
        if ($byAccount !== null) {
            return $byAccount;
        }

        $errors = [];
        if ($identity === '') {
            $errors['identity'] = '请填写用户名或邮箱。';
        }
        if ($password === '') {
            $errors['password'] = '请填写密码。';
        }
        if ($errors !== []) {
            return $this->render('login', [
                'pageTitle' => '登录',
                'navKey' => 'login',
                'errors' => $errors,
                'old' => ['identity' => $identity],
            ], 422);
        }

        try {
            $user = $services->accounts->findByIdentity($identity);
        } catch (Throwable $error) {
            $this->logError('auth.lookup', $error);
            return $this->unavailable();
        }

        // A dummy hash keeps the "no such account" path roughly as expensive as
        // the "wrong password" path.
        $hash = $user === null
            ? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'
            : (string) $user['password_hash'];
        $passwordOk = password_verify($password, $hash);

        if ($user === null || !$passwordOk) {
            return $this->render('login', [
                'pageTitle' => '登录',
                'navKey' => 'login',
                'errors' => ['form' => '用户名或密码不对，请再试一次。'],
                'old' => ['identity' => $identity],
            ], 422);
        }

        if ((string) $user['status'] === 'disabled') {
            return $this->render('login', [
                'pageTitle' => '登录',
                'navKey' => 'login',
                'errors' => ['form' => '这个账号目前不能登录，如有疑问请联系管理员。'],
                'old' => ['identity' => $identity],
            ], 403);
        }

        $services->auth->login((int) $user['id']);
        $services->limiter?->clear('login_account', mb_strtolower($identity));
        $this->flash('ok', '已登录，欢迎回来。');
        return Response::redirect('/');
    }

    /** @param array<string, string> $params */
    public function showRegister(Request $request, array $params): Response
    {
        if ($this->loggedIn()) {
            return Response::redirect('/');
        }
        if ($this->services === null || !$this->services->canAuthenticate()) {
            return $this->unavailable();
        }
        return $this->render('register', [
            'pageTitle' => '注册',
            'navKey' => 'register',
            'errors' => [],
            'old' => ['username' => '', 'email' => ''],
        ]);
    }

    /** @param array<string, string> $params */
    public function register(Request $request, array $params): Response
    {
        if ($this->loggedIn()) {
            return Response::redirect('/');
        }
        $services = $this->services;
        if ($services === null || !$services->canAuthenticate() || $services->accounts === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $limited = $this->rateLimit($request, 'register_ip', $request->ip, 5, 3600);
        if ($limited !== null) {
            return $limited;
        }

        $username = $request->field('username', 60);
        $email = $request->field('email', 191);
        $password = is_string($request->post['password'] ?? null) ? (string) $request->post['password'] : '';
        $confirm = is_string($request->post['password_confirm'] ?? null) ? (string) $request->post['password_confirm'] : '';

        $errors = [];
        $usernameError = Validation::username($username);
        if ($usernameError !== null) {
            $errors['username'] = $usernameError;
        }
        $emailError = Validation::email($email);
        if ($emailError !== null) {
            $errors['email'] = $emailError;
        }
        $passwordError = Validation::password($password);
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = '两次输入的密码不一致。';
        }

        if ($errors === []) {
            try {
                if ($services->accounts->existsUsername($username)) {
                    $errors['username'] = '这个用户名已经被用了，换一个试试。';
                }
                if ($services->accounts->existsEmail($email)) {
                    $errors['email'] = '这个邮箱已经注册过了。';
                }
            } catch (Throwable $error) {
                $this->logError('auth.register_check', $error);
                return $this->unavailable();
            }
        }

        if ($errors !== []) {
            return $this->render('register', [
                'pageTitle' => '注册',
                'navKey' => 'register',
                'errors' => $errors,
                'old' => ['username' => $username, 'email' => $email],
            ], 422);
        }

        try {
            $id = $services->accounts->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (Throwable $error) {
            // A duplicate can still happen if another request won the race.
            if ($error->getMessage() === 'duplicate_account') {
                return $this->render('register', [
                    'pageTitle' => '注册',
                    'navKey' => 'register',
                    'errors' => ['username' => '这个用户名或邮箱刚刚被占用了，换一个试试。'],
                    'old' => ['username' => $username, 'email' => $email],
                ], 422);
            }
            $this->logError('auth.register', $error);
            return $this->unavailable();
        }

        $services->auth->login($id);
        $this->flash('ok', '注册成功，已为你登录。');
        return Response::redirect('/');
    }

    /** @param array<string, string> $params */
    public function logout(Request $request, array $params): Response
    {
        $services = $this->services;
        if ($services === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }
        $services->auth->logout();
        $this->flash('ok', '已退出登录。');
        return Response::redirect('/');
    }
}
