<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use Throwable;

/**
 * 管理后台：待审内容、举报队列与处置记录。
 *
 * 每个写操作都：校验管理员身份 → 校验 CSRF → 校验目标存在 → 写库 → 记审计。
 * 审计只记操作者、动作、对象和必要上下文，从不记录密码或令牌。
 */
final class AdminController extends ForumController
{
    private const CONTENT_STATUSES = ['visible', 'hidden', 'deleted'];

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }
        $services = $this->services;
        if ($services === null || $services->writer === null) {
            return $this->unavailable();
        }

        try {
            $reports = $services->writer->openReports(50);
            $pendingThreads = $services->writer->pendingThreads(50);
            $pendingComments = $services->writer->pendingComments(50);
            $audit = $services->writer->recentAudit(30);
        } catch (Throwable $error) {
            $this->logError('admin.queue', $error);
            return $this->unavailable();
        }

        return $this->render('admin', [
            'pageTitle' => '管理后台',
            'navKey' => 'admin',
            'reports' => $reports,
            'pendingThreads' => $pendingThreads,
            'pendingComments' => $pendingComments,
            'audit' => $audit,
        ]);
    }

    /** @param array<string, string> $params */
    public function threadStatus(Request $request, array $params): Response
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }
        $services = $this->services;
        if ($services === null || $services->writer === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $id = $this->idFromParam($params);
        $status = $request->field('status', 20);
        if ($id === null) {
            return $this->notFound('帖子');
        }
        if (!in_array($status, self::CONTENT_STATUSES, true)) {
            $this->flash('error', '处置选项无效。');
            return Response::redirect('/admin');
        }

        try {
            $thread = $services->writer->threadForModeration($id);
            if ($thread === null) {
                return $this->notFound('帖子');
            }
            $services->writer->setThreadStatus($id, $status);
            $services->writer->audit((int) ($services->auth->id() ?? 0), 'thread.' . $status, 'thread', $id, [
                'previous' => (string) $thread['status'],
            ]);
        } catch (Throwable $error) {
            $this->logError('admin.thread_status', $error, ['thread' => $id]);
            return $this->unavailable();
        }

        $this->flash('ok', '帖子已处理。');
        return Response::redirect('/admin');
    }

    /** @param array<string, string> $params */
    public function commentStatus(Request $request, array $params): Response
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }
        $services = $this->services;
        if ($services === null || $services->writer === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $id = $this->idFromParam($params);
        $status = $request->field('status', 20);
        if ($id === null) {
            return $this->notFound('评论');
        }
        if (!in_array($status, self::CONTENT_STATUSES, true)) {
            $this->flash('error', '处置选项无效。');
            return Response::redirect('/admin');
        }

        try {
            $comment = $services->writer->commentForModeration($id);
            if ($comment === null) {
                return $this->notFound('评论');
            }
            $services->writer->setCommentStatus($id, $status);
            $services->writer->audit((int) ($services->auth->id() ?? 0), 'comment.' . $status, 'comment', $id, [
                'previous' => (string) $comment['status'],
            ]);
        } catch (Throwable $error) {
            $this->logError('admin.comment_status', $error, ['comment' => $id]);
            return $this->unavailable();
        }

        $this->flash('ok', '评论已处理。');
        return Response::redirect('/admin');
    }

    /** @param array<string, string> $params */
    public function report(Request $request, array $params): Response
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }
        $services = $this->services;
        if ($services === null || $services->writer === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $id = $this->idFromParam($params);
        $action = $request->field('action', 20);
        if ($id === null) {
            return $this->notFound('举报');
        }
        if (!in_array($action, ['dismiss', 'hide_target'], true)) {
            $this->flash('error', '处置选项无效。');
            return Response::redirect('/admin');
        }

        try {
            $report = null;
            foreach ($services->writer->openReports(100) as $row) {
                if ((int) $row['id'] === $id) {
                    $report = $row;
                    break;
                }
            }
            if ($report === null) {
                $this->flash('error', '这条举报已经处理过了。');
                return Response::redirect('/admin');
            }

            $actorId = (int) ($services->auth->id() ?? 0);
            if ($action === 'hide_target') {
                $type = (string) $report['target_type'];
                $targetId = (int) $report['target_id'];
                if ($type === 'thread') {
                    $services->writer->setThreadStatus($targetId, 'hidden');
                } elseif ($type === 'comment') {
                    $services->writer->setCommentStatus($targetId, 'hidden');
                }
                $services->writer->audit($actorId, 'report.hide_target', $type, $targetId, ['report' => $id]);
            }
            $services->writer->resolveReport($id, $actorId, $action === 'dismiss' ? 'dismissed' : 'resolved');
            $services->writer->audit(
                $actorId,
                $action === 'dismiss' ? 'report.dismiss' : 'report.resolve',
                'report',
                $id
            );
        } catch (Throwable $error) {
            $this->logError('admin.report', $error, ['report' => $id]);
            return $this->unavailable();
        }

        $this->flash('ok', $action === 'dismiss' ? '举报已驳回，内容保留。' : '举报已处理，内容已隐藏。');
        return Response::redirect('/admin');
    }

    /** @param array<string, string> $params */
    public function userStatus(Request $request, array $params): Response
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }
        $services = $this->services;
        if ($services === null || $services->accounts === null || $services->writer === null) {
            return $this->unavailable();
        }
        if (!$services->csrf->validate($request->post['csrf'] ?? null)) {
            return $this->rejectCsrf();
        }

        $id = $this->idFromParam($params);
        $status = $request->field('status', 20);
        if ($id === null) {
            return $this->notFound('用户');
        }
        if (!in_array($status, ['active', 'restricted', 'disabled'], true)) {
            $this->flash('error', '账号状态选项无效。');
            return Response::redirect('/admin');
        }
        // Do not let an admin lock themselves out of the only admin session.
        if ($id === (int) ($services->auth->id() ?? 0) && $status !== 'active') {
            $this->flash('error', '不能限制或停用自己的账号。');
            return Response::redirect('/admin');
        }

        try {
            $user = $services->accounts->findById($id);
            if ($user === null) {
                return $this->notFound('用户');
            }
            $services->accounts->setStatus($id, $status);
            $services->writer->audit((int) ($services->auth->id() ?? 0), 'user.' . $status, 'user', $id, [
                'previous' => (string) $user['status'],
            ]);
        } catch (Throwable $error) {
            $this->logError('admin.user_status', $error, ['user' => $id]);
            return $this->unavailable();
        }

        $this->flash('ok', '账号状态已更新。');
        return Response::redirect('/admin');
    }
}
