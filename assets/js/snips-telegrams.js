/**
 * Snips Field Ledger Engine
 * Handles timestamp localization, composer focus, toggle replies, and zero-reload AJAX submission.
 */
document.addEventListener('DOMContentLoaded', function () {
  var config = window.SnipsTelegramsData || {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    timeMode: 'local',
    nonce: '',
  };

  // 1. Timestamp Localization
  function localizeTimestamps() {
    var times = document.querySelectorAll('.snip-ledger-time[datetime]');
    var now = new Date();

    times.forEach(function (el) {
      var dateStr = el.getAttribute('datetime');
      if (!dateStr) return;
      var date = new Date(dateStr);

      if (config.timeMode === 'utc') {
        var months = [
          'JAN',
          'FEB',
          'MAR',
          'APR',
          'MAY',
          'JUN',
          'JUL',
          'AUG',
          'SEP',
          'OCT',
          'NOV',
          'DEC',
        ];
        var month = months[date.getUTCMonth()];
        var day = date.getUTCDate();
        var year = date.getUTCFullYear();
        var hours = String(date.getUTCHours()).padStart(2, '0');
        var mins = String(date.getUTCMinutes()).padStart(2, '0');
        el.textContent =
          month +
          ' ' +
          day +
          ', ' +
          year +
          ' ' +
          hours +
          ':' +
          mins +
          ' UTC';
      } else {
        var diffSec = Math.floor((now - date) / 1000);
        if (diffSec < 60) {
          el.textContent = 'just now';
        } else if (diffSec < 3600) {
          el.textContent = Math.floor(diffSec / 60) + 'm ago';
        } else if (diffSec < 86400) {
          el.textContent = Math.floor(diffSec / 3600) + 'h ago';
        } else if (diffSec < 604800) {
          el.textContent = Math.floor(diffSec / 86400) + 'd ago';
        } else {
          el.textContent = date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
          });
        }
      }
    });
  }
  localizeTimestamps();

  // 2. Smooth Focus Physics
  var leaveBtn = document.querySelector('.snip-btn-field-note');
  if (leaveBtn) {
    leaveBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var dockWrap = document.querySelector('.snip-dock-input-wrap');
      var textarea = document.querySelector('#snip-comment-text');
      if (textarea && dockWrap) {
        textarea.focus();
        dockWrap.classList.add('snip-dock-highlight');
        textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () {
          dockWrap.classList.remove('snip-dock-highlight');
        }, 1800);
      }
    });
  }

  // 3. Reply Toggle Engine
  var composerDock = document.getElementById('snip-composer-dock');
  var rootContainer = document.getElementById('snip-dock-root-anchor');
  var parentInput = document.getElementById('snip_comment_parent');
  var replyBanner = document.getElementById('snip-reply-banner');
  var replyAuthorText = document.getElementById('snip-reply-target-author');
  var cancelBtn = document.getElementById('snip-cancel-reply-btn');

  function resetComposer() {
    if (!composerDock || !rootContainer) return;
    rootContainer.appendChild(composerDock);
    if (parentInput) parentInput.value = '0';
    if (replyBanner) replyBanner.style.display = 'none';
    document.querySelectorAll('.snip-reply-btn').forEach(function (btn) {
      btn.classList.remove('snip-btn-active');
      btn.textContent = 'reply';
    });
  }

  document.addEventListener('click', function (e) {
    var replyBtn = e.target.closest('.snip-reply-btn');
    if (!replyBtn) return;
    e.preventDefault();

    var commentId = replyBtn.getAttribute('data-id');
    var authorName = replyBtn.getAttribute('data-author');
    var targetComment = document.getElementById('comment-' + commentId);

    if (!targetComment || !composerDock) return;

    if (parentInput && parentInput.value === commentId) {
      resetComposer();
      return;
    }

    document.querySelectorAll('.snip-reply-btn').forEach(function (btn) {
      btn.classList.remove('snip-btn-active');
      btn.textContent = 'reply';
    });

    var contentWrap = targetComment.querySelector('.snip-entry-content');
    if (contentWrap) {
      contentWrap.after(composerDock);
    } else {
      targetComment.appendChild(composerDock);
    }

    if (parentInput) parentInput.value = commentId;
    if (replyAuthorText) replyAuthorText.textContent = authorName;
    if (replyBanner) replyBanner.style.display = 'flex';

    replyBtn.classList.add('snip-btn-active');
    replyBtn.textContent = 'close';

    var textarea = composerDock.querySelector('textarea');
    if (textarea) textarea.focus();
  });

  if (cancelBtn) {
    cancelBtn.addEventListener('click', function (e) {
      e.preventDefault();
      resetComposer();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && parentInput && parentInput.value !== '0') {
      resetComposer();
    }
  });

  // 4. Zero-Reload AJAX Transmission
  var commentForm = document.getElementById('snip-custom-commentform');
  if (!commentForm) return;

  commentForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var textarea = commentForm.querySelector('#snip-comment-text');
    var submitBtn = commentForm.querySelector('#snip-submit-btn');

    if (!textarea || !textarea.value.trim()) return;

    var formData = new FormData(commentForm);
    formData.append('action', 'snips_submit_field_note');
    formData.append('nonce', config.nonce);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Transmitting...';
    }

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (response) {
        if (!response.success) {
          alert(response.data.message || 'Error recording dispatch.');
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Dispatch ↵';
          }
          return;
        }

        var stream = document.querySelector('.snip-ledger-stream');
        var emptyNote = document.querySelector('.snip-ledger-empty-note');

        if (emptyNote) {
          emptyNote.style.display = 'none';
        }
        if (stream) {
          stream.style.display = 'block';
        }

        var parentId = response.data.parent_id;
        var tempWrapper = document.createElement('div');
        tempWrapper.innerHTML = response.data.html.trim();
        var newElement = tempWrapper.firstElementChild;

        newElement.classList.add('snip-entry-flash');

        if (parentId && parentId !== '0') {
          var parentComment = document.getElementById('comment-' + parentId);
          if (parentComment) {
            var childrenList = parentComment.querySelector('ul.children');
            if (!childrenList) {
              childrenList = document.createElement('ul');
              childrenList.className = 'children';
              parentComment.appendChild(childrenList);
            }
            childrenList.appendChild(newElement);
          } else if (stream) {
            stream.appendChild(newElement);
          }
        } else if (stream) {
          stream.appendChild(newElement);
        }

        setTimeout(function () {
          newElement.classList.remove('snip-entry-flash');
        }, 3000);

        localizeTimestamps();
        resetComposer();

        textarea.value = '';
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Dispatch ↵';
        }
      })
      .catch(function (err) {
        console.error('Error submitting dispatch:', err);
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Dispatch ↵';
        }
      });
  });
});