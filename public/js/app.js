(() => {
  document.addEventListener(
    'click',
    (event) => {
      const anchor = event.target.closest('a[data-ee-instant-back]');
      if (!anchor) return;

      event.preventDefault();

      const href = anchor.getAttribute('href');
      if (href) {
        window.location.href = href;
      }
    },
    true
  );

  /** @deprecated Use EntryEaseNotifications (bell) instead of toast. */
  function showToast(message, type = 'success') {
    if (window.EntryEaseNotifications) {
      window.EntryEaseNotifications.add({
        title: type === 'error' ? 'Error' : 'Success',
        message: message,
        type: type,
      });
      return;
    }

    const toast = document.getElementById('toast');
    if (!toast || !message) return;

    toast.textContent = '';
    const icon = document.createElement('i');
    icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    toast.appendChild(icon);
    toast.appendChild(document.createTextNode(' ' + message));
    toast.className = `toast ${type} show`;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  window.showToast = showToast;
})();
