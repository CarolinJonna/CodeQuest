<?php
// ============================================================
//  CodeQuest — Progress API
//  File: api/progress.php
//  Handles: save_xp, complete_chapter, save_lang, get_progress,
//           save_quiz, save_profile, leaderboard, activity
// ============================================================

require_once __DIR__ . '/../config.php';
startSession();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$raw    = file_get_contents('php://input');
$body   = $raw ? (json_decode($raw, true) ?? []) : [];

switch ($action) {

    // ─────────────────────────────────────────────────────────
    // GET FULL USER PROGRESS
    // ─────────────────────────────────────────────────────────
    case 'get': {
        $uid = requireAuth();
        $db  = getDB();

        $user = $db->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$uid]);
        $u = $user->fetch();

        // Chapter completions grouped by lesson key
        $chStmt = $db->prepare('SELECT lesson_key, chapter_num, xp_earned FROM chapter_completions WHERE user_id = ? ORDER BY lesson_key, chapter_num');
        $chStmt->execute([$uid]);
        $chapters = [];
        foreach ($chStmt->fetchAll() as $row) {
            $chapters[$row['lesson_key']][] = (int)$row['chapter_num'];
        }

        // Recent activity (last 10)
        $actStmt = $db->prepare('SELECT action_type, description, xp_change, logged_at FROM activity_log WHERE user_id = ? ORDER BY logged_at DESC LIMIT 10');
        $actStmt->execute([$uid]);
        $activity = $actStmt->fetchAll();

        jsonResponse([
            'success'  => true,
            'xp'       => (int)$u['xp'],
            'streak'   => (int)$u['streak'],
            'gems'     => (int)$u['gems'],
            'lessons'  => (int)$u['lessons_done'],
            'progress' => (int)$u['progress'],
            'lang'     => $u['selected_lang'] ?: 'Python',
            'user'     => [
                'displayName' => $u['display_name'] ?: $u['full_name'],
                'username'    => $u['username'],
            ],
            'chapters' => $chapters,
            'activity' => $activity,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // COMPLETE A CHAPTER
    // ─────────────────────────────────────────────────────────
    case 'complete_chapter': {
        $uid        = requireAuth();
        $lesson_key = $body['lesson_key'] ?? '';   // e.g. 'python1' or 'java1'
        $chapter    = (int)($body['chapter'] ?? 0);
        $xp         = (int)($body['xp'] ?? 0);

        if (!$lesson_key || !$chapter) {
            jsonResponse(['success' => false, 'message' => 'lesson_key and chapter are required.']);
        }

        $db = getDB();

        // INSERT OR IGNORE (don't double-count XP)
        $stmt = $db->prepare('
            INSERT IGNORE INTO chapter_completions (user_id, lesson_key, chapter_num, xp_earned)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$uid, $lesson_key, $chapter, $xp]);
        $newRow = $stmt->rowCount() > 0; // 1 if newly inserted, 0 if duplicate

        if ($newRow && $xp > 0) {
            $db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?')->execute([$xp, $uid]);
            $db->prepare('INSERT INTO activity_log (user_id, action_type, description, xp_change) VALUES (?, "chapter", ?, ?)')
               ->execute([$uid, "Completed {$lesson_key} Ch{$chapter}", $xp]);
        }

        // If final chapter (5) of a lesson → mark lesson done & update progress
        if ($chapter === 5 && $newRow) {
            // Count how many chapters completed in this lesson
            $countStmt = $db->prepare('SELECT COUNT(*) as cnt FROM chapter_completions WHERE user_id = ? AND lesson_key = ?');
            $countStmt->execute([$uid, $lesson_key]);
            $cnt = (int)$countStmt->fetch()['cnt'];

            if ($cnt >= 5) {
                $db->prepare('UPDATE users SET lessons_done = lessons_done + 1, progress = GREATEST(progress, 1), streak = streak + 1 WHERE id = ?')
                   ->execute([$uid]);
                $db->prepare('INSERT INTO activity_log (user_id, action_type, description) VALUES (?, "lesson", "Completed lesson: ??")')
                   ->execute([$uid, $lesson_key]);
            }
        }

        // Return fresh xp
        $fresh = $db->prepare('SELECT xp, streak, lessons_done, progress FROM users WHERE id = ?');
        $fresh->execute([$uid]);
        $f = $fresh->fetch();

        jsonResponse([
            'success'  => true,
            'credited' => $newRow,
            'xp'       => (int)$f['xp'],
            'streak'   => (int)$f['streak'],
            'lessons'  => (int)$f['lessons_done'],
            'progress' => (int)$f['progress'],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // SAVE LANGUAGE SELECTION
    // ─────────────────────────────────────────────────────────
    case 'save_lang': {
        $uid  = requireAuth();
        $lang = $body['lang'] ?? '';
        if (!$lang) jsonResponse(['success' => false, 'message' => 'lang is required.']);

        getDB()->prepare('UPDATE users SET selected_lang = ? WHERE id = ?')->execute([$lang, $uid]);
        jsonResponse(['success' => true, 'lang' => $lang]);
    }

    // ─────────────────────────────────────────────────────────
    // SAVE QUIZ SCORE
    // ─────────────────────────────────────────────────────────
    case 'save_quiz': {
        $uid   = requireAuth();
        $score = (int)($body['score'] ?? 0);
        $total = (int)($body['total'] ?? 5);
        $xp    = $score * 10;

        $db = getDB();
        $db->prepare('INSERT INTO quiz_scores (user_id, score, total, xp_earned) VALUES (?, ?, ?, ?)')
           ->execute([$uid, $score, $total, $xp]);

        if ($xp > 0) {
            $db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?')->execute([$xp, $uid]);
            $db->prepare('INSERT INTO activity_log (user_id, action_type, description, xp_change) VALUES (?, "quiz", ?, ?)')
               ->execute([$uid, "Quiz: {$score}/{$total}", $xp]);
        }

        $fresh = $db->prepare('SELECT xp FROM users WHERE id = ?');
        $fresh->execute([$uid]);
        jsonResponse(['success' => true, 'xp' => (int)$fresh->fetch()['xp']]);
    }

    // ─────────────────────────────────────────────────────────
    // SAVE PROFILE (display name, username, password)
    // ─────────────────────────────────────────────────────────
    case 'save_profile': {
        $uid         = requireAuth();
        $displayName = trim($body['display_name'] ?? '');
        $username    = trim($body['username'] ?? '');
        $currentPw   = $body['current_password'] ?? '';
        $newPw       = $body['new_password'] ?? '';

        $db = getDB();

        if ($newPw) {
            // Verify current password
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($currentPw, $row['password'])) {
                jsonResponse(['success' => false, 'message' => 'Current password is incorrect.']);
            }
            $newHash = password_hash($newPw, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, $uid]);
        }

        $db->prepare('UPDATE users SET display_name = ?, username = ? WHERE id = ?')
           ->execute([$displayName ?: null, $username ?: null, $uid]);

        jsonResponse(['success' => true, 'message' => 'Profile saved!']);
    }

    // ─────────────────────────────────────────────────────────
    // LEADERBOARD (top 10 by XP)
    // ─────────────────────────────────────────────────────────
    case 'leaderboard': {
        $uid = requireAuth();
        $db  = getDB();

        $stmt = $db->prepare('
            SELECT id, COALESCE(display_name, full_name) AS display_name, username, xp, streak
            FROM users
            ORDER BY xp DESC
            LIMIT 10
        ');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $ranked = [];
        foreach ($rows as $i => $r) {
            $ranked[] = [
                'rank'         => $i + 1,
                'id'           => (int)$r['id'],
                'display_name' => $r['display_name'],
                'username'     => $r['username'],
                'xp'           => (int)$r['xp'],
                'streak'       => (int)$r['streak'],
                'is_me'        => ((int)$r['id'] === $uid),
            ];
        }

        // Also find current user's rank if not in top 10
        $myRankStmt = $db->prepare('SELECT COUNT(*)+1 as rank_pos FROM users WHERE xp > (SELECT xp FROM users WHERE id = ?)');
        $myRankStmt->execute([$uid]);
        $myRank = (int)$myRankStmt->fetch()['rank_pos'];

        jsonResponse(['success' => true, 'leaderboard' => $ranked, 'my_rank' => $myRank]);
    }

    // ─────────────────────────────────────────────────────────
    // ACTIVITY LOG
    // ─────────────────────────────────────────────────────────
    case 'activity': {
        $uid  = requireAuth();
        $stmt = getDB()->prepare('SELECT action_type, description, xp_change, logged_at FROM activity_log WHERE user_id = ? ORDER BY logged_at DESC LIMIT 15');
        $stmt->execute([$uid]);
        jsonResponse(['success' => true, 'activity' => $stmt->fetchAll()]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
}
