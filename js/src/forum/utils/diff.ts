export interface DiffLine {
  type: 'add' | 'del' | 'eq' | 'fold';
  text?: string;
}

/**
 * Line-level diff (LCS) between two blocks of text. Returns a sequence of
 * lines tagged added / removed / unchanged, suitable for a unified-diff view.
 */
export function lineDiff(oldStr: string, newStr: string): DiffLine[] {
  const a = oldStr ? oldStr.split('\n') : [];
  const b = newStr ? newStr.split('\n') : [];
  const n = a.length;
  const m = b.length;

  // Longest-common-subsequence table (small inputs -- article revisions).
  const dp: number[][] = Array.from({ length: n + 1 }, () => new Array(m + 1).fill(0));
  for (let i = n - 1; i >= 0; i--) {
    for (let j = m - 1; j >= 0; j--) {
      dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
    }
  }

  const out: DiffLine[] = [];
  let i = 0;
  let j = 0;
  while (i < n && j < m) {
    if (a[i] === b[j]) {
      out.push({ type: 'eq', text: a[i] });
      i++;
      j++;
    } else if (dp[i + 1][j] >= dp[i][j + 1]) {
      out.push({ type: 'del', text: a[i] });
      i++;
    } else {
      out.push({ type: 'add', text: b[j] });
      j++;
    }
  }
  while (i < n) out.push({ type: 'del', text: a[i++] });
  while (j < m) out.push({ type: 'add', text: b[j++] });

  return out;
}

/** True when the diff contains any add/del lines. */
export function hasChanges(lines: DiffLine[]): boolean {
  return lines.some((l) => l.type === 'add' || l.type === 'del');
}

/**
 * Collapse long runs of unchanged lines, keeping `ctx` lines of context around
 * each change. Collapsed runs become a single `fold` marker.
 */
export function foldContext(lines: DiffLine[], ctx: number = 2): DiffLine[] {
  const keep = new Array(lines.length).fill(false);
  lines.forEach((l, i) => {
    if (l.type !== 'eq') {
      for (let k = Math.max(0, i - ctx); k <= Math.min(lines.length - 1, i + ctx); k++) keep[k] = true;
    }
  });

  const out: DiffLine[] = [];
  let folded = false;
  for (let i = 0; i < lines.length; i++) {
    if (keep[i]) {
      out.push(lines[i]);
      folded = false;
    } else if (!folded) {
      out.push({ type: 'fold' });
      folded = true;
    }
  }
  return out;
}
