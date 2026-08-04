
window.showToast = function (message, type) {
  const existing = document.querySelector('.gda-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'gda-toast ' + (type === 'error' ? 'error' : 'success');
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(function () { toast.classList.add('show'); });
  setTimeout(function () {
    toast.classList.remove('show');
    setTimeout(function () { toast.remove(); }, 250);
  }, 2500);
};

window.copyPixKey = async function (elementId) {
  const element = document.getElementById(elementId);
  const pixKey = element ? element.textContent.trim() : '';
  if (!pixKey) {
    window.showToast('Não foi possível copiar a chave PIX.', 'error');
    return;
  }

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(pixKey);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = pixKey;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      const copied = document.execCommand('copy');
      textarea.remove();
      if (!copied) throw new Error('copy_failed');
    }
    window.showToast('Chave PIX copiada!', 'success');
  } catch (error) {
    window.showToast('Não foi possível copiar a chave PIX.', 'error');
  }
};

window.formatBrazilianPhone = function (value) {
  const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
  if (!digits) return '';
  if (digits.length <= 2) return digits.replace(/^(\d{0,2})/, '($1');
  if (digits.length <= 6) return digits.replace(/^(\d{2})(\d+)/, '($1) $2');
  if (digits.length <= 10) return digits.replace(/^(\d{2})(\d{4})(\d+)/, '($1) $2-$3');
  return digits.replace(/^(\d{2})(\d{5})(\d+)/, '($1) $2-$3');
};

window.GdaMoney = (function () {
  function normalizeRaw(value) {
    let text = String(value ?? '').trim().replace(/^R\$\s*/, '').replace(/\s/g, '');
    if (!text) return '';

    if (text.includes(',')) {
      const parts = text.split(',');
      const integer = (parts.shift() || '').replace(/\D/g, '') || '0';
      const cents = parts.join('').replace(/\D/g, '').slice(0, 2);
      return integer.replace(/^0+(?=\d)/, '') + ',' + cents;
    }

    // Valores vindos do servidor usam ponto como separador decimal.
    if (/^\d+\.\d{1,2}$/.test(text)) {
      const [integer, cents] = text.split('.');
      return integer.replace(/^0+(?=\d)/, '') + ',' + cents.padEnd(2, '0').slice(0, 2);
    }

    return text.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
  }

  function formatRaw(raw, editing) {
    raw = normalizeRaw(raw);
    if (!raw) return '';
    const hasComma = raw.includes(',');
    const [integerPart, centsPart = ''] = raw.split(',');
    const integer = Number(integerPart || '0').toLocaleString('pt-BR');
    let cents = '00';
    if (hasComma) {
      cents = editing ? centsPart.padEnd(2, '0') : centsPart.padEnd(2, '0').slice(0, 2);
    }
    return 'R$ ' + integer + ',' + cents;
  }

  function numericValue(raw) {
    raw = normalizeRaw(raw);
    if (!raw) return '';
    return raw.includes(',') ? raw.replace(',', '.') : raw + '.00';
  }

  function attach(input) {
    let raw = normalizeRaw(input.value);

    function render() {
      input.value = formatRaw(raw, true);
      input.dataset.moneyRaw = raw;
    }

    input.addEventListener('keydown', function (event) {
      if (event.ctrlKey || event.metaKey || event.altKey || ['Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

      if (/^\d$/.test(event.key)) {
        event.preventDefault();
        if (raw.includes(',')) {
          const [integer, cents = ''] = raw.split(',');
          if (cents.length < 2) raw = integer + ',' + cents + event.key;
        } else {
          raw = (raw + event.key).replace(/^0+(?=\d)/, '');
        }
        render();
        return;
      }

      if (event.key === ',' || event.key === '.') {
        event.preventDefault();
        if (!raw.includes(',')) raw = (raw || '0') + ',';
        render();
        return;
      }

      if (event.key === 'Backspace' || event.key === 'Delete') {
        event.preventDefault();
        raw = raw.slice(0, -1);
        if (raw.endsWith(',')) raw = raw.slice(0, -1);
        render();
        return;
      }

      event.preventDefault();
    });

    input.addEventListener('paste', function (event) {
      event.preventDefault();
      raw = normalizeRaw((event.clipboardData || window.clipboardData).getData('text'));
      render();
    });

    input.addEventListener('focus', function () {
      setTimeout(function () { input.setSelectionRange(input.value.length, input.value.length); }, 0);
    });

    input.addEventListener('click', function () {
      input.setSelectionRange(input.value.length, input.value.length);
    });

    input.addEventListener('blur', render);

    if (input.form) {
      input.form.addEventListener('submit', function () {
        input.value = numericValue(raw);
      });
    }

    input.setMoneyValue = function (value) {
      raw = normalizeRaw(value);
      render();
    };

    render();
  }

  return { attach: attach };
})();

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.phone-mask').forEach(function (input) {
    const apply = function () { input.value = window.formatBrazilianPhone(input.value); };
    input.addEventListener('input', apply);
    input.addEventListener('paste', function () { setTimeout(apply, 0); });
    apply();
  });

  document.querySelectorAll('.money-input').forEach(window.GdaMoney.attach);

  const participantSelect = document.getElementById('participant-select');
  const amountInput = document.getElementById('contribution-amount');
  if (participantSelect && amountInput) {
    const suggest = function () {
      const option = participantSelect.options[participantSelect.selectedIndex];
      const daily = option ? option.dataset.daily || '' : '';
      if (daily && amountInput.setMoneyValue) amountInput.setMoneyValue(daily);
    };
    participantSelect.addEventListener('change', suggest);
    if (participantSelect.value) suggest();
  }
});


document.addEventListener('DOMContentLoaded', () => {
  const sample = {
    '{nome}': 'João da Silva', '{telefone}': '(71) 99999-9999', '{caixa}': 'Caixa Família',
    '{mes}': 'Julho', '{valor_diario}': 'R$ 20,00', '{valor_total_ponto}': 'R$ 600,00',
    '{valor_pago}': 'R$ 180,00', '{chave_pix}': '71999999999', '{data_ponto}': '15/07/2026',
    '{dias_pagos}': '9', '{dias_pendentes}': '21', '{link_participante}': 'https://caixa.gda.app/acesso/exemplo'
  };
  document.querySelectorAll('[data-template-form]').forEach(form => {
    const textarea = form.querySelector('[data-template-body]');
    const preview = form.querySelector('[data-template-preview]');
    if (!textarea || !preview) return;
    const render = () => {
      let value = textarea.value || 'Digite uma mensagem para visualizar.';
      Object.entries(sample).forEach(([key, replacement]) => value = value.split(key).join(replacement));
      preview.textContent = value;
    };
    form.querySelectorAll('[data-insert-variable]').forEach(button => button.addEventListener('click', () => {
      const variable = button.dataset.insertVariable || '';
      const start = textarea.selectionStart ?? textarea.value.length;
      const end = textarea.selectionEnd ?? textarea.value.length;
      textarea.value = textarea.value.slice(0, start) + variable + textarea.value.slice(end);
      textarea.focus(); textarea.setSelectionRange(start + variable.length, start + variable.length); render();
    }));
    textarea.addEventListener('input', render); render();
  });
});

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.table-search').forEach(function (input) {
    const table = document.querySelector(input.dataset.tableSearch || '');
    if (!table) return;
    input.addEventListener('input', function () {
      const term = input.value.toLocaleLowerCase('pt-BR').trim();
      table.querySelectorAll('tbody tr').forEach(function (row) {
        row.hidden = term !== '' && !row.textContent.toLocaleLowerCase('pt-BR').includes(term);
      });
    });
  });

  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.topbar nav');
  if (toggle && nav) toggle.addEventListener('click', function () {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.querySelectorAll('.topbar .nav-group').forEach(function (group) {
    group.addEventListener('toggle', function () {
      if (!group.open) return;
      document.querySelectorAll('.topbar .nav-group[open]').forEach(function (other) {
        if (other !== group) other.removeAttribute('open');
      });
    });
  });

  document.querySelectorAll('.alert.success,.alert.error').forEach(function (alert) {
    const isError = alert.classList.contains('error');
    window.showToast(alert.textContent.trim(), isError ? 'error' : 'success');
    setTimeout(function () { alert.remove(); }, 500);
  });

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('public/sw.js').catch(function () {});
});

document.addEventListener('DOMContentLoaded', function () {
  const modal = document.querySelector('[data-connect-modal]');
  const open = document.querySelector('[data-connect-open]');
  if (!modal || !open) return;
  const close = modal.querySelector('[data-connect-close]');
  const urlInput = modal.querySelector('[data-connect-url]');
  const qr = modal.querySelector('[data-connect-qr]');
  const status = modal.querySelector('[data-connect-status]');
  let timer = null;

  function refreshQr() {
    const value = urlInput.value;
    qr.src = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' + encodeURIComponent(value);
  }
  async function checkStatus() {
    try {
      const response = await fetch('index.php?page=connect_status&_=' + Date.now(), {cache: 'no-store'});
      const data = await response.json();
      if (data.connect_url && data.connect_url !== urlInput.value) {
        urlInput.value = data.connect_url;
        refreshQr();
      }
      if (data.connected) {
        status.className = 'connect-status connected';
        status.innerHTML = '<strong>🟢 Celular conectado</strong><span>' + (data.device || 'Dispositivo móvel') + ' • último acesso em ' + data.last_seen + '</span>';
      }
    } catch (error) {}
  }
  function show() {
    modal.hidden = false;
    document.body.classList.add('modal-open');
    refreshQr();
    checkStatus();
    timer = setInterval(checkStatus, 3000);
  }
  function hide() {
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    if (timer) clearInterval(timer);
  }
  open.addEventListener('click', show);
  close.addEventListener('click', hide);
  modal.addEventListener('click', function (event) { if (event.target === modal) hide(); });
  modal.querySelector('[data-connect-copy]').addEventListener('click', async function () {
    try {
      await navigator.clipboard.writeText(urlInput.value);
      window.showToast('Endereço copiado!');
    } catch (error) {
      urlInput.select(); document.execCommand('copy'); window.showToast('Endereço copiado!');
    }
  });
});
