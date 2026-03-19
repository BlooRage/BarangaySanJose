(() => {
  const mainDisplay = document.getElementById('main-display');
  if (!mainDisplay) return;

  const endpoint = mainDisplay.dataset.endpoint || '../../PhpFiles/Admin-End/areaStatisticsData.php';
  const fixedScope = mainDisplay.dataset.pageScope || 'barangay';
  const moduleSelect = document.getElementById('moduleSelect');
  const dateFromInput = document.getElementById('dateFrom');
  const dateToInput = document.getElementById('dateTo');
  const statusSelect = document.getElementById('statusSelect');
  const resetBtn = document.querySelector('.area-reset-btn');
  const applyBtn = document.getElementById('btnApplyAreaFilters');
  const widgetList = document.getElementById('widgetCustomizerList');
  const resetWidgetBtn = document.getElementById('btnResetWidgetLayout');
  const widgetNodes = Array.from(document.querySelectorAll('[data-widget]'));
  const widgetStorageKey = `area_widget_layout:${fixedScope.replace(/\s+/g, '_').toLowerCase()}:${window.location.pathname.toLowerCase()}`;

  const chartPalette = {
    orange: '#de710c',
    amber: '#f59f00',
    sky: '#1c7ed6',
    emerald: '#2f9e44',
    rose: '#e03131',
    grid: 'rgba(32, 35, 41, 0.08)'
  };

  Chart.defaults.font.family = "'Geist', sans-serif";
  Chart.defaults.color = '#495057';

  function defaultDateRange() {
    const now = new Date();
    const to = now.toISOString().slice(0, 10);
    const fromDate = new Date(now.getFullYear(), now.getMonth() - 5, 1);
    const from = fromDate.toISOString().slice(0, 10);
    return { from, to };
  }

  function setDefaults() {
    const defaults = defaultDateRange();
    if (dateFromInput && !dateFromInput.value) dateFromInput.value = defaults.from;
    if (dateToInput && !dateToInput.value) dateToInput.value = defaults.to;
    if (moduleSelect && !moduleSelect.value) moduleSelect.value = 'all';
    if (statusSelect && !statusSelect.value) statusSelect.value = 'all';
  }

  function startPageAnimation() {
    document.body.classList.remove('area-widgets-ready');
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        document.body.classList.add('area-widgets-ready');
      });
    });
  }

  function buildBarChart(canvas) {
    return new Chart(canvas, {
      type: 'bar',
      data: { labels: [], datasets: [{ data: [], backgroundColor: 'rgba(28, 126, 214, 0.82)', borderRadius: 10, borderSkipped: false }] },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: chartPalette.grid } }
        }
      }
    });
  }

  function buildDonutChart(canvas) {
    return new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: [],
        datasets: [{
          data: [],
          backgroundColor: [chartPalette.sky, chartPalette.rose, chartPalette.orange, chartPalette.amber],
          borderColor: '#fff',
          borderWidth: 4
        }]
      },
      options: {
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } } }
      }
    });
  }

  function buildLineChart(canvas) {
    return new Chart(canvas, {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          label: 'Volume',
          data: [],
          borderColor: chartPalette.orange,
          backgroundColor: 'rgba(222, 113, 12, 0.14)',
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointHoverRadius: 5
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: chartPalette.grid } }
        }
      }
    });
  }

  const charts = {
    module: buildBarChart(document.querySelector('.area-module-chart')),
    demographic: buildDonutChart(document.querySelector('.area-demographic-chart')),
    trend: buildLineChart(document.querySelector('.area-trend-chart'))
  };

  function readWidgetState() {
    try {
      const raw = window.localStorage.getItem(widgetStorageKey);
      return raw ? JSON.parse(raw) : {};
    } catch {
      return {};
    }
  }

  function writeWidgetState(state) {
    try {
      window.localStorage.setItem(widgetStorageKey, JSON.stringify(state));
    } catch {
      // Ignore storage failures.
    }
  }

  function collapseWidget(node) {
    node.style.maxHeight = `${node.scrollHeight}px`;
    window.requestAnimationFrame(() => {
      node.classList.add('area-widget-hidden');
      node.style.maxHeight = '0px';
    });
  }

  function expandWidget(node) {
    node.classList.remove('area-widget-hidden');
    node.style.maxHeight = '0px';
    window.requestAnimationFrame(() => {
      node.style.maxHeight = `${node.scrollHeight}px`;
    });
  }

  function finalizeWidgetTransition(event) {
    const node = event.currentTarget;
    if (event.propertyName !== 'max-height') return;
    if (node.classList.contains('area-widget-hidden')) {
      node.style.maxHeight = '0px';
    } else {
      node.style.maxHeight = 'none';
    }
  }

  function applyWidgetState() {
    const state = readWidgetState();
    widgetNodes.forEach((node) => {
      const widgetId = node.dataset.widget;
      const visible = state[widgetId] !== false;
      const alreadyHidden = node.classList.contains('area-widget-hidden');
      if (visible && alreadyHidden) {
        expandWidget(node);
      } else if (!visible && !alreadyHidden) {
        collapseWidget(node);
      }
    });
  }

  function buildWidgetCustomizer() {
    if (!widgetList) return;
    const state = readWidgetState();
    widgetList.innerHTML = widgetNodes.map((node) => {
      const widgetId = node.dataset.widget || '';
      const label = node.dataset.widgetLabel || widgetId;
      const checked = state[widgetId] !== false ? 'checked' : '';
      return `
        <div class="area-widget-option">
          <label for="widget-toggle-${widgetId}">${label}</label>
          <div class="form-check form-switch m-0">
            <input class="form-check-input area-widget-toggle" type="checkbox" role="switch" id="widget-toggle-${widgetId}" data-widget-target="${widgetId}" ${checked}>
          </div>
        </div>
      `;
    }).join('');

    widgetList.querySelectorAll('.area-widget-toggle').forEach((input) => {
      input.addEventListener('change', () => {
        const nextState = readWidgetState();
        nextState[input.dataset.widgetTarget] = input.checked;
        writeWidgetState(nextState);
        applyWidgetState();
      });
    });
  }

  function params() {
    const p = new URLSearchParams();
    p.set('scope', fixedScope);
    p.set('module', moduleSelect?.value || 'all');
    p.set('status', statusSelect?.value || 'all');
    p.set('date_from', dateFromInput?.value || '');
    p.set('date_to', dateToInput?.value || '');
    return p;
  }

  function renderTable(rows) {
    const tableBody = document.querySelector('.area-summary-table tbody');
    if (!tableBody) return;
    if (!rows.length) {
      tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No records found for the selected filters.</td></tr>';
      return;
    }
    tableBody.innerHTML = rows.map((row) => `
      <tr>
        <td>${row.module}</td>
        <td>${Number(row.total || 0).toLocaleString()}</td>
        <td>${Number(row.active_pending || 0).toLocaleString()}</td>
        <td>${Number(row.completed_resolved || 0).toLocaleString()}</td>
        <td>${row.notes || '-'}</td>
      </tr>
    `).join('');
  }

  function renderHighlights(items) {
    const container = document.querySelector('.area-highlight-list');
    if (!container) return;
    container.innerHTML = (items || []).map((item) => `
      <div class="area-highlight-item">
        <span class="area-highlight-label">${item.label || '-'}</span>
        <strong class="area-highlight-value">${item.value || '-'}</strong>
      </div>
    `).join('');
  }

  function renderCards(payload) {
    document.querySelectorAll('[data-stat="population"]').forEach((el) => el.textContent = Number(payload.cards?.population || 0).toLocaleString());
    document.querySelectorAll('[data-stat="households"]').forEach((el) => el.textContent = Number(payload.cards?.households || 0).toLocaleString());
    document.querySelectorAll('[data-stat="documents"]').forEach((el) => el.textContent = Number(payload.cards?.documents || 0).toLocaleString());
    document.querySelectorAll('[data-stat="cases"]').forEach((el) => el.textContent = Number(payload.cards?.cases || 0).toLocaleString());
  }

  async function loadData() {
    try {
      const response = await fetch(`${endpoint}?${params().toString()}`, { credentials: 'same-origin' });
      if (!response.ok) throw new Error(`Request failed with status ${response.status}`);
      const payload = await response.json();

      renderCards(payload);

      charts.module.data.labels = payload.module_chart?.labels || [];
      charts.module.data.datasets[0].data = payload.module_chart?.values || [];
      charts.module.update();

      charts.demographic.data.labels = payload.demographics?.labels || [];
      charts.demographic.data.datasets[0].data = payload.demographics?.values || [];
      charts.demographic.update();

      charts.trend.data.labels = payload.trend?.labels || [];
      charts.trend.data.datasets[0].data = payload.trend?.values || [];
      charts.trend.update();

      renderHighlights(payload.highlights || []);
      renderTable(payload.table || []);
    } catch (error) {
      renderHighlights([
        { label: 'Load error', value: 'Failed to fetch area statistics.' },
        { label: 'Details', value: error instanceof Error ? error.message : 'Unknown error' }
      ]);
      renderTable([]);
    }
  }

  setDefaults();
  widgetNodes.forEach((node) => node.addEventListener('transitionend', finalizeWidgetTransition));
  buildWidgetCustomizer();
  applyWidgetState();
  startPageAnimation();
  loadData();

  [moduleSelect, dateFromInput, dateToInput, statusSelect].forEach((input) => {
    if (!input) return;
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && applyBtn) {
        event.preventDefault();
        loadData();
      }
    });
  });

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      const defaults = defaultDateRange();
      if (moduleSelect) moduleSelect.value = 'all';
      if (statusSelect) statusSelect.value = 'all';
      if (dateFromInput) dateFromInput.value = defaults.from;
      if (dateToInput) dateToInput.value = defaults.to;
    });
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', loadData);
  }

  if (resetWidgetBtn) {
    resetWidgetBtn.addEventListener('click', () => {
      try {
        window.localStorage.removeItem(widgetStorageKey);
      } catch {
        // Ignore storage failures.
      }
      buildWidgetCustomizer();
      applyWidgetState();
    });
  }
})();
