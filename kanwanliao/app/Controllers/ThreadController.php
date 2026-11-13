<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Category;
use App\Request;
use App\Response;
use App\Spoiler;
use Throwable;

final class ThreadController extends ForumController
{
    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->idFrom($params);
        if ($id === null) {
            return $this->notFound('帖子');
        }
        if ($this->reader === null) {
            return $this->unavailable();
        }

        // Revealing spoilers is an explicit reader action carried by the URL.
        // It is never the default, and the query layer still refuses to return
        // the body for a spoiler thread unless this flag is set.
        $revealed = ($request->query['reveal'] ?? '') === '1';

        try {
            $thread = $this->reader->thread($id, $revealed);
            if ($thread === null) {
                return $this->notFound('帖子');
            }
            $comments = $this->reader->comments($id, $revealed);
            $commentTotal = $this->reader->countComments($id);
        } catch (Throwable $error) {
            $this->logger->error('thread.read', ['type' => get_class($error), 'thread' => $id]);
            return $this->unavailable();
        }

        $isSpoiler = (int) $thread['is_spoiler'] === 1;
        $body = (string) ($thread['body'] ?? '');
        $showBody = $body !== '';

        $commentRows = [];
        foreach ($comments as $comment) {
            $commentSpoiler = (int) $comment['is_spoiler'] === 1;
            $commentRows[] = [
                'id' => (int) $comment['id'],
                'author_name' => (string) $comment['author_name'],
                'created_at' => (string) $comment['created_at'],
                'is_spoiler' => $commentSpoiler,
                'text' => $commentSpoiler && !$revealed
                    ? Spoiler::NOTICE
                    : (string) ($comment['body'] ?? ''),
                'hidden' => $commentSpoiler && !$revealed,
            ];
        }

        return $this->render('thread', [
            'pageTitle' => (string) $thread['public_title'],
            // Share metadata must never carry a spoiler body.
            'metaDescription' => Spoiler::shareDescription($body, $isSpoiler),
            'navKey' => 'thread',
            'thread' => $thread,
            'categoryName' => Category::name((string) $thread['category']),
            'isSpoiler' => $isSpoiler,
            'revealed' => $revealed,
            'showBody' => $showBody,
            'bodyText' => $showBody ? $body : '',
            'notice' => Spoiler::NOTICE,
            'comments' => $commentRows,
            'commentTotal' => $commentTotal,
        ]);
    }

    /** @param array<string, string> $params */
    private function idFrom(array $params): ?int
    {
        $raw = (string) ($params['id'] ?? '');
        if (preg_match('/^[1-9][0-9]{0,9}$/', $raw) !== 1) {
            return null;
        }
        return (int) $raw;
    }
}
