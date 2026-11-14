<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use App\Validation;
use Throwable;

/**
 * 发表评论。
 *
 * 只能评论公开可见的帖子：隐藏、待审、已删除的帖子一律 404，避免通过评论
 * 接口确认某条被处置内容是否存在。
 */
final class CommentController extends ForumController
{
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

        $threadId = $this->idFromParam($params);
        if ($threadId === null) {
            return $this->notFound('帖子');
        }

        $userId = (int) ($services->auth->id() ?? 0);
        $limited = $this->rateLimit($request, 'comment_user', (string) $userId, 30, 3600);
        if ($limited !== null) {
            return $limited;
        }

        try {
            if (!$services->writer->isThreadVisible($threadId)) {
                return $this->notFound('帖子');
            }
        } catch (Throwable $error) {
            $this->logError('comment.thread_check', $error, ['thread' => $threadId]);
            return $this->unavailable();
        }

        $body = $request->field('body', 2400);
        $isSpoiler = $request->checkbox('is_spoiler');
        $error = Validation::commentBody($body);
        if ($error !== null) {
            $this->flash('error', $error);
            return Response::redirect('/thread/' . $threadId . '#comments');
        }

        $status = 'pending';
        try {
            $services->writer->createComment($threadId, $userId, $body, $isSpoiler, $status);
        } catch (Throwable $e) {
            $this->logError('comment.create', $e, ['thread' => $threadId, 'user' => $userId]);
            return $this->unavailable();
        }

        $this->flash(
            'ok',
            $status === 'pending'
                ? '已提交，这条评论需要管理员看过之后才会公开。'
                : '评论已发布。'
        );
        return Response::redirect('/thread/' . $threadId . '#comments');
    }
}
