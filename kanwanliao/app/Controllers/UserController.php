<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use Throwable;

/**
 * 用户公开主页：只展示公开可见的帖子，不展示邮箱、状态等非公开信息。
 */
final class UserController extends ForumController
{
    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->idFromParam($params);
        if ($id === null) {
            return $this->notFound('用户');
        }
        if ($this->reader === null) {
            return $this->unavailable();
        }

        try {
            $profile = $this->reader->userProfile($id);
            if ($profile === null) {
                return $this->notFound('用户');
            }
            $total = $this->reader->countThreadsByAuthor($id);
            $pager = $this->pagerFor($this->pageFrom($request), 20, $total);
            $rows = $this->reader->threadsByAuthor($id, $pager->page, $pager->pageSize);
        } catch (Throwable $error) {
            $this->logError('user.profile', $error, ['user' => $id]);
            return $this->unavailable();
        }

        return $this->render('user', [
            'pageTitle' => (string) $profile['username'],
            'navKey' => 'user',
            'profile' => $profile,
            'threadTotal' => $total,
            'threads' => $this->decorateThreads($rows),
            'pager' => $pager,
        ]);
    }
}
