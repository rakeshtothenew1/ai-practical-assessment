/**
 * Ticket Management frontend — vanilla JS (no build step).
 *
 * Screens: list (GET /api/tickets), detail (GET/PATCH status/POST comments),
 * create (POST /api/tickets). DOM states: loading / empty / success / error.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const settings = () => drupalSettings.ticketManagement || {};

  let csrfToken = null;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function labelStatus(status) {
    return (settings().statusLabels && settings().statusLabels[status]) || status;
  }

  function labelPriority(priority) {
    return (settings().priorityLabels && settings().priorityLabels[priority]) || priority;
  }

  function formatDate(iso) {
    if (!iso) {
      return '';
    }
    try {
      return new Date(iso).toLocaleString();
    } catch (e) {
      return iso;
    }
  }

  function setBanner(root, type, message) {
    const errorEl = root.querySelector('[data-tm-error]');
    const successEl = root.querySelector('[data-tm-success]');
    if (errorEl) {
      errorEl.hidden = type !== 'error';
      errorEl.textContent = type === 'error' ? message : '';
    }
    if (successEl) {
      successEl.hidden = type !== 'success';
      successEl.textContent = type === 'success' ? message : '';
    }
  }

  function clearFieldErrors(root) {
    root.querySelectorAll('[data-tm-field-error]').forEach((el) => {
      el.hidden = true;
      el.textContent = '';
    });
  }

  function showFieldErrors(root, errors) {
    clearFieldErrors(root);
    (errors || []).forEach((err) => {
      if (!err || !err.field) {
        return;
      }
      const el = root.querySelector('[data-tm-field-error="' + err.field + '"]');
      if (el) {
        el.textContent = err.message || 'Invalid value.';
        el.hidden = false;
      }
    });
  }

  function setState(scope, state) {
    scope.querySelectorAll('[data-tm-state]').forEach((el) => {
      el.hidden = el.getAttribute('data-tm-state') !== state;
    });
    scope.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
  }

  async function getCsrfToken() {
    if (csrfToken) {
      return csrfToken;
    }
    const url = settings().csrfUrl || '/session/token';
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) {
      throw new Error('Unable to obtain CSRF token.');
    }
    csrfToken = (await res.text()).trim();
    return csrfToken;
  }

  async function api(path, options = {}) {
    const opts = {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(options.headers || {}),
      },
      ...options,
    };
    const method = (opts.method || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = await getCsrfToken();
    }
    const res = await fetch(path, opts);
    let body = null;
    const text = await res.text();
    if (text) {
      try {
        body = JSON.parse(text);
      } catch (e) {
        body = { message: text };
      }
    }
    return { res, body };
  }

  function nextStatuses(current) {
    const map = settings().transitions || {};
    return map[current] || [];
  }

  /* ------------------------------------------------------------------ */
  /* List                                                               */
  /* ------------------------------------------------------------------ */

  function initList(root) {
    const form = root.querySelector('[data-tm-filters]');
    const rows = root.querySelector('[data-tm-rows]');
    const pager = root.querySelector('[data-tm-pager]');
    const results = root.querySelector('[data-tm-results]');
    let page = 1;

    async function load() {
      setBanner(root, null, '');
      setState(results, 'loading');
      form.querySelectorAll('input, select, button').forEach((el) => {
        el.disabled = true;
      });

      const search = root.querySelector('[data-tm-search]').value.trim();
      const status = root.querySelector('[data-tm-status]').value;
      const params = new URLSearchParams();
      if (search) {
        params.set('search', search);
      }
      if (status) {
        params.set('status', status);
      }
      params.set('page', String(page));
      params.set('limit', '25');

      try {
        const { res, body } = await api(settings().apiBase + '?' + params.toString());
        form.querySelectorAll('input, select, button').forEach((el) => {
          el.disabled = false;
        });

        if (!res.ok) {
          setState(results, 'empty');
          setBanner(root, 'error', (body && body.message) || 'Unable to load tickets.');
          return;
        }

        const data = (body && body.data) || [];
        const meta = (body && body.meta) || { page: 1, total: 0, limit: 25 };

        if (!data.length) {
          setState(results, 'empty');
          rows.innerHTML = '';
          pager.hidden = true;
          return;
        }

        rows.innerHTML = data
          .map((ticket) => {
            const href = '/tickets/' + ticket.id;
            return (
              '<tr>' +
              '<td><a href="' + href + '">#' + escapeHtml(ticket.id) + '</a></td>' +
              '<td><a href="' + href + '">' + escapeHtml(ticket.title) + '</a></td>' +
              '<td><span class="tm-badge tm-badge--' + escapeHtml(ticket.status) + '">' + escapeHtml(labelStatus(ticket.status)) + '</span></td>' +
              '<td>' + escapeHtml(labelPriority(ticket.priority)) + '</td>' +
              '<td>' + escapeHtml(formatDate(ticket.updatedAt)) + '</td>' +
              '</tr>'
            );
          })
          .join('');

        const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.limit || 25)));
        if (totalPages > 1) {
          pager.hidden = false;
          pager.innerHTML =
            '<button type="button" class="tm-btn" data-tm-page-prev' + (page <= 1 ? ' disabled' : '') + '>Previous</button>' +
            '<span class="tm-muted">Page ' + page + ' of ' + totalPages + ' (' + meta.total + ' total)</span>' +
            '<button type="button" class="tm-btn" data-tm-page-next' + (page >= totalPages ? ' disabled' : '') + '>Next</button>';
          const prev = pager.querySelector('[data-tm-page-prev]');
          const next = pager.querySelector('[data-tm-page-next]');
          if (prev) {
            prev.addEventListener('click', () => {
              page = Math.max(1, page - 1);
              load();
            });
          }
          if (next) {
            next.addEventListener('click', () => {
              page += 1;
              load();
            });
          }
        } else {
          pager.hidden = true;
          pager.innerHTML = '';
        }

        setState(results, 'success');
      } catch (e) {
        form.querySelectorAll('input, select, button').forEach((el) => {
          el.disabled = false;
        });
        setState(results, 'empty');
        setBanner(root, 'error', e.message || 'Unable to load tickets.');
      }
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      page = 1;
      load();
    });
    form.addEventListener('reset', () => {
      setTimeout(() => {
        page = 1;
        load();
      }, 0);
    });

    load();
  }

  /* ------------------------------------------------------------------ */
  /* Create                                                             */
  /* ------------------------------------------------------------------ */

  function initCreate(root) {
    const form = root.querySelector('[data-tm-create-form]');
    const submitBtn = root.querySelector('[data-tm-submit]');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      setBanner(root, null, '');
      clearFieldErrors(root);

      const payload = {
        title: form.querySelector('[data-tm-field="title"]').value.trim(),
        description: form.querySelector('[data-tm-field="description"]').value.trim(),
        priority: form.querySelector('[data-tm-field="priority"]').value,
      };
      const assignee = form.querySelector('[data-tm-field="assignedTo"]');
      if (assignee && assignee.value) {
        payload.assignedTo = Number(assignee.value);
      }

      submitBtn.disabled = true;
      setState(form, 'loading');

      try {
        const { res, body } = await api(settings().apiBase, {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        submitBtn.disabled = false;
        form.querySelector('[data-tm-state="loading"]').hidden = true;

        if (!res.ok) {
          showFieldErrors(root, body && body.errors);
          setBanner(root, 'error', (body && body.message) || 'Could not create ticket.');
          return;
        }

        setBanner(root, 'success', 'Ticket created.');
        window.location.href = '/tickets/' + body.id;
      } catch (e) {
        submitBtn.disabled = false;
        form.querySelector('[data-tm-state="loading"]').hidden = true;
        setBanner(root, 'error', e.message || 'Could not create ticket.');
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Detail                                                             */
  /* ------------------------------------------------------------------ */

  function renderTicketFields(container, ticket) {
    container.innerHTML =
      '<dl class="tm-dl">' +
      '<div><dt>ID</dt><dd>#' + escapeHtml(ticket.id) + '</dd></div>' +
      '<div><dt>Title</dt><dd>' + escapeHtml(ticket.title) + '</dd></div>' +
      '<div><dt>Status</dt><dd><span class="tm-badge tm-badge--' + escapeHtml(ticket.status) + '">' + escapeHtml(labelStatus(ticket.status)) + '</span></dd></div>' +
      '<div><dt>Priority</dt><dd>' + escapeHtml(labelPriority(ticket.priority)) + '</dd></div>' +
      '<div><dt>Assigned to</dt><dd>' + escapeHtml(ticket.assignedTo == null ? 'Unassigned' : ticket.assignedTo) + '</dd></div>' +
      '<div><dt>Created by</dt><dd>' + escapeHtml(ticket.createdBy) + '</dd></div>' +
      '<div><dt>Created</dt><dd>' + escapeHtml(formatDate(ticket.createdAt)) + '</dd></div>' +
      '<div><dt>Updated</dt><dd>' + escapeHtml(formatDate(ticket.updatedAt)) + '</dd></div>' +
      '<div class="tm-dl-block"><dt>Description</dt><dd>' + escapeHtml(ticket.description) + '</dd></div>' +
      '</dl>';
  }

  function renderComments(root, comments) {
    const list = root.querySelector('[data-tm-comments]');
    const empty = root.querySelector('[data-tm-comments-empty]');
    if (!comments.length) {
      list.innerHTML = '';
      if (empty) {
        empty.hidden = false;
      }
      return;
    }
    if (empty) {
      empty.hidden = true;
    }
    list.innerHTML = comments
      .map(
        (c) =>
          '<li class="tm-comment">' +
          '<p class="tm-comment-meta">#' + escapeHtml(c.id) + ' · user ' + escapeHtml(c.createdBy) + ' · ' + escapeHtml(formatDate(c.createdAt)) + '</p>' +
          '<p class="tm-comment-body">' + escapeHtml(c.message) + '</p>' +
          '</li>'
      )
      .join('');
  }

  function renderStatusActions(root, ticket) {
    const actions = root.querySelector('[data-tm-status-actions]');
    const terminal = root.querySelector('[data-tm-status-terminal]');
    if (!actions) {
      return;
    }
    const perms = settings().permissions || {};
    if (!perms.transition) {
      actions.innerHTML = '';
      return;
    }
    const next = nextStatuses(ticket.status);
    if (!next.length) {
      actions.innerHTML = '';
      if (terminal) {
        terminal.hidden = false;
      }
      return;
    }
    if (terminal) {
      terminal.hidden = true;
    }
    actions.innerHTML = next
      .map(
        (status) =>
          '<button type="button" class="tm-btn tm-btn--status" data-tm-transition="' +
          escapeHtml(status) +
          '">' +
          escapeHtml(labelStatus(status)) +
          '</button>'
      )
      .join('');

    actions.querySelectorAll('[data-tm-transition]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const target = btn.getAttribute('data-tm-transition');
        setBanner(root, null, '');
        btn.disabled = true;
        try {
          const { res, body } = await api(settings().apiBase + '/' + ticket.id + '/status', {
            method: 'PATCH',
            body: JSON.stringify({ status: target }),
          });
          if (!res.ok) {
            setBanner(root, 'error', (body && body.message) || 'Status update failed.');
            btn.disabled = false;
            return;
          }
          setBanner(root, 'success', 'Status updated to ' + labelStatus(body.status) + '.');
          await loadDetail(root);
        } catch (e) {
          setBanner(root, 'error', e.message || 'Status update failed.');
          btn.disabled = false;
        }
      });
    });
  }

  async function loadDetail(root) {
    const ticketId = settings().ticketId || Number(root.getAttribute('data-tm-ticket-id'));
    const panel = root.querySelector('[data-tm-detail-root]');
    setState(panel, 'loading');
    setBanner(root, null, '');

    try {
      const { res, body } = await api(settings().apiBase + '/' + ticketId);
      if (res.status === 404) {
        setState(panel, 'empty');
        setBanner(root, 'error', 'Ticket not found.');
        return;
      }
      if (!res.ok) {
        setState(panel, 'empty');
        setBanner(root, 'error', (body && body.message) || 'Unable to load ticket.');
        return;
      }

      const ticket = body.data;
      const comments = body.comments || [];
      const titleEl = root.querySelector('[data-tm-detail-title]');
      if (titleEl) {
        titleEl.textContent = '#' + ticket.id + ' — ' + ticket.title;
      }
      renderTicketFields(root.querySelector('[data-tm-ticket-fields]'), ticket);
      renderComments(root, comments);
      renderStatusActions(root, ticket);
      setState(panel, 'success');
    } catch (e) {
      setState(panel, 'empty');
      setBanner(root, 'error', e.message || 'Unable to load ticket.');
    }
  }

  function initDetail(root) {
    const commentForm = root.querySelector('[data-tm-comment-form]');
    if (commentForm) {
      commentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(root);
        setBanner(root, null, '');
        const ticketId = settings().ticketId || Number(root.getAttribute('data-tm-ticket-id'));
        const message = commentForm.querySelector('[data-tm-field="message"]').value.trim();
        const submitBtn = root.querySelector('[data-tm-comment-submit]');
        submitBtn.disabled = true;
        try {
          const { res, body } = await api(settings().apiBase + '/' + ticketId + '/comments', {
            method: 'POST',
            body: JSON.stringify({ message }),
          });
          submitBtn.disabled = false;
          if (!res.ok) {
            showFieldErrors(root, body && body.errors);
            setBanner(root, 'error', (body && body.message) || 'Could not post comment.');
            return;
          }
          commentForm.reset();
          setBanner(root, 'success', 'Comment added.');
          await loadDetail(root);
        } catch (e) {
          submitBtn.disabled = false;
          setBanner(root, 'error', e.message || 'Could not post comment.');
        }
      });
    }
    loadDetail(root);
  }

  Drupal.behaviors.ticketManagementApp = {
    attach(context) {
      once('ticket-management-app', '.tm-app', context).forEach((root) => {
        const page = (settings().page || root.getAttribute('data-tm-page') || '').toLowerCase();
        if (page === 'list') {
          initList(root);
        } else if (page === 'create') {
          initCreate(root);
        } else if (page === 'detail') {
          initDetail(root);
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
