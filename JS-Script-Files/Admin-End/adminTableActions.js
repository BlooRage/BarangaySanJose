(() => {
  'use strict';

  const style = document.createElement('style');
  style.textContent = `
    .admin-table-action-dropdown{display:inline-block;white-space:nowrap}
    .admin-table-action-dropdown>.dropdown-toggle{min-width:92px}
    .admin-table-action-dropdown .dropdown-menu{--bs-dropdown-link-hover-bg:#f8fafc;--bs-dropdown-link-hover-color:#0f172a;--bs-dropdown-link-active-bg:#f8fafc;--bs-dropdown-link-active-color:#0f172a;min-width:12rem;padding:.4rem;border:1px solid #e2e8f0;border-radius:.65rem;box-shadow:0 .5rem 1.25rem rgba(15,23,42,.14);z-index:2060}
    .admin-table-action-dropdown .dropdown-item{display:flex;align-items:center;width:100%;gap:.45rem;margin:0!important;padding:.55rem .7rem;border:0;border-radius:.4rem;background:transparent;color:#1f2937;font-size:.875rem;text-align:left;text-decoration:none;white-space:nowrap}
    .admin-table-action-dropdown .dropdown-item:hover,.admin-table-action-dropdown .dropdown-item:focus,.admin-table-action-dropdown .dropdown-item:active,.admin-table-action-dropdown .dropdown-item.active{background:#f8fafc!important;color:#0f172a!important}
    .admin-table-action-dropdown .dropdown-item.action-effect-view{color:#1f2937;background:transparent}
    .admin-table-action-dropdown .dropdown-item.action-effect-success{color:#087443;background:transparent}
    .admin-table-action-dropdown .dropdown-item.action-effect-warning{color:#9a5708;background:transparent}
    .admin-table-action-dropdown .dropdown-item.action-effect-danger{color:#b42318;background:transparent}
    .admin-table-action-dropdown .dropdown-item.action-effect-neutral{color:#667085;background:transparent}
    .admin-table-action-dropdown .dropdown-item.action-effect-view:hover,.admin-table-action-dropdown .dropdown-item.action-effect-view:focus,.admin-table-action-dropdown .dropdown-item.action-effect-view:active,.admin-table-action-dropdown .dropdown-item.action-effect-view.active{background:#f8fafc!important;color:#0f172a!important}
    .admin-table-action-dropdown .dropdown-item.action-effect-success:hover,.admin-table-action-dropdown .dropdown-item.action-effect-success:focus,.admin-table-action-dropdown .dropdown-item.action-effect-success:active,.admin-table-action-dropdown .dropdown-item.action-effect-success.active{background:#f8fafc!important;color:#087443!important}
    .admin-table-action-dropdown .dropdown-item.action-effect-warning:hover,.admin-table-action-dropdown .dropdown-item.action-effect-warning:focus,.admin-table-action-dropdown .dropdown-item.action-effect-warning:active,.admin-table-action-dropdown .dropdown-item.action-effect-warning.active{background:#f8fafc!important;color:#9a5708!important}
    .admin-table-action-dropdown .dropdown-item.action-effect-danger:hover,.admin-table-action-dropdown .dropdown-item.action-effect-danger:focus,.admin-table-action-dropdown .dropdown-item.action-effect-danger:active,.admin-table-action-dropdown .dropdown-item.action-effect-danger.active{background:#f8fafc!important;color:#b42318!important}
    .admin-table-action-dropdown .dropdown-item.action-effect-neutral:hover,.admin-table-action-dropdown .dropdown-item.action-effect-neutral:focus,.admin-table-action-dropdown .dropdown-item.action-effect-neutral:active,.admin-table-action-dropdown .dropdown-item.action-effect-neutral.active{background:#f8fafc!important;color:#667085!important}
    .admin-table-action-dropdown .dropdown-item+.dropdown-item,.admin-table-action-dropdown form+.dropdown-item,.admin-table-action-dropdown .dropdown-item+form{margin-top:.25rem!important}
    .admin-table-action-dropdown .dropdown-item:disabled,.admin-table-action-dropdown .dropdown-item.disabled{opacity:.55;pointer-events:none}
    .admin-table-action-dropdown .dropdown-item .admin-action-icon{width:1.15rem;text-align:center}
    .admin-table-action-dropdown form{display:block!important;width:100%;margin:0!important;padding:0!important}
    .tracker-action-dropdown .dropdown-menu{--bs-dropdown-link-hover-bg:#f8fafc;--bs-dropdown-link-hover-color:#0f172a;--bs-dropdown-link-active-bg:#f8fafc;--bs-dropdown-link-active-color:#0f172a;z-index:2060}
    .tracker-action-dropdown .dropdown-item,.tracker-action-dropdown .dropdown-item.action-effect-view{background-color:transparent!important;color:#1f2937!important}
    .tracker-action-dropdown .dropdown-menu .dropdown-item:hover,.tracker-action-dropdown .dropdown-menu .dropdown-item:focus,.tracker-action-dropdown .dropdown-menu .dropdown-item:active,.tracker-action-dropdown .dropdown-menu .dropdown-item.active{background-color:#f8fafc!important;color:#0f172a!important}
    body #main-display .table-responsive .dropdown-menu,body main .table-responsive .dropdown-menu{--bs-dropdown-link-hover-bg:#f8fafc;--bs-dropdown-link-hover-color:#0f172a;--bs-dropdown-link-active-bg:#f8fafc;--bs-dropdown-link-active-color:#0f172a;z-index:2060}
    body #main-display .table-responsive .dropdown-menu .dropdown-item:focus,body #main-display .table-responsive .dropdown-menu .dropdown-item:active,body #main-display .table-responsive .dropdown-menu .dropdown-item.active,body main .table-responsive .dropdown-menu .dropdown-item:focus,body main .table-responsive .dropdown-menu .dropdown-item:active,body main .table-responsive .dropdown-menu .dropdown-item.active{background:#f8fafc!important;color:#0f172a!important}
    body>.admin-table-action-menu-portal{--bs-dropdown-link-hover-bg:#f8fafc;--bs-dropdown-link-hover-color:#0f172a;--bs-dropdown-link-active-bg:#f8fafc;--bs-dropdown-link-active-color:#0f172a;position:fixed!important;min-width:12rem;margin:0!important;padding:.4rem;border:1px solid #e2e8f0;border-radius:.65rem;box-shadow:0 .5rem 1.25rem rgba(15,23,42,.14);z-index:2060}
    body>.admin-table-action-menu-portal .dropdown-item{display:flex;align-items:center;width:100%;gap:.45rem;margin:0!important;padding:.55rem .7rem;border:0;border-radius:.4rem;background:transparent;font-size:.875rem;text-align:left;text-decoration:none;white-space:nowrap}
    body>.admin-table-action-menu-portal .dropdown-item:hover,body>.admin-table-action-menu-portal .dropdown-item:focus,body>.admin-table-action-menu-portal .dropdown-item:active{background:#f8fafc!important}
    body>.admin-table-action-menu-portal form{display:block!important;width:100%;margin:0!important;padding:0!important}
    body>.admin-table-action-menu-portal .dropdown-item+.dropdown-item,body>.admin-table-action-menu-portal form+.dropdown-item,body>.admin-table-action-menu-portal .dropdown-item+form{margin-top:.25rem!important}
  `;
  document.head.appendChild(style);

  const normalizedHeading = value => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
  const isActionHeading = value => ['action', 'actions'].includes(normalizedHeading(value));

  function actionPresentation(control, originalClasses = []) {
    const descriptor = [
      control.textContent,
      control.getAttribute('title'),
      control.getAttribute('aria-label'),
      control.getAttribute('data-action'),
      control.getAttribute('data-view-action'),
      control.getAttribute('data-inline-action')
    ].filter(Boolean).join(' ').toLowerCase();
    const hasClass = pattern => originalClasses.some(name => pattern.test(name));
    if (control.disabled || control.classList.contains('disabled') || /\bunavailable\b|\bno action/.test(descriptor)) {
      return ['neutral', 'fa-ban'];
    }
    if (hasClass(/^btn-(?:outline-)?danger$/) || /delete|remove|reject|deny|decline|fail|deactivate|revoke|void|block/.test(descriptor)) {
      return ['danger', /reject|deny|decline|fail/.test(descriptor) ? 'fa-circle-xmark' : 'fa-trash-can'];
    }
    if (hasClass(/^btn-(?:outline-)?success$/) || /approve|verify|accept|activate|restore|release|complete|confirm|pass|save/.test(descriptor)) {
      return ['success', /restore/.test(descriptor) ? 'fa-rotate-left' : 'fa-circle-check'];
    }
    if (hasClass(/^btn-(?:outline-)?warning$/) || /archive|edit|update|reset|resend|retry|transition|transfer|assign/.test(descriptor)) {
      return ['warning', /archive/.test(descriptor) ? 'fa-box-archive' : (/edit|update/.test(descriptor) ? 'fa-pen' : 'fa-rotate')];
    }
    if (/view|open|details|preview|document|profile|history|inspect/.test(descriptor)) return ['view', 'fa-eye'];
    if (/download|export/.test(descriptor)) return ['view', 'fa-download'];
    if (/print/.test(descriptor)) return ['view', 'fa-print'];
    return ['neutral', 'fa-ellipsis'];
  }

  function decorateAction(control, originalClasses = Array.from(control.classList)) {
    if (!control || control.dataset.actionDecorated === '1') return;
    const [effect, icon] = actionPresentation(control, originalClasses);
    control.classList.add(`action-effect-${effect}`);
    if (!control.querySelector('i,svg,.spinner-border,.spinner-grow')) {
      const iconElement = document.createElement('i');
      iconElement.className = `fas ${icon} admin-action-icon`;
      iconElement.setAttribute('aria-hidden', 'true');
      control.prepend(iconElement);
    }
    control.dataset.actionDecorated = '1';
  }

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
      const originalClasses = Array.from(control.classList);
      control.className = Array.from(control.classList)
        .filter(name => name !== 'btn'
          && name !== 'btn-sm'
          && !/^btn-(?:primary|secondary|success|danger|warning|info|light|dark|link|outline-.+)$/.test(name)
          && name !== 'me-1' && name !== 'me-2' && name !== 'ms-1' && name !== 'ms-2')
        .join(' ');
      control.classList.add('dropdown-item');
      decorateAction(control, originalClasses);
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
        actionIndexes.forEach(index => {
          const cell = row.cells[index];
          convertCell(cell);
          cell?.querySelectorAll('.admin-table-action-dropdown .dropdown-item,.tracker-action-dropdown .dropdown-item').forEach(item => decorateAction(item));
        });
      });
    });
  }

  function processAll(root = document) {
    if (root instanceof HTMLTableElement) processTable(root);
    root.querySelectorAll?.('table').forEach(processTable);
    const containingTable = root instanceof Element ? root.closest('table') : null;
    if (containingTable) processTable(containingTable);
    prepareTableDropdowns(root);
  }

  function isTableDropdown(dropdown) {
    return dropdown instanceof Element
      && dropdown.matches('.admin-table-action-dropdown,.tracker-action-dropdown,.compact-admin-table-shell .dropdown,.table-responsive .dropdown');
  }

  function tableDropdownFromToggle(toggle) {
    const dropdown = toggle?.closest?.('.dropdown');
    return isTableDropdown(dropdown) ? dropdown : null;
  }

  function prepareDropdown(dropdown) {
    const toggle = dropdown?.querySelector?.('[data-bs-toggle="dropdown"]');
    if (!toggle) return;
    if (!toggle.hasAttribute('data-bs-boundary')) toggle.setAttribute('data-bs-boundary', 'viewport');
    if (!toggle.hasAttribute('data-bs-offset')) toggle.setAttribute('data-bs-offset', '0,6');
  }

  function prepareTableDropdowns(root = document) {
    if (root instanceof Element && isTableDropdown(root)) prepareDropdown(root);
    root.querySelectorAll?.('.admin-table-action-dropdown,.tracker-action-dropdown,.compact-admin-table-shell .dropdown,.table-responsive .dropdown').forEach(prepareDropdown);
  }

  function portalDropdownMenu(dropdown) {
    const toggle = dropdown?.querySelector?.('[data-bs-toggle="dropdown"]');
    const menu = dropdown?.querySelector?.('.dropdown-menu');
    if (!toggle || !menu || menu.parentElement === document.body) return;

    dropdown._adminActionMenu = menu;
    menu.classList.add('admin-table-action-menu-portal');
    document.body.appendChild(menu);

    const toggleRect = toggle.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const gutter = 8;
    const left = Math.max(gutter, Math.min(toggleRect.right - menuRect.width, window.innerWidth - menuRect.width - gutter));
    const fitsBelow = toggleRect.bottom + menuRect.height + gutter <= window.innerHeight;
    const top = fitsBelow
      ? toggleRect.bottom + 6
      : Math.max(gutter, toggleRect.top - menuRect.height - 6);

    menu.style.inset = 'auto';
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
    menu.style.transform = 'none';
  }

  function restoreDropdownMenu(dropdown) {
    const menu = dropdown?._adminActionMenu;
    if (!menu) return;
    menu.classList.remove('admin-table-action-menu-portal');
    menu.removeAttribute('style');
    dropdown.appendChild(menu);
    delete dropdown._adminActionMenu;
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
      const dropdown = tableDropdownFromToggle(event.target);
      if (!dropdown) return;
      prepareDropdown(dropdown);
    });
    document.addEventListener('shown.bs.dropdown', event => {
      const dropdown = tableDropdownFromToggle(event.target);
      if (!dropdown) return;
      portalDropdownMenu(dropdown);
    });
    document.addEventListener('hidden.bs.dropdown', event => {
      const dropdown = tableDropdownFromToggle(event.target);
      if (!dropdown) return;
      restoreDropdownMenu(dropdown);
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
