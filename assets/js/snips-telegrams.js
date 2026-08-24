/**
 * Snips Field Ledger Engine
 * Handles timestamp localization, auto-expanding composer, smooth docking, inline endorsements with transient pulse animations, and zero-reload AJAX submission.
 */
document.addEventListener('DOMContentLoaded', function () {
  var config = window.SnipsTelegramsData || {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    timeMode: 'local',
    nonce: '',
  };

  // 1. Auto-Expanding Textarea Engine
  function autoExpandTextarea(textarea) {
    if (!textarea) return;
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';

    if (textarea.scrollHeight > 260) {
      textarea.style.overflowY = 'auto';
    } else {
      textarea.style.overflowY = 'hidden';
    }
  }

  var composerTextarea = document.querySelector('#snip-comment-text');
  if (composerTextarea) {
    composerTextarea.addEventListener('input', function () {
      autoExpandTextarea(this);
    });
  }

  // 2. Timestamp Localization
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

  // 3. Smooth Focus Physics
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

  // 4. Reply Toggle & Composer Dock
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

    if (composerTextarea) {
      composerTextarea.style.height = 'auto';
    }
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

    if (composerTextarea) {
      composerTextarea.focus();
      autoExpandTextarea(composerTextarea);
    }
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

  // 5. Endorsement (▲) Local State & Pulse Animations
  var likedComments = JSON.parse(
    localStorage.getItem('snips_liked_dispatches') || '[]'
  );

  document.addEventListener('click', function (e) {
    var likeBtn = e.target.closest('.snip-like-btn');
    if (!likeBtn) return;
    e.preventDefault();

    var commentId = parseInt(likeBtn.getAttribute('data-id'), 10);
    var countSpan = likeBtn.querySelector('.snip-like-count');
    var isLiked = likedComments.indexOf(commentId) !== -1;
    var action = isLiked ? 'unlike' : 'like';

    // Clear any previous animation classes
    likeBtn.classList.remove('snip-like-flash', 'snip-unlike-flash');
    void likeBtn.offsetWidth; // Force CSS reflow

    var currentCount = parseInt(countSpan.textContent, 10) || 0;

    if (action === 'like') {
      // 1. Trigger Emerald Flash
      likeBtn.classList.add('snip-like-flash');
      var nextCount = currentCount + 1;
      countSpan.textContent = nextCount;
      countSpan.style.display = 'inline';

      likedComments.push(commentId);

      // Transition back to muted state
      setTimeout(function () {
        likeBtn.classList.remove('snip-like-flash');
      }, 700);
    } else {
      // 2. Trigger Crimson Pulse
      likeBtn.classList.add('snip-unlike-flash');
      var newCount = Math.max(0, currentCount - 1);
      countSpan.textContent = newCount;
      if (newCount === 0) {
        countSpan.style.display = 'none';
      }

      likedComments = likedComments.filter(function (id) {
        return id !== commentId;
      });

      // Transition back to muted state
      setTimeout(function () {
        likeBtn.classList.remove('snip-unlike-flash');
      }, 700);
    }

    localStorage.setItem(
      'snips_liked_dispatches',
      JSON.stringify(likedComments)
    );

    // Asynchronous Database Update
    var formData = new FormData();
    formData.append('action', 'snips_toggle_comment_like');
    formData.append('nonce', config.nonce);
    formData.append('comment_id', commentId);
    formData.append('like_action', action);

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (res) {
        if (res.success && res.data) {
          var finalCount = parseInt(res.data.count, 10);
          countSpan.textContent = finalCount;
          countSpan.style.display = finalCount > 0 ? 'inline' : 'none';
        }
      })
      .catch(function (err) {
        console.error('Failed to toggle endorsement:', err);
      });
  });

  // 6. Zero-Reload AJAX Comment Submission
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
        textarea.style.height = 'auto';

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