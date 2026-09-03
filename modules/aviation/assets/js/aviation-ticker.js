(function () {
  'use strict';

  function initTicker(wrap) {
    if (wrap._cahierTickerInitialized) return;
    wrap._cahierTickerInitialized = true;

    const track = wrap.querySelector('.cahier-metar-ticker-track');
    const content = wrap.querySelector('.cahier-metar-ticker-content');
    if (!track || !content) return;

    // Clone content block once or twice to guarantee continuous infinite wrapping
    const clone1 = content.cloneNode(true);
    clone1.setAttribute('aria-hidden', 'true');
    track.appendChild(clone1);

    let contentWidth = content.offsetWidth;
    window.addEventListener('resize', () => {
      contentWidth = content.offsetWidth;
    });

    const cps = parseFloat(wrap.dataset.cps) || 12;
    // Estimate monospace character width (~0.6 * font-size)
    const fontSize = parseFloat(window.getComputedStyle(wrap).fontSize) || 13;
    const pxPerChar = fontSize * 0.6;
    const baseSpeed = (cps * pxPerChar) / 60; // Pixels per frame (approx 60fps)

    let pos = 0;
    let velocity = 0;
    let isDown = false;
    let startX = 0;
    let lastX = 0;
    let isHovered = false;
    const pauseOnHover = wrap.dataset.pauseOnHover === '1';

    // Hover detection
    wrap.addEventListener('mouseenter', () => {
      if (pauseOnHover) isHovered = true;
    });
    wrap.addEventListener('mouseleave', () => {
      isHovered = false;
    });

    // Pointer event scrubber (Works for touch, pen, and mouse)
    wrap.addEventListener('pointerdown', (e) => {
      isDown = true;
      wrap.classList.add('is-dragging');
      startX = e.clientX;
      lastX = e.clientX;
      velocity = 0;
      wrap.setPointerCapture(e.pointerId);
    });

    wrap.addEventListener('pointermove', (e) => {
      if (!isDown) return;
      const delta = e.clientX - lastX;
      lastX = e.clientX;
      pos += delta;
      velocity = delta; // Capture flick velocity
    });

    function endDrag(e) {
      if (!isDown) return;
      isDown = false;
      wrap.classList.remove('is-dragging');
      try {
        wrap.releasePointerCapture(e.pointerId);
      } catch (err) {}
    }

    wrap.addEventListener('pointerup', endDrag);
    wrap.addEventListener('pointercancel', endDrag);

    // Manual mouse-wheel scrubber (horizontal scrub via shift+wheel or standard trackpad swipe)
    wrap.addEventListener('wheel', (e) => {
      const delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
      pos -= delta * 0.5;
      velocity = -delta * 0.1;
      e.preventDefault();
    }, { passive: false });

    // Animation Loop
    function frame() {
      if (isDown) {
        // Direct tracking during grab
      } else if (Math.abs(velocity) > 0.05) {
        // Inertia glide with exponential friction
        pos += velocity;
        velocity *= 0.94; // Friction damping
      } else {
        velocity = 0;
        if (!isHovered) {
          pos -= baseSpeed; // Constant CPS forward march
        }
      }

      // Infinite wrap math
      if (contentWidth > 0) {
        while (pos <= -contentWidth) {
          pos += contentWidth;
        }
        while (pos > 0) {
          pos -= contentWidth;
        }
      }

      track.style.transform = `translate3d(${pos}px, 0, 0)`;
      requestAnimationFrame(frame);
    }

    requestAnimationFrame(frame);
  }

  function scanTickers() {
    document.querySelectorAll('.cahier-metar-ticker-wrap').forEach(initTicker);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanTickers);
  } else {
    scanTickers();
  }

  // Hook for Gutenberg block preview updates
  window.initCahierAviationTickers = scanTickers;
})();