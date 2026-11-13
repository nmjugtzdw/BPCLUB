<?php

declare(strict_types=1);

namespace App;

/**
 * Server-side input rules, in one place.
 *
 * Every message is written for a reader ("用户名 2–20 个字…"), never a技术术语.
 * Returning null means valid; a string is the message to show next to the field.
 * Length is counted in characters, matching what the user typed, not in bytes.
 */
final class Validation
{
    public static function username(string $value): ?string
    {
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 2 || $length > 20) {
            return '用户名需要 2 到 20 个字。';
        }
        if (preg_match('/^[\p{Han}A-Za-z0-9_]+$/u', $value) !== 1) {
            return '用户名只能用中文、字母、数字和下划线。';
        }
        return null;
    }

    public static function email(string $value): ?string
    {
        if ($value === '' || mb_strlen($value, 'UTF-8') > 191) {
            return '请填写有效的邮箱地址。';
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || !str_contains($value, '@')) {
            return '请填写有效的邮箱地址。';
        }
        return null;
    }

    public static function password(string $value): ?string
    {
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 8) {
            return '密码至少 8 位。';
        }
        // bcrypt silently truncates beyond 72 bytes; refuse instead of lying.
        if (strlen($value) > 72) {
            return '密码太长了，请控制在 72 个字符以内。';
        }
        if (preg_match('/[A-Za-z]/', $value) !== 1 || preg_match('/[0-9]/', $value) !== 1) {
            return '密码需要同时包含字母和数字。';
        }
        return null;
    }

    public static function threadTitle(string $value): ?string
    {
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 2) {
            return '标题至少 2 个字。';
        }
        if ($length > 150) {
            return '标题最多 150 个字。';
        }
        return null;
    }

    public static function threadBody(string $value): ?string
    {
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 1) {
            return '正文不能为空。';
        }
        if ($length > 20000) {
            return '正文太长了，请控制在 20000 个字以内。';
        }
        return null;
    }

    public static function commentBody(string $value): ?string
    {
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 1) {
            return '评论不能为空。';
        }
        if ($length > 2000) {
            return '评论最多 2000 个字。';
        }
        return null;
    }

    public static function reportReason(string $value): ?string
    {
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 2) {
            return '请简单说明举报原因（至少 2 个字）。';
        }
        if ($length > 500) {
            return '举报原因最多 500 个字。';
        }
        return null;
    }
}
