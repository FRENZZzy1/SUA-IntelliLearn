// Safe, escape-first Markdown renderer for chatbot replies.
// Usage: botBubble.innerHTML = renderMarkdown(data.reply);
// Keep using textContent for the USER's messages.
function renderMarkdown(text) {
  const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const inline = s => esc(s)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/`([^`]+)`/g, '<code>$1</code>');

  const lines = text.split('\n');
  let html = '', list = null, i = 0;
  const closeList = () => { if (list) { html += `</${list}>`; list = null; } };

  while (i < lines.length) {
    const line = lines[i];

    // table
    if (/^\s*\|.+\|\s*$/.test(line) && /^\s*\|[\s:\-|]+\|\s*$/.test(lines[i + 1] || '')) {
      closeList();
      const cells = l => l.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
      html += '<div class="md-table-wrap"><table><thead><tr>' +
        cells(line).map(c => `<th>${inline(c)}</th>`).join('') + '</tr></thead><tbody>';
      i += 2;
      while (i < lines.length && /^\s*\|.+\|\s*$/.test(lines[i])) {
        html += '<tr>' + cells(lines[i]).map(c => `<td>${inline(c)}</td>`).join('') + '</tr>';
        i++;
      }
      html += '</tbody></table></div>';
      continue;
    }

    let m;
    if ((m = line.match(/^\s*[-*]\s+(.*)/))) {
      if (list !== 'ul') { closeList(); html += '<ul>'; list = 'ul'; }
      html += `<li>${inline(m[1])}</li>`;
    } else if ((m = line.match(/^\s*\d+\.\s+(.*)/))) {
      if (list !== 'ol') { closeList(); html += '<ol>'; list = 'ol'; }
      html += `<li>${inline(m[1])}</li>`;
    } else if ((m = line.match(/^#{1,4}\s+(.*)/))) {
      closeList(); html += `<h4>${inline(m[1])}</h4>`;
    } else if (line.trim() === '') {
      closeList();
    } else {
      closeList(); html += `<p>${inline(line)}</p>`;
    }
    i++;
  }
  closeList();
  return html;
}
