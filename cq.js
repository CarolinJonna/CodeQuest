/**
 * CodeQuest — Shared Backend Helper (cq.js)
 * Drop this ONE file next to all HTML pages.
 * Replaces the old localStorage-only CQ object with real API calls.
 * Also keeps localStorage as a fast-read cache so the UI feels instant.
 */

const CQ = {
  // ── Local cache helpers ──────────────────────────────────
  _get: (k, d) => { try { const v = localStorage.getItem('cq_' + k); return v !== null ? JSON.parse(v) : d; } catch { return d; } },
  _set: (k, v) => localStorage.setItem('cq_' + k, JSON.stringify(v)),

  getXP:       () => CQ._get('xp', 0),
  getStreak:   () => CQ._get('streak', 0),
  getGems:     () => CQ._get('gems', 0),
  getProgress: () => CQ._get('progress', 0),
  getLessons:  () => CQ._get('lessons', 0),
  getUser:     () => CQ._get('user', { displayName: 'Coder', username: 'coder01' }),
  getCompCh:   (l) => CQ._get('ch_' + l, []),

  // ── Sync full progress from server ──────────────────────
  async syncFromServer() {
    try {
      const res  = await fetch('api/progress.php?action=get');
      const data = await res.json();
      if (!data.success) return false;

      CQ._set('xp',       data.xp);
      CQ._set('streak',   data.streak);
      CQ._set('gems',     data.gems);
      CQ._set('lessons',  data.lessons);
      CQ._set('progress', data.progress);
      CQ._set('lang',     data.lang);
      CQ._set('user', { displayName: data.user.displayName, username: data.user.username });

      // Chapter completions
      for (const [key, chapters] of Object.entries(data.chapters || {})) {
        CQ._set('ch_' + key, chapters);
      }
      return data;
    } catch (e) {
      console.warn('CQ.syncFromServer failed — offline mode', e);
      return false;
    }
  },

  // ── Complete a chapter (saves to DB + updates cache) ────
  async completeChapter(lessonKey, chapterNum, xp) {
    // Optimistic local update
    CQ._set('xp', CQ.getXP() + xp);
    const arr = CQ.getCompCh(lessonKey);
    if (!arr.includes(chapterNum)) arr.push(chapterNum);
    CQ._set('ch_' + lessonKey, arr);

    try {
      const res  = await fetch('api/progress.php?action=complete_chapter', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lesson_key: lessonKey, chapter: chapterNum, xp })
      });
      const data = await res.json();
      if (data.success) {
        // Authoritative values from server
        CQ._set('xp',       data.xp);
        CQ._set('streak',   data.streak);
        CQ._set('lessons',  data.lessons);
        CQ._set('progress', data.progress);
      }
      return data;
    } catch (e) {
      console.warn('completeChapter API failed — offline only', e);
      return { success: false };
    }
  },

  // ── Save quiz score ──────────────────────────────────────
  async saveQuiz(score, total) {
    const xpEarned = score * 10;
    CQ._set('xp', CQ.getXP() + xpEarned);
    try {
      const res  = await fetch('api/progress.php?action=save_quiz', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ score, total })
      });
      const data = await res.json();
      if (data.success) CQ._set('xp', data.xp);
    } catch (e) { console.warn('saveQuiz API failed', e); }
  },

  // ── Save language selection ──────────────────────────────
  async saveLang(lang) {
    CQ._set('lang', lang);
    localStorage.setItem('cq_lang', lang);
    try {
      await fetch('api/progress.php?action=save_lang', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lang })
      });
    } catch (e) { console.warn('saveLang API failed', e); }
  },

  // ── Save profile ─────────────────────────────────────────
  async saveProfile(displayName, username, currentPassword, newPassword) {
    try {
      const res  = await fetch('api/progress.php?action=save_profile', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ display_name: displayName, username, current_password: currentPassword, new_password: newPassword })
      });
      const data = await res.json();
      if (data.success) {
        const user = CQ.getUser();
        user.displayName = displayName;
        user.username    = username;
        CQ._set('user', user);
      }
      return data;
    } catch (e) {
      return { success: false, message: 'Server error' };
    }
  },

  // ── Fetch leaderboard ────────────────────────────────────
  async getLeaderboard() {
    try {
      const res  = await fetch('api/progress.php?action=leaderboard');
      const data = await res.json();
      return data.success ? data : null;
    } catch (e) { return null; }
  },

  // ── Fetch activity ───────────────────────────────────────
  async getActivity() {
    try {
      const res  = await fetch('api/progress.php?action=activity');
      const data = await res.json();
      return data.success ? data.activity : [];
    } catch (e) { return []; }
  },

  // ── Logout ───────────────────────────────────────────────
  async logout() {
    try { await fetch('api/auth.php?action=logout'); } catch(e) {}
    localStorage.clear();
    window.location.href = 'welcome.html';
  },

  // ── Auth guard — redirect to welcome if not logged in ───
  async requireAuth() {
    try {
      const res  = await fetch('api/auth.php?action=me');
      const data = await res.json();
      if (!data.success) { window.location.href = 'welcome.html'; return false; }
      // Refresh cache with latest server values
      CQ._set('xp',       data.user.xp);
      CQ._set('streak',   data.user.streak);
      CQ._set('gems',     data.user.gems);
      CQ._set('lessons',  data.user.lessons_done);
      CQ._set('progress', data.user.progress);
      CQ._set('user', { displayName: data.user.displayName, username: data.user.username });
      CQ._set('lang',     data.user.selected_lang);
      localStorage.setItem('cq_lang', data.user.selected_lang);
      return true;
    } catch (e) {
      // If server is unreachable, fall through (offline mode)
      return false;
    }
  }
};
