(() => {
  function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function showToast(_toast, msg, type = 'success') {
    if (window.EntryEaseNotifications) {
      window.EntryEaseNotifications.add({
        title: type === 'error' ? 'Error' : 'Success',
        message: msg,
        type: type,
      });
      return;
    }
    if (typeof window.showToast === 'function') {
      window.showToast(msg, type);
    }
  }

  function initApplications() {
    const list = document.getElementById('statusList');
    if (!list) return;
    const api = list.dataset.apiApplicationsUrl || '/api/student/applications';
    const modal = document.getElementById('editModal');
    const toast = document.getElementById('toast');
    const serverRendered = list.dataset.serverRendered === '1';
    let editingId = null;

    const statusClass = (status) =>
      ({
        Pending: 'badge-pending',
        'Under Review': 'badge-review',
        Approved: 'badge-approved',
        Rejected: 'badge-rejected',
      })[status] || 'badge-pending';

    const render = () => {
      fetch(api, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((apps) => {
          list.innerHTML = '';
          if (!apps || !apps.length) {
            list.innerHTML =
              `<div class="empty-state"><i class="fas fa-inbox"></i><p>No applications found.</p>`;
            return;
          }
          apps.forEach((app, idx) => {
            const date = new Date(app.created_at).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
            });
            const isPending = app.status === 'Pending';
            const card = document.createElement('div');
            card.className = 'status-card';
            card.style.animationDelay = `${idx * 0.08}s`;
            card.innerHTML =
              `<div class="status-header"><div class="application-grade">${app.grade_level}</div><div class="status-badge-pill ${statusClass(app.status)}">${app.status}</div></div>` +
              `<div class="application-details"><div><span class="detail-label">Submitted:</span> ${date}</div>` +
              (app.admin_notes
                ? `<div><span class="detail-label">Notes:</span> ${app.admin_notes}</div>`
                : '') +
              `</div>` +
              (isPending
                ? `<div class="card-actions"><button class="btn-edit" type="button" data-id="${app.id}" data-grade="${app.grade_level}"><i class="fas fa-pen"></i> Edit</button><button class="btn-delete" type="button" data-id="${app.id}"><i class="fas fa-trash"></i> Delete</button></div><p class="pending-note"><i class="fas fa-info-circle"></i> Edit and delete are only available while your application is Pending.</p>`
                : '');
            list.appendChild(card);
          });
        })
        .catch(() => {
          list.innerHTML =
            '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load applications. Please refresh.</p></div>';
        });
    };

    list.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.btn-edit');
      const delBtn = e.target.closest('.btn-delete');
      if (editBtn) {
        editingId = editBtn.dataset.id;
        const g = document.getElementById('editGradeLevel');
        if (g) g.value = editBtn.dataset.grade || '';
        if (modal) modal.classList.add('open');
      }
      if (delBtn) {
        fetch(`${api}/${delBtn.dataset.id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        })
          .then((r) => r.json())
          .then((d) => {
            if (d && d.success) {
              showToast(toast, 'Application deleted successfully.');
              render();
            } else {
              showToast(toast, (d && d.message) || 'Could not delete application.', 'error');
            }
          })
          .catch(() => showToast(toast, 'An error occurred. Please try again.', 'error'));
      }
    });

    const cancel = document.getElementById('cancelEdit');
    if (cancel)
      cancel.addEventListener('click', () => {
        if (modal) modal.classList.remove('open');
        editingId = null;
      });
    if (modal)
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('open');
          editingId = null;
        }
      });
    const save = document.getElementById('saveEdit');
    if (save)
      save.addEventListener('click', () => {
        if (!editingId) return;
        const gradeEl = document.getElementById('editGradeLevel');
        const grade = gradeEl ? gradeEl.value : '';
        const original = save.innerHTML;
        save.disabled = true;
        save.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        fetch(`${api}/${editingId}`, {
          method: 'PUT',
          headers: {
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ grade_level: grade }),
        })
          .then((r) => r.json())
          .then((d) => {
            if (d && d.success) {
              if (modal) modal.classList.remove('open');
              editingId = null;
              showToast(toast, 'Application updated successfully.');
              render();
            } else {
              showToast(toast, (d && d.message) || 'Could not update application.', 'error');
            }
          })
          .catch(() => showToast(toast, 'An error occurred. Please try again.', 'error'))
          .finally(() => {
            save.disabled = false;
            save.innerHTML = original;
          });
      });

    if (!serverRendered) {
      render();
    }
  }

  function initApply() {
    const form = document.getElementById('applicationForm');
    if (!form) return;
    const api = form.dataset.apiApplyUrl || '/api/student/apply';
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    const errorMsg = document.getElementById('errorMsg');

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (successAlert) successAlert.classList.remove('show');
      if (errorAlert) errorAlert.classList.remove('show');
      const btn = document.querySelector('.submit-btn');
      const original = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
      }
      fetch(api, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ grade_level: document.getElementById('gradeLevel').value }),
      })
        .then((r) => r.json())
        .then((d) => {
          if (d && d.success) {
            form.reset();
            if (successAlert) {
              successAlert.classList.add('show');
              setTimeout(() => successAlert.classList.remove('show'), 3000);
            }
          } else {
            if (errorMsg) errorMsg.textContent = (d && d.message) || 'Could not submit application.';
            if (errorAlert) errorAlert.classList.add('show');
          }
        })
        .catch(() => {
          if (errorMsg) errorMsg.textContent = 'An error occurred. Please try again.';
          if (errorAlert) errorAlert.classList.add('show');
        })
        .finally(() => {
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = original;
          }
        });
    });
  }

  function initDocuments() {
    const form = document.getElementById('documentForm');
    if (!form) return;
    const uploadUrl = form.dataset.uploadUrl;
    const loading = document.getElementById('loadingIndicator');
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');

    const showSuccess = (message) => {
      const msg = document.getElementById('successMessage');
      if (msg) msg.textContent = message;
      if (successAlert) successAlert.style.display = 'flex';
      if (errorAlert) errorAlert.style.display = 'none';
      setTimeout(() => {
        if (successAlert) successAlert.style.display = 'none';
      }, 5000);
    };
    const showError = (message) => {
      const msg = document.getElementById('errorMessage');
      if (msg) msg.textContent = message;
      if (errorAlert) errorAlert.style.display = 'flex';
      if (successAlert) successAlert.style.display = 'none';
    };

    const formatFileSize = (bytes) => {
      if (!bytes) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    const setSelected = (fieldName, file) => {
      const displayId = fieldName === 'photo_2x2' ? 'photo2x2File' : 'psaBirthCertFile';
      const el = document.getElementById(displayId);
      if (!el) return;
      el.innerHTML = `<i class="fas fa-file"></i> Selected: ${file.name} (${formatFileSize(file.size)})`;
    };

    const wireArea = (areaId, inputId, fieldName) => {
      const area = document.getElementById(areaId);
      const input = document.getElementById(inputId);
      if (!area || !input) return;
      area.addEventListener('click', () => input.click());
      input.addEventListener('change', (e) => {
        const file = e.target.files && e.target.files[0];
        if (file) setSelected(fieldName, file);
      });
      area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.classList.add('dragover');
      });
      area.addEventListener('dragleave', () => area.classList.remove('dragover'));
      area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (!files || !files.length) return;
        input.files = files;
        setSelected(fieldName, files[0]);
      });
    };

    wireArea('photo2x2Area', 'photo2x2Input', 'photo_2x2');
    wireArea('psaBirthCertArea', 'psaBirthCertInput', 'psa_birth_cert');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      const photo = formData.get('photo_2x2');
      const psa = formData.get('psa_birth_cert');
      if (!(photo && photo.size) && !(psa && psa.size)) {
        showError('Please select at least one document to upload.');
        return;
      }
      if (loading) loading.classList.add('show');
      try {
        const res = await fetch(uploadUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
          body: formData,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          if (data && data.errors) showError(Object.values(data.errors).flat().join(', '));
          else showError((data && (data.error || data.message)) || 'Upload failed. Please try again.');
          return;
        }
        showSuccess((data && data.message) || 'Documents uploaded successfully!');
        setTimeout(() => location.reload(), 2000);
      } catch {
        showError('Network error. Please try again.');
      } finally {
        if (loading) loading.classList.remove('show');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initApplications();
    initApply();
    initDocuments();
  });
})();
