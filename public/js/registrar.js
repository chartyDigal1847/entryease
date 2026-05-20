(() => {
  function initDocumentPreview() {
    const modal = document.getElementById('previewModal');
    if (!modal) return;
    const img = document.getElementById('previewImage');
    const nameEl = document.getElementById('previewDocName');
    const dateEl = document.getElementById('previewDate');
    const close = document.getElementById('closePreview');

    const hide = () => modal.classList.remove('show');
    const show = (url, label, date) => {
      if (img) img.src = url;
      if (nameEl) nameEl.textContent = label || '-';
      if (dateEl) dateEl.textContent = date || '-';
      modal.classList.add('show');
    };

    document.querySelectorAll('[data-preview-url]').forEach((btn) => {
      btn.addEventListener('click', () => {
        show(btn.dataset.previewUrl, btn.dataset.previewLabel, btn.dataset.previewDate);
      });
    });

    if (close) close.addEventListener('click', hide);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) hide();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') hide();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initDocumentPreview();
  });
})();
