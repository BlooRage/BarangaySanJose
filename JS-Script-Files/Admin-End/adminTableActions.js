(() => {
  'use strict';

  const style = document.createElement('style');
  style.textContent = `
    .admin-table-action-dropdown{display:inline-block;white-space:nowrap}
    .admin-table-action-dropdown>.dropdown-toggle{min-width:92px}
    .admin-table-action-dropdown .dropdown-menu{min-width:12rem;padding:.4rem;border:1px solid #e2e8f0;border-radius:.65rem;box-shadow:0 .5rem 1.25rem rgba(15,23,42,.14)}
    .admin-table-action-dropdown .dropdown-item{display:flex;align-items:center;width:100%;gap:.45rem;margin:0!important;padding:.55rem .7rem;border:0;border-radius:.4rem;background:transparent;color:#1f2937;font-size:.875rem;text-align:left;text-decoration:none;white-space:nowrap}
    .admin-table-action-dropdown .dropdown-item:hover,.admin-table-action-dropdown .dropdown-item:focus{background:#f1f5f9;color:#0f172a}
    .admin-table-action-dropdown .dropdown-item:active{background:#0d6efd;color:#fff}
    .admin-table-action-dropdown form{display:block!important;width:100%;margin:0!important;padding:0!important}
  `;
  document.head.appendChild(style);

  const normalizedHeading = value => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
  const isActionHeading = value => ['action', 'actions'].includes(normalizedHeading(value));

  function actionableNode(control, cell) {
    const form = control.closest('form');
    return form && cell.contains(form) ? form : control;
  }

  function collectActions(cell) {
    const seen = new Set();
    const actions = [];
    cell.querySelectorAll('a.btn,button.btn,input[type="button"].btn,input[type="submit"].btn').forEach(control => {
      if (control.closest('.dropdown-menu,.admin-table-action-dropdown,.tracker-action-dropdown')) return;
      if (control.matches('[data-bs-toggle="dropdown"]')) return;
      const node = actionableNode(control, cell);
      if (seen.has(node)) return;
      seen.add(node);
      actions.push({ node, control });
    });
    return actions;
  }

  function convertCell(cell) {
    if (!cell || cell.querySelector('.admin-table-action-dropdown,.tracker-action-dropdown')) return;
    const actions = collectActions(cell);
    if (actions.length < 2) return;

    const dropdown = document.createElement('div');
    dropdown.className = 'dropdown admin-table-action-dropdown';
    dropdown.innerHTML = '<button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button><div class="dropdown-menu dropdown-menu-end"></div>';
    const menu = dropdown.querySelector('.dropdown-menu');

    actions.forEach(({ node, control }) => {
      control.className = Array.from(control.classList)
        .filter(name => name !== 'btn'
          && name !== 'btn-sm'
          && !/^btn-(?:primary|secondary|success|danger|warning|info|light|dark|link|outline-.+)$/.test(name)
          && name !== 'me-1' && name !== 'me-2' && name !== 'ms-1' && name !== 'ms-2')
        .join(' ');
      control.classList.add('dropdown-item');
      if (node !== control) {
        node.classList.add('admin-table-action-form');
      }
      menu.appendChild(node);
    });

    Array.from(cell.children).forEach(child => {
      if (child !== dropdown && child.children.length === 0 && !String(child.textContent || '').trim()) child.remove();
    });
    cell.appendChild(dropdown);
  }

  function processTable(table) {
    if (!table || table.dataset.adminActionDropdown === 'off') return;
    const headingRows = Array.from(table.tHead?.rows || []);
    const headingRow = headingRows[headingRows.length - 1];
    if (!headingRow) return;
    const actionIndexes = Array.from(headingRow.cells).reduce((indexes, heading, index) => {
      if (isActionHeading(heading.textContent)) indexes.push(index);
      return indexes;
    }, []);
    if (!actionIndexes.length) return;
    Array.from(table.tBodies || []).forEach(body => {
      Array.from(body.rows || []).forEach(row => {
        actionIndexes.forEach(index => convertCell(row.cells[index]));
      });
    });
  }

  function processAll(root = document) {
    if (root instanceof HTMLTableElement) processTable(root);
    root.querySelectorAll?.('table').forEach(processTable);
    const containingTable = root instanceof Element ? root.closest('table') : null;
    if (containingTable) processTable(containingTable);
  }

  const start = () => {
    processAll();
    let queued = false;
    const observer = new MutationObserver(records => {
      if (records.every(record => Array.from(record.addedNodes).every(node => node instanceof Element && node.closest?.('.admin-table-action-dropdown')))) return;
      if (queued) return;
      queued = true;
      requestAnimationFrame(() => {
        queued = false;
        records.forEach(record => processAll(record.target));
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('show.bs.dropdown', event => {
      const responsive = event.target?.closest?.('.table-responsive');
      if (!responsive) return;
      responsive.dataset.actionOverflow = responsive.style.overflow || '';
      responsive.style.overflow = 'visible';
    });
    document.addEventListener('hidden.bs.dropdown', event => {
      const responsive = event.target?.closest?.('.table-responsive');
      if (!responsive) return;
      responsive.style.overflow = responsive.dataset.actionOverflow || '';
      delete responsive.dataset.actionOverflow;
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
