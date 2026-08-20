<div class="mb-3">
  <label for="f_sql">SQL Query</label>
  <textarea class="form-control" id="f_sql" rows="8" style="font-family:monospace;font-size:.85rem;" placeholder="select id, name from users where status = 'active' order by created_at desc"></textarea>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Format SQL</button>
<script>
const tpSqlKeywords = ['SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'ORDER BY', 'GROUP BY', 'HAVING', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'ON', 'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE FROM', 'LIMIT', 'OFFSET', 'AS', 'DISTINCT', 'UNION'];
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    let sql = document.getElementById('f_sql').value.trim();
    if (!sql) throw new Error('Please paste a SQL query to format.');

    sql = sql.replace(/\s+/g, ' ');
    const breakBefore = ['FROM', 'WHERE', 'AND', 'OR', 'ORDER BY', 'GROUP BY', 'HAVING', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'LIMIT', 'VALUES', 'SET'];
    breakBefore.forEach((kw) => {
      const re = new RegExp('\\s+(' + kw.replace(' ', '\\s+') + ')\\b', 'gi');
      sql = sql.replace(re, '\n' + kw);
    });
    tpSqlKeywords.forEach((kw) => {
      const re = new RegExp('\\b' + kw.replace(' ', '\\s+') + '\\b', 'gi');
      sql = sql.replace(re, kw);
    });

    document.getElementById('f_sql').value = sql;
    tpShowResult('Formatted ✓', { label: 'Result', raw: sql });
  } catch (e) { tpShowError(e.message); }
});
</script>
