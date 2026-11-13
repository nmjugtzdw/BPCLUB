<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use App\Validation;
use Throwable;

/**
 * 举报帖子或评论。
 *
 * 同一账号对同一对象只保留一条未处理举报，重复提交不刷队列。举报本身不改动
 * 被举报内容——处置权在管理员手上，避免被举报即消失。
 */
final class ReportController extends ForumController
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

        $userId = (int) ($services->auth->id() ?? 0);
        $limited = $this->rateLimit($request, 'report_user', (string) $userId, 10, 3600);
        if ($limited !== null) {
            return $limited;
        }

        $targetType = $request->field('target_type', 20);
        $targetIdRaw = $request->field('target_id', 20);
        $reason = $request->field('reason', 600);

        $backTo = '/';
        if (!in_array($targetType, ['thread', 'comment'], true)) {
            $this->flash('error', '举报对象无效。');
            return Response::redirect('/');
        }
        if (preg_match('/^[1-9][0-9]{0,9}$/', $targetIdRaw) !== 1) {
            $this->flash('error', '举报对象无效。');
            return Response::redirect('/');
        }
        $targetId = (int) $targetIdRaw;

        // Where to send the reader back to, and whether the target is real.
        try {
            if ($targetType === 'thread') {
                $backTo = '/thread/' . $targetId;
                if ($this->reader?->thread($targetId) === null) {
                    return $this->notFound('帖子');
                }
            } else {
                $comment = $services->writer->commentForModeration($targetId);
                if ($comment === null || (string) $comment['status'] !== 'visible') {
                    return $this->notFound('评论');
                }
                $backTo = '/thread/' . (int) $comment['thread_id'];
            }
        } catch (Throwable $error) {
            $this->logError('report.target_check', $error);
            return $this->unavailable();
        }

        $reasonError = Validation::reportReason($reason);
        if ($reasonError !== null) {
            $this->flash('error', $reasonError);
            return Response::redirect($backTo);
        }

        try {
            if ($services->writer->hasOpenReport($userId, $targetType, $targetId)) {
                $this->flash('ok', '你已经举报过了，管理员会尽快处理。');
                return Response::redirect($backTo);
            }
            $services->writer->createReport($userId, $targetType, $targetId, $reason);
        } catch (Throwable $error) {
            $this->logError('report.create', $error, ['target_type' => $targetType, 'target_id' => $targetId]);
            return $this->unavailable();
        }

        $this->flash('ok', '已收到举报，管理员会尽快查看。');
        return Response::redirect($backTo);
    }
}
