function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open')
      .forEach(m => { m.classList.remove('open'); document.body.style.overflow = ''; });
  }
});
const hamburger = document.getElementById('hamburger');
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebar-overlay');
if (hamburger) {
  hamburger.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
  });
}
if (overlay) {
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });
}
document.querySelectorAll('.alert[data-auto]').forEach(el => {
  setTimeout(() => el.style.display = 'none', 4000);
});
document.querySelectorAll('select[data-rsvp]').forEach(sel => {
  sel.addEventListener('change', function () {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = '/actions/guest_action.php';
    const fields = { action: 'rsvp', guest_id: this.dataset.rsvp, rsvp_status: this.value };
    Object.entries(fields).forEach(([k,v]) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = v;
      form.appendChild(i);
    });
    document.body.appendChild(form); form.submit();
  });
});
document.querySelectorAll('select[data-task]').forEach(sel => {
  sel.addEventListener('change', function () {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = '/actions/task_action.php';
    const fields = { action: 'status', task_id: this.dataset.task, status: this.value };
    Object.entries(fields).forEach(([k,v]) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = v;
      form.appendChild(i);
    });
    document.body.appendChild(form); form.submit();
  });
});
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', (e) => {
    if (!confirm(el.dataset.confirm || 'Удалить?')) e.preventDefault();
  });
});
document.querySelectorAll('.save-actual').forEach(btn => {
  btn.addEventListener('click', function () {
    const id  = this.dataset.id;
    const val = document.getElementById('actual_' + id).value;
    const form = document.createElement('form');
    form.method = 'POST'; form.action = '/actions/budget_action.php';
    const fields = { action: 'set_actual', item_id: id, actual_amount: val };
    Object.entries(fields).forEach(([k,v]) => {
      const i = document.createElement('input');
      i.type='hidden'; i.name=k; i.value=v; form.appendChild(i);
    });
    document.body.appendChild(form); form.submit();
  });
});
function filterTable(inputId, tableId) {
  const inp   = document.getElementById(inputId);
  const tbody = document.querySelector('#' + tableId + ' tbody');
  if (!inp || !tbody) return;
  inp.addEventListener('input', () => {
    const q = inp.value.toLowerCase();
    tbody.querySelectorAll('tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}
