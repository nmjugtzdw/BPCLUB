<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Category;
use App\Request;
use App\Response;
use App\Validation;
use Throwable;

/**
 * 发布新帖。
 *
 * 可见性策略（明确定义并测试）：普通账号发布后立即可见（先发布后审核）；
 * 受限账号发布的内容进入待审核，管理员放行后才公开。理由是最先把「有人愿意
 * 发第一帖」跑通，同时保留对受限账号的强制前置审核。
 */
final class PublishController extends ForumController
{
    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $needLogin = $this->requireLogin();
        if ($needLogin !== null) {
            return $needLogin;
        }
        $titles = [];
        if ($this->reader !== null) {
            try {
                $titles = $this->reader->titles('', 1, 100);
            } catch (Throwable $error) {
                $this->logError('publish.titles', $error);
            }
        }
        return $this->render('publish', [
            'pageTitle' => '发布讨论',
            'navKey' => 'publish',
            'categories' => Category::all(),
            'workOptions' => $titles,
            'errors' => [],
            'old' => ['category' => '', 'title' => '', 'body' => '', 'title_id' => '', 'is_spoiler' => ''],
        ]);
    }

    /** @param array<string, string> $params */
    public function create(Request $request, array $params): Response
    {
        $services = $this->services;
        if ($services === null || !$services->canWrite() || $services->writer === null) {
            return $this->unavailable();
        }
        if (!$this->loggedIn()) {
            return Response::redirect('/login');
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $userId = (int) ($services->auth->id() ?? 0);
        $limited = $this->rateLimit($request, 'publish_user', (string) $userId, 10, 3600);
        if ($limited !== null) {
            return $limited;
        }

        $category = $request->field('category', 20);
        $title = $request->field('title', 200);
        $body = $request->field('body', 24000);
        $titleIdRaw = $request->field('title_id', 20);
        $isSpoiler = $request->checkbox('is_spoiler');

        $errors = [];
        if (!Category::exists($category)) {
            $errors['category'] = '请选择一个分类。';
        }
        $titleError = Validation::threadTitle($title);
        if ($titleError !== null) {
            $errors['title'] = $titleError;
        }
        $bodyError = Validation::threadBody($body);
        if ($bodyError !== null) {
            $errors['body'] = $bodyError;
        }

        $titleId = null;
        if ($titleIdRaw !== '') {
            if (preg_match('/^[1-9][0-9]{0,9}$/', $titleIdRaw) !== 1) {
                $errors['title_id'] = '关联作品的选择无效。';
            } else {
                $titleId = (int) $titleIdRaw;
                try {
                    if ($this->reader?->title($titleId) === null) {
                        $errors['title_id'] = '没有找到这部作品，请重新选择。';
                    }
                } catch (Throwable $error) {
                    $this->logError('publish.title_lookup', $error);
                    $errors['title_id'] = '作品信息暂时读不出来，请稍后再试。';
                }
            }
        }

        if ($errors !== []) {
            return $this->renderPublishForm($errors, [
                'category' => $category,
                'title' => $title,
                'body' => $body,
                'title_id' => $titleIdRaw,
                'is_spoiler' => $isSpoiler ? '1' : '',
            ]);
        }

        $status = $services->auth->isRestricted() ? 'pending' : 'visible';
        try {
            $id = $services->writer->createThread(
                $userId,
                $category,
                $title,
                $body,
                $titleId,
                $isSpoiler,
                $status
            );
        } catch (Throwable $error) {
            $this->logError('publish.create', $error, ['user' => $userId]);
            return $this->unavailable();
        }

        if ($status === 'pending') {
            $this->flash('ok', '已提交，因为账号状态受限，这条讨论需要管理员看过之后才会公开。');
            return Response::redirect('/');
        }

        $this->flash('ok', '已发布。');
        return Response::redirect('/thread/' . $id);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function renderPublishForm(array $errors, array $old): Response
    {
        $titles = [];
        if ($this->reader !== null) {
            try {
                $titles = $this->reader->titles('', 1, 100);
            } catch (Throwable $error) {
                $this->logError('publish.titles', $error);
            }
        }
        return $this->render('publish', [
            'pageTitle' => '发布讨论',
            'navKey' => 'publish',
            'categories' => Category::all(),
            'workOptions' => $titles,
            'errors' => $errors,
            'old' => $old,
        ], 422);
    }
}
