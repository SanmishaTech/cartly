/**
 * Lightweight rich text editor (TEX-compatible API).
 * Uses contenteditable with bold, italic, strikethrough, ulist, olist.
 */
(function (global) {
  const COMMANDS = {
    bold: { cmd: 'bold', val: null },
    italic: { cmd: 'italic', val: null },
    underline: { cmd: 'underline', val: null },
    strikethrough: { cmd: 'strikeThrough', val: null },
    ulist: { cmd: 'insertUnorderedList', val: null },
    olist: { cmd: 'insertOrderedList', val: null },
    link: { cmd: 'createLink', val: 'url' },
  };

  function createToolbar(buttons, editorEl) {
    const bar = document.createElement('div');
    bar.className = 'tex-toolbar flex flex-wrap gap-1 p-2 border-b border-base-300 bg-base-200 rounded-t-lg';
    buttons.forEach(function (key) {
      const spec = COMMANDS[key];
      if (!spec) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tex-btn btn btn-ghost btn-xs';
      btn.dataset.cmd = spec.cmd;
      btn.dataset.val = spec.val || '';
      btn.title = key;
      btn.textContent = key.charAt(0).toUpperCase() + key.slice(1);
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (spec.val === 'url') {
          const url = prompt('Link URL:', 'https://');
          if (url) document.execCommand(spec.cmd, false, url);
        } else {
          document.execCommand(spec.cmd, false, null);
        }
        editorEl.focus();
      });
      bar.appendChild(btn);
    });
    return bar;
  }

  function init(options) {
    const el = options.element;
    const buttons = options.buttons || ['bold', 'italic', 'strikethrough', 'ulist', 'olist'];
    const onChange = options.onChange;

    if (el === undefined || el === null) return null;

    const container = document.createElement('div');
    container.className = 'tex-editor-container border border-base-300 rounded-lg overflow-hidden';

    const editorEl = typeof el === 'string' ? document.createElement('div') : el;
    if (typeof el === 'string') {
      editorEl.innerHTML = el;
    }
    editorEl.contentEditable = 'true';
    editorEl.className = (editorEl.className || '') + ' tex-content p-3 min-h-[100px] text-base-content bg-base-100 focus:outline-none';
    editorEl.style.outline = 'none';

    const toolbar = createToolbar(buttons, editorEl);
    container.appendChild(toolbar);
    container.appendChild(editorEl);

    const inputEl = options.inputElement || null;
    if (inputEl && inputEl.tagName === 'INPUT' && inputEl.type === 'hidden') {
      editorEl.innerHTML = inputEl.value || '';
      editorEl.addEventListener('input', function () {
        inputEl.value = editorEl.innerHTML;
        if (onChange) onChange(editorEl.innerHTML);
      });
      editorEl.addEventListener('blur', function () {
        inputEl.value = editorEl.innerHTML;
        if (onChange) onChange(editorEl.innerHTML);
      });
    } else if (onChange) {
      editorEl.addEventListener('input', function () { onChange(editorEl.innerHTML); });
      editorEl.addEventListener('blur', function () { onChange(editorEl.innerHTML); });
    }

    return { element: editorEl, container: container, getContent: function () { return editorEl.innerHTML; } };
  }

  function initOne(wrapper) {
    if (wrapper.dataset.texInitialized === '1') return;
    wrapper.dataset.texInitialized = '1';
    const inputId = wrapper.dataset.texInputId;
    let value = (wrapper.dataset.texValue || '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&amp;/g, '&');
    const buttons = (wrapper.dataset.texButtons || 'bold,italic,strikethrough,ulist,olist').split(',').map(function (s) { return s.trim(); });

    let inputEl = wrapper.nextElementSibling;
    if (!inputEl || inputEl.tagName !== 'INPUT' || inputEl.type !== 'hidden') {
      inputEl = inputId ? document.getElementById(inputId) : null;
    }
    const isHidden = !!inputEl && inputEl.tagName === 'INPUT' && inputEl.type === 'hidden';
    if (isHidden && inputEl.value) {
      value = inputEl.value;
    }

    const result = init({
      element: value,
      buttons: buttons,
      inputElement: isHidden ? inputEl : null,
    });

    if (!result || !result.container) {
      wrapper.dataset.texInitialized = '';
      return;
    }

    wrapper.innerHTML = '';
    wrapper.appendChild(result.container);
  }

  function initAll() {
    const wrappers = document.querySelectorAll('[data-tex-editor]:not([data-tex-initialized])');
    wrappers.forEach(function (wrapper) {
      initOne(wrapper);
    });
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { init: init, initAll: initAll };
  } else {
    global.tex = { init: init, initAll: initAll };
  }
})(typeof window !== 'undefined' ? window : this);
